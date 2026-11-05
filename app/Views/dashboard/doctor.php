<?php /** Doctor dashboard: own queue, schedule and follow-ups. */ ?>
<div class="page-header">
    <div><h1>My day</h1><p><?= e(date('l, d F Y')) ?></p></div>
    <div class="page-actions"><a class="btn btn-primary" href="<?= e(url('/queue')) ?>"><?= icon('hourglass-split') ?> Open my queue</a></div>
</div>

<?php if (!$hasProfile): ?>
    <section class="card"><?php $image = 'error-generic.svg'; $heading = 'No active doctor profile'; $text = 'Your account is not linked to an active doctor profile yet. Please ask the clinic administrator to set it up.'; require __DIR__ . '/../partials/empty.php'; ?></section>
<?php return; endif; $k = $kpis; ?>

<div class="kpi-grid kpi-grid--4">
    <?php
    $label = 'Appointments today'; $value = (string) $k['today_total']; $glyph = 'calendar2-check'; $tone = 'green'; $note = 'Assigned to me'; require __DIR__ . '/_kpi.php';
    $label = 'Waiting'; $value = (string) $k['waiting']; $glyph = 'hourglass-split'; $tone = 'orange'; $note = 'Checked in'; require __DIR__ . '/_kpi.php';
    $label = 'In consultation'; $value = (string) $k['in_progress']; $glyph = 'person-heart'; $tone = 'blue'; $note = 'Right now'; require __DIR__ . '/_kpi.php';
    $label = 'Completed'; $value = (string) $k['completed']; $glyph = 'check2-circle'; $tone = 'green'; $note = 'Today'; require __DIR__ . '/_kpi.php';
    ?>
</div>

<div class="grid grid-main" style="margin-top:var(--space-4)">
    <div class="stack">
        <section class="card">
            <div class="card-head"><div><h2><span class="icon-tile icon-tile--blue"><?= icon('list-ol') ?></span> My waiting queue</h2><p>Call the next patient to start a consultation</p></div></div>
            <?php if ($queue === []): ?>
                <?php $image = 'empty-list.svg'; $heading = 'No patients waiting'; $text = 'Patients appear here as soon as the front desk checks them in.'; $compact = true; require __DIR__ . '/../partials/empty.php'; ?>
            <?php else: ?>
                <div class="card-body queue-list">
                <?php foreach ($queue as $q): ?>
                    <div class="queue-item<?= $q['status'] === 'In Progress' ? ' is-active' : '' ?>">
                        <div class="queue-no">#<?= e($q['queue_number']) ?></div>
                        <div class="queue-main"><strong><?= e($q['patient_name']) ?></strong><span><?= e(age_from($q['date_of_birth'])) ?> &middot; <?= e($q['reason'] ?: 'No reason given') ?></span></div>
                        <?php if ($q['status'] === 'Waiting'): ?>
                            <form method="post" action="<?= e(url('/appointments/' . $q['id'] . '/consult')) ?>"><?= csrf_field() ?><button class="btn btn-primary btn-sm" type="submit"><?= icon('play-fill') ?> Start</button></form>
                        <?php else: ?>
                            <a class="btn btn-secondary btn-sm" href="<?= e(url('/appointments/' . $q['id'])) ?>">Resume</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <section class="card">
            <div class="card-head"><h2><span class="icon-tile"><?= icon('calendar2-week') ?></span> Today's schedule</h2></div>
            <?php $rows = $schedule; $emptyText = 'Nothing is booked for you today.'; require __DIR__ . '/_today_table.php'; ?>
        </section>
    </div>
    <div class="stack">
        <section class="card">
            <div class="card-head"><h2><span class="icon-tile icon-tile--orange"><?= icon('arrow-repeat') ?></span> My follow-ups</h2><a class="btn btn-ghost btn-sm" href="<?= e(url('/followups')) ?>">Open</a></div>
            <div class="card-body">
                <div class="stat-pair" style="margin-bottom:var(--space-4)">
                    <div class="stat"><span>Overdue</span><strong><?= e($follow_ups['overdue']) ?></strong></div>
                    <div class="stat"><span>Due today</span><strong><?= e($follow_ups['due']) ?></strong></div>
                    <div class="stat"><span>Upcoming</span><strong><?= e($follow_ups['upcoming']) ?></strong></div>
                </div>
                <?php foreach (array_merge($overdue_follow_ups, $due_follow_ups) as $f): ?>
                    <div class="row row--between small" style="padding:8px 0;border-top:1px solid var(--line)"><span><strong><?= e($f['patient_name']) ?></strong><br><span class="muted"><?= e(format_date($f['follow_up_date'])) ?></span></span><?= status_badge($f['bucket'] === 'Overdue' ? 'Overdue' : 'Pending') ?></div>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="card">
            <div class="card-head"><h2><span class="icon-tile icon-tile--blue"><?= icon('journal-medical') ?></span> Recent consultations</h2></div>
            <?php if ($recent_visits === []): ?>
                <?php $image = 'empty-list.svg'; $heading = 'No consultations yet'; $text = 'Finished consultations will be listed here.'; $compact = true; require __DIR__ . '/../partials/empty.php'; ?>
            <?php else: ?>
                <div class="table-wrap"><table class="table"><tbody>
                <?php foreach ($recent_visits as $v): ?>
                    <tr><td><a href="<?= e(url('/visits/' . $v['id'])) ?>"><strong><?= e($v['patient_name']) ?></strong></a><br><span class="small muted"><?= e(format_datetime($v['visited_at'])) ?></span></td><td class="text-right"><?= status_badge($v['status']) ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </section>
    </div>
</div>
