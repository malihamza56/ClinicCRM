<div class="page-header"><div><h1>Payments</h1><p>The money ledger: every payment, refund and adjustment. Entries are permanent.</p></div>
<?php if (can('exports.financial')): ?><div class="page-actions"><a class="btn btn-secondary" href="<?= e(url('/payments/export', array_filter($filters))) ?>"><?= icon('download') ?> Export CSV</a></div><?php endif; ?></div>
<div class="card">
<form class="filters" method="get" data-no-loading>
<div class="field field--grow"><label for="q">Search</label><div class="input-icon"><?= icon('search') ?><input class="input" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Receipt, invoice, patient or reference"></div></div>
<div class="field"><label for="entry_type">Type</label><select class="input" id="entry_type" name="entry_type"><option value="">All</option><?php foreach (['Payment', 'Refund', 'Adjustment'] as $t): ?><option<?= selected($filters['entry_type'], $t) ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="method">Method</label><select class="input" id="method" name="method"><option value="">All</option><?php foreach ($methods as $m): ?><option<?= selected($filters['method'], $m) ?>><?= e($m) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="from">From</label><input class="input" type="date" id="from" name="from" value="<?= e($filters['from']) ?>"></div>
<div class="field"><label for="to">To</label><input class="input" type="date" id="to" name="to" value="<?= e($filters['to']) ?>"></div>
<button class="btn btn-primary" type="submit"><?= icon('funnel') ?> Filter</button><a class="btn btn-secondary" href="<?= e(url('/payments')) ?>">Reset</a>
</form>
<?php if ($page->items === []): $image = 'empty-money.svg'; $heading = 'No entries found'; $text = 'Try a wider date range.'; require __DIR__ . '/../partials/empty.php'; else: ?>
<div class="table-wrap"><table class="table"><thead><tr><th>Receipt</th><th>Date</th><th>Patient</th><th>Invoice</th><th>Type</th><th>Method</th><th class="num">Amount</th><th>By</th></tr></thead><tbody>
<?php foreach ($page->items as $p): ?><tr><td><a href="<?= e(url('/payments/' . $p['id'] . '/receipt')) ?>" target="_blank" rel="noopener"><?= e($p['receipt_no']) ?></a></td><td class="small nowrap"><?= e(format_datetime($p['paid_at'])) ?></td><td><?= e($p['patient_name']) ?><br><span class="small muted"><?= e($p['patient_code']) ?></span></td><td><a href="<?= e(url('/invoices/' . $p['invoice_id'])) ?>"><?= e($p['invoice_no']) ?></a></td><td><?= status_badge($p['entry_type']) ?></td><td class="small"><?= e($p['method'] ?: '-') ?></td><td class="num"><strong><?= e(($p['entry_type'] === 'Refund' ? '- ' : '') . money($p['amount'])) ?></strong></td><td class="small"><?= e($p['recorded_by_name']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php require __DIR__ . '/../partials/pagination.php'; endif; ?></div>
