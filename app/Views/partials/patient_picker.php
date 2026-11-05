<?php
/** Patient search box (typeahead). Expects $patient (array|null). Posts patient_id. */
$pid = old('patient_id', $patient['id'] ?? '');
$err = field_error('patient_id');
?>
<div class="typeahead" data-patient-search data-url="<?= e(url('/patients/lookup')) ?>">
    <div class="field"<?= $patient ? ' hidden' : '' ?>>
        <label for="patient_search">Patient<span class="req" aria-hidden="true">*</span></label>
        <div class="input-icon"><?= icon('search') ?><input class="input<?= $err ? ' is-invalid' : '' ?>" type="search" id="patient_search" placeholder="Search by name, patient ID or phone" autocomplete="off"></div>
        <div class="help">Type at least 2 characters.<?php if (can('patients.create')): ?> Not registered yet? <a href="<?= e(url('/patients/create', ['next' => $registerNext ?? 'book'])) ?>">Register a new patient</a>.<?php endif; ?></div>
        <?php if ($err): ?><div class="error-text" role="alert"><?= icon('exclamation-circle') ?> <?= e($err) ?></div><?php endif; ?>
    </div>
    <input type="hidden" name="patient_id" value="<?= e($pid) ?>">
    <div class="typeahead-list" hidden></div>
    <div class="selected-patient" <?= $patient ? '' : 'hidden' ?> data-name="<?= e($patient['full_name'] ?? '') ?>" data-code="<?= e($patient['patient_code'] ?? '') ?>" data-phone="<?= e($patient['phone'] ?? '') ?>">
        <div class="cell-person"><span class="avatar"><?= icon('person') ?></span><div><strong class="sp-name"><?= e($patient['full_name'] ?? '') ?></strong><span class="sp-meta"><?= $patient ? e($patient['patient_code'] . ' · ' . $patient['phone']) : '' ?></span></div></div>
        <button type="button" class="btn btn-secondary btn-sm sp-clear">Change</button>
    </div>
</div>
