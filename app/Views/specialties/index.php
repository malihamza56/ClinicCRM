<div class="page-header"><div><h1>Specialties</h1><p>Medical departments that doctors belong to.</p></div>
<div class="page-actions"><a class="btn btn-primary" href="<?= e(url('/specialties/create')) ?>"><?= icon('plus-lg') ?> New specialty</a></div></div>
<div class="card"><div class="table-wrap"><table class="table">
<thead><tr><th>Name</th><th>Description</th><th>Doctors</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($specialties as $s): ?>
<tr><td><strong><?= e($s['name']) ?></strong></td><td class="muted"><?= e($s['description'] ?: '-') ?></td><td><?= e($s['doctor_count']) ?></td><td><?= status_badge($s['is_active'] ? 'Active' : 'Inactive') ?></td>
<td><div class="row-actions"><a class="btn btn-secondary btn-sm" href="<?= e(url('/specialties/' . $s['id'] . '/edit')) ?>">Edit</a>
<?= post_button(url('/specialties/' . $s['id'] . '/toggle'), $s['is_active'] ? 'Deactivate' : 'Activate', 'btn btn-secondary btn-sm') ?></div></td></tr>
<?php endforeach; ?></tbody></table></div></div>
