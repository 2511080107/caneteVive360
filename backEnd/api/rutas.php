<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

require_method(['GET', 'POST', 'PUT', 'DELETE']);

try {
    $pdo = db();

    if (method() === 'GET') {
        $id = id_query_param();
        $rutas = fetch_rutas($pdo);

        if ($id !== null) {
            $encontrada = null;

            foreach ($rutas as $ruta) {
                if (in_array($id, $ruta['ruta_ids'], true)) {
                    $encontrada = $ruta;
                    break;
                }
            }

            if ($encontrada === null) {
                fail('Ruta no encontrada.', 404);
            }

            json_response(['ruta' => public_ruta($encontrada)]);
        }

        $rutas = filtrar_rutas(
            $rutas,
            id_query_param('servicio'),
            id_query_param('distrito'),
            id_query_param('categoria'),
            trim((string) query_param('q', '')),
            trim((string) query_param('fecha_desde', '')),
            trim((string) query_param('fecha_hasta', ''))
        );

        json_response([
            'rutas' => array_map('public_ruta', $rutas),
            'total' => count($rutas),
        ]);
    }

    if (method() === 'POST') {
        crear_ruta($pdo);
    }

    if (method() === 'PUT') {
        actualizar_ruta($pdo);
    }

    $id = resolve_id([], 'id_ruta');
    $ruta = find_ruta($pdo, $id);

    if ($ruta === null) {
        fail('Ruta no encontrada.', 404);
    }

    eliminar_ruta($pdo, $ruta);

    json_response(['mensaje' => 'Ruta eliminada correctamente.']);
} catch (Throwable $error) {
    respond_error($error);
}

