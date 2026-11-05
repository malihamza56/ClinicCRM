<?php
declare(strict_types=1);

use App\Core\Env;

return [
    'name'     => 'Clinic CRM',
    'version'  => '1.0.0',
    'env'      => Env::get('APP_ENV', 'production'),
    'debug'    => Env::bool('APP_DEBUG', false),
    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Karachi'),
];
