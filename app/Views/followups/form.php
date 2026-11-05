<div class="page-header"><div><div class="breadcrumb"><a href="<?= e(url('/followups')) ?>">Follow-ups</a> / New</div><h1>New follow-up</h1><p>Only patients you have treated or are booked with can be chosen.</p></div></div>
<form method="post" action="<?= e(url('/followups')) ?>" novalidate><?= csrf_field() ?>
<div class="card" style="max-width:700px"><div class="card-body stack">
<?php $registerNext = 'book'; require __DIR__ . '/../partials/patient_picker.php'; ?>
<?= field('follow_up_date', 'Follow-up date', ['type' => 'date', 'required' => true, 'min' => date('Y-m-d')]) ?>
<?= field('notes', 'Notes', ['type' => 'textarea', 'rows' => 2, 'maxlength' => 500]) ?>
<?= form_actions(url('/followups'), 'Save follow-up') ?>
</div></div></form>
