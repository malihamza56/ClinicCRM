<div class="page-header"><div><h1>Invoices</h1><p><?= $filters['outstanding'] ? 'Showing invoices with a balance still due.' : 'All invoices issued by the clinic.' ?></p></div>
<div class="page-actions">
<?php if (can('exports.financial')): ?><a class="btn btn-secondary" href="<?= e(url('/invoices/export', ['q' => $filters['q'], 'status' => $filters['status'], 'from' => $filters['from'], 'to' => $filters['to'], 'outstanding' => $filters['outstanding'] ? 1 : null])) ?>"><?= icon('download') ?> Export CSV</a><?php endif; ?>
<?php if (can('invoices.create')): ?><a class="btn btn-primary" href="<?= e(url('/invoices/create')) ?>"><?= icon('plus-lg') ?> New invoice</a><?php endif; ?></div></div>
<div class="card">
<form class="filters" method="get" data-no-loading>
<div class="field field--grow"><label for="q">Search</label><div class="input-icon"><?= icon('search') ?><input class="input" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Invoice no., patient name or ID"></div></div>
<div class="field"><label for="status">Status</label><select class="input" id="status" name="status"><option value="">All</option><?php foreach (['Unpaid', 'Partial', 'Paid', 'Void'] as $s): ?><option<?= selected($filters['status'], $s) ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="from">From</label><input class="input" type="date" id="from" name="from" value="<?= e($filters['from']) ?>"></div>
<div class="field"><label for="to">To</label><input class="input" type="date" id="to" name="to" value="<?= e($filters['to']) ?>"></div>
<label class="check" style="padding-bottom:10px"><input type="checkbox" name="outstanding" value="1"<?= checked($filters['outstanding']) ?>> Balance due only</label>
<button class="btn btn-primary" type="submit"><?= icon('funnel') ?> Filter</button><a class="btn btn-secondary" href="<?= e(url('/invoices')) ?>">Reset</a>
</form>
<?php if ($page->items === []): $image = 'empty-money.svg'; $heading = 'No invoices found'; $text = 'Try different filters, or create a new invoice.'; $actionUrl = can('invoices.create') ? url('/invoices/create') : null; $actionLabel = 'New invoice'; require __DIR__ . '/../partials/empty.php'; else: ?>
<div class="table-wrap"><table class="table"><thead><tr><th>Invoice</th><th>Patient</th><th>Date</th><th class="num">Total</th><th class="num">Paid</th><th class="num">Balance</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($page->items as $i): ?><tr><td><a href="<?= e(url('/invoices/' . $i['id'])) ?>"><strong><?= e($i['invoice_no']) ?></strong></a></td>
<td><?= e($i['patient_name']) ?><br><span class="small muted"><?= e($i['patient_code']) ?></span></td><td class="small nowrap"><?= e(format_date($i['issue_date'])) ?></td>
<td class="num"><?= e(money($i['total'])) ?></td><td class="num"><?= e(money($i['amount_paid'])) ?></td><td class="num"><strong><?= e(money($i['balance'])) ?></strong></td><td><?= status_badge($i['status']) ?></td>
<td><div class="row-actions"><a class="btn btn-secondary btn-sm" href="<?= e(url('/invoices/' . $i['id'])) ?>">Open</a></div></td></tr><?php endforeach; ?>
</tbody></table></div><?php require __DIR__ . '/../partials/pagination.php'; endif; ?>
</div>
