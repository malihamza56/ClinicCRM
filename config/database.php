<?php
declare(strict_types=1);

use App\Core\Env;

return [
    'host'    => Env::get('DB_HOST', '127.0.0.1'),
    'port'    => (int) Env::get('DB_PORT', '3306'),
    'name'    => Env::get('DB_NAME', 'clinic_crm'),
    'user'    => Env::get('DB_USER', 'root'),
    'pass'    => Env::get('DB_PASS', ''),
    'charset' => 'utf8mb4',
];
