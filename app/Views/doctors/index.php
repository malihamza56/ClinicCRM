<div class="page-header"><div><h1>Doctors</h1><p>Profiles, fees and weekly working hours.</p></div>
<?php if (can('doctors.manage')): ?><div class="page-actions"><a class="btn btn-primary" href="<?= e(url('/doctors/create')) ?>"><?= icon('person-plus') ?> New doctor</a></div><?php endif; ?></div>
<div class="card" style="margin-bottom:var(--space-4)">
<form class="filters" method="get" data-no-loading style="border-bottom:0">
    <div class="field field--grow"><label for="q">Search</label><div class="input-icon"><?= icon('search') ?><input class="input" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Doctor name"></div></div>
    <div class="field"><label for="specialty_id">Specialty</label><select class="input" id="specialty_id" name="specialty_id"><option value="">All specialties</option><?php foreach ($specialties as $s): ?><option value="<?= e($s['id']) ?>"<?= selected($filters['specialty_id'], $s['id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="status">Status</label><select class="input" id="status" name="status"><option value="">All</option><option value="active"<?= selected($filters['status'], 'active') ?>>Active</option><option value="inactive"<?= selected($filters['status'], 'inactive') ?>>Inactive</option></select></div>
    <button class="btn btn-primary" type="submit"><?= icon('funnel') ?> Filter</button>
    <?php if ($filters['q'] !== '' || $filters['specialty_id'] || $filters['status'] !== ''): ?><a class="btn btn-secondary" href="<?= e(url('/doctors')) ?>">Reset</a><?php endif; ?>
</form></div>
<?php if ($doctors === []): ?>
<div class="card"><?php $image = 'empty-list.svg'; $heading = ($filters['q'] !== '' || $filters['specialty_id'] || $filters['status'] !== '') ? 'No doctors match' : 'No doctors yet'; $text = ($filters['q'] !== '' || $filters['specialty_id'] || $filters['status'] !== '') ? 'Try clearing the filters.' : 'Add the first doctor to start booking appointments.'; $actionUrl = can('doctors.manage') ? url('/doctors/create') : null; $actionLabel = 'Add doctor'; require __DIR__ . '/../partials/empty.php'; ?></div>
<?php else: ?>
<div class="grid grid-3">
<?php foreach ($doctors as $d): ?>
<a class="card card-body" style="text-decoration:none;color:inherit" href="<?= e(url('/doctors/' . $d['id'])) ?>">
    <div class="profile-head"><span class="avatar avatar--lg"><?= e(initials($d['full_name'])) ?></span><div><h3><?= e($d['full_name']) ?></h3><div class="muted small"><?= e($d['specialty_name']) ?></div></div></div>
    <dl class="dl" style="margin-top:var(--space-4)"><dt>Fee</dt><dd><?= e(money($d['consultation_fee'])) ?></dd><dt>Room</dt><dd><?= e($d['room'] ?: '-') ?></dd><dt>Status</dt><dd><?= status_badge(($d['is_active'] && $d['account_active']) ? 'Active' : 'Inactive') ?></dd></dl>
</a>
<?php endforeach; ?>
</div>
<?php endif; ?>
