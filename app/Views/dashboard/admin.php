<?php
/** Admin dashboard - mirrors the reference: Business Overview KPI cards, booking trend line chart, attendance donut. */
$k = $kpis;
$pendingToday = max(0, $attendance['pending'] - $k['ongoing']);
$totalToday = array_sum($attendance);
$donePct = $totalToday > 0 ? (int) round($attendance['completed'] / $totalToday * 100) : 0;
?>
<div class="page-header">
    <div>
        <h1>Dashboard</h1>
        <p>Monitor your clinic's metrics and performance.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="<?= e(url('/reports/operational')) ?>"><?= icon('bar-chart-line') ?> Operational reports</a>
        <a class="btn btn-secondary" href="<?= e(url('/reports/financial')) ?>"><?= icon('graph-up-arrow') ?> Financial reports</a>
    </div>
</div>

<section class="card overview" aria-labelledby="overview-title">
    <div class="overview-head">
        <div>
            <h2 id="overview-title">Business overview</h2>
            <p>Showing <strong><?= e($range->label()) ?></strong> for revenue; appointment tiles show today.</p>
        </div>
        <?php require __DIR__ . '/_range.php'; ?>
    </div>
    <div class="kpi-grid">
        <?php
        $label = 'Revenue collected'; $value = money_minor($k['revenue'], true); $glyph = 'currency-exchange'; $tone = 'green'; $valueTone = true; $note = 'Payments minus refunds'; require __DIR__ . '/_kpi.php';
        $label = 'Ongoing now'; $value = (string) $k['ongoing']; $glyph = 'hourglass-split'; $tone = 'blue'; $valueTone = false; $note = 'Waiting or in consultation'; require __DIR__ . '/_kpi.php';
        $label = 'Pending today'; $value = (string) $pendingToday; $glyph = 'clock-history'; $tone = 'orange'; $note = 'Booked, not yet arrived'; require __DIR__ . '/_kpi.php';
        $label = 'Completed today'; $value = (string) $k['completed']; $glyph = 'check2-circle'; $tone = 'green'; $note = 'Consultations finished'; require __DIR__ . '/_kpi.php';
        $label = 'Cancelled / no-show'; $value = (string) $k['cancelled']; $glyph = 'x-circle'; $tone = 'red'; $note = 'Today'; require __DIR__ . '/_kpi.php';
        ?>
    </div>
</section>

<div class="grid grid-4" style="margin:var(--space-4) 0">
    <?php foreach ([
        ['people', 'Active patients', number_format($secondary['patients']), ''],
        ['person-plus', 'New registrations', number_format($secondary['new_patients']), $range->label()],
        ['heart-pulse', 'Active doctors', number_format($secondary['doctors']), ''],
        ['wallet2', 'Outstanding balance', money_minor($secondary['outstanding'], true), 'Unpaid and partial invoices'],
    ] as [$glyph, $label, $value, $note]): ?>
        <div class="kpi"><div class="kpi-top"><span class="kpi-label"><?= e($label) ?></span><span class="icon-tile"><?= icon($glyph) ?></span></div><div class="kpi-value" style="font-size:1.35rem"><?= e($value) ?></div><?php if ($note !== ''): ?><div class="kpi-note"><?= e($note) ?></div><?php endif; ?></div>
    <?php endforeach; ?>
</div>

