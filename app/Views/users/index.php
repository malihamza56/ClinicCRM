<div class="page-header">
    <div><h1>Staff accounts</h1><p>Everyone who can sign in to Clinic CRM. Deactivate accounts instead of deleting them so history stays intact.</p></div>
    <div class="page-actions"><a class="btn btn-primary" href="<?= e(url('/users/create')) ?>"><?= icon('person-plus') ?> New account</a></div>
</div>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Name</th><th>Role</th><th>Last sign-in</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><div class="cell-person"><span class="avatar"><?= e(initials($u['full_name'])) ?></span><div><strong><?= e($u['full_name']) ?></strong><span><?= e($u['email']) ?></span></div></div></td>
                <td><?= e($u['role_name']) ?></td>
                <td class="small"><?= e($u['last_login_at'] ? format_datetime($u['last_login_at']) : 'Never') ?><?php if ($u['must_change_password']): ?><br><span class="muted">Password change pending</span><?php endif; ?></td>
                <td><?= status_badge($u['is_active'] ? 'Active' : 'Inactive') ?></td>
                <td><div class="row-actions">
                    <a class="btn btn-secondary btn-sm" href="<?= e(url('/users/' . $u['id'] . '/edit')) ?>">Edit</a>
                    <?= post_button(url('/users/' . $u['id'] . '/reset-password'), 'Reset password', 'btn btn-secondary btn-sm', 'Issue a new temporary password for ' . $u['full_name'] . '? Their current password will stop working.') ?>
                    <?= post_button(url('/users/' . $u['id'] . '/toggle'), $u['is_active'] ? 'Deactivate' : 'Activate', $u['is_active'] ? 'btn btn-danger-outline btn-sm' : 'btn btn-secondary btn-sm', $u['is_active'] ? 'Deactivate ' . $u['full_name'] . '? They will no longer be able to sign in.' : null) ?>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
