<?php $archived = (bool) $patient['is_archived']; ?>
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="<?= e(url('/patients')) ?>">Patients</a> / Profile</div>
        <div class="profile-head"><span class="avatar avatar--lg"><?= e(initials($patient['full_name'])) ?></span>
        <div><h1><?= e($patient['full_name']) ?></h1><p><?= e($patient['patient_code']) ?> <?= status_badge($archived ? 'Archived' : 'Active') ?></p></div></div>
    </div>
    <div class="page-actions">
        <?php if ($full && !$archived && can('appointments.manage')): ?><a class="btn btn-primary" href="<?= e(url('/appointments/create', ['patient_id' => $patient['id']])) ?>"><?= icon('calendar-plus') ?> Book appointment</a><?php endif; ?>
        <?php if (!$archived && can('invoices.create')): ?><a class="btn btn-secondary" href="<?= e(url('/invoices/create', ['patient_id' => $patient['id']])) ?>"><?= icon('receipt') ?> New invoice</a><?php endif; ?>
        <?php if (can('patients.update')): ?><a class="btn btn-secondary" href="<?= e(url('/patients/' . $patient['id'] . '/edit')) ?>"><?= icon('pencil') ?> Edit</a><?php endif; ?>
        <?php if (can('patients.archive')): ?>
            <?= $archived ? post_button(url('/patients/' . $patient['id'] . '/restore'), 'Restore', 'btn btn-secondary', null, [], 'arrow-counterclockwise')
                : post_button(url('/patients/' . $patient['id'] . '/archive'), 'Archive', 'btn btn-danger-outline', 'Archive this patient? They will be hidden from searches and cannot be booked, but all history is kept.', [], 'archive') ?>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-main">
    <div class="stack">
        <?php if ($full): ?>
        <section class="card"><div class="card-head"><h2><span class="icon-tile"><?= icon('calendar2-week') ?></span> Appointments</h2></div>
            <?php if ($appointments === []): $image = 'empty-calendar.svg'; $heading = 'No appointments yet'; $text = 'Booked visits will be listed here.'; $compact = true; require __DIR__ . '/../partials/empty.php'; else: ?>
            <div class="table-wrap"><table class="table"><thead><tr><th>When</th><th>Doctor</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($appointments as $a): ?><tr><td class="nowrap"><a href="<?= e(url('/appointments/' . $a['id'])) ?>"><strong><?= e(format_date($a['appointment_date'])) ?></strong></a><br><span class="small muted"><?= e(format_time($a['start_time'])) ?></span></td><td><?= e($a['doctor_name']) ?><br><span class="small muted"><?= e($a['specialty_name']) ?></span></td><td><?= status_badge($a['status']) ?></td></tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($canClinical): ?>
        <section class="card"><div class="card-head"><div><h2><span class="icon-tile icon-tile--blue"><?= icon('journal-medical') ?></span> Clinical history</h2><p>Visible only to treating doctors</p></div>
            <?php if (can('followups.create')): ?><a class="btn btn-secondary btn-sm" href="<?= e(url('/followups/create', ['patient_id' => $patient['id']])) ?>">Add follow-up</a><?php endif; ?></div>
            <?php if ($visits === []): $image = 'empty-list.svg'; $heading = 'No consultations recorded'; $text = 'Finished consultations will appear here.'; $compact = true; require __DIR__ . '/../partials/empty.php'; else: ?>
            <div class="table-wrap"><table class="table"><thead><tr><th>Date</th><th>Doctor</th><th>Diagnosis</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($visits as $v): ?><tr><td class="nowrap"><a href="<?= e(url('/visits/' . $v['id'])) ?>"><strong><?= e(format_date($v['visited_at'])) ?></strong></a></td><td><?= e($v['doctor_name']) ?></td><td><?= e($v['diagnosis'] ? mb_strimwidth($v['diagnosis'], 0, 60, '...') : '-') ?></td><td><?= status_badge($v['status']) ?></td></tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if (can('invoices.view')): ?>
        <section class="card"><div class="card-head"><h2><span class="icon-tile icon-tile--orange"><?= icon('receipt') ?></span> Invoices</h2></div>
            <?php if ($invoices === []): $image = 'empty-money.svg'; $heading = 'No invoices'; $text = 'Invoices raised for this patient will be listed here.'; $compact = true; require __DIR__ . '/../partials/empty.php'; else: ?>
            <div class="table-wrap"><table class="table"><thead><tr><th>Invoice</th><th>Date</th><th class="num">Total</th><th class="num">Balance</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($invoices as $i): ?><tr><td><a href="<?= e(url('/invoices/' . $i['id'])) ?>"><strong><?= e($i['invoice_no']) ?></strong></a></td><td class="small"><?= e(format_date($i['issue_date'])) ?></td><td class="num"><?= e(money($i['total'])) ?></td><td class="num"><?= e(money($i['balance'])) ?></td><td><?= status_badge($i['status']) ?></td></tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
        </section>
        <?php endif; ?>
    </div>

    <div class="stack">
        <section class="card"><div class="card-head"><h2>Details</h2></div><div class="card-body"><dl class="dl">
            <?php if ($full): ?>
            <dt>Age</dt><dd><?= e(age_from($patient['date_of_birth'])) ?> (<?= e(format_date($patient['date_of_birth'])) ?>)</dd>
            <dt>Gender</dt><dd><?= e($patient['gender']) ?></dd>
            <dt>Blood group</dt><dd><?= e($patient['blood_group'] ?: 'Not known') ?></dd>
            <?php endif; ?>
            <dt>Phone</dt><dd><?= e($patient['phone']) ?></dd>
            <?php if ($full): ?>
            <dt>Email</dt><dd><?= e($patient['email'] ?: '-') ?></dd>
            <dt>Address</dt><dd><?= e(trim(($patient['address'] ?? '') . ', ' . ($patient['city'] ?? ''), ', ') ?: '-') ?></dd>
            <dt>Emergency</dt><dd><?= e($patient['emergency_contact_name'] ?: '-') ?><?= $patient['emergency_contact_phone'] ? ' &middot; ' . e($patient['emergency_contact_phone']) : '' ?></dd>
            <dt>Registered</dt><dd><?= e(format_date($patient['registered_on'])) ?></dd>
            <?php endif; ?>
        </dl></div></section>
        <?php if ($followUps !== []): ?>
        <section class="card"><div class="card-head"><h2><span class="icon-tile icon-tile--orange"><?= icon('arrow-repeat') ?></span> Follow-ups</h2></div><div class="card-body">
            <?php foreach (array_slice($followUps, 0, 5) as $f): ?><div class="row row--between small" style="padding:6px 0"><span><strong><?= e(format_date($f['follow_up_date'])) ?></strong><br><span class="muted"><?= e($f['notes'] ?: 'No notes') ?></span></span><?= status_badge($f['bucket'] === 'Overdue' ? 'Overdue' : ($f['status'] === 'Pending' ? 'Pending' : $f['status'])) ?></div><?php endforeach; ?>
        </div></section>
        <?php endif; ?>
    </div>
</div>
