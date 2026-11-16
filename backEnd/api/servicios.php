<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

require_method(['GET', 'POST', 'PUT', 'DELETE']);

try {
    $pdo = db();

    if (method() === 'GET') {
        $id = id_query_param();
        $idDistrito = id_query_param('distrito');

        $sql = 'SELECT s.id_servicio,
                       s.id_distrito,
                       s.tipo_servicio,
                       s.telefono,
                       s.direccion,
                       d.nombre AS distrito,
                       (SELECT COUNT(DISTINCT CONCAT_WS("|", dr.id_turista, r.nombre, r.fecha_visita))
                          FROM detalle_ruta dr
                          JOIN ruta r ON r.id_ruta = dr.id_ruta
                         WHERE dr.id_servicio = s.id_servicio) AS total_rutas,
                       (SELECT COUNT(DISTINCT r.id_atractivo)
                          FROM detalle_ruta dr
                          JOIN ruta r ON r.id_ruta = dr.id_ruta
                         WHERE dr.id_servicio = s.id_servicio) AS total_atractivos
                FROM servicio s
                LEFT JOIN distrito d ON d.id_distrito = s.id_distrito';

        $where = [];
        $params = [];

        if ($id !== null) {
            $where[] = 's.id_servicio = ?';
            $params[] = $id;
        }

        if ($idDistrito !== null) {
            $where[] = 's.id_distrito = ?';
            $params[] = $idDistrito;
        }

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY s.tipo_servicio';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $servicios = array_map('normalize_servicio', $stmt->fetchAll());

        if ($id !== null) {
            if (!$servicios) {
                fail('Servicio no encontrado.', 404);
            }

            json_response(['servicio' => $servicios[0]]);
        }

        json_response(['servicios' => $servicios, 'total' => count($servicios)]);
    }

    if (method() === 'POST') {
        $data = json_body();
        $valores = validate_servicio($pdo, $data);

        $stmt = $pdo->prepare('INSERT INTO servicio (id_distrito, tipo_servicio, telefono, direccion)
                               VALUES (?, ?, ?, ?)');
        $stmt->execute([
            $valores['id_distrito'],
            $valores['tipo_servicio'],
            $valores['telefono'],
            $valores['direccion'],
        ]);

        json_response([
            'mensaje' => 'Servicio registrado correctamente.',
            'servicio' => fetch_servicio($pdo, (int) $pdo->lastInsertId()),
        ], 201);
    }

    if (method() === 'PUT') {
        $data = json_body();
        $id = resolve_id($data, 'id_servicio');
        $actual = fetch_servicio($pdo, $id);

        if (!$actual) {
            fail('Servicio no encontrado.', 404);
        }

        $fusion = array_merge($actual, $data);
        $valores = validate_servicio($pdo, $fusion);

        $stmt = $pdo->prepare('UPDATE servicio
                               SET id_distrito = ?, tipo_servicio = ?, telefono = ?, direccion = ?
                               WHERE id_servicio = ?');
        $stmt->execute([
            $valores['id_distrito'],
            $valores['tipo_servicio'],
            $valores['telefono'],
            $valores['direccion'],
            $id,
        ]);

        json_response([
            'mensaje' => 'Servicio actualizado correctamente.',
            'servicio' => fetch_servicio($pdo, $id),
        ]);
    }

    $id = resolve_id([], 'id_servicio');

    if (!fetch_servicio($pdo, $id)) {
        fail('Servicio no encontrado.', 404);
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM detalle_ruta WHERE id_servicio = ?');
    $stmt->execute([$id]);

    if ((int) $stmt->fetchColumn() > 0) {
        fail('No se puede eliminar el servicio porque tiene rutas asociadas.', 409);
    }

    $stmt = $pdo->prepare('DELETE FROM servicio WHERE id_servicio = ?');
    $stmt->execute([$id]);

    json_response(['mensaje' => 'Servicio eliminado correctamente.']);
} catch (Throwable $error) {
    respond_error($error);
}

function validate_servicio(PDO $pdo, array $data): array
{
    $idDistrito = optional_int($data, 'id_distrito', 'distrito');

    if ($idDistrito !== null) {
        assert_exists($pdo, 'distrito', 'id_distrito', $idDistrito, 'El distrito indicado no existe.');
    }

    return [
        'id_distrito' => $idDistrito,
        'tipo_servicio' => required_string($data, 'tipo_servicio', 'tipo de servicio', 2, 40),
        'telefono' => optional_phone($data, 'telefono', 'telefono'),
        'direccion' => optional_string($data, 'direccion', 'direccion', 180),
    ];
}

function normalize_servicio(array $row): array
{
    return [
        'id_servicio' => (int) $row['id_servicio'],
        'id_distrito' => $row['id_distrito'] !== null ? (int) $row['id_distrito'] : null,
        'tipo_servicio' => $row['tipo_servicio'],
        'telefono' => $row['telefono'],
        'direccion' => $row['direccion'],
        'distrito' => $row['distrito'] ?? null,
        'total_rutas' => (int) ($row['total_rutas'] ?? 0),
        'total_atractivos' => (int) ($row['total_atractivos'] ?? 0),
    ];
}

function fetch_servicio(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT s.id_servicio,
                                  s.id_distrito,
                                  s.tipo_servicio,
                                  s.telefono,
                                  s.direccion,
                                  d.nombre AS distrito,
                                  (SELECT COUNT(DISTINCT CONCAT_WS("|", dr.id_turista, r.nombre, r.fecha_visita))
                                     FROM detalle_ruta dr
                                     JOIN ruta r ON r.id_ruta = dr.id_ruta
                                    WHERE dr.id_servicio = s.id_servicio) AS total_rutas,
                                  (SELECT COUNT(DISTINCT r.id_atractivo)
                                     FROM detalle_ruta dr
                                     JOIN ruta r ON r.id_ruta = dr.id_ruta
                                    WHERE dr.id_servicio = s.id_servicio) AS total_atractivos
                           FROM servicio s
                           LEFT JOIN distrito d ON d.id_distrito = s.id_distrito
                           WHERE s.id_servicio = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ? normalize_servicio($row) : null;
}
