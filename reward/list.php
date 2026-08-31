<?php
include '../_base.php';

auth('Member');

if (is_post()) {
    $id = req('id');
    $result = redeem_reward($id);

    if (!empty($result['ok'])) {
        temp('info', 'Reward redeemed successfully! Check My Reward History.');
    }
    else {
        temp('info', $result['error'] ?? 'Unable to redeem this reward');
    }
    redirect('list.php');
}

$points = get_user_points($_user->id);

$stm = $_db->query('
    SELECT * FROM reward
    WHERE active = 1
    ORDER BY sort_order ASC, points ASC
');
$rewards = $stm->fetchAll();

$cheapest = null;
foreach ($rewards as $r) {
    if ($r->stock > 0) {
        if ($cheapest === null || $r->points < $cheapest->points) {
            $cheapest = $r;
        }
    }
}

$next = null;
$progress = 100;
$until = 0;
foreach ($rewards as $r) {
    if ($r->stock > 0 && $r->points > $points) {
        $next = $r;
        break;
    }
}
if ($next) {
    $until = $next->points - $points;
    $progress = min(100, round(($points / max(1, $next->points)) * 100));
} elseif ($cheapest && $points >= $cheapest->points) {
    $progress = 100;
}

$_breadcrumbs = ['Dashboard' => '/', 'Rewards' => ''];
$_title = 'Rewards';
include '../_head.php';
?>

<section class="rewards-hero">
    <div class="rewards-hero-inner">
        <p class="rewards-kicker">⭐ Rewards</p>
        <h2 class="rewards-points"><?= number_format($points) ?> <span>pts</span></h2>
        <p class="rewards-sub">Current reward points · <?= rtrim(rtrim(sprintf('%.2f', points_rate()), '0'), '.') ?> pt per RM1 spent</p>

        <div class="rewards-progress">
            <div class="rewards-progress-bar"><span style="width:<?= $progress ?>%"></span></div>
            <?php if ($next): ?>
                <p class="rewards-progress-text"><?= number_format($until) ?> pts until <?= encode($next->name) ?></p>
            <?php elseif ($cheapest): ?>
                <p class="rewards-progress-text">You can redeem <?= encode($cheapest->name) ?> now</p>
            <?php else: ?>
                <p class="rewards-progress-text">Keep earning points with every order</p>
            <?php endif ?>
        </div>

        <p class="rewards-actions">
            <a href="history.php" class="btn-link">My Reward History →</a>
        </p>
    </div>
</section>

<section class="rewards-section">
    <h3>🎁 Available Rewards</h3>
    <p class="section-hint">Redeem your points for exclusive Specialty Coffee &amp; Tea treats.</p>

    <?php if (empty($rewards)): ?>
        <div class="empty-state">
            <span class="emoji">🎁</span>
            <p class="title">No rewards available yet</p>
            <p class="hint">Check back soon for redeemable items.</p>
        </div>
    <?php else: ?>
        <div class="reward-grid">
            <?php foreach ($rewards as $r):
                $can = $points >= $r->points && $r->stock > 0;
            ?>
            <article class="reward-card <?= $can ? '' : 'is-locked' ?>">
                <div class="reward-card-media">
                    <img src="<?= photo_src($r->photo, '0.jpg', 'rewards') ?>" alt="<?= encode($r->name) ?>">
                    <?php if ($r->stock < 1): ?>
                        <span class="reward-badge out">Out of stock</span>
                    <?php elseif ($points < $r->points): ?>
                        <span class="reward-badge need">Need <?= number_format($r->points - $points) ?> more</span>
                    <?php else: ?>
                        <span class="reward-badge ok">Ready to redeem</span>
                    <?php endif ?>
                </div>
                <div class="reward-card-body">
                    <h4><?= encode($r->name) ?></h4>
                    <p><?= encode($r->description) ?></p>
                    <div class="reward-card-meta">
                        <strong><?= number_format($r->points) ?> pts</strong>
                        <span><?= $r->stock ?> left</span>
                    </div>
                    <?php if ($can): ?>
                        <button data-post="?id=<?= $r->id ?>" data-confirm="Redeem <?= encode($r->name) ?> for <?= $r->points ?> points?">Redeem</button>
                    <?php else: ?>
                        <button disabled><?= $r->stock < 1 ? 'Out of Stock' : 'Not Enough Points' ?></button>
                    <?php endif ?>
                </div>
            </article>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</section>

<?php include '../_foot.php';
