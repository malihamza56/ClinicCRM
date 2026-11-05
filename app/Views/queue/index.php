<?php
$sections = [
    'In Progress' => ['person-heart', 'With the doctor', 'icon-tile--blue'],
    'Waiting' => ['hourglass-split', 'Waiting', 'icon-tile--orange'],
    'Expected' => ['clock-history', 'Expected today (not yet arrived)', ''],
];
?>
<div class="page-header">
    <div><h1><?= $isDoctor ? 'My queue' : 'Queue' ?></h1><p>Live list for <?= e(date('l, d F')) ?>. Refresh the page to update.</p></div>
    <div class="page-actions"><a class="btn btn-secondary" href="<?= e(url('/queue')) ?>"><?= icon('arrow-clockwise') ?> Refresh</a><?php if (can('appointments.manage')): ?><a class="btn btn-primary" href="<?= e(url('/appointments/walk-in')) ?>"><?= icon('person-walking') ?> Register walk-in</a><?php endif; ?></div>
</div>
<?php if ($doctors !== []): ?>
<form method="get" class="row" style="margin-bottom:var(--space-4)" data-no-loading>
    <label for="doctor_id" class="small muted">Show queue for</label>
    <select class="input" id="doctor_id" name="doctor_id" style="width:auto" data-autosubmit><option value="">All doctors</option><?php foreach ($doctors as $d): ?><option value="<?= e($d['id']) ?>"<?= selected($doctorId, $d['id']) ?>><?= e($d['full_name']) ?></option><?php endforeach; ?></select>
    <button class="btn btn-secondary btn-sm" type="submit">Apply</button>
</form>
<?php endif; ?>
<?php foreach ($sections as $key => [$glyph, $label, $tile]): $rows = $groups[$key]; ?>
<section class="card" style="margin-bottom:var(--space-4)">
    <div class="card-head"><h2><span class="icon-tile <?= e($tile) ?>"><?= icon($glyph) ?></span> <?= e($label) ?> <span class="badge badge--neutral"><?= e(count($rows)) ?></span></h2></div>
    <?php if ($rows === []): $image = 'empty-list.svg'; $heading = 'Nobody here'; $text = ''; $compact = true; require __DIR__ . '/../partials/empty.php'; else: ?>
    <div class="card-body queue-list">
        <?php foreach ($rows as $q): ?>
        <div class="queue-item<?= $key === 'In Progress' ? ' is-active' : '' ?>">
            <div class="queue-no"><?= $q['queue_number'] ? '#' . e($q['queue_number']) : e(substr((string) $q['start_time'], 0, 5)) ?></div>
            <div class="queue-main"><strong><a href="<?= e(url('/appointments/' . $q['id'])) ?>"><?= e($q['patient_name']) ?></a></strong>
                <span><?= e(format_time($q['start_time'])) ?> &middot; <?= e($q['doctor_name']) ?> &middot; <?= e($q['reason'] ?: 'No reason given') ?><?= $q['booking_type'] === 'Walk-in' ? ' &middot; Walk-in' : '' ?></span></div>
            <?= status_badge($q['status']) ?>
            <div class="row-actions">
            <?php if ($key === 'Expected' && can('appointments.manage') && $q['appointment_date'] === date('Y-m-d')): ?>
                <?= post_button(url('/appointments/' . $q['id'] . '/status'), 'Check in', 'btn btn-primary btn-sm', null, ['to' => 'Waiting', 'back' => 'queue'], 'box-arrow-in-right') ?>
                <?= post_button(url('/appointments/' . $q['id'] . '/status'), 'No-show', 'btn btn-secondary btn-sm', 'Mark ' . $q['patient_name'] . ' as a no-show?', ['to' => 'No Show', 'back' => 'queue']) ?>
            <?php elseif ($key === 'Waiting' && can('appointments.consult')): ?>
                <?= post_button(url('/appointments/' . $q['id'] . '/consult'), 'Start consultation', 'btn btn-primary btn-sm', null, [], 'play-fill') ?>
            <?php elseif ($key === 'In Progress' && can('appointments.consult')): ?>
                <?= post_button(url('/appointments/' . $q['id'] . '/consult'), 'Resume', 'btn btn-secondary btn-sm') ?>
            <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<?php endforeach; ?>
<?php if ($groups['Done'] !== []): ?>
<section class="card"><div class="card-head"><h2><span class="icon-tile"><?= icon('check2-all') ?></span> Finished today <span class="badge badge--neutral"><?= e(count($groups['Done'])) ?></span></h2></div>
<div class="table-wrap"><table class="table"><tbody><?php foreach ($groups['Done'] as $q): ?><tr><td class="nowrap"><?= e(format_time($q['start_time'])) ?></td><td><a href="<?= e(url('/appointments/' . $q['id'])) ?>"><?= e($q['patient_name']) ?></a></td><td class="muted small"><?= e($q['doctor_name']) ?></td><td class="text-right"><?= status_badge($q['status']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php endif; ?>
