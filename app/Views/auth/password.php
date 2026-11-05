<?php /** Change own password. $forced is true when the account must replace a temporary password. */ ?>
<div class="page-header">
    <div>
        <h1>Change password</h1>
        <p><?= $forced ? 'You are using a temporary password. Choose your own to continue.' : 'Use a long password that you do not use anywhere else.' ?></p>
    </div>
</div>
<div class="card" style="max-width:620px">
    <form method="post" action="<?= e(url('/account/password')) ?>" class="card-body" novalidate>
        <?= csrf_field() ?>
        <div class="stack">
            <?php foreach ([['current_password', 'Current password', 'current-password'], ['new_password', 'New password', 'new-password'], ['confirm_password', 'Confirm new password', 'new-password']] as [$name, $label, $auto]): ?>
                <div class="field">
                    <label for="<?= e($name) ?>"><?= e($label) ?><span class="req" aria-hidden="true">*</span></label>
                    <input class="input<?= field_error($name) ? ' is-invalid' : '' ?>" type="password" id="<?= e($name) ?>" name="<?= e($name) ?>" autocomplete="<?= e($auto) ?>" required>
                    <?php if ($name === 'new_password'): ?><div class="help">At least 10 characters, with letters and numbers.</div><?php endif; ?>
                    <?php if (field_error($name)): ?><div class="error-text"><?= icon('exclamation-circle') ?> <?= e(field_error($name)) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="form-actions">
            <?php if (!$forced): ?><a class="btn btn-secondary" href="<?= e(url('/dashboard')) ?>">Cancel</a><?php endif; ?>
            <button class="btn btn-primary" type="submit"><?= icon('check2') ?> Save new password</button>
        </div>
    </form>
</div>
