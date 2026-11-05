<div class="page-header">
    <div><h1>Patients</h1><p><?= $fullView ? 'Registered patients and their contact details.' : 'Patients and their billing contact details.' ?></p></div>
    <div class="page-actions">
        <?php if (can('exports.patients')): ?><a class="btn btn-secondary" href="<?= e(url('/patients/export', ['q' => $filters['q'], 'status' => $filters['status']])) ?>"><?= icon('download') ?> Export CSV</a><?php endif; ?>
        <?php if (can('patients.create')): ?><a class="btn btn-primary" href="<?= e(url('/patients/create')) ?>"><?= icon('person-plus') ?> Register patient</a><?php endif; ?>
    </div>
</div>
<div class="card">
    <form class="filters" method="get" data-no-loading>
        <div class="field field--grow"><label for="q">Search</label><div class="input-icon"><?= icon('search') ?><input class="input" id="q" name="q" type="search" value="<?= e($filters['q']) ?>" placeholder="Name, patient ID or phone"></div></div>
        <div class="field"><label for="status">Show</label><select class="input" id="status" name="status"><?php foreach (['active' => 'Active patients', 'archived' => 'Archived', 'all' => 'All'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($filters['status'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <button class="btn btn-primary" type="submit"><?= icon('funnel') ?> Search</button>
        <?php if ($filters['q'] !== '' || $filters['status'] !== 'active'): ?><a class="btn btn-secondary" href="<?= e(url('/patients')) ?>">Reset</a><?php endif; ?>
    </form>
    <?php if ($page->items === []): ?>
        <?php if ($filters['q'] !== ''): $image = 'empty-search.svg'; $heading = 'No patient found'; $text = 'Check the spelling, try the phone number, or register the patient.'; else: $image = 'empty-list.svg'; $heading = 'No patients yet'; $text = 'Registered patients will appear here.'; endif;
        $actionUrl = can('patients.create') ? url('/patients/create') : null; $actionLabel = 'Register patient'; require __DIR__ . '/../partials/empty.php'; ?>
    <?php else: ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Patient</th><?php if ($fullView): ?><th>Age / Gender</th><?php endif; ?><th>Phone</th><?php if ($fullView): ?><th>Registered</th><?php endif; ?><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($page->items as $p): ?>
            <tr>
                <td><div class="cell-person"><span class="avatar"><?= e(initials($p['full_name'])) ?></span><div><strong><a href="<?= e(url('/patients/' . $p['id'])) ?>"><?= e($p['full_name']) ?></a></strong><span><?= e($p['patient_code']) ?></span></div></div></td>
                <?php if ($fullView): ?><td><?= e(age_from($p['date_of_birth'])) ?> &middot; <?= e($p['gender']) ?></td><?php endif; ?>
                <td class="nowrap"><?= e($p['phone']) ?></td>
                <?php if ($fullView): ?><td class="small"><?= e(format_date($p['registered_on'])) ?></td><?php endif; ?>
                <td><?= status_badge($p['is_archived'] ? 'Archived' : 'Active') ?></td>
                <td><div class="row-actions"><a class="btn btn-secondary btn-sm" href="<?= e(url('/patients/' . $p['id'])) ?>">Open</a></div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php require __DIR__ . '/../partials/pagination.php'; endif; ?>
</div>
