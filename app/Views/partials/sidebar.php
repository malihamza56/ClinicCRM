<?php
/** Left navigation. Links are filtered by permission - the server still enforces access on every route. */
use App\Config\Roles;
use App\Core\Auth;

$queueLabel = Auth::isRole(Roles::DOCTOR) ? 'My queue' : 'Queue';
$groups = [
    ['label' => 'Main', 'items' => [
        ['/dashboard', 'Dashboard', 'speedometer2', ['dashboard.view']],
        ['/patients', 'Patients', 'people', ['patients.view', 'patients.view_billing']],
        ['/appointments', 'Appointments', 'calendar2-check', ['appointments.view']],
        ['/queue', $queueLabel, 'hourglass-split', ['queue.view']],
        ['/followups', 'Follow-ups', 'arrow-repeat', ['followups.view']],
        ['/doctors', 'Doctors', 'heart-pulse', ['doctors.view']],
    ]],
    ['label' => 'Billing', 'items' => [
        ['/invoices', 'Invoices', 'receipt', ['invoices.view']],
        ['/payments', 'Payments', 'cash-coin', ['payments.view']],
    ]],
    ['label' => 'Insights', 'items' => [
        ['/reports/financial', 'Financial reports', 'graph-up-arrow', ['reports.financial']],
        ['/reports/operational', 'Operational reports', 'bar-chart-line', ['reports.operational']],
    ]],
    ['label' => 'Administration', 'items' => [
        ['/specialties', 'Specialties', 'diagram-3', ['specialties.manage']],
        ['/services', 'Services & fees', 'clipboard2-pulse', ['services.manage']],
        ['/users', 'Staff accounts', 'person-gear', ['users.manage']],
        ['/settings', 'Clinic settings', 'gear', ['settings.manage']],
        ['/audit', 'Audit log', 'shield-check', ['audit.view']],
    ]],
];
?>
<aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <a class="brand" href="<?= e(url('/dashboard')) ?>">
        <img src="<?= e(asset('images/logo.svg')) ?>" alt="">
        <span><strong>Clinic CRM</strong><small><?= e(setting('clinic_name', 'Clinic')) ?></small></span>
    </a>
    <nav>
        <?php foreach ($groups as $group):
            $items = array_filter($group['items'], static fn (array $i): bool => can_any(...$i[3]));
            if ($items === []) {
                continue;
            }
            ?>
            <div class="nav-group">
                <div class="nav-label"><?= e($group['label']) ?></div>
                <?php foreach ($items as [$path, $label, $glyph]): ?>
                    <a class="nav-link<?= nav_active($path) ?>" href="<?= e(url($path)) ?>"<?= nav_active($path) !== '' ? ' aria-current="page"' : '' ?>>
                        <?= icon($glyph) ?><span><?= e($label) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">Clinic CRM v<?= e((string) \App\Core\Config::get('app.version')) ?></div>
</aside>
