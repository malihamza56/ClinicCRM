<?php $t = $r['totals']; $rate = $t['total'] > 0 ? (int) round($t['completed'] / $t['total'] * 100) : 0; ?>
<div class="page-header"><div><h1>Operational reports</h1><p>Appointment volumes, outcomes and patient growth.</p></div>
<?php if (can('exports.appointments')): ?><div class="page-actions"><a class="btn btn-secondary" href="<?= e(url('/appointments/export', ['from' => $range->from, 'to' => $range->to])) ?>"><?= icon('download') ?> Export appointments</a></div><?php endif; ?></div>
<?php require __DIR__ . '/_filter.php'; ?>
<div class="kpi-grid">
<?php
$label = 'Appointments'; $value = number_format($t['total']); $glyph = 'calendar2-check'; $tone = 'green'; $valueTone = true; $note = 'Booked in this period'; require __DIR__ . '/../dashboard/_kpi.php';
$label = 'Completed'; $value = $rate . '%'; $glyph = 'check2-circle'; $tone = 'green'; $valueTone = false; $note = $t['completed'] . ' consultations'; require __DIR__ . '/../dashboard/_kpi.php';
$label = 'Cancelled'; $value = number_format($t['cancelled']); $glyph = 'x-circle'; $tone = 'orange'; $note = 'By patient or clinic'; require __DIR__ . '/../dashboard/_kpi.php';
$label = 'No-shows'; $value = number_format($t['no_show']); $glyph = 'person-x'; $tone = 'red'; $note = 'Did not arrive'; require __DIR__ . '/../dashboard/_kpi.php';
$label = 'Walk-ins'; $value = number_format($t['walk_ins']); $glyph = 'person-walking'; $tone = 'blue'; $note = 'Without a booking'; require __DIR__ . '/../dashboard/_kpi.php';
?></div>
<div class="grid grid-main" style="margin-top:var(--space-4)">
<section class="card"><div class="card-head"><h2><span class="icon-tile"><?= icon('graph-up') ?></span> Appointment outcomes</h2></div><div class="card-body"><div class="chart-box">
<canvas role="img" aria-label="Line chart of appointment outcomes over time" data-chart="<?= json_attr(['type' => 'line', 'labels' => $r['trend']['labels'], 'datasets' => [['label' => 'Completed', 'data' => $r['trend']['completed'], 'color' => 'green', 'fill' => true], ['label' => 'Pending', 'data' => $r['trend']['pending'], 'color' => 'orange'], ['label' => 'Cancelled / no-show', 'data' => $r['trend']['cancelled'], 'color' => 'red']]]) ?>"></canvas>
<div class="chart-empty"><div><?= icon('graph-up', 'icon-lg') ?><br>No appointments in this period.</div></div></div></div></section>
<section class="card"><div class="card-head"><h2><span class="icon-tile icon-tile--blue"><?= icon('person-plus') ?></span> New patients</h2></div><div class="card-body"><div class="chart-box">
<canvas role="img" aria-label="Bar chart of new patient registrations" data-chart="<?= json_attr(['type' => 'bar', 'labels' => $r['new_patients']['labels'], 'datasets' => [['label' => 'Registrations', 'data' => $r['new_patients']['registrations'], 'color' => 'blue']]]) ?>"></canvas>
<div class="chart-empty"><div><?= icon('person-plus', 'icon-lg') ?><br>No registrations in this period.</div></div></div></div></section>
</div>
<section class="card" style="margin-top:var(--space-4)"><div class="card-head"><h2>By doctor</h2></div><div class="table-wrap"><table class="table"><thead><tr><th>Doctor</th><th>Specialty</th><th class="num">Appointments</th><th class="num">Completed</th><th class="num">Cancelled</th><th class="num">No-show</th></tr></thead><tbody>
<?php foreach ($r['by_doctor'] as $d): ?><tr><td><strong><?= e($d['doctor']) ?></strong></td><td class="muted"><?= e($d['specialty']) ?></td><td class="num"><?= e($d['total']) ?></td><td class="num"><?= e($d['completed']) ?></td><td class="num"><?= e($d['cancelled']) ?></td><td class="num"><?= e($d['no_show']) ?></td></tr><?php endforeach; ?>
<?php if ($r['by_doctor'] === []): ?><tr><td colspan="6" class="muted">No appointments in this period.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="card" style="margin-top:var(--space-4)"><div class="card-head"><h2>Follow-ups in this period</h2></div><div class="card-body stat-pair"><div class="stat"><span>Completed</span><strong><?= e($r['follow_ups']['completed']) ?></strong></div><div class="stat"><span>Still pending</span><strong><?= e($r['follow_ups']['pending']) ?></strong></div><div class="stat"><span>Overdue</span><strong><?= e($r['follow_ups']['overdue']) ?></strong></div></div></section>
