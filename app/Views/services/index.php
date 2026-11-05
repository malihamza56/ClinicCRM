<div class="page-header"><div><h1>Services &amp; fees</h1><p>Billable services. Doctor consultation fees are set on each doctor's profile.</p></div>
<div class="page-actions"><a class="btn btn-primary" href="<?= e(url('/services/create')) ?>"><?= icon('plus-lg') ?> New service</a></div></div>
<div class="card"><div class="table-wrap"><table class="table">
<thead><tr><th>Service</th><th class="num">Price</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($services as $s): ?>
<tr><td><strong><?= e($s['name']) ?></strong><?php if ($s['description']): ?><br><span class="small muted"><?= e($s['description']) ?></span><?php endif; ?></td><td class="num"><?= e(money($s['price'])) ?></td><td><?= status_badge($s['is_active'] ? 'Active' : 'Inactive') ?></td>
<td><div class="row-actions"><a class="btn btn-secondary btn-sm" href="<?= e(url('/services/' . $s['id'] . '/edit')) ?>">Edit</a>
<?= post_button(url('/services/' . $s['id'] . '/toggle'), $s['is_active'] ? 'Deactivate' : 'Activate', 'btn btn-secondary btn-sm') ?></div></td></tr>
<?php endforeach; ?></tbody></table></div></div>
