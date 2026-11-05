<?php $editing = $doctor !== null; $opts = []; foreach ($specialties as $s) { $opts[$s['id']] = $s['name']; } ?>
<div class="page-header"><div><div class="breadcrumb"><a href="<?= e(url('/doctors')) ?>">Doctors</a> / <?= $editing ? 'Edit' : 'New' ?></div><h1><?= $editing ? 'Edit ' . e($doctor['full_name']) : 'New doctor' ?></h1></div></div>
<form method="post" action="<?= e(url($editing ? '/doctors/' . $doctor['id'] : '/doctors')) ?>" novalidate><?= csrf_field() ?>
<div class="card" style="max-width:900px"><div class="card-body">
<?php if (!$editing): ?>
<div class="section-title"><?= icon('person-badge') ?> Login account</div>
<div class="form-grid">
<?= field('full_name', 'Full name', ['required' => true, 'maxlength' => 120, 'help' => 'Include the title, e.g. Dr. Name Surname']) ?>
<?= field('email', 'Email (used to sign in)', ['type' => 'email', 'required' => true, 'maxlength' => 190]) ?>
</div>
<?php endif; ?>
<div class="section-title"><?= icon('heart-pulse') ?> Professional profile</div>
<div class="form-grid">
<?= field('specialty_id', 'Specialty', ['type' => 'select', 'required' => true, 'options' => $opts, 'blank' => 'Choose a specialty', 'value' => $doctor['specialty_id'] ?? '']) ?>
<?= field('qualification', 'Qualification', ['value' => $doctor['qualification'] ?? '', 'maxlength' => 150]) ?>
<?= field('license_no', 'License number', ['value' => $doctor['license_no'] ?? '', 'maxlength' => 50]) ?>
<?= field('phone', 'Phone', ['type' => 'tel', 'value' => $doctor['phone'] ?? '']) ?>
<?= field('room', 'Room', ['value' => $doctor['room'] ?? '', 'maxlength' => 30]) ?>
<?= field('consultation_fee', 'Consultation fee (' . setting('currency_symbol', 'Rs.') . ')', ['required' => true, 'value' => $doctor['consultation_fee'] ?? '', 'inputmode' => 'decimal']) ?>
<?= field('slot_minutes', 'Visit length (minutes)', ['type' => 'number', 'required' => true, 'value' => $doctor['slot_minutes'] ?? 15, 'min' => 5, 'max' => 120, 'step' => 5, 'help' => 'Length of each appointment slot.']) ?>
</div>
<?= form_actions(url($editing ? '/doctors/' . $doctor['id'] : '/doctors'), $editing ? 'Save changes' : 'Create doctor') ?>
</div></div></form>
