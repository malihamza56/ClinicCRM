<?php $editing = $specialty !== null; ?>
<div class="page-header"><div><div class="breadcrumb"><a href="<?= e(url('/specialties')) ?>">Specialties</a> / <?= $editing ? 'Edit' : 'New' ?></div><h1><?= $editing ? 'Edit specialty' : 'New specialty' ?></h1></div></div>
<div class="card" style="max-width:640px"><form method="post" action="<?= e(url($editing ? '/specialties/' . $specialty['id'] : '/specialties')) ?>" class="card-body" novalidate>
<?= csrf_field() ?><div class="stack">
<?= field('name', 'Specialty name', ['required' => true, 'value' => $specialty['name'] ?? '', 'maxlength' => 100]) ?>
<?= field('description', 'Description', ['value' => $specialty['description'] ?? '', 'maxlength' => 255, 'help' => 'Optional']) ?>
</div><?= form_actions(url('/specialties'), $editing ? 'Save changes' : 'Add specialty') ?></form></div>
