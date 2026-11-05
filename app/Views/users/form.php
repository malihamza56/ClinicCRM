<?php $editing = $user !== null; $action = $editing ? '/users/' . $user['id'] : '/users';
$roleOptions = []; foreach ($roles as $r) { $roleOptions[$r['id']] = $r['name']; } ?>
<div class="page-header"><div><div class="breadcrumb"><a href="<?= e(url('/users')) ?>">Staff accounts</a> / <?= $editing ? 'Edit' : 'New' ?></div><h1><?= $editing ? 'Edit staff account' : 'New staff account' ?></h1>
<p><?= $editing ? '' : 'A temporary password is generated and shown once. The person must change it at first sign-in.' ?></p></div></div>
<div class="card" style="max-width:760px"><form method="post" action="<?= e(url($action)) ?>" class="card-body" novalidate>
    <?= csrf_field() ?>
    <div class="form-grid">
        <?= field('full_name', 'Full name', ['required' => true, 'value' => $user['full_name'] ?? '', 'maxlength' => 120, 'autocomplete' => 'off']) ?>
        <?= field('email', 'Email (used to sign in)', ['type' => 'email', 'required' => true, 'value' => $user['email'] ?? '', 'maxlength' => 190]) ?>
        <?= field('phone', 'Phone', ['type' => 'tel', 'value' => $user['phone'] ?? '', 'help' => 'Optional']) ?>
        <?= field('role_id', 'Role', ['type' => 'select', 'required' => true, 'options' => $roleOptions, 'blank' => 'Choose a role', 'value' => $user['role_id'] ?? '', 'help' => 'Doctor accounts are created from the Doctors page so a profile is attached.']) ?>
    </div>
    <?= form_actions(url('/users'), $editing ? 'Save changes' : 'Create account') ?>
</form></div>