<div class="grid grid-main">
    <section class="card" aria-labelledby="trend-title">
        <div class="card-head"><div><h2 id="trend-title"><span class="icon-tile"><?= icon('graph-up') ?></span> Booking trends</h2><p><?= $range->days() > 31 ? 'Week-by-week' : 'Day-by-day' ?> comparison of appointment outcomes</p></div></div>
        <div class="card-body">
            <div class="chart-box">
                <canvas role="img" aria-label="Line chart of completed, pending and cancelled appointments" data-chart="<?= json_attr([
                    'type' => 'line', 'labels' => $trend['labels'],
                    'datasets' => [
                        ['label' => 'Completed', 'data' => $trend['completed'], 'color' => 'green', 'fill' => true],
                        ['label' => 'Pending', 'data' => $trend['pending'], 'color' => 'orange'],
                        ['label' => 'Cancelled / no-show', 'data' => $trend['cancelled'], 'color' => 'red'],
                    ],
                ]) ?>"></canvas>
                <div class="chart-empty"><div><?= icon('graph-up', 'icon-lg') ?><br>No appointments in this period yet.</div></div>
            </div>
            <div class="legend"><span><i style="background:var(--green-500)"></i>Completed</span><span><i style="background:var(--orange-500)"></i>Pending</span><span><i style="background:var(--red-500)"></i>Cancelled / no-show</span></div>
        </div>
    </section>

    <section class="card" aria-labelledby="att-title">
        <div class="card-head"><div><h2 id="att-title"><span class="icon-tile icon-tile--blue"><?= icon('pie-chart') ?></span> Today's appointments</h2><p>Outcome split for <?= e(date('d M Y')) ?></p></div></div>
        <div class="card-body">
            <div class="chart-box chart-box--donut">
                <canvas role="img" aria-label="Doughnut chart of today's appointments by outcome" data-chart="<?= json_attr([
                    'type' => 'doughnut', 'labels' => ['Completed', 'Pending', 'Cancelled / no-show'],
                    'datasets' => [['data' => [$attendance['completed'], $attendance['pending'], $attendance['cancelled']], 'colors' => ['green', 'orange', 'red']]],
                ]) ?>"></canvas>
                <div class="donut-center"><div><strong><?= e($donePct) ?>%</strong><span>completed</span></div></div>
                <div class="chart-empty"><div><?= icon('calendar2-x', 'icon-lg') ?><br>No appointments today.</div></div>
            </div>
            <div class="legend"><span><i style="background:var(--green-500)"></i>Completed <?= e($attendance['completed']) ?></span><span><i style="background:var(--orange-500)"></i>Pending <?= e($attendance['pending']) ?></span><span><i style="background:var(--red-500)"></i>Cancelled <?= e($attendance['cancelled']) ?></span></div>
        </div>
    </section>
</div>

<div class="grid grid-main" style="margin-top:var(--space-4)">
    <section class="card">
        <div class="card-head"><div><h2><span class="icon-tile"><?= icon('calendar2-week') ?></span> Today's schedule</h2><p>Next appointments across all doctors</p></div><a class="btn btn-secondary btn-sm" href="<?= e(url('/appointments', ['date' => date('Y-m-d')])) ?>">View all</a></div>
        <?php $rows = $schedule; require __DIR__ . '/_today_table.php'; ?>
    </section>
    <div class="stack">
        <section class="card">
            <div class="card-head"><h2><span class="icon-tile icon-tile--orange"><?= icon('receipt') ?></span> Outstanding invoices</h2><a class="btn btn-ghost btn-sm" href="<?= e(url('/invoices', ['outstanding' => 1])) ?>">All</a></div>
            <?php if ($outstanding_invoices === []): ?>
                <?php $image = 'empty-money.svg'; $heading = 'All settled'; $text = 'No unpaid balances right now.'; $compact = true; require __DIR__ . '/../partials/empty.php'; ?>
            <?php else: ?>
                <div class="table-wrap"><table class="table"><tbody>
                <?php foreach ($outstanding_invoices as $i): ?>
                    <tr><td><a href="<?= e(url('/invoices/' . $i['id'])) ?>"><strong><?= e($i['invoice_no']) ?></strong></a><br><span class="small muted"><?= e($i['patient_name']) ?></span></td><td class="num"><strong><?= e(money($i['balance'])) ?></strong><br><?= status_badge($i['status']) ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </section>
        <section class="card">
            <div class="card-head"><h2><span class="icon-tile icon-tile--blue"><?= icon('arrow-repeat') ?></span> Follow-ups</h2><a class="btn btn-ghost btn-sm" href="<?= e(url('/followups')) ?>">Open</a></div>
            <div class="card-body stat-pair">
                <div class="stat"><span>Overdue</span><strong><?= e($follow_ups['overdue']) ?></strong></div>
                <div class="stat"><span>Due today</span><strong><?= e($follow_ups['due']) ?></strong></div>
                <div class="stat"><span>Upcoming</span><strong><?= e($follow_ups['upcoming']) ?></strong></div>
            </div>
        </section>
    </div>
</div>
