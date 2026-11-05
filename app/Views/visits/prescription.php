<?php /** Printable prescription. Expects $v (visit), $rx, $items. */ ?>
<div class="print-bar"><a class="btn btn-secondary btn-sm" href="<?= e(url('/visits/' . $v['id'])) ?>"><?= icon('arrow-left') ?> Back</a><button type="button" class="btn btn-primary btn-sm" data-print><?= icon('printer') ?> Print</button></div>
<article class="print-page">
    <header class="doc-head">
        <div class="clinic"><h1><?= e(setting('clinic_name', 'Clinic')) ?></h1><p><?= e(setting('clinic_address')) ?></p><p><?= e(trim(setting('clinic_phone') . ' ' . setting('clinic_email'))) ?></p></div>
        <div class="doc-title"><h2>PRESCRIPTION</h2><div><?= e($rx['prescription_no']) ?></div><div><?= e(format_date($v['visited_at'])) ?></div></div>
    </header>
    <div class="doc-meta">
        <div><span>Patient</span><strong><?= e($v['patient_name']) ?></strong></div><div><span>Patient ID</span><?= e($v['patient_code']) ?></div>
        <div><span>Age / Gender</span><?= e(age_from($v['date_of_birth'])) ?> / <?= e($v['gender']) ?></div><div><span>Doctor</span><strong><?= e($v['doctor_name']) ?></strong></div>
        <div><span>Weight</span><?= e($v['weight_kg'] ? $v['weight_kg'] . ' kg' : '-') ?></div><div><span>Specialty</span><?= e($v['specialty_name']) ?></div>
    </div>
    <?php if ($v['diagnosis']): ?><p><strong>Diagnosis:</strong> <?= e($v['diagnosis']) ?></p><?php endif; ?>
    <table class="doc-table"><thead><tr><th style="width:36px">#</th><th>Medicine</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Instructions</th></tr></thead><tbody>
    <?php foreach ($items as $n => $i): ?><tr><td><?= e($n + 1) ?></td><td><strong><?= e($i['medicine_name']) ?></strong></td><td><?= e($i['dosage']) ?></td><td><?= e($i['frequency']) ?></td><td><?= e($i['duration']) ?></td><td><?= e($i['instructions'] ?: '') ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php if ($rx['advice']): ?><p><strong>Advice:</strong> <?= e($rx['advice']) ?></p><?php endif; ?>
    <?php if ($v['follow_up_advice']): ?><p><strong>Follow-up:</strong> <?= e($v['follow_up_advice']) ?></p><?php endif; ?>
    <div class="signature"><div><?= e($v['doctor_name']) ?><br><span class="muted"><?= e($v['qualification'] ?: '') ?></span></div></div>
    <footer class="doc-foot"><span>Valid only with the doctor's signature.</span><span><?= $v['status'] === 'Draft' ? 'DRAFT - not finalized' : 'Printed ' . e(date('d M Y')) ?></span></footer>
</article>
