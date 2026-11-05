<?php
/** One-time banner messages set with Session::flash(). Errors use role="alert" so screen readers announce them. */
$banners = [
    'success' => ['check-circle-fill', 'status'],
    'error'   => ['x-octagon-fill', 'alert'],
    'warning' => ['exclamation-triangle-fill', 'status'],
    'info'    => ['info-circle-fill', 'status'],
];
foreach ($banners as $type => [$glyph, $role]):
    $message = \App\Core\Session::flashed($type);
    if (!is_string($message) || $message === '') {
        continue;
    }
    ?>
    <div class="alert alert--<?= e($type) ?>" role="<?= e($role) ?>">
        <?= icon($glyph) ?>
        <div class="alert-body"><?= e($message) ?></div>
        <button type="button" class="alert-close" data-dismiss aria-label="Dismiss message">&times;</button>
    </div>
<?php endforeach; ?>
<?php $secret = \App\Core\Session::flashed('secret'); ?>
<?php if (is_array($secret)): ?>
    <div class="alert alert--info" role="status">
        <?= icon('key-fill') ?>
        <div class="alert-body">
            <strong><?= e($secret['label']) ?></strong><br>
            Temporary password: <code><?= e($secret['value']) ?></code><br>
            <span class="small">Shown only once. Share it privately - the user must choose a new password at first sign-in.</span>
        </div>
    </div>
<?php endif; ?>
