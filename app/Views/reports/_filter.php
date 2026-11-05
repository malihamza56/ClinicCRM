<?php /** Report date-range bar. Expects $range. */ ?>
<div class="card" style="margin-bottom:var(--space-4)"><div class="card-body row row--between"><div><strong><?= e($range->label()) ?></strong> <span class="muted small">&middot; <?= e($range->days()) ?> day(s)</span></div><?php require __DIR__ . '/../dashboard/_range.php'; ?></div></div>
