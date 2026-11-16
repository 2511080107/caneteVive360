<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/database.php';

date_default_timezone_set(CANETE_ZONA_HORARIA);

apply_cors();
