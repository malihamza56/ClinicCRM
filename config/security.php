<?php
declare(strict_types=1);

use App\Core\Env;

return [
    'session_name'          => 'clinic_crm_session',
    'session_idle_minutes'  => (int) Env::get('SESSION_IDLE_MINUTES', '30'),
    'cookie_secure'         => Env::get('SESSION_COOKIE_SECURE', 'auto'),
    // Throttling is per (email + IP) pair so that a stranger cannot lock a real user out of their own account.
    'login_max_attempts'    => (int) Env::get('LOGIN_MAX_ATTEMPTS', '5'),
    'login_lockout_minutes' => (int) Env::get('LOGIN_LOCKOUT_MINUTES', '15'),
    // Safety net against one IP guessing many different accounts.
    'login_ip_max_attempts' => 25,
    'password_min_length'   => 10,
];
