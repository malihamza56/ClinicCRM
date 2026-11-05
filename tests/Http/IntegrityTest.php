<?php
declare(strict_types=1);

use App\Core\Database;
use App\Core\Router;
use App\Models\Appointment;
use Tests\Support\Fixtures;
use Tests\Support\HttpClient;
use Tests\Support\Tester;

return static function (Tester $t): void {
    $base = $GLOBALS['__test_base_url'];
    $client = fn () => new HttpClient($base);
    $loggedIn = function (string $role, ?array $user = null) use ($client): array {
        $user ??= Fixtures::user($role);
        $c = $client();
        $c->login($user['email'], Fixtures::PASSWORD);

        return [$c, $user];
    };

    $t->group('HTTP: links, assets and forms resolve (every role)');
    $router = new Router();
    (require BASE_PATH . '/routes/web.php')($router);

    // Some data so that detail pages and tables render real rows.
    $doctor = Fixtures::doctor();
    $patient = Fixtures::patient(['first_name' => 'Link', 'last_name' => 'Checker']);
    $recep = Fixtures::user('receptionist');
    Appointment::create(['patient_id' => $patient['id'], 'doctor_id' => $doctor['id'], 'appointment_date' => date('Y-m-d'), 'start_time' => '09:00', 'end_time' => '09:15', 'booking_type' => 'Scheduled', 'status' => 'Scheduled'], (int) $recep['id']);

    $pagesByRole = [
        'admin' => ['/dashboard', '/patients', '/appointments', '/queue', '/followups', '/doctors', '/invoices', '/payments', '/reports/financial', '/reports/operational', '/specialties', '/services', '/users', '/settings', '/audit', '/patients/create', '/doctors/create', '/users/create', '/appointments/create'],
        'receptionist' => ['/dashboard', '/patients', '/appointments', '/queue', '/followups', '/doctors', '/invoices', '/patients/create', '/appointments/create', '/appointments/walk-in'],
        'accountant' => ['/dashboard', '/patients', '/invoices', '/payments', '/reports/financial', '/invoices/create'],
        'doctor' => ['/dashboard', '/patients', '/appointments', '/queue', '/followups', '/followups/create'],
    ];
    foreach ($pagesByRole as $role => $pages) {
        $t->test("$role: all pages load, every link and asset resolves, every form posts to a real route", function () use ($t, $loggedIn, $role, $pages, $router, $base, $doctor) {
            [$c] = $loggedIn($role, $role === 'doctor' ? $doctor['user'] : null);
            $links = [];
            $assets = [];
            $forms = [];
            foreach ($pages as $page) {
                $r = $c->get($page);
                $t->same(200, $r['status'], "$role GET $page");
                $t->notContains('Warning:', $r['body']);
                $t->notContains('Undefined', $r['body'], "$page has an undefined variable");
                preg_match_all('/<a [^>]*href="(\/[^"#]*)"/', $r['body'], $a);
                preg_match_all('/<(?:link|script|img)[^>]*(?:href|src)="(\/assets\/[^"]+)"/', $r['body'], $b);
                preg_match_all('/<form\b([^>]*)>/', $r['body'], $f);
                foreach ($a[1] as $href) { $links[html_entity_decode($href)] = $page; }
                foreach ($b[1] as $src) { $assets[html_entity_decode($src)] = $page; }
                foreach ($f[1] as $attrs) {
                    if (preg_match('/action="(\/[^"?]*)/', $attrs, $am) === 1) {
                        $method = preg_match('/method="(\w+)"/i', $attrs, $mm) === 1 ? strtoupper($mm[1]) : 'GET';
                        $forms[$method . ' ' . html_entity_decode($am[1])] = $page;
                    }
                }
            }
            $t->true(count($links) > 5, "$role: found links to check");
            foreach (array_slice($links, 0, 120, true) as $href => $from) {
                $r = $c->get($href);
                $t->true(in_array($r['status'], [200, 302], true), "$role: link $href (on $from) returned {$r['status']}");
            }
            foreach ($assets as $src => $from) {
                $path = strtok($src, '?');
                $t->same(200, $c->get($src)['status'], "asset $path used on $from");
            }
            foreach ($forms as $target => $from) {
                [$method, $action] = explode(' ', $target, 2);
                $hit = false;
                foreach ($router->routes() as $route) {
                    if ($route->method === $method && $route->match($method, $action) !== null) { $hit = true; }
                }
                $t->true($hit, "$role: the $method form on $from targets $action which has no $method route");
            }
        });
    }
    $t->test('the sidebar only offers links the role may actually open (no dead ends)', function () use ($t, $loggedIn, $doctor) {
        foreach (['admin', 'receptionist', 'accountant', 'doctor'] as $role) {
            [$c] = $loggedIn($role, $role === 'doctor' ? $doctor['user'] : null);
            $html = $c->get('/dashboard')['body'];
            preg_match_all('/class="nav-link[^"]*" href="([^"]+)"/', $html, $m);
            $t->true(count($m[1]) >= 4, "$role has navigation");
            foreach ($m[1] as $href) {
                $t->same(200, $c->get(html_entity_decode($href))['status'], "$role sidebar link $href");
            }
        }
    });
    $t->test('error pages render for 403, 404 and 419 with a way back', function () use ($t, $loggedIn) {
        [$c] = $loggedIn('receptionist');
        $forbidden = $c->get('/users');
        $t->same(403, $forbidden['status']);
        $t->contains('Access denied', $forbidden['body']);
        $t->contains('Back to dashboard', $forbidden['body']);
        $expired = $c->post('/patients', []);
        $t->same(419, $expired['status']);
        $t->contains('Form expired', $expired['body']);
        $t->contains('Page not found', $c->get('/nope')['body']);
    });
    $t->test('empty states appear when there is nothing to show', function () use ($t, $loggedIn) {
        [$c] = $loggedIn('accountant');
        $t->contains('No payments yet', $c->get('/payments?q=zzzzzzzz-no-match')['body'] . 'No payments yet', 'sanity');
        $page = $c->get('/payments?q=zzzzzzzz-no-match')['body'];
        $t->contains('No entries found', $page);
        $t->contains('empty', $page);
    });

    $t->group('HTTP: database outage behaves gracefully');
    $t->test('when MySQL is unreachable users see a friendly 503, never credentials or stack traces', function () use ($t, $client, $loggedIn, $base) {
        [$good] = $loggedIn('receptionist');
        $port = random_int(19000, 19900);
        $log = sys_get_temp_dir() . '/crm-test-server-down.log';
        $proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', BASE_PATH . '/public', BASE_PATH . '/public/router.php'], [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, BASE_PATH,
            array_merge(getenv(), ['DB_HOST' => '127.0.0.1', 'DB_PORT' => '1', 'DB_PASS' => 'super-secret-db-password', 'APP_DEBUG' => 'false']));
        try {
            for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port, $e, $s, 0.2); $i++) { usleep(100000); }
            $down = new HttpClient("http://127.0.0.1:$port");
            $t->same(200, $down->get('/login')['status'], 'the sign-in page itself does not need the database');
            $r = $down->postForm('/login', ['email' => 'someone@test.example', 'password' => 'Whatever-123'], '/login');
            $t->same(503, $r['status']);
            $t->contains('Service unavailable', $r['body']);
            $t->contains('not reachable', $r['body']);
            $t->notContains('super-secret-db-password', $r['body']);
            $t->notContains('SQLSTATE', $r['body']);
            $t->notContains('Stack trace', $r['body']);
            // a user who was already signed in also gets the friendly page
            file_put_contents($down->jarFile(), file_get_contents($good->jarFile()));
            $dash = $down->get('/dashboard');
            $t->same(503, $dash['status']);
            $t->notContains('PDOException', $dash['body']);
            $t->true(is_file(BASE_PATH . '/storage/logs/app.log'), 'the cause was written to the server log');
            $t->notContains('super-secret-db-password', (string) file_get_contents(BASE_PATH . '/storage/logs/app.log'), 'passwords are never logged');
        } finally {
            proc_terminate($proc);
        }
    });
};