function crear_ruta(PDO $pdo): never
{
    $data = json_body();

    $nombres = required_name($data, 'nombres', 'nombres');
    $apellidos = required_name($data, 'apellidos', 'apellidos');
    $correo = required_email($data, 'correo', 'correo');
    $idServicio = required_int($data, 'id_servicio', 'servicio');

    assert_exists($pdo, 'servicio', 'id_servicio', $idServicio, 'El servicio seleccionado no existe.');

    $items = $data['atractivos'] ?? null;

    if (!is_array($items)) {
        fail('Debes enviar la lista de atractivos de la ruta.', 422);
    }

    if (count($items) < 3 || count($items) > 5) {
        fail('La ruta debe tener entre 3 y 5 atractivos.', 422);
    }

    $detalles = validate_route_items($pdo, $items);
    $nombreRuta = required_string($data, 'nombre_ruta', 'nombre de la ruta', 3, 120);
    $fechaVisita = required_string($data, 'fecha_visita', 'fecha de la ruta', 10, 10);
    date_or_fail($fechaVisita, 'fecha_visita');

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT id_turista FROM turista WHERE correo = ? FOR UPDATE');
        $stmt->execute([$correo]);
        $turista = $stmt->fetch();

        if ($turista) {
            $idTurista = (int) $turista['id_turista'];
            $stmt = $pdo->prepare('UPDATE turista
                                   SET nombres = ?, apellidos = ?
                                   WHERE id_turista = ?');
            $stmt->execute([$nombres, $apellidos, $idTurista]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO turista (nombres, apellidos, correo)
                                   VALUES (?, ?, ?)');
            $stmt->execute([$nombres, $apellidos, $correo]);
            $idTurista = (int) $pdo->lastInsertId();
        }

        if (grupo_existe($pdo, $idTurista, $idServicio, $nombreRuta, $fechaVisita)) {
            fail('Ya existe una ruta con ese nombre y fecha para este turista y servicio.', 409);
        }

        $ids = insertar_ruta($pdo, $idTurista, $idServicio, $nombreRuta, $fechaVisita, $detalles);

        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    json_response([
        'mensaje' => 'Ruta registrada correctamente.',
        'ruta' => public_ruta(find_ruta($pdo, min($ids))),
    ], 201);
}

function actualizar_ruta(PDO $pdo): never
{
    $data = json_body();
    $id = resolve_id($data, 'id_ruta');
    $actual = find_ruta($pdo, $id);

    if ($actual === null) {
        fail('Ruta no encontrada.', 404);
    }

    $cambiaNombre = array_key_exists('nombre', $data);
    $cambiaFecha = array_key_exists('fecha_visita', $data);
    $cambiaServicio = array_key_exists('id_servicio', $data);
    $cambiaAtractivos = array_key_exists('atractivos', $data);

    if (!$cambiaNombre && !$cambiaFecha && !$cambiaServicio && !$cambiaAtractivos) {
        fail('No se enviaron campos para actualizar.', 422);
    }

    $nombreRuta = $cambiaNombre
        ? required_string($data, 'nombre', 'nombre', 3, 120)
        : $actual['nombre'];

    $fechaVisita = $actual['fecha_visita'];

    if ($cambiaFecha) {
        $fechaVisita = required_string($data, 'fecha_visita', 'fecha de la ruta', 10, 10);
        date_or_fail($fechaVisita, 'fecha_visita');
    }

    $idServicio = $actual['servicio']['id_servicio'];

    if ($cambiaServicio) {
        $idServicio = required_int($data, 'id_servicio', 'servicio');
        assert_exists($pdo, 'servicio', 'id_servicio', $idServicio, 'El servicio indicado no existe.');
    }

    $nuevosDetalles = null;

    if ($cambiaAtractivos) {
        $items = $data['atractivos'];

        if (!is_array($items) || count($items) < 3 || count($items) > 5) {
            fail('La ruta debe tener entre 3 y 5 atractivos.', 422);
        }

        $nuevosDetalles = validate_route_items($pdo, $items);
    }

    $idTurista = $actual['turista']['id_turista'];
    $ids = $actual['ruta_ids'];

    $pdo->beginTransaction();

    try {
        if (grupo_existe($pdo, $idTurista, $idServicio, $nombreRuta, $fechaVisita, $ids)) {
            $pdo->rollBack();
            fail('Ya existe una ruta con ese nombre y fecha para este turista y servicio.', 409);
        }

        if ($nuevosDetalles !== null) {
            eliminar_filas_ruta($pdo, $ids);
            $ids = insertar_ruta($pdo, $idTurista, $idServicio, $nombreRuta, $fechaVisita, $nuevosDetalles);
        } else {
            $marcas = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("UPDATE ruta SET nombre = ?, fecha_visita = ? WHERE id_ruta IN ({$marcas})");
            $stmt->execute(array_merge([$nombreRuta, $fechaVisita], $ids));

            $stmt = $pdo->prepare("UPDATE detalle_ruta SET id_servicio = ? WHERE id_ruta IN ({$marcas})");
            $stmt->execute(array_merge([$idServicio], $ids));
        }

        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    json_response([
        'mensaje' => 'Ruta actualizada correctamente.',
        'ruta' => public_ruta(find_ruta($pdo, min($ids))),
    ]);
}

function eliminar_ruta(PDO $pdo, array $ruta): void
{
    $pdo->beginTransaction();

    try {
        eliminar_filas_ruta($pdo, $ruta['ruta_ids']);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function eliminar_filas_ruta(PDO $pdo, array $ids): void
{
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM detalle_ruta WHERE id_ruta IN ({$marcas})");
    $stmt->execute($ids);

    $stmt = $pdo->prepare("DELETE FROM ruta WHERE id_ruta IN ({$marcas})");
    $stmt->execute($ids);
}

function insertar_ruta(PDO $pdo, int $idTurista, int $idServicio, string $nombre, string $fecha, array $detalles): array
{
    $stmtRuta = $pdo->prepare('INSERT INTO ruta
            (nombre, duracion_horas, id_atractivo, orden_visita, fecha_visita)
            VALUES (?, ?, ?, ?, ?)');
    $stmtDetalle = $pdo->prepare('INSERT INTO detalle_ruta
            (id_ruta, id_servicio, id_turista)
            VALUES (?, ?, ?)');

    $ids = [];

    foreach ($detalles as $detalle) {
        $horas = max(0.1, round($detalle['tiempo_estadia_min'] / 60, 1));
        $stmtRuta->execute([
            $nombre,
            $horas,
            $detalle['id_atractivo'],
            $detalle['orden_visita'],
            $fecha,
        ]);

        $idRuta = (int) $pdo->lastInsertId();
        $stmtDetalle->execute([$idRuta, $idServicio, $idTurista]);
        $ids[] = $idRuta;
    }

    return $ids;
}

function grupo_existe(
    PDO $pdo,
    int $idTurista,
    int $idServicio,
    string $nombre,
    string $fecha,
    array $excluirIds = []
): bool {
    $sql = 'SELECT 1
            FROM detalle_ruta dr
            JOIN ruta r ON r.id_ruta = dr.id_ruta
            WHERE dr.id_turista = ?
              AND dr.id_servicio = ?
              AND r.nombre = ?
              AND r.fecha_visita = ?';
    $params = [$idTurista, $idServicio, $nombre, $fecha];

    if ($excluirIds) {
        $marcas = implode(',', array_fill(0, count($excluirIds), '?'));
        $sql .= " AND r.id_ruta NOT IN ({$marcas})";
        $params = array_merge($params, $excluirIds);
    }

    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);

    return (bool) $stmt->fetchColumn();
}

function validate_route_items(PDO $pdo, array $items): array
{
    $vistos = [];
    $limpios = [];

    foreach (array_values($items) as $indice => $item) {
        if (!is_array($item)) {
            fail('Cada atractivo de la ruta debe ser un objeto.', 422);
        }

        $idAtractivo = required_int($item, 'id_atractivo', 'atractivo');
        $tiempo = required_int($item, 'tiempo_estadia_min', 'tiempo de estadia', 15, 1440);

        if (isset($vistos[$idAtractivo])) {
            fail('No puedes repetir el mismo atractivo dentro de la ruta.', 422);
        }

        $vistos[$idAtractivo] = true;

        $limpios[] = [
            'id_atractivo' => $idAtractivo,
            'orden_visita' => $indice + 1,
            'tiempo_estadia_min' => $tiempo,
        ];
    }

    $placeholders = implode(',', array_fill(0, count($vistos), '?'));
    $stmt = $pdo->prepare("SELECT id_atractivo FROM atractivo WHERE id_atractivo IN ({$placeholders})");
    $stmt->execute(array_keys($vistos));
    $encontrados = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    foreach (array_keys($vistos) as $idAtractivo) {
        if (!in_array($idAtractivo, $encontrados, true)) {
            fail("El atractivo con id {$idAtractivo} no existe.", 422);
        }
    }

    return $limpios;
}

function fetch_rutas(PDO $pdo): array
{
    $sql = 'SELECT r.id_ruta,
                   r.nombre,
                   r.duracion_horas,
                   r.orden_visita,
                   r.fecha_visita,
                   r.id_atractivo,
                   dr.id_detalle,
                   dr.id_servicio,
                   dr.id_turista,
                   t.nombres,
                   t.apellidos,
                   t.correo,
                   s.tipo_servicio,
                   s.telefono AS servicio_telefono,
                   s.direccion AS servicio_direccion,
                   ds.id_distrito AS servicio_distrito_id,
                   ds.nombre AS servicio_distrito,
                   a.nombre AS atractivo_nombre,
                   a.descripcion AS atractivo_descripcion,
                   a.direccion AS atractivo_direccion,
                   a.latitud,
                   a.longitud,
                   c.id_categoria,
                   c.nombre AS categoria,
                   d.id_distrito,
                   d.nombre AS distrito
            FROM ruta r
            JOIN detalle_ruta dr ON dr.id_ruta = r.id_ruta
            JOIN turista t ON t.id_turista = dr.id_turista
            JOIN servicio s ON s.id_servicio = dr.id_servicio
            LEFT JOIN distrito ds ON ds.id_distrito = s.id_distrito
            JOIN atractivo a ON a.id_atractivo = r.id_atractivo
            JOIN categoria c ON c.id_categoria = a.id_categoria
            JOIN distrito d ON d.id_distrito = a.id_distrito
            ORDER BY r.fecha_visita DESC, r.nombre, r.orden_visita, r.id_ruta';

    $filas = $pdo->query($sql)->fetchAll();
    $grupos = [];

    foreach ($filas as $fila) {
        $idTurista = (int) $fila['id_turista'];
        $idServicio = (int) $fila['id_servicio'];
        $clave = $idTurista . '|' . $idServicio . '|' . $fila['nombre'] . '|' . $fila['fecha_visita'];

        if (!isset($grupos[$clave])) {
            $grupos[$clave] = [
                'id_ruta' => (int) $fila['id_ruta'],
                'ruta_ids' => [],
                'nombre' => $fila['nombre'],
                'fecha_visita' => $fila['fecha_visita'],
                'duracion_horas' => 0.0,
                'total_minutos' => 0,
                'turista' => [
                    'id_turista' => $idTurista,
                    'nombres' => $fila['nombres'],
                    'apellidos' => $fila['apellidos'],
                    'correo' => $fila['correo'],
                ],
                'servicio' => [
                    'id_servicio' => $idServicio,
                    'tipo_servicio' => $fila['tipo_servicio'],
                    'telefono' => $fila['servicio_telefono'],
                    'direccion' => $fila['servicio_direccion'],
                    'distrito' => $fila['servicio_distrito_id'] !== null ? [
                        'id_distrito' => (int) $fila['servicio_distrito_id'],
                        'nombre' => $fila['servicio_distrito'],
                    ] : null,
                ],
                'detalles' => [],
                'categorias' => [],
                'distritos' => [],
            ];
        }

        $horas = (float) $fila['duracion_horas'];
        $minutos = (int) round($horas * 60);

        $grupos[$clave]['ruta_ids'][] = (int) $fila['id_ruta'];
        $grupos[$clave]['duracion_horas'] += $horas;
        $grupos[$clave]['total_minutos'] += $minutos;
        $grupos[$clave]['detalles'][] = [
            'id_detalle' => (int) $fila['id_detalle'],
            'id_ruta' => (int) $fila['id_ruta'],
            'orden_visita' => (int) ($fila['orden_visita'] ?? 0),
            'duracion_horas' => $horas,
            'tiempo_estadia_min' => $minutos,
            'atractivo' => [
                'id_atractivo' => (int) $fila['id_atractivo'],
                'nombre' => $fila['atractivo_nombre'],
                'descripcion' => $fila['atractivo_descripcion'],
                'direccion' => $fila['atractivo_direccion'],
                'latitud' => $fila['latitud'] !== null ? (float) $fila['latitud'] : null,
                'longitud' => $fila['longitud'] !== null ? (float) $fila['longitud'] : null,
                'categoria' => [
                    'id_categoria' => (int) $fila['id_categoria'],
                    'nombre' => $fila['categoria'],
                ],
                'distrito' => [
                    'id_distrito' => (int) $fila['id_distrito'],
                    'nombre' => $fila['distrito'],
                ],
            ],
        ];
        $grupos[$clave]['categorias'][$fila['categoria']] = true;
        $grupos[$clave]['distritos'][$fila['distrito']] = true;
    }

    $rutas = [];

    foreach ($grupos as $grupo) {
        $grupo['id_ruta'] = min($grupo['ruta_ids']);
        $grupo['duracion_horas'] = round($grupo['duracion_horas'], 1);
        $grupo['total_atractivos'] = count($grupo['detalles']);
        $grupo['categorias'] = array_keys($grupo['categorias']);
        $grupo['distritos'] = array_keys($grupo['distritos']);
        $rutas[] = $grupo;
    }

    usort($rutas, static function (array $a, array $b): int {
        $fecha = strcmp((string) $b['fecha_visita'], (string) $a['fecha_visita']);

        if ($fecha !== 0) {
            return $fecha;
        }

        $nombre = strcmp((string) $a['nombre'], (string) $b['nombre']);

        return $nombre !== 0 ? $nombre : $a['id_ruta'] <=> $b['id_ruta'];
    });

    return $rutas;
}

function filtrar_rutas(
    array $rutas,
    ?int $idServicio,
    ?int $idDistrito,
    ?int $idCategoria,
    string $busqueda,
    string $desde,
    string $hasta
): array {
    if ($desde !== '') {
        $desde = date_or_fail($desde, 'fecha_desde');
    }

    if ($hasta !== '') {
        $hasta = date_or_fail($hasta, 'fecha_hasta');
    }

    $busqueda = mb_strtolower($busqueda);

    return array_values(array_filter($rutas, static function (array $ruta) use (
        $idServicio,
        $idDistrito,
        $idCategoria,
        $busqueda,
        $desde,
        $hasta
    ): bool {
        if ($idServicio !== null && $ruta['servicio']['id_servicio'] !== $idServicio) {
            return false;
        }

        if ($idDistrito !== null) {
            $coincide = false;

            foreach ($ruta['detalles'] as $detalle) {
                if ($detalle['atractivo']['distrito']['id_distrito'] === $idDistrito) {
                    $coincide = true;
                    break;
                }
            }

            if (!$coincide) {
                return false;
            }
        }

        if ($idCategoria !== null) {
            $coincide = false;

            foreach ($ruta['detalles'] as $detalle) {
                if ($detalle['atractivo']['categoria']['id_categoria'] === $idCategoria) {
                    $coincide = true;
                    break;
                }
            }

            if (!$coincide) {
                return false;
            }
        }

        $fecha = (string) $ruta['fecha_visita'];

        if ($desde !== '' && $fecha < $desde) {
            return false;
        }

        if ($hasta !== '' && $fecha > $hasta) {
            return false;
        }

        if ($busqueda !== '') {
            $texto = mb_strtolower(implode(' ', [
                $ruta['turista']['nombres'],
                $ruta['turista']['apellidos'],
                $ruta['turista']['correo'],
                $ruta['nombre'],
                $ruta['servicio']['tipo_servicio'],
            ]));

            if (strpos($texto, $busqueda) === false) {
                return false;
            }
        }

        return true;
    }));
}

function find_ruta(PDO $pdo, int $id): ?array
{
    foreach (fetch_rutas($pdo) as $ruta) {
        if (in_array($id, $ruta['ruta_ids'], true)) {
            return $ruta;
        }
    }

    return null;
}

function public_ruta(array $ruta): array
{
    unset($ruta['ruta_ids']);

    return $ruta;
}

function date_or_fail(string $value, string $campo): string
{
    $fecha = DateTime::createFromFormat('Y-m-d', $value);

    if (!$fecha || $fecha->format('Y-m-d') !== $value) {
        fail("El campo {$campo} debe tener el formato AAAA-MM-DD.", 422);
    }

    return $value;
}
