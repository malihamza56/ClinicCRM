<div class="page-header"><div><div class="breadcrumb"><a href="<?= e(url('/appointments/' . $a['id'])) ?>">Appointment</a> / Reschedule</div><h1>Reschedule appointment</h1>
<p><?= e($a['patient_name']) ?> with <?= e($a['doctor_name']) ?> &middot; currently <?= e(format_date($a['appointment_date'])) ?> at <?= e(format_time($a['start_time'])) ?></p></div></div>
<form method="post" action="<?= e(url('/appointments/' . $a['id'] . '/reschedule')) ?>" novalidate><?= csrf_field() ?>
<input type="hidden" id="f_doctor_id" value="<?= e($a['doctor_id']) ?>">
<div class="card" style="max-width:760px"><div class="card-body stack">
    <?= field('appointment_date', 'New date', ['type' => 'date', 'required' => true, 'value' => $a['appointment_date'] < date('Y-m-d') ? date('Y-m-d') : $a['appointment_date'], 'min' => date('Y-m-d')]) ?>
    <div class="field" data-slot-picker data-url="<?= e(url('/appointments/slots')) ?>" data-doctor="#f_doctor_id" data-date="#f_appointment_date" data-exclude="<?= e($a['id']) ?>" data-selected="">
        <span class="label">Available times<span class="req" aria-hidden="true">*</span></span><div class="slot-box"></div>
        <?php if (field_error('start_time')): ?><div class="error-text" role="alert"><?= icon('exclamation-circle') ?> <?= e(field_error('start_time')) ?></div><?php endif; ?>
    </div>
    <?= form_actions(url('/appointments/' . $a['id']), 'Reschedule') ?>
</div></div></form>
