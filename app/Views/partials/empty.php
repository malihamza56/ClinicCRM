<?php
/** Empty state. Expects $image (file in images/), $heading, $text; optional $actionUrl, $actionLabel, $compact. */
?>
<div class="empty<?= !empty($compact) ? ' empty--compact' : '' ?>">
    <img src="<?= e(asset('images/' . ($image ?? 'empty-list.svg'))) ?>" alt="">
    <h3><?= e($heading ?? 'Nothing here yet') ?></h3>
    <p><?= e($text ?? '') ?></p>
    <?php if (!empty($actionUrl)): ?>
        <a class="btn btn-primary" href="<?= e($actionUrl) ?>"><?= e($actionLabel ?? 'Add') ?></a>
    <?php endif; ?>
</div>
