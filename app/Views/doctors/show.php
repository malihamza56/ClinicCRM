<?php $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
$byDay = []; foreach ($schedules as $s) { $byDay[(int) $s['weekday']][] = $s; } ?>
<div class="page-header">
    <div><div class="breadcrumb"><a href="<?= e(url('/doctors')) ?>">Doctors</a> / Profile</div>
    <div class="profile-head"><span class="avatar avatar--lg"><?= e(initials($doctor['full_name'])) ?></span><div><h1><?= e($doctor['full_name']) ?></h1><p><?= e($doctor['specialty_name']) ?> &middot; <?= e($doctor['qualification'] ?: 'Qualification not set') ?></p></div></div></div>
    <?php if (can('doctors.manage')): ?><div class="page-actions"><a class="btn btn-secondary" href="<?= e(url('/doctors/' . $doctor['id'] . '/edit')) ?>"><?= icon('pencil') ?> Edit</a>
    <?= post_button(url('/doctors/' . $doctor['id'] . '/toggle'), $doctor['is_active'] ? 'Deactivate' : 'Activate', $doctor['is_active'] ? 'btn btn-danger-outline' : 'btn btn-secondary', $doctor['is_active'] ? 'Deactivate this doctor? They will not appear for new bookings.' : null) ?></div><?php endif; ?>
</div>
<div class="grid grid-main">
    <section class="card"><div class="card-head"><div><h2><span class="icon-tile"><?= icon('calendar-week') ?></span> Weekly working hours</h2><p>Appointment slots are created from these windows (<?= e($doctor['slot_minutes']) ?>-minute visits).</p></div></div>
    <div class="card-body--flush"><table class="table"><tbody>
    <?php foreach ($days as $n => $name): ?>
        <tr><td style="width:140px"><strong><?= e($name) ?></strong></td><td>
        <?php if (empty($byDay[$n])): ?><span class="muted">Off</span><?php else: foreach ($byDay[$n] as $s): ?>
            <span class="badge badge--neutral" style="margin-right:6px"><?= e(format_time($s['start_time'])) ?> - <?= e(format_time($s['end_time'])) ?></span>
            <?php if (can('doctors.manage')): ?><?= post_button(url('/doctors/' . $doctor['id'] . '/schedule/' . $s['id'] . '/delete'), 'Remove', 'btn btn-ghost btn-sm', 'Remove this working window? Existing appointments are not cancelled.') ?><?php endif; ?>
        <?php endforeach; endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <?php if (can('doctors.manage')): ?>
    <form method="post" action="<?= e(url('/doctors/' . $doctor['id'] . '/schedule')) ?>" class="card-foot" novalidate><?= csrf_field() ?>
        <div class="row" style="align-items:flex-end">
            <div class="field"><label for="weekday">Day</label><select class="input" name="weekday" id="weekday"><?php foreach ($days as $n => $name): ?><option value="<?= e($n) ?>"><?= e($name) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="start_time">From</label><input class="input" type="time" name="start_time" id="start_time" value="09:00" required></div>
            <div class="field"><label for="end_time">To</label><input class="input" type="time" name="end_time" id="end_time" value="13:00" required></div>
            <button class="btn btn-primary" type="submit"><?= icon('plus-lg') ?> Add hours</button>
        </div>
    </form><?php endif; ?></section>
    <section class="card"><div class="card-head"><h2>Details</h2></div><div class="card-body"><dl class="dl">
        <dt>Status</dt><dd><?= status_badge(($doctor['is_active'] && $doctor['account_active']) ? 'Active' : 'Inactive') ?></dd>
        <dt>Consultation fee</dt><dd><?= e(money($doctor['consultation_fee'])) ?></dd>
        <dt>Room</dt><dd><?= e($doctor['room'] ?: '-') ?></dd>
        <dt>Visit length</dt><dd><?= e($doctor['slot_minutes']) ?> minutes</dd>
        <dt>License no.</dt><dd><?= e($doctor['license_no'] ?: '-') ?></dd>
        <dt>Phone</dt><dd><?= e($doctor['phone'] ?: '-') ?></dd>
        <dt>Login email</dt><dd><?= e($doctor['email']) ?></dd></dl></div></section>
</div>
