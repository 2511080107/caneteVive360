<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

require_method(['GET', 'POST', 'PUT', 'DELETE']);

try {
    $pdo = db();

    if (method() === 'GET') {
        $id = id_query_param();
        $busqueda = trim((string) query_param('q', ''));

        $sql = 'SELECT t.id_turista,
                       t.nombres,
                       t.apellidos,
                       t.correo,
                       (SELECT COUNT(DISTINCT CONCAT_WS("|", dr.id_servicio, r.nombre, r.fecha_visita))
                          FROM detalle_ruta dr
                          JOIN ruta r ON r.id_ruta = dr.id_ruta
                         WHERE dr.id_turista = t.id_turista) AS total_rutas
                FROM turista t';

        $where = [];
        $params = [];

        if ($id !== null) {
            $where[] = 't.id_turista = ?';
            $params[] = $id;
        }

        if ($busqueda !== '') {
            $where[] = '(t.nombres LIKE ? OR t.apellidos LIKE ? OR t.correo LIKE ?)';
            $like = '%' . $busqueda . '%';
            array_push($params, $like, $like, $like);
        }

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY t.id_turista DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $turistas = array_map('normalize_turista', $stmt->fetchAll());

        if ($id !== null) {
            if (!$turistas) {
                fail('Turista no encontrado.', 404);
            }

            json_response(['turista' => $turistas[0]]);
        }

        json_response(['turistas' => $turistas, 'total' => count($turistas)]);
    }

    if (method() === 'POST') {
        $data = json_body();
        $valores = validate_turista($data);

        $stmt = $pdo->prepare('INSERT INTO turista (nombres, apellidos, correo)
                               VALUES (?, ?, ?)');
        $stmt->execute([
            $valores['nombres'],
            $valores['apellidos'],
            $valores['correo'],
        ]);

        json_response([
            'mensaje' => 'Turista registrado correctamente.',
            'turista' => fetch_turista($pdo, (int) $pdo->lastInsertId()),
        ], 201);
    }

    if (method() === 'PUT') {
        $data = json_body();
        $id = resolve_id($data, 'id_turista');
        $actual = fetch_turista($pdo, $id);

        if (!$actual) {
            fail('Turista no encontrado.', 404);
        }

        $valores = validate_turista(array_merge($actual, $data));

        $stmt = $pdo->prepare('UPDATE turista
                               SET nombres = ?, apellidos = ?, correo = ?
                               WHERE id_turista = ?');
        $stmt->execute([
            $valores['nombres'],
            $valores['apellidos'],
            $valores['correo'],
            $id,
        ]);

        json_response([
            'mensaje' => 'Turista actualizado correctamente.',
            'turista' => fetch_turista($pdo, $id),
        ]);
    }

    $id = resolve_id([], 'id_turista');

    if (!fetch_turista($pdo, $id)) {
        fail('Turista no encontrado.', 404);
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM detalle_ruta WHERE id_turista = ?');
    $stmt->execute([$id]);

    if ((int) $stmt->fetchColumn() > 0) {
        fail('No se puede eliminar el turista porque tiene rutas registradas.', 409);
    }

    $stmt = $pdo->prepare('DELETE FROM turista WHERE id_turista = ?');
    $stmt->execute([$id]);

    json_response(['mensaje' => 'Turista eliminado correctamente.']);
} catch (Throwable $error) {
    respond_error($error);
}

function validate_turista(array $data): array
{
    return [
        'nombres' => required_name($data, 'nombres', 'nombres'),
        'apellidos' => required_name($data, 'apellidos', 'apellidos'),
        'correo' => required_email($data, 'correo', 'correo'),
    ];
}

function normalize_turista(array $row): array
{
    return [
        'id_turista' => (int) $row['id_turista'],
        'nombres' => $row['nombres'],
        'apellidos' => $row['apellidos'],
        'correo' => $row['correo'],
        'total_rutas' => (int) ($row['total_rutas'] ?? 0),
    ];
}

function fetch_turista(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT t.id_turista,
                                  t.nombres,
                                  t.apellidos,
                                  t.correo,
                                  (SELECT COUNT(DISTINCT CONCAT_WS("|", dr.id_servicio, r.nombre, r.fecha_visita))
                                     FROM detalle_ruta dr
                                     JOIN ruta r ON r.id_ruta = dr.id_ruta
                                    WHERE dr.id_turista = t.id_turista) AS total_rutas
                           FROM turista t
                           WHERE t.id_turista = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ? normalize_turista($row) : null;
}
