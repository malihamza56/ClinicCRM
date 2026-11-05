<?php /** Today's appointments. Expects $rows; optional $emptyText. */ ?>
<?php if ($rows === []): ?>
    <?php $image = 'empty-calendar.svg'; $heading = 'No appointments today'; $text = $emptyText ?? 'Appointments booked for today will appear here.'; $compact = true; require __DIR__ . '/../partials/empty.php'; ?>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Time</th><th>Patient</th><th>Doctor</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $a): ?>
                <tr>
                    <td class="nowrap"><strong><?= e(format_time($a['start_time'])) ?></strong><?php if ($a['booking_type'] === 'Walk-in'): ?><br><span class="small muted">Walk-in</span><?php endif; ?></td>
                    <td><div class="cell-person"><span class="avatar"><?= e(initials($a['patient_name'])) ?></span><div><strong><a href="<?= e(url('/appointments/' . $a['id'])) ?>"><?= e($a['patient_name']) ?></a></strong><span><?= e($a['patient_code']) ?></span></div></div></td>
                    <td><?= e($a['doctor_name']) ?><br><span class="small muted"><?= e($a['specialty_name']) ?></span></td>
                    <td><?= status_badge($a['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
