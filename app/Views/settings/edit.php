<div class="page-header"><div><h1>Clinic settings</h1><p>Used on invoices and prescriptions, and for booking hours and currency.</p></div></div>
<form method="post" action="<?= e(url('/settings')) ?>" novalidate><?= csrf_field() ?>
<div class="card" style="max-width:900px"><div class="card-body">
<div class="section-title"><?= icon('hospital') ?> Clinic details</div>
<div class="form-grid">
<?= field('clinic_name', 'Clinic name', ['required' => true, 'value' => $settings['clinic_name'] ?? '', 'maxlength' => 150, 'span2' => true]) ?>
<?= field('clinic_address', 'Address', ['value' => $settings['clinic_address'] ?? '', 'maxlength' => 255, 'span2' => true]) ?>
<?= field('clinic_phone', 'Phone', ['type' => 'tel', 'value' => $settings['clinic_phone'] ?? '']) ?>
<?= field('clinic_email', 'Email', ['type' => 'email', 'value' => $settings['clinic_email'] ?? '']) ?>
</div>
<div class="section-title"><?= icon('clock') ?> Opening hours</div>
<div class="form-grid">
<?= field('opening_time', 'Opens', ['type' => 'time', 'required' => true, 'value' => $settings['opening_time'] ?? '09:00']) ?>
<?= field('closing_time', 'Closes', ['type' => 'time', 'required' => true, 'value' => $settings['closing_time'] ?? '21:00', 'help' => 'Appointments can only be booked inside these hours.']) ?>
</div>
<div class="section-title"><?= icon('cash-coin') ?> Billing</div>
<div class="form-grid form-grid--3">
<?= field('currency_symbol', 'Currency symbol', ['required' => true, 'value' => $settings['currency_symbol'] ?? 'Rs.', 'maxlength' => 6]) ?>
<?= field('currency_code', 'Currency code', ['required' => true, 'value' => $settings['currency_code'] ?? 'PKR', 'maxlength' => 3, 'help' => 'Three letters, e.g. PKR']) ?>
<?= field('tax_rate_percent', 'Tax rate (%)', ['required' => true, 'value' => $settings['tax_rate_percent'] ?? '0.00', 'inputmode' => 'decimal', 'help' => '0 = no tax. Applied to new invoices only.']) ?>
<?= field('invoice_footer', 'Invoice footer text', ['value' => $settings['invoice_footer'] ?? '', 'maxlength' => 300, 'span2' => true]) ?>
</div>
<?= form_actions(url('/dashboard'), 'Save settings') ?>
</div></div></form>
