<div class="page-header"><div><h1>Audit log</h1><p>A permanent record of who did what. Entries cannot be edited or deleted.</p></div></div>
<div class="card">
<form class="filters" method="get" data-no-loading>
    <div class="field"><label for="from">From</label><input class="input" type="date" id="from" name="from" value="<?= e($filters['from']) ?>"></div>
    <div class="field"><label for="to">To</label><input class="input" type="date" id="to" name="to" value="<?= e($filters['to']) ?>"></div>
    <div class="field"><label for="action">Action</label><select class="input" id="action" name="action"><option value="">All</option><?php foreach ($actions as $a): ?><option value="<?= e($a) ?>"<?= selected($filters['action'], $a) ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="user_id">User</label><select class="input" id="user_id" name="user_id"><option value="">All</option><?php foreach ($users as $u): ?><option value="<?= e($u['id']) ?>"<?= selected($filters['user_id'], $u['id']) ?>><?= e($u['full_name']) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-primary" type="submit"><?= icon('funnel') ?> Filter</button>
    <a class="btn btn-secondary" href="<?= e(url('/audit')) ?>">Reset</a>
</form>
<?php if ($page->items === []): ?>
    <?php $image = 'empty-search.svg'; $heading = 'No log entries match'; $text = 'Try a wider date range or clear the filters.'; require __DIR__ . '/../partials/empty.php'; ?>
<?php else: ?>
<div class="table-wrap"><table class="table"><thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead><tbody>
<?php foreach ($page->items as $l): ?>
<tr><td class="nowrap small"><?= e(format_datetime($l['created_at'])) ?></td><td><?= e($l['user_name'] ?? 'System / not signed in') ?></td><td><span class="badge badge--neutral"><?= e($l['action']) ?></span></td>
<td><?= e($l['summary']) ?><?php if ($l['entity_id']): ?> <span class="muted small">(<?= e($l['entity_type']) ?> #<?= e($l['entity_id']) ?>)</span><?php endif; ?></td><td class="small muted"><?= e($l['ip_address']) ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php require __DIR__ . '/../partials/pagination.php'; endif; ?>
</div>
