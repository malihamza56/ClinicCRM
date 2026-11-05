<?php $label = ['Payment' => 'PAYMENT RECEIPT', 'Refund' => 'REFUND VOUCHER', 'Adjustment' => 'ADJUSTMENT NOTE'][$p['entry_type']]; ?>
<div class="print-bar"><a class="btn btn-secondary btn-sm" href="<?= e(url('/invoices/' . $i['id'])) ?>"><?= icon('arrow-left') ?> Back</a><button type="button" class="btn btn-primary btn-sm" data-print><?= icon('printer') ?> Print</button></div>
<article class="print-page">
<header class="doc-head"><div class="clinic"><h1><?= e(setting('clinic_name', 'Clinic')) ?></h1><p><?= e(setting('clinic_address')) ?></p><p><?= e(trim(setting('clinic_phone') . ' ' . setting('clinic_email'))) ?></p></div>
<div class="doc-title"><h2><?= e($label) ?></h2><div><?= e($p['receipt_no']) ?></div><div><?= e(format_datetime($p['paid_at'])) ?></div></div></header>
<div class="doc-meta"><div><span>Patient</span><strong><?= e($p['patient_name']) ?></strong></div><div><span>Patient ID</span><?= e($p['patient_code']) ?></div><div><span>Invoice</span><?= e($i['invoice_no']) ?></div><div><span>Method</span><?= e($p['method'] ?: '-') ?></div>
<?php if ($p['reference']): ?><div><span>Reference</span><?= e($p['reference']) ?></div><?php endif; ?><?php if ($p['reason']): ?><div><span>Reason</span><?= e($p['reason']) ?></div><?php endif; ?></div>
<div class="totals" style="margin:24px 0"><div class="grand"><span><?= e($p['entry_type']) ?> amount</span><span><?= e(money($p['amount'])) ?></span></div>
<div><span>Invoice total</span><span><?= e(money($i['total'])) ?></span></div><div><span>Balance after (current)</span><span><?= e(money($i['balance'])) ?></span></div></div>
<div class="signature"><div>Received by: <?= e($p['recorded_by_name']) ?></div></div>
<footer class="doc-foot"><span><?= e(setting('invoice_footer')) ?></span><span>Reprinted <?= e(date('d M Y')) ?></span></footer></article>
