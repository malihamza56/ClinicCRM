<?php $editing = $service !== null; ?>
<div class="page-header"><div><div class="breadcrumb"><a href="<?= e(url('/services')) ?>">Services</a> / <?= $editing ? 'Edit' : 'New' ?></div><h1><?= $editing ? 'Edit service' : 'New service' ?></h1></div></div>
<div class="card" style="max-width:640px"><form method="post" action="<?= e(url($editing ? '/services/' . $service['id'] : '/services')) ?>" class="card-body" novalidate>
<?= csrf_field() ?><div class="stack">
<?= field('name', 'Service name', ['required' => true, 'value' => $service['name'] ?? '', 'maxlength' => 120]) ?>
<?= field('description', 'Description', ['value' => $service['description'] ?? '', 'maxlength' => 255, 'help' => 'Optional']) ?>
<?= field('price', 'Price (' . setting('currency_symbol', 'Rs.') . ')', ['required' => true, 'value' => $service['price'] ?? '', 'inputmode' => 'decimal', 'help' => 'For example 1500 or 1500.50. Changing the price does not change invoices already issued.']) ?>
</div><?= form_actions(url('/services'), $editing ? 'Save changes' : 'Add service') ?></form></div>
