<?php $void = $i['status'] === 'Void'; ?>
<div class="print-bar"><a class="btn btn-secondary btn-sm" href="<?= e(url('/invoices/' . $i['id'])) ?>"><?= icon('arrow-left') ?> Back</a><button type="button" class="btn btn-primary btn-sm" data-print><?= icon('printer') ?> Print</button></div>
<article class="print-page">
<header class="doc-head"><div class="clinic"><h1><?= e(setting('clinic_name', 'Clinic')) ?></h1><p><?= e(setting('clinic_address')) ?></p><p><?= e(trim(setting('clinic_phone') . ' ' . setting('clinic_email'))) ?></p></div>
<div class="doc-title"><h2>INVOICE</h2><div><?= e($i['invoice_no']) ?></div><div><?= e(format_date($i['issue_date'])) ?></div>
<?php if ($void): ?><div class="stamp stamp--void">VOID</div><?php elseif ($i['status'] === 'Paid'): ?><div class="stamp stamp--paid">PAID</div><?php endif; ?></div></header>
<div class="doc-meta"><div><span>Patient</span><strong><?= e($i['patient_name']) ?></strong></div><div><span>Patient ID</span><?= e($i['patient_code']) ?></div><div><span>Phone</span><?= e($i['patient_phone']) ?></div><div><span>Status</span><?= e($i['status']) ?></div></div>
<table class="doc-table"><thead><tr><th>Description</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Amount</th></tr></thead><tbody>
<?php foreach ($items as $it): ?><tr><td><?= e($it['description']) ?></td><td class="num"><?= e($it['quantity']) ?></td><td class="num"><?= e(money($it['unit_price'])) ?></td><td class="num"><?= e(money($it['line_total'])) ?></td></tr><?php endforeach; ?></tbody></table>
<div class="totals"><div><span>Subtotal</span><span><?= e(money($i['subtotal'])) ?></span></div>
<?php if ((float) $i['discount_amount'] > 0): ?><div><span>Discount</span><span>- <?= e(money($i['discount_amount'])) ?></span></div><?php endif; ?>
<?php if ((float) $i['tax_amount'] > 0): ?><div><span>Tax (<?= e($i['tax_rate']) ?>%)</span><span><?= e(money($i['tax_amount'])) ?></span></div><?php endif; ?>
<div class="grand"><span>Total</span><span><?= e(money($i['total'])) ?></span></div><div><span>Paid</span><span><?= e(money($i['amount_paid'])) ?></span></div><div><strong>Balance due</strong><strong><?= e(money($i['balance'])) ?></strong></div></div>
<?php if ($payments !== []): ?><p style="margin-top:20px"><strong>Payments</strong></p><table class="doc-table"><tbody><?php foreach ($payments as $p): ?><tr><td><?= e($p['receipt_no']) ?></td><td><?= e(format_date($p['paid_at'])) ?></td><td><?= e($p['entry_type']) ?><?= $p['method'] ? ' (' . e($p['method']) . ')' : '' ?></td><td class="num"><?= e(($p['entry_type'] === 'Refund' ? '- ' : '') . money($p['amount'])) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
<footer class="doc-foot"><span><?= e(setting('invoice_footer')) ?></span><span>Prepared by <?= e($i['created_by_name']) ?></span></footer></article>
