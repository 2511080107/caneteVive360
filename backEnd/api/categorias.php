<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

require_method(['GET', 'POST', 'PUT', 'DELETE']);

try {
    $pdo = db();

    if (method() === 'GET') {
        $id = id_query_param();

        $sql = 'SELECT c.id_categoria,
                       c.nombre,
                       (SELECT COUNT(*) FROM atractivo a WHERE a.id_categoria = c.id_categoria) AS total_atractivos
                FROM categoria c';

        if ($id !== null) {
            $stmt = $pdo->prepare($sql . ' WHERE c.id_categoria = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();

            if (!$row) {
                fail('Categoria no encontrada.', 404);
            }

            json_response(['categoria' => normalize_categoria($row)]);
        }

        $stmt = $pdo->query($sql . ' ORDER BY c.nombre');
        $categorias = array_map('normalize_categoria', $stmt->fetchAll());

        json_response(['categorias' => $categorias, 'total' => count($categorias)]);
    }

    if (method() === 'POST') {
        $data = json_body();
        $nombre = required_string($data, 'nombre', 'nombre', 3, 60);

        $stmt = $pdo->prepare('INSERT INTO categoria (nombre) VALUES (?)');
        $stmt->execute([$nombre]);

        json_response([
            'mensaje' => 'Categoria registrada correctamente.',
            'categoria' => fetch_categoria($pdo, (int) $pdo->lastInsertId()),
        ], 201);
    }

    if (method() === 'PUT') {
        $data = json_body();
        $id = resolve_id($data, 'id_categoria');

        if (!fetch_categoria($pdo, $id)) {
            fail('Categoria no encontrada.', 404);
        }

        $nombre = required_string($data, 'nombre', 'nombre', 3, 60);
        $stmt = $pdo->prepare('UPDATE categoria SET nombre = ? WHERE id_categoria = ?');
        $stmt->execute([$nombre, $id]);

        json_response([
            'mensaje' => 'Categoria actualizada correctamente.',
            'categoria' => fetch_categoria($pdo, $id),
        ]);
    }

    $id = resolve_id([], 'id_categoria');

    if (!fetch_categoria($pdo, $id)) {
        fail('Categoria no encontrada.', 404);
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM atractivo WHERE id_categoria = ?');
    $stmt->execute([$id]);

    if ((int) $stmt->fetchColumn() > 0) {
        fail('No se puede eliminar la categoria porque tiene atractivos asociados.', 409);
    }

    $stmt = $pdo->prepare('DELETE FROM categoria WHERE id_categoria = ?');
    $stmt->execute([$id]);

    json_response(['mensaje' => 'Categoria eliminada correctamente.']);
} catch (Throwable $error) {
    respond_error($error);
}

function normalize_categoria(array $row): array
{
    return [
        'id_categoria' => (int) $row['id_categoria'],
        'nombre' => $row['nombre'],
        'total_atractivos' => (int) ($row['total_atractivos'] ?? 0),
    ];
}

function fetch_categoria(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT c.id_categoria,
                                  c.nombre,
                                  (SELECT COUNT(*) FROM atractivo a WHERE a.id_categoria = c.id_categoria) AS total_atractivos
                           FROM categoria c
                           WHERE c.id_categoria = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ? normalize_categoria($row) : null;
}
