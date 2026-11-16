<?php

declare(strict_types=1);

/**
 * Devuelve una unica conexion PDO reutilizable durante la peticion.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        CANETE_DB_HOST,
        CANETE_DB_PORT,
        CANETE_DB_NAME,
        CANETE_DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, CANETE_DB_USER, CANETE_DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $error) {
        json_response([
            'error' => 'No se pudo conectar con la base de datos.',
            'detalle' => $error->getMessage(),
        ], 500);
    }

    return $pdo;
}

/**
 * Ejecuta una consulta preparada y devuelve el statement.
 */
function db_run(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt;
}
