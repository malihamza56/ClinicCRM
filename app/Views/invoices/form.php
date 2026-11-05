<?php
$symbol = setting('currency_symbol', 'Rs.');
$serviceRow = static function (array $services, string $sid = '', string $qty = '1'): string {
    $h = '<div class="row" data-row style="margin-bottom:8px;flex-wrap:nowrap"><select class="input" name="service_id[]" style="flex:1" aria-label="Service"><option value="">Choose a service</option>';
    foreach ($services as $s) { $h .= '<option value="' . e($s['id']) . '" data-price="' . e($s['price']) . '"' . selected($sid, $s['id']) . '>' . e($s['name']) . ' - ' . e(money($s['price'])) . '</option>'; }
    return $h . '</select><input class="input" type="number" name="service_qty[]" value="' . e($qty) . '" min="1" max="99" style="width:90px" aria-label="Quantity"><button type="button" class="btn btn-ghost btn-sm" data-remove-row aria-label="Remove line">' . icon('trash3') . '</button></div>';
};
?>
<div class="page-header"><div><div class="breadcrumb"><a href="<?= e(url('/invoices')) ?>">Invoices</a> / New</div><h1>New invoice</h1><p>Prices come from the doctor's fee and the services list - they cannot be typed in.</p></div></div>
<?php if (!$patient): ?>
<form method="get" action="<?= e(url('/invoices/create')) ?>" data-no-loading><div class="card" style="max-width:640px"><div class="card-body stack">
    <?php $registerNext = 'book'; require __DIR__ . '/../partials/patient_picker.php'; ?>
    <button class="btn btn-primary" type="submit">Continue <?= icon('arrow-right') ?></button>
</div></div></form>
<?php return; endif; ?>
<form method="post" action="<?= e(url('/invoices')) ?>" novalidate><?= csrf_field() ?>
<input type="hidden" name="patient_id" value="<?= e($patient['id']) ?>"><input type="hidden" name="visit_id" value="<?= e($visit['id'] ?? '') ?>">
<div class="grid grid-main">
<div class="card" data-invoice-calc data-tax-rate="<?= e($taxRate) ?>" data-symbol="<?= e($symbol) ?>"><div class="card-body">
    <div class="selected-patient" style="margin-bottom:var(--space-4)"><div class="cell-person"><span class="avatar"><?= e(initials($patient['full_name'])) ?></span><div><strong><?= e($patient['full_name']) ?></strong><span><?= e($patient['patient_code']) ?> &middot; <?= e($patient['phone']) ?></span></div></div><a class="btn btn-secondary btn-sm" href="<?= e(url('/invoices/create')) ?>">Change</a></div>
    <?php if (field_error('items')): ?><div class="alert alert--error" role="alert"><?= icon('x-octagon-fill') ?><div class="alert-body"><?= e(field_error('items')) ?></div></div><?php endif; ?>

    <?php if ($visits !== []): ?>
    <div class="section-title"><?= icon('journal-medical') ?> Link to a finished consultation</div>
    <p class="small muted">Not yet invoiced. Only the date and doctor are shown here.</p>
    <div class="chip-row" style="margin-bottom:var(--space-4)"><?php foreach ($visits as $vis): ?><a class="tab<?= ($visit['id'] ?? 0) === $vis['id'] ? ' is-active' : '' ?>" href="<?= e(url('/invoices/create', ['patient_id' => $patient['id'], 'visit_id' => $vis['id']])) ?>"><?= e(format_date($vis['visited_at'])) ?> &middot; <?= e($vis['doctor_name']) ?></a><?php endforeach; ?></div>
    <?php endif; ?>

    <div class="section-title"><?= icon('person-badge') ?> Consultation fee</div>
    <label class="check" style="margin-bottom:8px"><input type="checkbox" name="include_consultation" value="1"<?= checked($visit !== null || old('include_consultation') === '1') ?>> Charge a consultation fee</label>
    <div class="field"><label for="consultation_doctor_id" class="sr-only">Doctor</label><select class="input" id="consultation_doctor_id" name="consultation_doctor_id"><option value="" data-fee="0">Choose doctor</option>
    <?php foreach ($doctors as $d): ?><option value="<?= e($d['id']) ?>" data-fee="<?= e($d['consultation_fee']) ?>"<?= selected(old('consultation_doctor_id', $visit['doctor_id'] ?? ''), $d['id']) ?>><?= e($d['full_name']) ?> - <?= e(money($d['consultation_fee'])) ?></option><?php endforeach; ?></select></div>

    <div class="section-title"><?= icon('clipboard2-pulse') ?> Services</div>
    <div data-rows="#svcTemplate" data-add="#addSvc" data-max="<?= e(\App\Services\BillingService::MAX_LINES) ?>"><?= $serviceRow($services) ?></div>
    <template id="svcTemplate"><?= $serviceRow($services) ?></template>
    <button type="button" class="btn btn-secondary btn-sm" id="addSvc"><?= icon('plus-lg') ?> Add service</button>

    <div class="form-grid" style="margin-top:var(--space-5)">
        <?= field('discount', 'Discount (' . $symbol . ')', ['value' => '', 'inputmode' => 'decimal', 'placeholder' => '0', 'help' => 'Optional. Cannot exceed the subtotal.']) ?>
        <?= field('notes', 'Notes', ['maxlength' => 500, 'help' => 'Optional, shown on the invoice']) ?>
    </div>
</div></div>
<aside class="card" style="align-self:start"><div class="card-head"><h2>Summary</h2></div><div class="card-body"><div class="totals" style="width:100%">
    <div><span>Subtotal</span><strong data-sum="subtotal">-</strong></div><div><span>Discount</span><span data-sum="discount">-</span></div>
    <div><span>Tax (<?= e($taxRate) ?>%)</span><span data-sum="tax">-</span></div><div class="grand"><span>Total</span><span data-sum="total">-</span></div></div>
    <p class="help" style="margin-top:12px">This is a live estimate. The server calculates the final amounts when you save.</p>
    <button class="btn btn-primary btn-block" type="submit" style="margin-top:var(--space-4)"><?= icon('check2') ?> Create invoice</button></div></aside>
</div></form>
