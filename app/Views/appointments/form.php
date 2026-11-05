<?php $docOpts = []; foreach ($doctors as $d) { $docOpts[$d['id']] = $d['full_name'] . ' - ' . $d['specialty_name'] . ' (' . money($d['consultation_fee']) . ')'; } ?>
<div class="page-header"><div><div class="breadcrumb"><a href="<?= e(url('/appointments')) ?>">Appointments</a> / Book</div><h1>Book appointment</h1><p>Choose a patient, a doctor and a free time slot.</p></div></div>
<form method="post" action="<?= e(url('/appointments')) ?>" novalidate><?= csrf_field() ?>
<div class="card" style="max-width:900px"><div class="card-body">
    <?php require __DIR__ . '/../partials/patient_picker.php'; ?>
    <div class="form-grid" style="margin-top:var(--space-5)">
        <?= field('doctor_id', 'Doctor', ['type' => 'select', 'required' => true, 'options' => $docOpts, 'blank' => 'Choose a doctor', 'value' => '']) ?>
        <?= field('appointment_date', 'Date', ['type' => 'date', 'required' => true, 'value' => $date, 'min' => date('Y-m-d')]) ?>
    </div>
    <div class="field" style="margin-top:var(--space-4)" data-slot-picker data-url="<?= e(url('/appointments/slots')) ?>" data-doctor="#f_doctor_id" data-date="#f_appointment_date" data-selected="<?= e(old('start_time', '')) ?>">
        <span class="label">Available times<span class="req" aria-hidden="true">*</span></span>
        <div class="slot-box"></div>
        <?php if (field_error('start_time')): ?><div class="error-text" role="alert"><?= icon('exclamation-circle') ?> <?= e(field_error('start_time')) ?></div><?php endif; ?>
    </div>
    <div style="margin-top:var(--space-4)"><?= field('reason', 'Reason for visit', ['maxlength' => 255, 'help' => 'Optional, e.g. fever, follow-up, check-up']) ?></div>
    <?= form_actions(url('/appointments'), 'Book appointment') ?>
</div></div></form>
