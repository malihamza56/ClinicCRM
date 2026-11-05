<?php
$today = $a['appointment_date'] === date('Y-m-d');
$labels = ['Confirmed' => ['Confirm', 'check2'], 'Waiting' => ['Check in', 'box-arrow-in-right'], 'No Show' => ['Mark no-show', 'person-x']];
?>
<div class="page-header">
    <div><div class="breadcrumb"><a href="<?= e(url('/appointments')) ?>">Appointments</a> / Details</div><h1>Appointment <?= status_badge($a['status']) ?></h1>
    <p><?= e(format_date($a['appointment_date'], 'l, d F Y')) ?> at <?= e(format_time($a['start_time'])) ?> &middot; <?= e($a['booking_type']) ?></p></div>
    <div class="page-actions">
        <?php if ($canOpenVisit): ?><a class="btn btn-primary" href="<?= e(url('/visits/' . $visit['id'] . ($visit['status'] === 'Draft' ? '/edit' : ''))) ?>"><?= icon('journal-medical') ?> Open consultation</a><?php endif; ?>
        <?php if (can('appointments.consult') && $a['status'] === 'Waiting'): ?><?= post_button(url('/appointments/' . $a['id'] . '/consult'), 'Start consultation', 'btn btn-primary', null, [], 'play-fill') ?><?php endif; ?>
        <?php if ($visit !== null && can('invoices.create') && $visit['status'] === 'Finalized'): ?><a class="btn btn-secondary" href="<?= e(url('/invoices/create', ['patient_id' => $a['patient_id'], 'visit_id' => $visit['id']])) ?>"><?= icon('receipt') ?> Create invoice</a><?php endif; ?>
    </div>
</div>
<div class="grid grid-main">
    <section class="card"><div class="card-head"><h2>Visit details</h2></div><div class="card-body"><dl class="dl">
        <dt>Patient</dt><dd><div class="cell-person"><span class="avatar"><?= e(initials($a['patient_name'])) ?></span><div><?php if (can('patients.view')): ?><a href="<?= e(url('/patients/' . $a['patient_id'])) ?>"><strong><?= e($a['patient_name']) ?></strong></a><?php else: ?><strong><?= e($a['patient_name']) ?></strong><?php endif; ?><br><span class="small muted"><?= e($a['patient_code']) ?> &middot; <?= e($a['patient_phone']) ?></span></div></div></dd>
        <dt>Doctor</dt><dd><?= e($a['doctor_name']) ?> <span class="muted">(<?= e($a['specialty_name']) ?>)</span></dd>
        <dt>Room</dt><dd><?= e($a['room'] ?: '-') ?></dd>
        <dt>Time</dt><dd><?= e(format_time($a['start_time'])) ?> - <?= e(format_time($a['end_time'])) ?></dd>
        <dt>Reason</dt><dd><?= e($a['reason'] ?: '-') ?></dd>
        <?php if ($a['queue_number']): ?><dt>Queue number</dt><dd>#<?= e($a['queue_number']) ?></dd><?php endif; ?>
        <?php if ($a['cancellation_reason']): ?><dt>Cancellation reason</dt><dd><?= e($a['cancellation_reason']) ?></dd><?php endif; ?>
    </dl></div></section>
    <?php if (can('appointments.manage') && $next !== []): ?>
    <section class="card"><div class="card-head"><h2>Actions</h2></div><div class="card-body stack">
        <?php foreach ($next as $to): if (!isset($labels[$to])) { continue; }
            if ($to === 'Waiting' && !$today) { echo '<p class="small muted">Check-in is available on the day of the appointment.</p>'; continue; }
            if ($to === 'No Show' && $a['appointment_date'] > date('Y-m-d')) { continue; } ?>
            <?= post_button(url('/appointments/' . $a['id'] . '/status'), $labels[$to][0], $to === 'Waiting' ? 'btn btn-primary btn-block' : 'btn btn-secondary btn-block', $to === 'No Show' ? 'Mark this patient as a no-show?' : null, ['to' => $to], $labels[$to][1]) ?>
        <?php endforeach; ?>
        <?php if ($canReschedule): ?><a class="btn btn-secondary btn-block" href="<?= e(url('/appointments/' . $a['id'] . '/reschedule')) ?>"><?= icon('calendar2-range') ?> Reschedule</a><?php endif; ?>
        <?php if (in_array('Cancelled', $next, true)): ?>
        <form method="post" action="<?= e(url('/appointments/' . $a['id'] . '/status')) ?>" novalidate><?= csrf_field() ?><input type="hidden" name="to" value="Cancelled">
            <div class="field"><label for="reason">Cancel appointment</label><input class="input" id="reason" name="reason" maxlength="255" placeholder="Reason (required)" required></div>
            <button class="btn btn-danger-outline btn-block" style="margin-top:8px" type="submit" data-confirm="Cancel this appointment? The time slot becomes free again."><?= icon('x-circle') ?> Cancel appointment</button>
        </form><?php endif; ?>
    </div></section>
    <?php endif; ?>
</div>
