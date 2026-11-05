<?php /** Accountant dashboard: money in, money owed, how it arrived. */ $k = $kpis; ?>
<div class="page-header">
    <div><h1>Dashboard</h1><p>Invoices, payments and outstanding balances.</p></div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="<?= e(url('/reports/financial')) ?>"><?= icon('graph-up-arrow') ?> Financial reports</a>
        <a class="btn btn-primary" href="<?= e(url('/invoices/create')) ?>"><?= icon('plus-lg') ?> New invoice</a>
    </div>
</div>

<section class="card overview">
    <div class="overview-head">
        <div><h2>Financial overview</h2><p>Showing <strong><?= e($range->label()) ?></strong></p></div>
        <?php require __DIR__ . '/_range.php'; ?>
    </div>
    <div class="kpi-grid kpi-grid--4">
        <?php
        $label = 'Revenue collected'; $value = money_minor($k['revenue'], true); $glyph = 'currency-exchange'; $tone = 'green'; $valueTone = true; $note = 'Payments minus refunds'; require __DIR__ . '/_kpi.php';
        $label = 'Outstanding balance'; $value = money_minor($k['outstanding'], true); $glyph = 'wallet2'; $tone = 'orange'; $valueTone = false; $note = 'Unpaid and partial invoices'; require __DIR__ . '/_kpi.php';
        $label = 'Invoices issued'; $value = number_format($k['invoices']); $glyph = 'receipt'; $tone = 'blue'; $note = 'Excluding void'; require __DIR__ . '/_kpi.php';
        $label = 'Refunds'; $value = money_minor($k['refunds'], true); $glyph = 'arrow-counterclockwise'; $tone = 'red'; $note = 'In this period'; require __DIR__ . '/_kpi.php';
        ?>
    </div>
</section>

<div class="grid grid-main" style="margin-top:var(--space-4)">
    <section class="card">
        <div class="card-head"><div><h2><span class="icon-tile"><?= icon('graph-up') ?></span> Collections</h2><p>Net money received per <?= $range->days() > 31 ? 'week' : 'day' ?></p></div></div>
        <div class="card-body">
            <div class="chart-box">
                <canvas role="img" aria-label="Bar chart of money collected" data-chart="<?= json_attr(['type' => 'bar', 'labels' => $revenue_trend['labels'], 'money' => setting('currency_symbol', 'Rs.'), 'datasets' => [['label' => 'Collected', 'data' => $revenue_trend['collected'], 'color' => 'green']]]) ?>"></canvas>
                <div class="chart-empty"><div><?= icon('cash-coin', 'icon-lg') ?><br>No payments in this period.</div></div>
            </div>
        </div>
    </section>
    <section class="card">
        <div class="card-head"><div><h2><span class="icon-tile icon-tile--blue"><?= icon('credit-card') ?></span> By payment method</h2></div></div>
        <div class="card-body">
            <?php if ($by_method === []): ?>
                <?php $image = 'empty-money.svg'; $heading = 'No payments yet'; $text = 'Payments recorded in this period will be grouped by method here.'; $compact = true; require __DIR__ . '/../partials/empty.php'; ?>
            <?php else: ?>
                <table class="table"><tbody>
                <?php foreach ($by_method as $m): ?><tr><td><?= e($m['method'] ?: 'Other') ?></td><td class="num"><strong><?= e(money_minor($m['amount'])) ?></strong></td></tr><?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="card" style="margin-top:var(--space-4)">
    <div class="card-head"><div><h2><span class="icon-tile icon-tile--orange"><?= icon('hourglass-split') ?></span> Largest open balances</h2><p>Oldest unpaid invoices first in the full list</p></div><a class="btn btn-secondary btn-sm" href="<?= e(url('/invoices', ['outstanding' => 1])) ?>">View all</a></div>
    <?php if ($outstanding_invoices === []): ?>
        <?php $image = 'empty-money.svg'; $heading = 'Nothing outstanding'; $text = 'Every live invoice is fully paid.'; $compact = true; require __DIR__ . '/../partials/empty.php'; ?>
    <?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Invoice</th><th>Patient</th><th>Date</th><th class="num">Total</th><th class="num">Balance</th><th>Status</th></tr></thead>
            <tbody><?php foreach ($outstanding_invoices as $i): ?>
                <tr><td><a href="<?= e(url('/invoices/' . $i['id'])) ?>"><strong><?= e($i['invoice_no']) ?></strong></a></td><td><?= e($i['patient_name']) ?></td><td><?= e(format_date($i['issue_date'])) ?></td><td class="num"><?= e(money($i['total'])) ?></td><td class="num"><strong><?= e(money($i['balance'])) ?></strong></td><td><?= status_badge($i['status']) ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>
