<?php

declare(strict_types=1);

define('CANETE_DB_HOST', getenv('CANETE_DB_HOST') ?: '127.0.0.1');
define('CANETE_DB_PORT', getenv('CANETE_DB_PORT') ?: '3306');
define('CANETE_DB_NAME', getenv('CANETE_DB_NAME') ?: 'canetevive360');
define('CANETE_DB_USER', getenv('CANETE_DB_USER') ?: 'root');
define('CANETE_DB_PASS', getenv('CANETE_DB_PASS') ?: '');
define('CANETE_DB_CHARSET', 'utf8mb4');

define('CANETE_ZONA_HORARIA', 'America/Lima');
