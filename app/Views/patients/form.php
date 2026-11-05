<?php $editing = $patient !== null; $p = $patient ?? [];
$action = $editing ? '/patients/' . $patient['id'] : '/patients' . (!empty($next) ? '?next=' . $next : ''); ?>
<div class="page-header"><div><div class="breadcrumb"><a href="<?= e(url('/patients')) ?>">Patients</a> / <?= $editing ? 'Edit' : 'Register' ?></div><h1><?= $editing ? 'Edit ' . e($patient['full_name']) : 'Register patient' ?></h1>
<p>Only collect what the clinic needs. Fields marked <span class="req">*</span> are required.</p></div></div>
<?php if (!empty($duplicateId)): ?>
    <div class="alert alert--warning" role="status"><?= icon('person-exclamation') ?><div class="alert-body">This looks like an existing patient. <a href="<?= e(url('/patients/' . $duplicateId)) ?>"><strong>Open the existing record</strong></a> instead of creating a duplicate.</div></div>
<?php endif; ?>
<form method="post" action="<?= e(url($action)) ?>" novalidate><?= csrf_field() ?>
<div class="card" style="max-width:960px"><div class="card-body">
    <div class="section-title"><?= icon('person-vcard') ?> Personal details</div>
    <div class="form-grid">
        <?= field('first_name', 'First name', ['required' => true, 'value' => $p['first_name'] ?? '', 'maxlength' => 80, 'autocomplete' => 'off']) ?>
        <?= field('last_name', 'Last name', ['required' => true, 'value' => $p['last_name'] ?? '', 'maxlength' => 80, 'autocomplete' => 'off']) ?>
        <?= field('gender', 'Gender', ['type' => 'select', 'required' => true, 'options' => array_combine(\App\Services\PatientService::GENDERS, \App\Services\PatientService::GENDERS), 'blank' => 'Choose', 'value' => $p['gender'] ?? '']) ?>
        <?= field('date_of_birth', 'Date of birth', ['type' => 'date', 'required' => true, 'value' => $p['date_of_birth'] ?? '', 'max' => date('Y-m-d')]) ?>
        <?= field('blood_group', 'Blood group', ['type' => 'select', 'options' => array_combine(\App\Services\PatientService::BLOOD_GROUPS, \App\Services\PatientService::BLOOD_GROUPS), 'blank' => 'Not known', 'value' => $p['blood_group'] ?? '']) ?>
    </div>
    <div class="section-title"><?= icon('telephone') ?> Contact</div>
    <div class="form-grid">
        <?= field('phone', 'Phone', ['type' => 'tel', 'required' => true, 'value' => $p['phone'] ?? '', 'placeholder' => '03001234567', 'autocomplete' => 'off']) ?>
        <?= field('email', 'Email', ['type' => 'email', 'value' => $p['email'] ?? '', 'maxlength' => 190, 'help' => 'Optional']) ?>
        <?= field('address', 'Address', ['value' => $p['address'] ?? '', 'maxlength' => 255]) ?>
        <?= field('city', 'City', ['value' => $p['city'] ?? '', 'maxlength' => 80]) ?>
    </div>
    <div class="section-title"><?= icon('shield-plus') ?> Emergency contact</div>
    <div class="form-grid">
        <?= field('emergency_contact_name', 'Contact name', ['value' => $p['emergency_contact_name'] ?? '', 'maxlength' => 120]) ?>
        <?= field('emergency_contact_phone', 'Contact phone', ['type' => 'tel', 'value' => $p['emergency_contact_phone'] ?? '']) ?>
    </div>
    <?= form_actions(url($editing ? '/patients/' . $patient['id'] : '/patients'), $editing ? 'Save changes' : 'Register patient') ?>
</div></div></form>
