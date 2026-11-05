<?php
/** Consultation form: used for drafts ($amending=false) and for amending finalized visits ($amending=true). */
$c = $clinical;
$rows = old('rx_medicine', null);
if (is_array($rows)) {
    $rxRows = [];
    foreach ($rows as $i => $m) {
        $rxRows[] = ['medicine_name' => $m, 'dosage' => old('rx_dosage', [])[$i] ?? '', 'frequency' => old('rx_frequency', [])[$i] ?? '', 'duration' => old('rx_duration', [])[$i] ?? '', 'instructions' => old('rx_instructions', [])[$i] ?? ''];
    }
} else {
    $rxRows = $rx['items'];
}
if ($rxRows === []) { $rxRows = [['medicine_name' => '', 'dosage' => '', 'frequency' => '', 'duration' => '', 'instructions' => '']]; }
$action = $amending ? '/visits/' . $v['id'] . '/amend' : '/visits/' . $v['id'];
$rowHtml = static function (array $r): string {
    $cells = [['rx_medicine[]', $r['medicine_name'], 'Medicine, e.g. Paracetamol 500 mg', 150], ['rx_dosage[]', $r['dosage'], 'e.g. 1 tablet', 80], ['rx_frequency[]', $r['frequency'], 'e.g. Twice a day', 80], ['rx_duration[]', $r['duration'], 'e.g. 5 days', 60], ['rx_instructions[]', $r['instructions'] ?? '', 'e.g. After meals', 255]];
    $h = '<div class="rx-row" data-row>';
    foreach ($cells as [$n, $val, $ph, $max]) {
        $h .= '<input class="input" name="' . e($n) . '" value="' . e($val) . '" placeholder="' . e($ph) . '" maxlength="' . $max . '" aria-label="' . e($ph) . '">';
    }
    return $h . '<button type="button" class="btn btn-ghost btn-sm" data-remove-row aria-label="Remove medicine">' . icon('trash3') . '</button></div>';
};
?>
<div class="page-header">
    <div><div class="breadcrumb"><a href="<?= e(url('/queue')) ?>">Queue</a> / <?= $amending ? 'Amend consultation' : 'Consultation' ?></div>
    <div class="profile-head"><span class="avatar avatar--lg"><?= e(initials($v['patient_name'])) ?></span><div><h1><?= e($v['patient_name']) ?></h1>
    <p><?= e($v['patient_code']) ?> &middot; <?= e(age_from($v['date_of_birth'])) ?> &middot; <?= e($v['gender']) ?><?= $v['blood_group'] ? ' &middot; Blood group ' . e($v['blood_group']) : '' ?> <?= status_badge($v['status']) ?></p></div></div></div>
    <div class="page-actions"><a class="btn btn-secondary" href="<?= e(url('/patients/' . $v['patient_id'])) ?>" target="_blank" rel="noopener"><?= icon('clock-history') ?> Patient history</a></div>
</div>
<?php if ($amending): ?><div class="alert alert--warning" role="status"><?= icon('exclamation-triangle-fill') ?><div class="alert-body"><strong>You are amending a finalized record.</strong> The previous version is kept permanently with your name, the time and the reason you give below.</div></div><?php endif; ?>

