<?php /** Front-desk dashboard: arrivals, queue and registration shortcuts. */ $k = $kpis; ?>
<div class="page-header">
    <div><h1>Front desk</h1><p>Today, <?= e(date('l, d F Y')) ?></p></div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="<?= e(url('/patients/create')) ?>"><?= icon('person-plus') ?> Register patient</a>
        <a class="btn btn-secondary" href="<?= e(url('/appointments/walk-in')) ?>"><?= icon('person-walking') ?> Walk-in</a>
        <a class="btn btn-primary" href="<?= e(url('/appointments/create')) ?>"><?= icon('calendar-plus') ?> Book appointment</a>
    </div>
</div>

<div class="kpi-grid kpi-grid--4">
    <?php
    $label = 'Appointments today'; $value = (string) $k['today_total']; $glyph = 'calendar2-check'; $tone = 'green'; $note = 'All doctors'; require __DIR__ . '/_kpi.php';
    $label = 'In the queue'; $value = (string) $k['waiting']; $glyph = 'hourglass-split'; $tone = 'blue'; $note = 'Waiting or with a doctor'; require __DIR__ . '/_kpi.php';
    $label = 'Still expected'; $value = (string) $k['expected']; $glyph = 'clock-history'; $tone = 'orange'; $note = 'Booked, not yet checked in'; require __DIR__ . '/_kpi.php';
    $label = 'New patients today'; $value = (string) $k['new_patients']; $glyph = 'person-plus'; $tone = 'green'; $note = 'Registered today'; require __DIR__ . '/_kpi.php';
    ?>
</div>

<div class="grid grid-main" style="margin-top:var(--space-4)">
    <section class="card">
        <div class="card-head"><div><h2><span class="icon-tile"><?= icon('calendar2-week') ?></span> Today's schedule</h2><p>Check patients in as they arrive</p></div><a class="btn btn-secondary btn-sm" href="<?= e(url('/queue')) ?>">Open queue</a></div>
        <?php $rows = $schedule; require __DIR__ . '/_today_table.php'; ?>
    </section>
    <div class="stack">
        <section class="card">
            <div class="card-head"><h2><span class="icon-tile icon-tile--blue"><?= icon('list-ol') ?></span> Waiting now</h2></div>
            <?php if ($queue === []): ?>
                <?php $image = 'empty-list.svg'; $heading = 'Queue is empty'; $text = 'Checked-in patients appear here in order.'; $compact = true; require __DIR__ . '/../partials/empty.php'; ?>
            <?php else: ?>
                <div class="card-body queue-list">
                <?php foreach (array_slice($queue, 0, 6) as $q): ?>
                    <div class="queue-item<?= $q['status'] === 'In Progress' ? ' is-active' : '' ?>"><div class="queue-no">#<?= e($q['queue_number']) ?></div><div class="queue-main"><strong><?= e($q['patient_name']) ?></strong><span><?= e($q['doctor_name']) ?></span></div><?= status_badge($q['status']) ?></div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <section class="card">
            <div class="card-head"><h2><span class="icon-tile icon-tile--orange"><?= icon('arrow-repeat') ?></span> Follow-up calls</h2><a class="btn btn-ghost btn-sm" href="<?= e(url('/followups')) ?>">Open</a></div>
            <div class="card-body stat-pair">
                <div class="stat"><span>Overdue</span><strong><?= e($follow_ups['overdue']) ?></strong></div>
                <div class="stat"><span>Due today</span><strong><?= e($follow_ups['due']) ?></strong></div>
            </div>
        </section>
    </div>
</div>
