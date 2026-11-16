<?php

declare(strict_types=1);

/**
 * Cabeceras CORS y respuesta inmediata a peticiones OPTIONS.
 */
function apply_cors(): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With');
    header('Access-Control-Max-Age: 86400');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/**
 * Envia una respuesta JSON y termina la peticion.
 */
function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Envia una respuesta de error uniforme.
 */
function fail(string $message, int $status = 400, array $extra = []): never
{
    json_response(array_merge(['error' => $message], $extra), $status);
}

/**
 * Metodo HTTP actual en mayusculas.
 */
function method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

/**
 * Restringe el metodo HTTP permitido.
 */
function require_method(array $allowed): void
{
    if (!in_array(method(), $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        fail('Metodo HTTP no permitido.', 405);
    }
}

/**
 * Lee y valida el cuerpo JSON de la peticion.
 */
function json_body(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        fail('El cuerpo de la solicitud debe ser un objeto JSON valido.', 400);
    }

    return $data;
}

/**
 * Parametro de query string opcional.
 */
function query_param(string $name, mixed $default = null): mixed
{
    return $_GET[$name] ?? $default;
}

/**
 * Entero opcional proveniente del query string.
 */
function id_query_param(string $name = 'id'): ?int
{
    $value = $_GET[$name] ?? null;

    if ($value === null || $value === '') {
        return null;
    }

    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($id === false) {
        fail('El identificador indicado no es valido.');
    }

    return (int) $id;
}

function required_string(array $data, string $key, string $label, int $min = 1, int $max = 255): string
{
    $value = trim((string) ($data[$key] ?? ''));

    if ($value === '') {
        fail("El campo {$label} es obligatorio.", 422);
    }

    if (mb_strlen($value) < $min) {
        fail("El campo {$label} debe tener al menos {$min} caracteres.", 422);
    }

    if (mb_strlen($value) > $max) {
        fail("El campo {$label} no debe superar {$max} caracteres.", 422);
    }

    return $value;
}

function optional_string(array $data, string $key, string $label, int $max = 255): ?string
{
    if (!array_key_exists($key, $data) || $data[$key] === null) {
        return null;
    }

    $value = trim((string) $data[$key]);

    if ($value === '') {
        return null;
    }

    if (mb_strlen($value) > $max) {
        fail("El campo {$label} no debe superar {$max} caracteres.", 422);
    }

    return $value;
}

function optional_text(array $data, string $key, int $max = 5000): ?string
{
    return optional_string($data, $key, $key, $max);
}

function required_name(array $data, string $key, string $label): string
{
    $value = required_string($data, $key, $label, 2, 100);

    if (!preg_match("/^[\\p{L}][\\p{L}\\s.'-]*$/u", $value)) {
        fail("El campo {$label} solo puede contener letras, espacios, puntos, guiones y apostrofes.", 422);
    }

    return $value;
}

function required_email(array $data, string $key, string $label): string
{
    $value = mb_strtolower(required_string($data, $key, $label, 5, 120));

    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
        fail("El campo {$label} debe ser un correo electronico valido.", 422);
    }

    return $value;
}

function optional_phone(array $data, string $key, string $label): ?string
{
    $value = optional_string($data, $key, $label, 20);

    if ($value !== null && !preg_match('/^[0-9+()\s.-]+$/', $value)) {
        fail("El campo {$label} contiene caracteres no validos.", 422);
    }

    return $value;
}

function required_int(array $data, string $key, string $label, int $min = 1, ?int $max = null): int
{
    $value = $data[$key] ?? null;

    if ($value === null || $value === '') {
        fail("El campo {$label} es obligatorio.", 422);
    }

    $options = ['options' => ['min_range' => $min]];

    if ($max !== null) {
        $options['options']['max_range'] = $max;
    }

    $number = filter_var($value, FILTER_VALIDATE_INT, $options);

    if ($number === false) {
        $range = $max !== null ? "entre {$min} y {$max}" : "mayor o igual a {$min}";
        fail("El campo {$label} debe ser un numero entero {$range}.", 422);
    }

    return (int) $number;
}

function optional_int(array $data, string $key, string $label, int $min = 1, ?int $max = null): ?int
{
    if (!array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
        return null;
    }

    return required_int($data, $key, $label, $min, $max);
}

function optional_float(array $data, string $key, string $label): ?float
{
    if (!array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
        return null;
    }

    if (!is_numeric($data[$key])) {
        fail("El campo {$label} debe ser un numero.", 422);
    }

    return (float) $data[$key];
}

/**
 * Resuelve el id de un recurso desde el cuerpo o desde ?id=.
 */
function resolve_id(array $data, string $key): int
{
    $value = $data[$key] ?? ($_GET['id'] ?? null);

    if ($value === null || $value === '') {
        fail('El identificador del registro es obligatorio.', 422);
    }

    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($id === false) {
        fail('El identificador del registro no es valido.', 422);
    }

    return (int) $id;
}

/**
 * Comprueba que una fila exista en una tabla del catalogo.
 */
function assert_exists(PDO $pdo, string $table, string $idColumn, int $id, string $message): void
{
    $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$idColumn} = ? LIMIT 1");
    $stmt->execute([$id]);

    if (!$stmt->fetchColumn()) {
        fail($message, 422);
    }
}

/**
 * Traduce excepciones a respuestas JSON.
 */
function respond_error(Throwable $error): never
{
    if ($error instanceof PDOException) {
        if ((string) $error->getCode() === '23000') {
            fail('La operacion entra en conflicto con datos existentes (registro duplicado o en uso).', 409);
        }

        fail('Ocurrio un error en la base de datos.', 500, [
            'detalle' => $error->getMessage(),
        ]);
    }

    fail('Ocurrio un error interno en el servidor.', 500, [
        'detalle' => $error->getMessage(),
    ]);
}
