<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

require_method(['GET']);

try {
    $pdo = db();

    $totales = [
        'rutas' => (int) $pdo->query('SELECT COUNT(*)
                                      FROM (SELECT 1
                                            FROM ruta r
                                            JOIN detalle_ruta dr ON dr.id_ruta = r.id_ruta
                                            GROUP BY dr.id_turista, dr.id_servicio, r.nombre, r.fecha_visita) grupos')->fetchColumn(),
        'turistas' => (int) $pdo->query('SELECT COUNT(*) FROM turista')->fetchColumn(),
        'servicios' => (int) $pdo->query('SELECT COUNT(*) FROM servicio')->fetchColumn(),
        'atractivos' => (int) $pdo->query('SELECT COUNT(*) FROM atractivo')->fetchColumn(),
        'distritos' => (int) $pdo->query('SELECT COUNT(*) FROM distrito')->fetchColumn(),
        'categorias' => (int) $pdo->query('SELECT COUNT(*) FROM categoria')->fetchColumn(),
        'detalles' => (int) $pdo->query('SELECT COUNT(*) FROM detalle_ruta')->fetchColumn(),
    ];

    $totales['minutos_planificados'] = (int) $pdo->query(
        'SELECT COALESCE(SUM(ROUND(duracion_horas * 60)), 0) FROM ruta'
    )->fetchColumn();

    $rutasPorServicio = $pdo->query('SELECT s.id_servicio,
                                            s.tipo_servicio,
                                            COUNT(DISTINCT CASE
                                                WHEN r.id_ruta IS NOT NULL
                                                THEN CONCAT_WS("|", dr.id_turista, r.nombre, r.fecha_visita)
                                            END) AS total
                                     FROM servicio s
                                     LEFT JOIN detalle_ruta dr ON dr.id_servicio = s.id_servicio
                                     LEFT JOIN ruta r ON r.id_ruta = dr.id_ruta
                                     GROUP BY s.id_servicio, s.tipo_servicio
                                     ORDER BY total DESC, s.tipo_servicio')->fetchAll();

    $rutasPorCategoria = $pdo->query('SELECT c.id_categoria,
                                             c.nombre,
                                             COUNT(DISTINCT CASE
                                                 WHEN r.id_ruta IS NOT NULL
                                                 THEN CONCAT_WS("|", dr.id_turista, dr.id_servicio, r.nombre, r.fecha_visita)
                                             END) AS total
                                      FROM categoria c
                                      LEFT JOIN atractivo a ON a.id_categoria = c.id_categoria
                                      LEFT JOIN ruta r ON r.id_atractivo = a.id_atractivo
                                      LEFT JOIN detalle_ruta dr ON dr.id_ruta = r.id_ruta
                                      GROUP BY c.id_categoria, c.nombre
                                      ORDER BY total DESC, c.nombre')->fetchAll();

    $rutasPorDistrito = $pdo->query('SELECT d.id_distrito,
                                            d.nombre,
                                            COUNT(DISTINCT CASE
                                                WHEN r.id_ruta IS NOT NULL
                                                THEN CONCAT_WS("|", dr.id_turista, dr.id_servicio, r.nombre, r.fecha_visita)
                                            END) AS total
                                     FROM distrito d
                                     LEFT JOIN atractivo a ON a.id_distrito = d.id_distrito
                                     LEFT JOIN ruta r ON r.id_atractivo = a.id_atractivo
                                     LEFT JOIN detalle_ruta dr ON dr.id_ruta = r.id_ruta
                                     GROUP BY d.id_distrito, d.nombre
                                     ORDER BY total DESC, d.nombre')->fetchAll();

    json_response([
        'totales' => $totales,
        'rutas_por_servicio' => map_numeric($rutasPorServicio, 'total'),
        'rutas_por_categoria' => map_numeric($rutasPorCategoria, 'total'),
        'rutas_por_distrito' => map_numeric($rutasPorDistrito, 'total'),
    ]);
} catch (Throwable $error) {
    respond_error($error);
}

function map_numeric(array $rows, string $field): array
{
    return array_map(static function (array $row) use ($field): array {
        $row[$field] = (int) $row[$field];

        return $row;
    }, $rows);
}