<form method="post" action="<?= e(url($action)) ?>" novalidate><?= csrf_field() ?>
<div class="card"><div class="card-body">
    <div class="section-title"><?= icon('chat-left-text') ?> Presenting complaint</div>
    <div class="form-grid">
        <?= field('chief_complaint', 'Chief complaint', ['required' => true, 'value' => $c['chief_complaint'] ?? '', 'maxlength' => 500, 'span2' => true, 'help' => 'Required to finalize.']) ?>
        <?= field('symptoms', 'Symptoms and history', ['type' => 'textarea', 'value' => $c['symptoms'] ?? '', 'rows' => 3, 'span2' => true]) ?>
    </div>

    <div class="section-title"><?= icon('activity') ?> Vitals</div>
    <div class="vitals-grid">
        <?= field('bp_systolic', 'BP systolic (mmHg)', ['type' => 'number', 'value' => $c['bp_systolic'] ?? '', 'min' => 50, 'max' => 260, 'inputmode' => 'numeric']) ?>
        <?= field('bp_diastolic', 'BP diastolic (mmHg)', ['type' => 'number', 'value' => $c['bp_diastolic'] ?? '', 'min' => 30, 'max' => 160, 'inputmode' => 'numeric']) ?>
        <?= field('pulse_bpm', 'Pulse (bpm)', ['type' => 'number', 'value' => $c['pulse_bpm'] ?? '', 'min' => 20, 'max' => 250]) ?>
        <?= field('temperature_c', 'Temperature (°C)', ['type' => 'number', 'value' => $c['temperature_c'] ?? '', 'min' => 30, 'max' => 45, 'step' => '0.1']) ?>
        <?= field('respiratory_rate', 'Respiratory rate (/min)', ['type' => 'number', 'value' => $c['respiratory_rate'] ?? '', 'min' => 4, 'max' => 80]) ?>
        <?= field('spo2_percent', 'SpO2 (%)', ['type' => 'number', 'value' => $c['spo2_percent'] ?? '', 'min' => 50, 'max' => 100]) ?>
        <?= field('weight_kg', 'Weight (kg)', ['type' => 'number', 'value' => $c['weight_kg'] ?? '', 'min' => 0.5, 'max' => 400, 'step' => '0.1']) ?>
        <?= field('height_cm', 'Height (cm)', ['type' => 'number', 'value' => $c['height_cm'] ?? '', 'min' => 20, 'max' => 260, 'step' => '0.1']) ?>
    </div>

    <div class="section-title"><?= icon('clipboard2-pulse') ?> Assessment and plan</div>
    <div class="form-grid">
        <?= field('diagnosis', 'Diagnosis', ['type' => 'textarea', 'required' => true, 'value' => $c['diagnosis'] ?? '', 'rows' => 3, 'span2' => true, 'help' => 'Required to finalize.']) ?>
        <?= field('treatment_plan', 'Treatment plan', ['type' => 'textarea', 'value' => $c['treatment_plan'] ?? '', 'rows' => 3, 'span2' => true]) ?>
        <?= field('notes', 'Private clinical notes', ['type' => 'textarea', 'value' => $c['notes'] ?? '', 'rows' => 2, 'span2' => true]) ?>
    </div>

    <div class="section-title"><?= icon('capsule') ?> Prescription</div>
    <?php if (field_error('rx')): ?><div class="error-text" role="alert" style="margin-bottom:8px"><?= icon('exclamation-circle') ?> <?= e(field_error('rx')) ?></div><?php endif; ?>
    <div class="rx-head" aria-hidden="true"><span>Medicine</span><span>Dosage</span><span>Frequency</span><span>Duration</span><span>Instructions</span><span></span></div>
    <div data-rows="#rxTemplate" data-add="#addRx" data-max="<?= e(\App\Services\VisitService::MAX_MEDICINES) ?>">
        <?php foreach ($rxRows as $r): echo $rowHtml($r); endforeach; ?>
    </div>
    <template id="rxTemplate"><?= $rowHtml(['medicine_name' => '', 'dosage' => '', 'frequency' => '', 'duration' => '', 'instructions' => '']) ?></template>
    <button type="button" class="btn btn-secondary btn-sm" id="addRx"><?= icon('plus-lg') ?> Add medicine</button>
    <div class="form-grid" style="margin-top:var(--space-4)">
        <?= field('advice', 'General advice for the patient', ['type' => 'textarea', 'value' => $rx['advice'] ?? '', 'rows' => 2, 'span2' => true, 'help' => 'Printed on the prescription, e.g. diet, rest, warning signs.']) ?>
        <?= field('follow_up_advice', 'Follow-up advice', ['value' => $c['follow_up_advice'] ?? '', 'maxlength' => 255, 'span2' => true, 'help' => 'e.g. "Review after 1 week or earlier if fever persists."']) ?>
    </div>

    <?php if (!$amending): ?>
    <div class="section-title"><?= icon('arrow-repeat') ?> Schedule a follow-up (optional)</div>
    <div class="form-grid">
        <?= field('follow_up_date', 'Follow-up date', ['type' => 'date', 'value' => '', 'min' => date('Y-m-d'), 'help' => 'Creates a reminder for the front desk when you finalize.']) ?>
        <?= field('follow_up_notes', 'Follow-up notes', ['value' => '', 'maxlength' => 500]) ?>
    </div>
    <?php else: ?>
    <div class="section-title"><?= icon('pencil-square') ?> Reason for amendment</div>
    <?= field('amend_reason', 'Why is this record being changed?', ['type' => 'textarea', 'required' => true, 'rows' => 2, 'help' => 'Required. Shown in the permanent history, e.g. "Corrected dosage typo".']) ?>
    <?php endif; ?>

    <div class="sticky-actions">
        <a class="btn btn-secondary" href="<?= e(url($amending ? '/visits/' . $v['id'] : '/queue')) ?>">Cancel</a>
        <?php if ($amending): ?>
            <button class="btn btn-primary" type="submit"><?= icon('check2') ?> Save amendment</button>
        <?php else: ?>
            <button class="btn btn-secondary" type="submit" name="action" value="draft" data-no-confirm><?= icon('save') ?> Save draft</button>
            <button class="btn btn-primary" type="submit" name="action" value="finalize" data-confirm="Finalize this consultation? After finalizing, changes are only possible as recorded amendments." data-confirm-label="Finalize"><?= icon('check2-circle') ?> Finalize consultation</button>
        <?php endif; ?>
    </div>
</div></div>
</form>
