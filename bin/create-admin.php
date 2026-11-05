<?php
declare(strict_types=1);

/*
 * Creates the first clinic administrator (needed on a fresh database without demo data).
 *   php bin/create-admin.php "Full Name" admin@clinic.com
 * The password is typed at the prompt, is never stored in a file, and is hashed with password_hash().
 */

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Database;
use App\Models\User;
use App\Services\AuthService;

/** Prints an error to STDERR and stops with a non-zero exit code, so shell scripts can detect the failure. */
function fail(string $message): never
{
    fwrite(STDERR, rtrim($message) . PHP_EOL);
    exit(1);
}

if (PHP_SAPI !== 'cli') {
    fail('Run this script from the command line.');
}
if ($argc < 3) {
    fail("Usage: php bin/create-admin.php \"Full Name\" email@example.com\n");
}

[$script, $name, $email] = $argv;
$email = strtolower(trim($email));
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fail("That email address is not valid.\n");
}
if (User::findByEmail($email) !== null) {
    fail("An account with that email already exists.\n");
}

echo 'Choose a password (at least 10 characters, letters and numbers): ';
// Hide typing on terminals that support it (Linux/macOS); on Windows the password is visible while typing.
$hide = DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec');
if ($hide) {
    shell_exec('stty -echo');
}
$password = trim((string) fgets(STDIN));
if ($hide) {
    shell_exec('stty echo');
    echo "\n";
}

$problem = AuthService::passwordProblem($password);
if ($problem !== null) {
    fail($problem);
}

$role = Database::one("SELECT id FROM roles WHERE code = 'admin'");
$id = User::create([
    'role_id' => (int) $role['id'], 'full_name' => trim($name), 'email' => $email,
    'phone' => null, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'must_change_password' => 0,
]);
echo "Administrator created (id $id). You can now sign in at the application URL.\n";
