<?php $isDoctorView = !can('appointments.manage') && !empty($filters['doctor_id']) && $doctors === []; ?>
<div class="page-header">
    <div><h1>Appointments</h1><p><?= $filters['date'] ? 'Showing ' . e(format_date($filters['date'])) : 'Showing all dates' ?><?= $filters['date'] !== '' ? ' &middot; <a href="' . e(url('/appointments', ['date' => ''] + ['q' => $filters['q']])) . '">show all dates</a>' : '' ?></p></div>
    <div class="page-actions">
        <?php if (can('exports.appointments')): ?><a class="btn btn-secondary" href="<?= e(url('/appointments/export', ['date' => $filters['date'], 'doctor_id' => $filters['doctor_id'], 'status' => $filters['status'], 'q' => $filters['q']])) ?>"><?= icon('download') ?> Export CSV</a><?php endif; ?>
        <?php if (can('appointments.manage')): ?>
            <a class="btn btn-secondary" href="<?= e(url('/appointments/walk-in')) ?>"><?= icon('person-walking') ?> Walk-in</a>
            <a class="btn btn-primary" href="<?= e(url('/appointments/create')) ?>"><?= icon('calendar-plus') ?> Book appointment</a>
        <?php endif; ?>
    </div>
</div>
<div class="card">
    <form class="filters" method="get" data-no-loading>
        <div class="field"><label for="date">Date</label><input class="input" type="date" id="date" name="date" value="<?= e($filters['date']) ?>"></div>
        <?php if ($doctors !== []): ?><div class="field"><label for="doctor_id">Doctor</label><select class="input" id="doctor_id" name="doctor_id"><option value="">All doctors</option><?php foreach ($doctors as $d): ?><option value="<?= e($d['id']) ?>"<?= selected($filters['doctor_id'], $d['id']) ?>><?= e($d['full_name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
        <div class="field"><label for="status">Status</label><select class="input" id="status" name="status"><option value="">All statuses</option><?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>"<?= selected($filters['status'], $s) ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
        <div class="field field--grow"><label for="q">Patient</label><div class="input-icon"><?= icon('search') ?><input class="input" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Name, ID or phone"></div></div>
        <button class="btn btn-primary" type="submit"><?= icon('funnel') ?> Filter</button>
        <a class="btn btn-secondary" href="<?= e(url('/appointments')) ?>">Today</a>
    </form>
    <?php if ($page->items === []): $image = 'empty-calendar.svg'; $heading = 'No appointments found'; $text = 'Try another date or clear the filters.'; $actionUrl = can('appointments.manage') ? url('/appointments/create') : null; $actionLabel = 'Book appointment'; require __DIR__ . '/../partials/empty.php'; else: ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>When</th><th>Patient</th><th>Doctor</th><th>Reason</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($page->items as $a): ?>
            <tr>
                <td class="nowrap"><strong><?= e(format_date($a['appointment_date'])) ?></strong><br><span class="small muted"><?= e(format_time($a['start_time'])) ?><?= $a['booking_type'] === 'Walk-in' ? ' &middot; Walk-in' : '' ?></span></td>
                <td><div class="cell-person"><span class="avatar"><?= e(initials($a['patient_name'])) ?></span><div><strong><?= e($a['patient_name']) ?></strong><span><?= e($a['patient_code']) ?></span></div></div></td>
                <td><?= e($a['doctor_name']) ?><br><span class="small muted"><?= e($a['specialty_name']) ?></span></td>
                <td class="small"><?= e($a['reason'] ?: '-') ?></td>
                <td><?= status_badge($a['status']) ?></td>
                <td><div class="row-actions"><a class="btn btn-secondary btn-sm" href="<?= e(url('/appointments/' . $a['id'])) ?>">Open</a></div></td>
            </tr>
        <?php endforeach; ?></tbody></table></div>
    <?php require __DIR__ . '/../partials/pagination.php'; endif; ?>
</div>
