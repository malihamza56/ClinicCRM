<?php /** Two-panel sign-in layout. Receives $content and optional $title. */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? 'Sign in') . ' | Clinic CRM') ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="auth">
    <section class="auth-art" aria-hidden="true">
        <div class="brand"><img src="<?= e(asset('images/logo.svg')) ?>" alt=""><div><strong>Clinic CRM</strong><small>Multi-specialty clinic management</small></div></div>
        <div>
            <h2>One calm place for your whole clinic.</h2>
            <p>From the front desk to the consultation room and the billing counter - every step of a patient's visit stays connected.</p>
            <img class="hero" src="<?= e(asset('images/login-hero.svg')) ?>" alt="">
            <ul>
                <li><?= icon('calendar2-check') ?> Appointments, walk-ins and a live waiting queue</li>
                <li><?= icon('journal-medical') ?> Consultations, prescriptions and follow-ups</li>
                <li><?= icon('receipt') ?> Invoices, partial payments and clear balances</li>
            </ul>
        </div>
        <small>&copy; <?= e(date('Y')) ?> Clinic CRM</small>
    </section>
    <main class="auth-form-wrap" id="main">
        <div class="auth-form">
            <?php require __DIR__ . '/../partials/flash.php'; ?>
            <?= $content ?>
        </div>
    </main>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
