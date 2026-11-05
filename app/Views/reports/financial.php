<div class="page-header"><div><h1>Financial reports</h1><p>Money received, invoiced and still owed. Figures come directly from invoices and the payment ledger.</p></div>
<?php if (can('exports.financial')): ?><div class="page-actions"><a class="btn btn-secondary" href="<?= e(url('/payments/export', ['from' => $range->from, 'to' => $range->to])) ?>"><?= icon('download') ?> Export payments</a><a class="btn btn-secondary" href="<?= e(url('/invoices/export', ['from' => $range->from, 'to' => $range->to])) ?>"><?= icon('download') ?> Export invoices</a></div><?php endif; ?></div>
<?php require __DIR__ . '/_filter.php'; ?>
<div class="kpi-grid kpi-grid--4">
<?php
$label = 'Collected (net)'; $value = money_minor($r['collected'], true); $glyph = 'currency-exchange'; $tone = 'green'; $valueTone = true; $note = 'Payments minus refunds'; require __DIR__ . '/../dashboard/_kpi.php';
$label = 'Invoiced'; $value = money_minor($r['invoiced'], true); $glyph = 'receipt'; $tone = 'blue'; $valueTone = false; $note = $r['invoice_count'] . ' invoice(s), excluding void'; require __DIR__ . '/../dashboard/_kpi.php';
$label = 'Outstanding now'; $value = money_minor($r['outstanding'], true); $glyph = 'wallet2'; $tone = 'orange'; $note = 'All unpaid and partial invoices'; require __DIR__ . '/../dashboard/_kpi.php';
$label = 'Refunds / write-offs'; $value = money_minor($r['refunds'] + $r['adjustments']); $glyph = 'arrow-counterclockwise'; $tone = 'red'; $note = 'Refunds ' . money_minor($r['refunds']) . ' &middot; written off ' . money_minor($r['adjustments']); $note = strip_tags(html_entity_decode($note)); require __DIR__ . '/../dashboard/_kpi.php';
?></div>
<div class="grid grid-main" style="margin-top:var(--space-4)">
<section class="card"><div class="card-head"><h2><span class="icon-tile"><?= icon('graph-up') ?></span> Collections over time</h2></div><div class="card-body"><div class="chart-box">
<canvas role="img" aria-label="Bar chart of money collected" data-chart="<?= json_attr(['type' => 'bar', 'labels' => $r['trend']['labels'], 'money' => setting('currency_symbol', 'Rs.'), 'datasets' => [['label' => 'Collected', 'data' => $r['trend']['collected'], 'color' => 'green']]]) ?>"></canvas>
<div class="chart-empty"><div><?= icon('cash-coin', 'icon-lg') ?><br>No payments in this period.</div></div></div></div></section>
<section class="card"><div class="card-head"><h2><span class="icon-tile icon-tile--blue"><?= icon('credit-card') ?></span> By method</h2></div><div class="card-body">
<?php if ($r['by_method'] === []): ?><p class="muted small">No payments in this period.</p><?php else: ?><table class="table"><tbody><?php foreach ($r['by_method'] as $m): ?><tr><td><?= e($m['method'] ?: 'Other') ?></td><td class="num"><strong><?= e(money_minor($m['amount'])) ?></strong></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div></section>
</div>
<div class="grid grid-2" style="margin-top:var(--space-4)">
<section class="card"><div class="card-head"><h2>Invoices by status</h2></div><div class="table-wrap"><table class="table"><thead><tr><th>Status</th><th class="num">Count</th><th class="num">Total</th><th class="num">Balance</th></tr></thead><tbody>
<?php foreach ($r['by_status'] as $s): ?><tr><td><?= status_badge($s['status']) ?></td><td class="num"><?= e($s['n']) ?></td><td class="num"><?= e(money($s['total'])) ?></td><td class="num"><?= e(money($s['balance'])) ?></td></tr><?php endforeach; ?>
<?php if ($r['by_status'] === []): ?><tr><td colspan="4" class="muted">No invoices in this period.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="card"><div class="card-head"><h2>Invoiced by doctor</h2></div><div class="table-wrap"><table class="table"><thead><tr><th>Doctor</th><th class="num">Invoices</th><th class="num">Total</th></tr></thead><tbody>
<?php foreach ($r['by_doctor'] as $d): ?><tr><td><?= e($d['doctor']) ?></td><td class="num"><?= e($d['invoices']) ?></td><td class="num"><strong><?= e(money($d['total'])) ?></strong></td></tr><?php endforeach; ?>
<?php if ($r['by_doctor'] === []): ?><tr><td colspan="3" class="muted">No invoices in this period.</td></tr><?php endif; ?></tbody></table></div></section>
</div>
<?php if ($r['top_services'] !== []): ?><section class="card" style="margin-top:var(--space-4)"><div class="card-head"><h2>Top services</h2></div><div class="table-wrap"><table class="table"><thead><tr><th>Service</th><th class="num">Quantity</th><th class="num">Revenue</th></tr></thead><tbody>
<?php foreach ($r['top_services'] as $s): ?><tr><td><?= e($s['description']) ?></td><td class="num"><?= e($s['qty']) ?></td><td class="num"><strong><?= e(money($s['total'])) ?></strong></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>
