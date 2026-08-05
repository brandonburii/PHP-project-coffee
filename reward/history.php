<?php
include '../_base.php';
/////
auth('Member');

$stm = $_db->prepare('
    SELECT rr.*, r.name AS reward_name, r.photo
    FROM reward_redemption rr
    JOIN reward r ON r.id = rr.reward_id
    WHERE rr.user_id = ?
    ORDER BY rr.redeemed_at DESC
');
$stm->execute([$_user->id]);
$arr = $stm->fetchAll();

$_breadcrumbs = ['Dashboard' => '/', 'Rewards' => 'list.php', 'My Reward History' => ''];
$_title = 'My Reward History';
include '../_head.php';
?>

<p>
    <button data-get="list.php">← Back to Rewards</button>
</p>

<?php if (empty($arr)): ?>
    <div class="empty-state">
        <span class="emoji">📜</span>
        <p class="title">No redemptions yet</p>
        <p class="hint">Redeem a reward with your points and it will show here.</p>
    </div>
<?php else: ?>
<table class="table">
    <thead>
        <tr>
            <th>Reward</th>
            <th>Points Used</th>
            <th>Date</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($arr as $h): ?>
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:10px;">
                    <img src="/photos/<?= photo_url($h->photo) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:8px;">
                    <?= encode($h->reward_name) ?>
                </div>
            </td>
            <td class="right"><?= number_format($h->points) ?></td>
            <td><?= date('d M Y, H:i', strtotime($h->created_at)) ?></td>
            <td><span class="badge-status success"><?= encode($h->status) ?></span></td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>

<?php include '../_foot.php';
