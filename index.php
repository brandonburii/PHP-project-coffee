<?php
include '_base.php';

// Buy Again — add 1 unit to cart (Member only)
if ($_user && $_user->role == 'Member' && is_post() && req('btn') == 'buy_again') {
    $id = req('id');
    if ($id != '' && is_exists($id, 'product', 'id')) {
        $stm = $_db->prepare('SELECT stock FROM product WHERE id = ?');
        $stm->execute([$id]);
        $stock = (int) $stm->fetchColumn();
        $max   = min($stock, 10);

        $cart = get_cart();
        $unit = min(((int) ($cart[$id] ?? 0)) + 1, $max);

        if ($unit >= 1) {
            update_cart($id, $unit);
            audit('Cart', 'Buy Again', "Added product ID $id via Buy Again (qty $unit)");
            temp('info', 'Added to cart');
        }
        else {
            temp('info', 'This product is out of stock');
        }
    }
    redirect();
}

// If user is logged in, show different dashboard based on role
if ($_user) {
    if ($_user->role == 'Admin') {
        // Fetch Admin Dashboard Stats
        $total_members  = $_db->query("SELECT COUNT(*) FROM user WHERE role = 'Member'")->fetchColumn();
        $total_products = $_db->query("SELECT COUNT(*) FROM product")->fetchColumn();
        $total_orders   = $_db->query("SELECT COUNT(*) FROM `order`")->fetchColumn();
        $low_stock      = $_db->query("SELECT COUNT(*) FROM product WHERE stock < 5")->fetchColumn();

        // Loyalty / Rewards statistics
        $rewards_redeemed = 0;
        $points_issued    = 0;
        $active_vouchers  = 0;
        $most_redeemed    = null;
        $low_reward_stock = [];

        try {
            $rewards_redeemed = (int) $_db->query("SELECT COUNT(*) FROM reward_redemption")->fetchColumn();
            $points_issued    = (int) $_db->query("SELECT COALESCE(SUM(points_earned),0) FROM `order`")->fetchColumn();
            $active_vouchers  = (int) $_db->query("
                SELECT COUNT(*) FROM voucher
                WHERE active = 1
                  AND (start_date IS NULL OR start_date <= CURDATE())
                  AND (expiry IS NULL OR expiry >= CURDATE())
            ")->fetchColumn();

            $most_redeemed = $_db->query("
                SELECT r.name, COUNT(*) AS times
                FROM reward_redemption rr
                JOIN reward r ON r.id = rr.reward_id
                GROUP BY rr.reward_id
                ORDER BY times DESC
                LIMIT 1
            ")->fetch();

            $low_reward_stock = $_db->query("
                SELECT id, name, stock, photo, points
                FROM reward
                WHERE active = 1 AND stock < 5
                ORDER BY stock ASC, name ASC
                LIMIT 8
            ")->fetchAll();
        }
        catch (Exception $e) {
            // Loyalty tables may not be imported yet
        }

        // Low stock products (for notification center)
        $low_stock_products = $_db->query("
            SELECT id, name, stock, photo
            FROM product
            WHERE stock < 5
            ORDER BY stock ASC, name ASC
            LIMIT 10
        ")->fetchAll();

        // Recent Orders
        $recent_orders = $_db->query("
            SELECT o.*, u.name as user_name 
            FROM `order` o 
            JOIN user u ON o.user_id = u.id 
            ORDER BY o.id DESC 
            LIMIT 5
        ")->fetchAll();

        // Recent System Activities (Audit Logs)
        $recent_logs = $_db->query("
            SELECT * FROM audit_log
            ORDER BY id DESC
            LIMIT 5
        ")->fetchAll();
    } else {
        // Fetch Member Dashboard Stats
        $recent_orders = $_db->prepare("
            SELECT * FROM `order` 
            WHERE user_id = ? 
            ORDER BY id DESC 
            LIMIT 5
        ");
        $recent_orders->execute([$_user->id]);
        $recent_orders = $recent_orders->fetchAll();

        // Buy Again — products this member ordered before (include out-of-stock)
        $stm = $_db->prepare('
            SELECT p.*, MAX(o.datetime) AS last_bought
            FROM product p
            JOIN item i ON i.product_id = p.id
            JOIN `order` o ON o.id = i.order_id
            WHERE o.user_id = ?
            GROUP BY p.id
            ORDER BY last_bought DESC
            LIMIT 6
        ');
        $stm->execute([$_user->id]);
        $buy_again = $stm->fetchAll();

        // Fresh points for dashboard display
        $stm = $_db->prepare('SELECT points FROM user WHERE id = ?');
        $stm->execute([$_user->id]);
        $_SESSION['user']->points = (int) $stm->fetchColumn();
        $_user = $_SESSION['user'];

        // Loyalty widgets
        $recent_rewards = [];
        $available_rewards = [];
        $featured_reward = null;
        $active_voucher = null;

        try {
            $stm = $_db->prepare('
                SELECT rr.*, r.name AS reward_name, r.photo
                FROM reward_redemption rr
                JOIN reward r ON r.id = rr.reward_id
                WHERE rr.user_id = ?
                ORDER BY rr.created_at DESC
                LIMIT 3
            ');
            $stm->execute([$_user->id]);
            $recent_rewards = $stm->fetchAll();

            $available_rewards = $_db->query('
                SELECT * FROM reward
                WHERE active = 1 AND stock > 0
                ORDER BY sort_order ASC, points ASC
                LIMIT 4
            ')->fetchAll();

            $featured_reward = $_db->query('
                SELECT * FROM reward
                WHERE active = 1 AND stock > 0
                ORDER BY sort_order ASC, points ASC
                LIMIT 1
            ')->fetch();

            $stm = $_db->prepare('
                SELECT v.*
                FROM `order` o
                JOIN voucher v ON v.code = o.voucher_code
                WHERE o.user_id = ? AND o.voucher_code IS NOT NULL
                  AND v.active = 1
                  AND (v.expiry IS NULL OR v.expiry >= CURDATE())
                ORDER BY o.id DESC
                LIMIT 1
            ');
            $stm->execute([$_user->id]);
            $active_voucher = $stm->fetch();
        }
        catch (Exception $e) {
            // Loyalty tables may not be imported yet
        }
    }
}

// Recently viewed products (session)
$recent_products = [];
$recent_ids = get_recent();
if ($recent_ids) {
    $placeholders = implode(',', array_fill(0, count($recent_ids), '?'));
    $stm = $_db->prepare("SELECT * FROM product WHERE id IN ($placeholders)");
    $stm->execute(array_values($recent_ids));
    $map = [];
    foreach ($stm->fetchAll() as $rp) {
        $map[$rp->id] = $rp;
    }
    foreach ($recent_ids as $rid) {
        if (isset($map[$rid])) {
            $recent_products[] = $map[$rid];
        }
    }
}

$_title = 'Dashboard';
$_breadcrumbs = ['Dashboard' => ''];
include '_head.php';
?>

<?php if (!$_user): ?>
    <!-- Guest Login Information Table -->
    <p>Please log in using one of the following demo accounts:</p>
    <table class="table">
        <tr>
            <th>Email</th>
            <th>Password</th>
            <th>Role</th>
        </tr>
        <tr>
            <td>1@gmail.com</td>
            <td>123456</td>
            <td>Admin</td>
        </tr>
        <tr>
            <td>2@gmail.com</td>
            <td>123456</td>
            <td>Member</td>
        </tr>
        <tr>
            <td>3@gmail.com</td>
            <td>123456</td>
            <td>Member</td>
        </tr>
        <tr>
            <td>4@gmail.com</td>
            <td>123456</td>
            <td>Member</td>
        </tr>
    </table>
    <p>
        <button data-get="/login.php">Login</button>
        <button data-get="/user/register.php">Register</button>
        <button class="secondary" data-get="/product/list.php">Browse Products</button>
    </p>

    <?php if (!empty($recent_products)): ?>
        <h2>Recently Viewed</h2>
        <div id="products">
            <?php foreach ($recent_products as $p): ?>
                <?php
                $on_sale  = is_on_sale($p);
                $in_stock = (int) $p->stock > 0;
                $price    = product_price($p);
                ?>
                <div class="product <?= $in_stock ? '' : 'is-soldout' ?>">
                    <div class="thumb">
                        <?php if (!empty($p->tag)): ?>
                            <span class="badge tag-badge"><?= encode($p->tag) ?></span>
                        <?php endif ?>
                        <?php if ($on_sale && $in_stock): ?>
                            <span class="badge sale-badge">SALE</span>
                        <?php endif ?>
                        <img src="<?= photo_src($p->photo) ?>"
                             alt="<?= encode($p->name) ?>"
                             data-get="/product/detail.php?id=<?= $p->id ?>">
                    </div>
                    <div class="info">
                        <div class="name"><?= encode($p->name) ?></div>
                        <div class="price-row">
                            <div class="price">
                                <?php if ($on_sale && $in_stock): ?>
                                    <span class="price-was">RM <?= sprintf('%.2f', $p->price) ?></span>
                                <?php endif ?>
                                RM <?= sprintf('%.2f', $price) ?>
                            </div>
                            <span class="avail <?= $in_stock ? '' : 'out' ?>">
                                <?= $in_stock ? $p->stock . ' available' : 'Unavailable' ?>
                            </span>
                        </div>
                        <button class="secondary" data-get="/product/detail.php?id=<?= $p->id ?>" style="width:100%;">View</button>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif; ?>

<?php elseif ($_user->role == 'Admin'): ?>
    <!-- Admin Dashboard View -->
    <div class="dashboard-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div class="stat-card" style="background: #EAE6DC; border: 1px solid #5C4033; padding: 20px; border-radius: 8px; text-align: center;">
            <h3 style="margin: 0; color: #5C4033;">👥 Members</h3>
            <p style="font-size: 2rem; font-weight: bold; margin: 10px 0 0 0;"><?= $total_members ?></p>
        </div>
        <div class="stat-card" style="background: #EAE6DC; border: 1px solid #5C4033; padding: 20px; border-radius: 8px; text-align: center;">
            <h3 style="margin: 0; color: #5C4033;">📦 Products</h3>
            <p style="font-size: 2rem; font-weight: bold; margin: 10px 0 0 0;"><?= $total_products ?></p>
        </div>
        <div class="stat-card" style="background: #EAE6DC; border: 1px solid #5C4033; padding: 20px; border-radius: 8px; text-align: center;">
            <h3 style="margin: 0; color: #5C4033;">🛒 Orders</h3>
            <p style="font-size: 2rem; font-weight: bold; margin: 10px 0 0 0;"><?= $total_orders ?></p>
        </div>
        <div class="stat-card" style="background: #EAE6DC; border: 1px solid #5C4033; padding: 20px; border-radius: 8px; text-align: center; <?= $low_stock > 0 ? 'border: 2px solid red;' : '' ?>">
            <h3 style="margin: 0; color: <?= $low_stock > 0 ? 'red' : '#5C4033' ?>;">⚠️ Low Stock</h3>
            <p style="font-size: 2rem; font-weight: bold; margin: 10px 0 0 0; color: <?= $low_stock > 0 ? 'red' : 'inherit' ?>;"><?= $low_stock ?></p>
        </div>
    </div>

    <h2>Reward Statistics</h2>
    <div class="dashboard-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="stat-card loyalty-stat">
            <h3>🎁 Redeemed</h3>
            <p><?= number_format($rewards_redeemed) ?></p>
        </div>
        <div class="stat-card loyalty-stat">
            <h3>⭐ Points Issued</h3>
            <p><?= number_format($points_issued) ?></p>
        </div>
        <div class="stat-card loyalty-stat">
            <h3>🎟️ Active Vouchers</h3>
            <p><?= number_format($active_vouchers) ?></p>
        </div>
        <div class="stat-card loyalty-stat">
            <h3>🏆 Top Reward</h3>
            <p style="font-size:1.05rem;"><?= $most_redeemed ? encode($most_redeemed->name) : '—' ?></p>
            <?php if ($most_redeemed): ?>
                <small><?= (int) $most_redeemed->times ?> redemptions</small>
            <?php endif ?>
        </div>
    </div>

    <?php if (!empty($low_reward_stock)): ?>
    <div class="card" style="margin-bottom: 30px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px;">
            <h2 style="margin:0;">Low Reward Stock</h2>
            <button class="secondary" data-get="/admin/reward_list.php">Manage Rewards</button>
        </div>
        <div style="display:flex; flex-direction:column; gap:8px;">
            <?php foreach ($low_reward_stock as $lr): ?>
            <a href="/admin/reward_edit.php?id=<?= (int) $lr->id ?>"
               style="display:flex; align-items:center; gap:14px; padding:10px 12px; border:1px solid var(--line); border-radius:10px; text-decoration:none; color:inherit; background:#fff;">
                <img src="<?= photo_src($lr->photo) ?>" alt=""
                     style="width:42px; height:42px; object-fit:cover; border-radius:8px; border:1px solid var(--line);">
                <div style="flex:1; min-width:0;">
                    <div style="font-weight:600; color:var(--coffee-dark);"><?= encode($lr->name) ?></div>
                    <div style="font-size:.82rem; color:var(--muted);"><?= (int) $lr->points ?> pts</div>
                </div>
                <span class="badge-status <?= $lr->stock == 0 ? 'danger' : 'process' ?>">
                    <?= $lr->stock == 0 ? 'Out of stock' : ($lr->stock . ' left') ?>
                </span>
            </a>
            <?php endforeach ?>
        </div>
    </div>
    <?php endif ?>

    <!-- Low Stock Notification Center -->
    <div class="card" style="margin-bottom: 30px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px;">
            <h2 style="margin:0;">⚠ Low Stock Notifications</h2>
            <button class="secondary" data-get="/admin/stock_history.php">Order History</button>
        </div>

        <?php if (empty($low_stock_products)): ?>
            <p style="color:var(--muted); margin:0;">All products are sufficiently stocked.</p>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:8px;">
                <?php foreach ($low_stock_products as $lp): ?>
                <a href="/admin/product_edit.php?id=<?= urlencode($lp->id) ?>"
                   style="display:flex; align-items:center; gap:14px; padding:10px 12px; border:1px solid var(--line); border-radius:10px; text-decoration:none; color:inherit; background:#fff;">
                    <img src="<?= photo_src($lp->photo) ?>" alt=""
                         style="width:42px; height:42px; object-fit:cover; border-radius:8px; border:1px solid var(--line);">
                    <div style="flex:1; min-width:0;">
                        <div style="font-weight:600; color:var(--coffee-dark);"><?= encode($lp->name) ?></div>
                        <div style="font-size:.82rem; color:var(--muted);"><?= encode($lp->id) ?></div>
                    </div>
                    <span class="badge-status <?= $lp->stock == 0 ? 'danger' : 'process' ?>">
                        <?= $lp->stock == 0 ? 'Out of stock' : ($lp->stock . ' left') ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 30px; margin-top: 30px;">
        <!-- Recent Orders Column -->
        <div>
            <h2>Recent Orders</h2>
            <?php if (empty($recent_orders)): ?>
                <p>No recent orders found.</p>
            <?php else: ?>
                <table class="table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Member</th>
                            <th>Total (RM)</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_orders as $o): ?>
                        <tr>
                            <td><a href="/order/detail.php?id=<?= $o->id ?>" style="color: #5C4033; font-weight: bold;"><?= $o->id ?></a></td>
                            <td><?= encode($o->user_name) ?></td>
                            <td class="right"><?= sprintf('%.2f', $o->total) ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($o->datetime)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Recent System Activities Column -->
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0;">Recent System Activities</h2>
                <button class="secondary" data-get="/admin/audit_log.php" style="font-size: 0.8rem; padding: 4px 10px;">View All</button>
            </div>
            <?php if (empty($recent_logs)): ?>
                <p style="color: var(--muted);">No recent system activities.</p>
            <?php else: ?>
                <table class="table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $l): ?>
                        <tr>
                            <td>
                                <b><?= encode($l->username ?: 'Guest') ?></b>
                                <div style="font-size: 0.75rem; color: var(--muted);"><?= encode($l->role ?: 'Guest') ?></div>
                            </td>
                            <td>
                                <a href="/admin/audit_detail.php?id=<?= $l->id ?>" style="color: #5C4033; font-weight: bold;"><?= encode($l->action) ?></a>
                                <?php if (!empty($l->description)): ?>
                                    <div style="font-size: 0.75rem; color: var(--muted);" title="<?= encode($l->description) ?>">
                                        <?= encode(strlen($l->description) > 40 ? substr($l->description, 0, 37) . '...' : $l->description) ?>
                                    </div>
                                <?php endif ?>
                            </td>
                            <td><?= encode($l->module) ?></td>
                            <td>
                                <div style="font-size: 0.85rem; white-space: nowrap;"><?= date('d M Y', strtotime($l->created_at)) ?></div>
                                <div style="font-size: 0.75rem; color: var(--muted);"><?= date('h:i A', strtotime($l->created_at)) ?></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($_user->role == 'Member'): ?>
    <!-- Member Dashboard View -->
    <div style="background: #EAE6DC; border: 1px solid #5C4033; padding: 25px; border-radius: 8px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap;">
        <div>
            <h2 style="margin-top: 0; color: #5C4033;">Welcome back, <?= encode($_user->name) ?>!</h2>
            <p>Explore our premium selection of specialty coffee beans, tea leaves, and brewing accessories.</p>
            <button data-get="/product/list.php">Browse Catalog</button>
        </div>
        <div style="text-align: center; background: #FFF; border: 1px solid #5C4033; border-radius: 12px; padding: 16px 26px;">
            <div style="font-size: 1.8rem;">⭐</div>
            <div style="font-size: 1.8rem; font-weight: 700; color: #5C4033;"><?= number_format($_user->points ?? 0) ?></div>
            <div style="color: #8A7B6C; font-size: .8rem;">Reward Points</div>
            <button class="secondary" data-get="/reward/list.php" style="margin-top:10px;">View Rewards</button>
        </div>
    </div>

    <div class="loyalty-member-grid">
        <?php if ($featured_reward): ?>
        <div class="card loyalty-featured">
            <h3 style="margin-top:0;">Featured Reward</h3>
            <div style="display:flex; gap:14px; align-items:center;">
                <img src="<?= photo_src($featured_reward->photo) ?>" alt=""
                     style="width:72px;height:72px;object-fit:cover;border-radius:12px;border:1px solid var(--line);">
                <div>
                    <div style="font-weight:700;color:var(--coffee-dark);"><?= encode($featured_reward->name) ?></div>
                    <div style="color:var(--muted);font-size:.9rem;"><?= number_format($featured_reward->points) ?> pts · <?= (int) $featured_reward->stock ?> left</div>
                    <button data-get="/reward/list.php" style="margin-top:8px;">Redeem</button>
                </div>
            </div>
        </div>
        <?php endif ?>

        <div class="card">
            <h3 style="margin-top:0;">Recent Rewards</h3>
            <?php if (empty($recent_rewards)): ?>
                <p style="color:var(--muted);margin:0;">No redemptions yet.</p>
            <?php else: ?>
                <ul class="loyalty-list">
                    <?php foreach ($recent_rewards as $rr): ?>
                    <li>
                        <span><?= encode($rr->reward_name) ?></span>
                        <span><?= number_format($rr->points) ?> pts</span>
                    </li>
                    <?php endforeach ?>
                </ul>
                <p style="margin:10px 0 0;"><a href="/reward/history.php">My Reward History →</a></p>
            <?php endif ?>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Available Rewards</h3>
            <?php if (empty($available_rewards)): ?>
                <p style="color:var(--muted);margin:0;">Coming soon.</p>
            <?php else: ?>
                <ul class="loyalty-list">
                    <?php foreach ($available_rewards as $ar): ?>
                    <li>
                        <span><?= encode($ar->name) ?></span>
                        <span><?= number_format($ar->points) ?> pts</span>
                    </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Your Voucher</h3>
            <?php if ($active_voucher): ?>
                <div class="voucher-badge"><?= encode($active_voucher->code) ?></div>
                <p style="margin:8px 0 0; color:var(--muted); font-size:.9rem;">
                    <?= encode($active_voucher->description ?? '') ?><br>
                    <?= !empty($active_voucher->expiry) ? 'Expires ' . $active_voucher->expiry : 'Never expires' ?>
                </p>
            <?php else: ?>
                <p style="color:var(--muted);margin:0;">No recent active voucher. Enter a code at checkout.</p>
            <?php endif ?>
        </div>
    </div>

    <h2>Your Recent Orders</h2>
    <?php if (empty($recent_orders)): ?>
        <p>You haven't placed any orders yet. <a href="/product/list.php" style="color: #5C4033; font-weight: bold;">Shop now!</a></p>
    <?php else: ?>
        <table class="table" style="width: 100%;">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Date</th>
                    <th>Items Count</th>
                    <th>Total (RM)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_orders as $o): ?>
                <tr>
                    <td><a href="/order/detail.php?id=<?= $o->id ?>" style="color: #5C4033; font-weight: bold;"><?= $o->id ?></a></td>
                    <td><?= date('Y-m-d H:i', strtotime($o->datetime)) ?></td>
                    <td class="right"><?= $o->count ?></td>
                    <td class="right"><?= sprintf('%.2f', $o->total) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if (!empty($buy_again)): ?>
        <h2 style="margin-top: 36px;">Buy Again</h2>
        <p style="color:var(--muted); margin-top:-8px;">Products you purchased before — one click to add them back.</p>
        <div id="products">
            <?php foreach ($buy_again as $p): ?>
                <?php
                $on_sale  = is_on_sale($p);
                $in_stock = (int) $p->stock > 0;
                $price    = product_price($p);
                ?>
                <div class="product <?= $in_stock ? '' : 'is-soldout' ?>">
                    <div class="thumb">
                        <?php if (!empty($p->tag)): ?>
                            <span class="badge tag-badge"><?= encode($p->tag) ?></span>
                        <?php endif ?>
                        <?php if ($on_sale && $in_stock): ?>
                            <span class="badge sale-badge">SALE</span>
                        <?php endif ?>
                        <img src="<?= photo_src($p->photo) ?>"
                             alt="<?= encode($p->name) ?>"
                             data-get="/product/detail.php?id=<?= $p->id ?>">
                    </div>
                    <div class="info">
                        <div class="name"><?= encode($p->name) ?></div>
                        <div class="meta-line">
                            Last bought <?= date('Y-m-d', strtotime($p->last_bought)) ?>
                        </div>
                        <div class="price-row">
                            <div class="price">
                                <?php if ($on_sale && $in_stock): ?>
                                    <span class="price-was">RM <?= sprintf('%.2f', $p->price) ?></span>
                                <?php endif ?>
                                RM <?= sprintf('%.2f', $price) ?>
                            </div>
                            <span class="avail <?= $in_stock ? '' : 'out' ?>">
                                <?= $in_stock ? $p->stock . ' available' : 'Unavailable' ?>
                            </span>
                        </div>
                        <?php if ($in_stock): ?>
                            <form method="post" class="actions ajax-cart" data-cart-mode="add">
                                <input type="hidden" name="id" value="<?= encode($p->id) ?>">
                                <input type="hidden" name="unit" value="1">
                                <button type="submit" style="width:100%;">Buy Again</button>
                            </form>
                        <?php else: ?>
                            <div class="actions">
                                <button type="button" disabled style="width:100%;">Sold Out</button>
                            </div>
                        <?php endif ?>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($recent_products)): ?>
        <h2 style="margin-top: 36px;">Recently Viewed</h2>
        <div id="products">
            <?php foreach ($recent_products as $p): ?>
                <?php
                $on_sale  = is_on_sale($p);
                $in_stock = (int) $p->stock > 0;
                $price    = product_price($p);
                ?>
                <div class="product <?= $in_stock ? '' : 'is-soldout' ?>">
                    <div class="thumb">
                        <?php if (!empty($p->tag)): ?>
                            <span class="badge tag-badge"><?= encode($p->tag) ?></span>
                        <?php endif ?>
                        <?php if ($on_sale && $in_stock): ?>
                            <span class="badge sale-badge">SALE</span>
                        <?php endif ?>
                        <img src="<?= photo_src($p->photo) ?>"
                             alt="<?= encode($p->name) ?>"
                             data-get="/product/detail.php?id=<?= $p->id ?>">
                    </div>
                    <div class="info">
                        <div class="name"><?= encode($p->name) ?></div>
                        <div class="price-row">
                            <div class="price">
                                <?php if ($on_sale && $in_stock): ?>
                                    <span class="price-was">RM <?= sprintf('%.2f', $p->price) ?></span>
                                <?php endif ?>
                                RM <?= sprintf('%.2f', $price) ?>
                            </div>
                            <span class="avail <?= $in_stock ? '' : 'out' ?>">
                                <?= $in_stock ? $p->stock . ' available' : 'Unavailable' ?>
                            </span>
                        </div>
                        <button class="secondary" data-get="/product/detail.php?id=<?= $p->id ?>" style="width:100%;">View</button>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php
include '_foot.php';
?>