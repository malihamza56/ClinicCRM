<?php /** Friendly error page. Expects $status, $title, $message, optional $detail (debug only). */
$image = $status === 403 ? 'error-lock.svg' : ($status === 404 ? 'empty-search.svg' : 'error-generic.svg');
?>
<div class="plain-card">
    <img src="<?= e(asset('images/' . $image)) ?>" alt="">
    <div class="status-code"><?= e($status) ?></div>
    <h1><?= e($title) ?></h1>
    <p class="muted"><?= e($message) ?></p>
    <?php if (!empty($detail)): ?><pre><?= e($detail) ?></pre><?php endif; ?>
    <p><a class="btn btn-primary" href="<?= e(url('/dashboard')) ?>">Back to dashboard</a></p>
</div>
