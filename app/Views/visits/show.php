<?php
$labels = ['chief_complaint' => 'Chief complaint', 'symptoms' => 'Symptoms', 'bp_systolic' => 'BP systolic', 'bp_diastolic' => 'BP diastolic', 'pulse_bpm' => 'Pulse', 'temperature_c' => 'Temperature', 'respiratory_rate' => 'Respiratory rate', 'spo2_percent' => 'SpO2', 'weight_kg' => 'Weight', 'height_cm' => 'Height', 'diagnosis' => 'Diagnosis', 'treatment_plan' => 'Treatment plan', 'notes' => 'Notes', 'follow_up_advice' => 'Follow-up advice'];
$final = $v['status'] === 'Finalized';
$medText = static fn (array $items): string => $items === [] ? 'None' : implode('; ', array_map(static fn ($i) => $i['medicine_name'] . ' ' . $i['dosage'] . ', ' . $i['frequency'] . ', ' . $i['duration'], $items));
?>
<div class="page-header">
    <div><div class="breadcrumb"><a href="<?= e(url('/patients/' . $v['patient_id'])) ?>"><?= e($v['patient_name']) ?></a> / Consultation</div>
    <h1>Consultation <?= status_badge($v['status']) ?></h1><p><?= e(format_datetime($v['visited_at'])) ?> &middot; <?= e($v['doctor_name']) ?> (<?= e($v['specialty_name']) ?>)</p></div>
    <div class="page-actions">
        <?php if ($prescription): ?><a class="btn btn-secondary" href="<?= e(url('/visits/' . $v['id'] . '/prescription')) ?>" target="_blank" rel="noopener"><?= icon('printer') ?> Prescription</a><?php endif; ?>
        <?php if ($isOwner && !$final): ?><a class="btn btn-primary" href="<?= e(url('/visits/' . $v['id'] . '/edit')) ?>"><?= icon('pencil') ?> Continue editing</a><?php endif; ?>
        <?php if ($isOwner && $final && can('visits.amend')): ?><a class="btn btn-secondary" href="<?= e(url('/visits/' . $v['id'] . '/amend')) ?>"><?= icon('pencil-square') ?> Amend</a><?php endif; ?>
    </div>
</div>
<div class="grid grid-main">
    <div class="stack">
        <section class="card"><div class="card-head"><h2><span class="icon-tile"><?= icon('clipboard2-pulse') ?></span> Findings</h2></div><div class="card-body">
            <div class="chip-row" style="margin-bottom:var(--space-4)">
                <?php foreach ([['BP', $v['bp_systolic'] !== null ? $v['bp_systolic'] . '/' . $v['bp_diastolic'] . ' mmHg' : null], ['Pulse', $v['pulse_bpm'] !== null ? $v['pulse_bpm'] . ' bpm' : null], ['Temp', $v['temperature_c'] !== null ? $v['temperature_c'] . ' °C' : null], ['SpO2', $v['spo2_percent'] !== null ? $v['spo2_percent'] . '%' : null], ['Resp', $v['respiratory_rate'] !== null ? $v['respiratory_rate'] . '/min' : null], ['Weight', $v['weight_kg'] !== null ? $v['weight_kg'] . ' kg' : null], ['Height', $v['height_cm'] !== null ? $v['height_cm'] . ' cm' : null]] as [$l, $val]): if ($val === null) { continue; } ?>
                    <span class="chip"><?= e($l) ?> <strong><?= e($val) ?></strong></span>
                <?php endforeach; ?>
            </div>
            <?php foreach (['chief_complaint' => 'Chief complaint', 'symptoms' => 'Symptoms and history', 'diagnosis' => 'Diagnosis', 'treatment_plan' => 'Treatment plan', 'notes' => 'Private notes', 'follow_up_advice' => 'Follow-up advice'] as $k => $l): if (!$v[$k]) { continue; } ?>
                <div class="note-block"><div class="note-label"><?= e($l) ?></div><?= e($v[$k]) ?></div>
            <?php endforeach; ?>
        </div></section>
        <section class="card"><div class="card-head"><h2><span class="icon-tile icon-tile--blue"><?= icon('capsule') ?></span> Prescription <?= $prescription ? '<span class="muted small">' . e($prescription['prescription_no']) . '</span>' : '' ?></h2></div>
            <?php if ($items === []): $image = 'empty-list.svg'; $heading = 'No medicines prescribed'; $text = ''; $compact = true; require __DIR__ . '/../partials/empty.php'; else: ?>
            <div class="table-wrap"><table class="table"><thead><tr><th>Medicine</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Instructions</th></tr></thead><tbody>
            <?php foreach ($items as $i): ?><tr><td><strong><?= e($i['medicine_name']) ?></strong></td><td><?= e($i['dosage']) ?></td><td><?= e($i['frequency']) ?></td><td><?= e($i['duration']) ?></td><td class="muted"><?= e($i['instructions'] ?: '-') ?></td></tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
        </section>
    </div>
    <section class="card"><div class="card-head"><h2><span class="icon-tile icon-tile--orange"><?= icon('clock-history') ?></span> Amendment history</h2></div>
        <div class="card-body">
        <?php if ($amendments === []): ?><p class="muted small">No amendments. <?= $final ? 'This record is unchanged since it was finalized' . ($v['finalized_at'] ? ' on ' . e(format_datetime($v['finalized_at'])) : '') . '.' : 'It becomes locked once finalized.' ?></p>
        <?php else: ?><ul class="timeline"><?php foreach ($amendments as $a):
            $prev = json_decode((string) $a['previous_data'], true) ?: []; $new = json_decode((string) $a['new_data'], true) ?: []; ?>
            <li><strong><?= e($a['amended_by_name']) ?></strong> <span class="muted small">&middot; <?= e(format_datetime($a['amended_at'])) ?></span>
                <div class="small" style="margin-top:2px">Reason: <?= e($a['reason']) ?></div>
                <div class="diff">
                <?php foreach ($labels as $k => $l): $was = $prev['clinical'][$k] ?? null; $now = $new['clinical'][$k] ?? null; if ($was === $now) { continue; } ?>
                    <div class="was"><strong><?= e($l) ?> before:</strong> <?= e($was ?? '(empty)') ?></div><div class="now"><strong><?= e($l) ?> after:</strong> <?= e($now ?? '(empty)') ?></div>
                <?php endforeach; ?>
                <?php if (($prev['prescription'] ?? null) != ($new['prescription'] ?? null)): ?>
                    <div class="was"><strong>Prescription before:</strong> <?= e($medText($prev['prescription']['items'] ?? [])) ?></div><div class="now"><strong>Prescription after:</strong> <?= e($medText($new['prescription']['items'] ?? [])) ?></div>
                <?php endif; ?>
                </div></li>
        <?php endforeach; ?></ul><?php endif; ?>
        </div>
    </section>
</div>
