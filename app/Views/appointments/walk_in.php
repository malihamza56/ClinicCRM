<?php $docOpts = []; foreach ($doctors as $d) { $docOpts[$d['id']] = $d['full_name'] . ' - ' . $d['specialty_name']; } $registerNext = 'walk-in'; ?>
<div class="page-header"><div><div class="breadcrumb"><a href="<?= e(url('/queue')) ?>">Queue</a> / Walk-in</div><h1>Register walk-in</h1><p>For patients who arrive without a booking. They join the queue immediately.</p></div></div>
<form method="post" action="<?= e(url('/appointments/walk-in')) ?>" novalidate><?= csrf_field() ?>
<div class="card" style="max-width:760px"><div class="card-body stack">
    <?php require __DIR__ . '/../partials/patient_picker.php'; ?>
    <?= field('doctor_id', 'Doctor', ['type' => 'select', 'required' => true, 'options' => $docOpts, 'blank' => 'Choose a doctor', 'help' => 'The doctor must be on duty right now.']) ?>
    <?= field('reason', 'Reason for visit', ['maxlength' => 255, 'help' => 'Optional']) ?>
    <?= form_actions(url('/queue'), 'Add to queue') ?>
</div></div></form>
