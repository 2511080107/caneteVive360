<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

require_method(['GET', 'POST', 'PUT', 'DELETE']);

try {
    $pdo = db();

    if (method() === 'GET') {
        $id = id_query_param();
        $idDistrito = id_query_param('distrito');
        $idCategoria = id_query_param('categoria');
        $busqueda = trim((string) query_param('q', ''));

        $sql = 'SELECT a.id_atractivo,
                       a.id_distrito,
                       a.id_categoria,
                       a.nombre,
                       a.descripcion,
                       a.direccion,
                       a.latitud,
                       a.longitud,
                       d.nombre AS distrito,
                       c.nombre AS categoria
                FROM atractivo a
                JOIN distrito d ON d.id_distrito = a.id_distrito
                JOIN categoria c ON c.id_categoria = a.id_categoria';

        $where = [];
        $params = [];

        if ($id !== null) {
            $where[] = 'a.id_atractivo = ?';
            $params[] = $id;
        }

        if ($idDistrito !== null) {
            $where[] = 'a.id_distrito = ?';
            $params[] = $idDistrito;
        }

        if ($idCategoria !== null) {
            $where[] = 'a.id_categoria = ?';
            $params[] = $idCategoria;
        }

        if ($busqueda !== '') {
            $where[] = '(a.nombre LIKE ? OR a.descripcion LIKE ? OR a.direccion LIKE ?)';
            $like = '%' . $busqueda . '%';
            array_push($params, $like, $like, $like);
        }

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY d.nombre, c.nombre, a.nombre';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $atractivos = array_map('normalize_atractivo', $stmt->fetchAll());

        if ($id !== null) {
            if (!$atractivos) {
                fail('Atractivo no encontrado.', 404);
            }

            json_response(['atractivo' => $atractivos[0]]);
        }

        json_response(['atractivos' => $atractivos, 'total' => count($atractivos)]);
    }

    if (method() === 'POST') {
        $data = json_body();
        $valores = validate_atractivo($pdo, $data, true);

        $stmt = $pdo->prepare('INSERT INTO atractivo
                (id_distrito, id_categoria, nombre, descripcion, direccion, latitud, longitud)
                VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $valores['id_distrito'],
            $valores['id_categoria'],
            $valores['nombre'],
            $valores['descripcion'],
            $valores['direccion'],
            $valores['latitud'],
            $valores['longitud'],
        ]);

        json_response([
            'mensaje' => 'Atractivo registrado correctamente.',
            'atractivo' => fetch_atractivo($pdo, (int) $pdo->lastInsertId()),
        ], 201);
    }

    if (method() === 'PUT') {
        $data = json_body();
        $id = resolve_id($data, 'id_atractivo');

        if (!fetch_atractivo($pdo, $id)) {
            fail('Atractivo no encontrado.', 404);
        }

        $actuales = fetch_atractivo($pdo, $id);
        $fusion = array_merge([
            'id_distrito' => $actuales['id_distrito'],
            'id_categoria' => $actuales['id_categoria'],
            'nombre' => $actuales['nombre'],
            'descripcion' => $actuales['descripcion'],
            'direccion' => $actuales['direccion'],
            'latitud' => $actuales['latitud'],
            'longitud' => $actuales['longitud'],
        ], $data);

        $valores = validate_atractivo($pdo, $fusion, true);

        $stmt = $pdo->prepare('UPDATE atractivo
                SET id_distrito = ?, id_categoria = ?, nombre = ?, descripcion = ?,
                    direccion = ?, latitud = ?, longitud = ?
                WHERE id_atractivo = ?');
        $stmt->execute([
            $valores['id_distrito'],
            $valores['id_categoria'],
            $valores['nombre'],
            $valores['descripcion'],
            $valores['direccion'],
            $valores['latitud'],
            $valores['longitud'],
            $id,
        ]);

        json_response([
            'mensaje' => 'Atractivo actualizado correctamente.',
            'atractivo' => fetch_atractivo($pdo, $id),
        ]);
    }

    $id = resolve_id([], 'id_atractivo');

    if (!fetch_atractivo($pdo, $id)) {
        fail('Atractivo no encontrado.', 404);
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ruta WHERE id_atractivo = ?');
    $stmt->execute([$id]);

    if ((int) $stmt->fetchColumn() > 0) {
        fail('No se puede eliminar el atractivo porque forma parte de rutas registradas.', 409);
    }

    $stmt = $pdo->prepare('DELETE FROM atractivo WHERE id_atractivo = ?');
    $stmt->execute([$id]);

    json_response(['mensaje' => 'Atractivo eliminado correctamente.']);
} catch (Throwable $error) {
    respond_error($error);
}

function validate_atractivo(PDO $pdo, array $data, bool $requireAll): array
{
    $idDistrito = required_int($data, 'id_distrito', 'distrito');
    $idCategoria = required_int($data, 'id_categoria', 'categoria');

    assert_exists($pdo, 'distrito', 'id_distrito', $idDistrito, 'El distrito indicado no existe.');
    assert_exists($pdo, 'categoria', 'id_categoria', $idCategoria, 'La categoria indicada no existe.');

    $latitud = optional_float($data, 'latitud', 'latitud');
    $longitud = optional_float($data, 'longitud', 'longitud');

    if ($latitud !== null && ($latitud < -90 || $latitud > 90)) {
        fail('La latitud debe estar entre -90 y 90.', 422);
    }

    if ($longitud !== null && ($longitud < -180 || $longitud > 180)) {
        fail('La longitud debe estar entre -180 y 180.', 422);
    }

    return [
        'id_distrito' => $idDistrito,
        'id_categoria' => $idCategoria,
        'nombre' => required_string($data, 'nombre', 'nombre', 2, 120),
        'descripcion' => optional_text($data, 'descripcion'),
        'direccion' => optional_string($data, 'direccion', 'direccion', 180),
        'latitud' => $latitud,
        'longitud' => $longitud,
    ];
}

function normalize_atractivo(array $row): array
{
    return [
        'id_atractivo' => (int) $row['id_atractivo'],
        'id_distrito' => (int) $row['id_distrito'],
        'id_categoria' => (int) $row['id_categoria'],
        'nombre' => $row['nombre'],
        'descripcion' => $row['descripcion'],
        'direccion' => $row['direccion'],
        'latitud' => $row['latitud'] !== null ? (float) $row['latitud'] : null,
        'longitud' => $row['longitud'] !== null ? (float) $row['longitud'] : null,
        'distrito' => $row['distrito'] ?? null,
        'categoria' => $row['categoria'] ?? null,
    ];
}

function fetch_atractivo(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT a.id_atractivo,
                                  a.id_distrito,
                                  a.id_categoria,
                                  a.nombre,
                                  a.descripcion,
                                  a.direccion,
                                  a.latitud,
                                  a.longitud,
                                  d.nombre AS distrito,
                                  c.nombre AS categoria
                           FROM atractivo a
                           JOIN distrito d ON d.id_distrito = a.id_distrito
                           JOIN categoria c ON c.id_categoria = a.id_categoria
                           WHERE a.id_atractivo = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ? normalize_atractivo($row) : null;
}
