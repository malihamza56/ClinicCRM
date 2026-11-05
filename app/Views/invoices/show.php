<?php $void = $i['status'] === 'Void'; $open = !$void && \App\Core\Money::fromDb($i['balance']) > 0; $paid = \App\Core\Money::fromDb($i['amount_paid']) > 0; ?>
<div class="page-header">
    <div><div class="breadcrumb"><a href="<?= e(url('/invoices')) ?>">Invoices</a> / Details</div><h1><?= e($i['invoice_no']) ?> <?= status_badge($i['status']) ?></h1>
    <p>Issued <?= e(format_date($i['issue_date'])) ?> by <?= e($i['created_by_name']) ?></p></div>
    <div class="page-actions"><a class="btn btn-secondary" href="<?= e(url('/invoices/' . $i['id'] . '/print')) ?>" target="_blank" rel="noopener"><?= icon('printer') ?> Print</a></div>
</div>
<div class="grid grid-main">
<div class="stack">
    <section class="card"><div class="card-head"><h2><span class="icon-tile"><?= icon('receipt') ?></span> Items</h2></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Description</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Amount</th></tr></thead><tbody>
    <?php foreach ($items as $it): ?><tr><td><?= e($it['description']) ?></td><td class="num"><?= e($it['quantity']) ?></td><td class="num"><?= e(money($it['unit_price'])) ?></td><td class="num"><?= e(money($it['line_total'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
    <div class="card-body"><div class="totals"><div><span>Subtotal</span><span><?= e(money($i['subtotal'])) ?></span></div>
    <?php if ((float) $i['discount_amount'] > 0): ?><div><span>Discount</span><span>- <?= e(money($i['discount_amount'])) ?></span></div><?php endif; ?>
    <?php if ((float) $i['tax_amount'] > 0): ?><div><span>Tax (<?= e($i['tax_rate']) ?>%)</span><span><?= e(money($i['tax_amount'])) ?></span></div><?php endif; ?>
    <div class="grand"><span>Total</span><span><?= e(money($i['total'])) ?></span></div>
    <div><span>Paid</span><span><?= e(money($i['amount_paid'])) ?></span></div>
    <?php if ((float) $i['adjusted_amount'] > 0): ?><div><span>Written off</span><span><?= e(money($i['adjusted_amount'])) ?></span></div><?php endif; ?>
    <div><strong>Balance due</strong><strong><?= e(money($i['balance'])) ?></strong></div></div></div></section>

    <section class="card"><div class="card-head"><h2><span class="icon-tile icon-tile--blue"><?= icon('cash-coin') ?></span> Payment history</h2></div>
    <?php if ($payments === []): $image = 'empty-money.svg'; $heading = 'No payments yet'; $text = 'Recorded payments, refunds and adjustments appear here.'; $compact = true; require __DIR__ . '/../partials/empty.php'; else: ?>
    <div class="table-wrap"><table class="table"><thead><tr><th>Receipt</th><th>Date</th><th>Type</th><th>Method</th><th class="num">Amount</th><th>By</th></tr></thead><tbody>
    <?php foreach ($payments as $p): ?><tr><td><a href="<?= e(url('/payments/' . $p['id'] . '/receipt')) ?>" target="_blank" rel="noopener"><?= e($p['receipt_no']) ?></a></td><td class="small nowrap"><?= e(format_datetime($p['paid_at'])) ?></td><td><?= status_badge($p['entry_type']) ?><?= $p['reason'] ? '<br><span class="small muted">' . e($p['reason']) . '</span>' : '' ?></td><td><?= e($p['method'] ?: '-') ?><?= $p['reference'] ? '<br><span class="small muted">' . e($p['reference']) . '</span>' : '' ?></td><td class="num"><?= e(($p['entry_type'] === 'Refund' ? '- ' : '') . money($p['amount'])) ?></td><td class="small"><?= e($p['recorded_by_name']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
</div>
<div class="stack">
    <section class="card"><div class="card-head"><h2>Patient</h2></div><div class="card-body"><div class="cell-person"><span class="avatar"><?= e(initials($i['patient_name'])) ?></span><div><strong><a href="<?= e(url('/patients/' . $i['patient_id'])) ?>"><?= e($i['patient_name']) ?></a></strong><span><?= e($i['patient_code']) ?> &middot; <?= e($i['patient_phone']) ?></span></div></div>
    <?php if ($i['notes']): ?><p class="small" style="margin-top:12px"><strong>Notes:</strong> <?= e($i['notes']) ?></p><?php endif; ?>
    <?php if ($void): ?><div class="alert alert--error" style="margin:12px 0 0"><?= icon('x-octagon-fill') ?><div class="alert-body"><strong>Void</strong> - <?= e($i['void_reason']) ?><br><span class="small"><?= e(format_datetime($i['voided_at'])) ?></span></div></div><?php endif; ?></div></section>

    <?php if ($open && can('payments.record')): ?>
    <section class="card"><div class="card-head"><h2>Record payment</h2></div><form method="post" action="<?= e(url('/invoices/' . $i['id'] . '/payments')) ?>" class="card-body stack" novalidate><?= csrf_field() ?>
        <div class="field"><label for="pay_amount">Amount (<?= e(setting('currency_symbol', 'Rs.')) ?>)</label><input class="input" id="pay_amount" name="amount" inputmode="decimal" required value="<?= e($i['balance']) ?>"><div class="help">Balance due: <?= e(money($i['balance'])) ?>. Overpayments are not accepted.</div></div>
        <div class="field"><label for="pay_method">Method</label><select class="input" id="pay_method" name="method"><?php foreach ($methods as $m): ?><option><?= e($m) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="pay_ref">Reference</label><input class="input" id="pay_ref" name="reference" maxlength="100" placeholder="Optional, e.g. card slip no."></div>
        <button class="btn btn-primary btn-block" type="submit"><?= icon('check2') ?> Record payment</button></form></section>
    <?php endif; ?>

    <?php if ($open && can('payments.adjust')): ?>
    <section class="card"><div class="card-head"><h2>Write off / adjust</h2></div><form method="post" action="<?= e(url('/invoices/' . $i['id'] . '/adjust')) ?>" class="card-body stack" novalidate><?= csrf_field() ?>
        <div class="field"><label for="adj_amount">Amount</label><input class="input" id="adj_amount" name="amount" inputmode="decimal" required></div>
        <div class="field"><label for="adj_reason">Reason (required)</label><input class="input" id="adj_reason" name="reason" maxlength="255" required></div>
        <button class="btn btn-secondary btn-block" type="submit" data-confirm="Record this adjustment? It reduces the balance without any money being received.">Record adjustment</button></form></section>
    <?php endif; ?>

    <?php if (!$void && $paid && can('payments.refund')): ?>
    <section class="card"><div class="card-head"><h2>Refund</h2></div><form method="post" action="<?= e(url('/invoices/' . $i['id'] . '/refund')) ?>" class="card-body stack" novalidate><?= csrf_field() ?>
        <div class="field"><label for="ref_amount">Amount (up to <?= e(money($i['amount_paid'])) ?>)</label><input class="input" id="ref_amount" name="amount" inputmode="decimal" required></div>
        <div class="field"><label for="ref_method">Refunded by</label><select class="input" id="ref_method" name="method"><?php foreach ($methods as $m): ?><option><?= e($m) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="ref_reason">Reason (required)</label><input class="input" id="ref_reason" name="reason" maxlength="255" required></div>
        <button class="btn btn-danger-outline btn-block" type="submit" data-confirm="Record this refund? The balance on the invoice increases again.">Record refund</button></form></section>
    <?php endif; ?>

    <?php if (!$void && can('invoices.void')): ?>
    <section class="card"><div class="card-head"><h2>Void invoice</h2></div><form method="post" action="<?= e(url('/invoices/' . $i['id'] . '/void')) ?>" class="card-body stack" novalidate><?= csrf_field() ?>
        <?php if ($paid): ?><p class="small muted">Money has been received. Refund it first, then void.</p><?php endif; ?>
        <div class="field"><label for="void_reason">Reason (required)</label><input class="input" id="void_reason" name="reason" maxlength="255" required></div>
        <button class="btn btn-danger-outline btn-block" type="submit" data-confirm="Void this invoice? This cannot be undone.">Void invoice</button></form></section>
    <?php endif; ?>
</div></div>
