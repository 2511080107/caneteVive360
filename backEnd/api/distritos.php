<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

require_method(['GET', 'POST', 'PUT', 'DELETE']);

try {
    $pdo = db();

    if (method() === 'GET') {
        $id = id_query_param();

        $sql = 'SELECT d.id_distrito,
                       d.nombre,
                       d.descripcion,
                       (SELECT COUNT(*) FROM atractivo a WHERE a.id_distrito = d.id_distrito) AS total_atractivos,
                       (SELECT COUNT(*) FROM servicio s WHERE s.id_distrito = d.id_distrito) AS total_servicios
                FROM distrito d';

        if ($id !== null) {
            $stmt = $pdo->prepare($sql . ' WHERE d.id_distrito = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();

            if (!$row) {
                fail('Distrito no encontrado.', 404);
            }

            json_response(['distrito' => normalize_distrito($row)]);
        }

        $stmt = $pdo->query($sql . ' ORDER BY d.nombre');
        $distritos = array_map('normalize_distrito', $stmt->fetchAll());

        json_response(['distritos' => $distritos, 'total' => count($distritos)]);
    }

    if (method() === 'POST') {
        $data = json_body();
        $nombre = required_string($data, 'nombre', 'nombre', 2, 80);
        $descripcion = optional_text($data, 'descripcion');

        $stmt = $pdo->prepare('INSERT INTO distrito (nombre, descripcion) VALUES (?, ?)');
        $stmt->execute([$nombre, $descripcion]);

        json_response([
            'mensaje' => 'Distrito registrado correctamente.',
            'distrito' => fetch_distrito($pdo, (int) $pdo->lastInsertId()),
        ], 201);
    }

    if (method() === 'PUT') {
        $data = json_body();
        $id = resolve_id($data, 'id_distrito');

        if (!fetch_distrito($pdo, $id)) {
            fail('Distrito no encontrado.', 404);
        }

        $sets = [];
        $params = [];

        if (array_key_exists('nombre', $data)) {
            $sets[] = 'nombre = ?';
            $params[] = required_string($data, 'nombre', 'nombre', 2, 80);
        }

        if (array_key_exists('descripcion', $data)) {
            $sets[] = 'descripcion = ?';
            $params[] = optional_text($data, 'descripcion');
        }

        if (!$sets) {
            fail('No se enviaron campos para actualizar.', 422);
        }

        $params[] = $id;
        $stmt = $pdo->prepare('UPDATE distrito SET ' . implode(', ', $sets) . ' WHERE id_distrito = ?');
        $stmt->execute($params);

        json_response([
            'mensaje' => 'Distrito actualizado correctamente.',
            'distrito' => fetch_distrito($pdo, $id),
        ]);
    }

    $id = resolve_id([], 'id_distrito');

    if (!fetch_distrito($pdo, $id)) {
        fail('Distrito no encontrado.', 404);
    }

    $stmt = $pdo->prepare('SELECT
            (SELECT COUNT(*) FROM atractivo WHERE id_distrito = ?) AS atractivos,
            (SELECT COUNT(*) FROM servicio WHERE id_distrito = ?) AS servicios');
    $stmt->execute([$id, $id]);
    $uso = $stmt->fetch();

    if ((int) $uso['atractivos'] > 0 || (int) $uso['servicios'] > 0) {
        fail('No se puede eliminar el distrito porque tiene atractivos o servicios asociados.', 409);
    }

    $stmt = $pdo->prepare('DELETE FROM distrito WHERE id_distrito = ?');
    $stmt->execute([$id]);

    json_response(['mensaje' => 'Distrito eliminado correctamente.']);
} catch (Throwable $error) {
    respond_error($error);
}

function normalize_distrito(array $row): array
{
    return [
        'id_distrito' => (int) $row['id_distrito'],
        'nombre' => $row['nombre'],
        'descripcion' => $row['descripcion'],
        'total_atractivos' => (int) ($row['total_atractivos'] ?? 0),
        'total_servicios' => (int) ($row['total_servicios'] ?? 0),
    ];
}

function fetch_distrito(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT d.id_distrito,
                                  d.nombre,
                                  d.descripcion,
                                  (SELECT COUNT(*) FROM atractivo a WHERE a.id_distrito = d.id_distrito) AS total_atractivos,
                                  (SELECT COUNT(*) FROM servicio s WHERE s.id_distrito = d.id_distrito) AS total_servicios
                           FROM distrito d
                           WHERE d.id_distrito = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ? normalize_distrito($row) : null;
}
