<div class="page-header"><div><h1>Follow-ups</h1><p>Patients the doctor asked to see again. Reminders are tracked here - no messages are sent automatically.</p></div>
<?php if (can('followups.create')): ?><div class="page-actions"><a class="btn btn-primary" href="<?= e(url('/followups/create')) ?>"><?= icon('plus-lg') ?> New follow-up</a></div><?php endif; ?></div>
<div class="tabs" role="tablist">
<?php foreach ($buckets as $key => $label): $n = $counts[$key] ?? null; ?>
    <a class="tab<?= $bucket === $key ? ' is-active' : '' ?>" href="<?= e(url('/followups', ['bucket' => $key, 'from' => $from, 'to' => $to])) ?>"<?= $bucket === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?><?php if ($n !== null): ?> <span class="count"><?= e($n) ?></span><?php endif; ?></a>
<?php endforeach; ?>
</div>
<div class="card">
<form class="filters" method="get" data-no-loading>
    <input type="hidden" name="bucket" value="<?= e($bucket) ?>">
    <div class="field"><label for="from">Follow-up date from</label><input class="input" type="date" id="from" name="from" value="<?= e($from) ?>"></div>
    <div class="field"><label for="to">to</label><input class="input" type="date" id="to" name="to" value="<?= e($to) ?>"></div>
    <button class="btn btn-primary" type="submit"><?= icon('funnel') ?> Filter</button>
    <?php if ($from !== '' || $to !== ''): ?><a class="btn btn-secondary" href="<?= e(url('/followups', ['bucket' => $bucket])) ?>">Clear dates</a><?php endif; ?>
</form>
<?php if ($page->items === []): $image = 'empty-calendar.svg'; $heading = 'Nothing in this list'; $text = 'Follow-ups created from consultations will appear here.'; require __DIR__ . '/../partials/empty.php'; else: ?>
<div class="table-wrap"><table class="table"><thead><tr><th>Date</th><th>Patient</th><th>Doctor</th><th>Notes</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($page->items as $f): ?>
<tr><td class="nowrap"><strong><?= e(format_date($f['follow_up_date'])) ?></strong></td>
<td><div class="cell-person"><span class="avatar"><?= e(initials($f['patient_name'])) ?></span><div><strong><?= e($f['patient_name']) ?></strong><span><?= e($f['patient_phone']) ?></span></div></div></td>
<td><?= e($f['doctor_name']) ?></td><td class="small"><?= e($f['notes'] ?: '-') ?></td>
<td><?= status_badge($f['bucket'] === 'Overdue' ? 'Overdue' : ($f['bucket'] === 'Due today' ? 'Waiting' : ($f['status'] === 'Pending' ? 'Pending' : $f['status']))) ?><?= $f['bucket'] === 'Due today' ? '<span class="small muted"> due today</span>' : '' ?></td>
<td><div class="row-actions"><?php if ($f['status'] === 'Pending' && can('followups.update')): ?>
<?= post_button(url('/followups/' . $f['id'] . '/status'), 'Done', 'btn btn-primary btn-sm', null, ['to' => 'Completed'], 'check2') ?>
<?= post_button(url('/followups/' . $f['id'] . '/status'), 'Cancel', 'btn btn-secondary btn-sm', 'Cancel this follow-up?', ['to' => 'Cancelled']) ?><?php endif; ?></div></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php require __DIR__ . '/../partials/pagination.php'; endif; ?>
</div>
