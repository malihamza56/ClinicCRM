<?php
/** Main application shell: sidebar + top bar + content. Receives $content (page HTML) and optional $title, $charts. */
use App\Core\Auth;

$user = Auth::user();
$clinicName = setting('clinic_name', 'Clinic CRM');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? 'Dashboard') . ' | ' . $clinicName) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<div class="app">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>
    <div class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="btn btn-secondary btn-sm menu-toggle" type="button" id="menuToggle" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false"><?= icon('list') ?></button>
                <div class="welcome">Welcome, <strong><?= e($user['full_name'] ?? '') ?></strong></div>
            </div>
            <div class="topbar-right">
                <span class="date-chip"><?= icon('calendar3') ?> <?= e(date('D, d M Y')) ?></span>
                <?php if (can('appointments.manage')): ?>
                    <a class="btn btn-primary btn-sm" href="<?= e(url('/appointments/create')) ?>"><?= icon('plus-lg') ?> Appointment</a>
                <?php endif; ?>
                <details class="user-menu">
                    <summary aria-label="Account menu">
                        <span class="avatar"><?= e(initials((string) ($user['full_name'] ?? ''))) ?></span>
                        <span class="user-meta"><strong><?= e($user['full_name'] ?? '') ?></strong><span><?= e($user['role_name'] ?? '') ?></span></span>
                        <?= icon('chevron-down') ?>
                    </summary>
                    <div class="user-dropdown">
                        <a href="<?= e(url('/account/password')) ?>"><?= icon('key') ?> Change password</a>
                        <form method="post" action="<?= e(url('/logout')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit"><?= icon('box-arrow-right') ?> Sign out</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>
        <main id="main" class="content">
            <?php require __DIR__ . '/../partials/flash.php'; ?>
            <?= $content ?>
        </main>
    </div>
</div>

<dialog class="confirm" id="confirmDialog" aria-labelledby="confirmTitle">
    <form method="dialog">
        <div class="confirm-body">
            <h3 id="confirmTitle"><?= icon('exclamation-triangle', 'icon-warn') ?> Please confirm</h3>
            <p id="confirmText" class="muted"></p>
        </div>
        <div class="confirm-actions">
            <button class="btn btn-secondary" value="cancel">Cancel</button>
            <button class="btn btn-danger" value="ok" id="confirmOk">Yes, continue</button>
        </div>
    </form>
</dialog>

<?php if (!empty($charts)): ?>
    <script src="<?= e(asset('vendor/chartjs/chart.umd.js')) ?>"></script>
<?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
