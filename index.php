<?php
include '_base.php';

// If user is logged in, show different dashboard based on role
if ($_user) {
    if ($_user->role == 'Admin') {
        // Fetch Admin Dashboard Stats
        $total_members  = $_db->query("SELECT COUNT(*) FROM user WHERE role = 'Member'")->fetchColumn();
        $total_products = $_db->query("SELECT COUNT(*) FROM product")->fetchColumn();
        $total_orders   = $_db->query("SELECT COUNT(*) FROM `order`")->fetchColumn();
        $low_stock      = $_db->query("SELECT COUNT(*) FROM product WHERE stock < 5")->fetchColumn();

        // Recent Orders
        $recent_orders = $_db->query("
            SELECT o.*, u.name as user_name 
            FROM `order` o 
            JOIN user u ON o.user_id = u.id 
            ORDER BY o.id DESC 
            LIMIT 5
        ")->fetchAll();

        // Recent Audit Logs
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
    </p>

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

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start;">
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

        <div>
            <h2>Recent Activities (Audit Logs)</h2>
            <?php if (empty($recent_logs)): ?>
                <p>No recent activities found.</p>
            <?php else: ?>
                <table class="table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>Action</th>
                            <th>User</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $l): ?>
                        <tr>
                            <td><?= encode($l->module) ?></td>
                            <td><a href="/admin/audit_detail.php?id=<?= $l->id ?>" style="color: #5C4033; font-weight: bold;"><?= encode($l->action) ?></a></td>
                            <td><?= $l->username ? encode($l->username) : 'Guest' ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($l->created_at)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($_user->role == 'Member'): ?>
    <!-- Member Dashboard View -->
    <div style="background: #EAE6DC; border: 1px solid #5C4033; padding: 25px; border-radius: 8px; margin-bottom: 30px;">
        <h2 style="margin-top: 0; color: #5C4033;">Welcome back, <?= encode($_user->name) ?>!</h2>
        <p>Explore our premium selection of specialty coffee beans, tea leaves, and brewing accessories.</p>
        <button data-get="/product/list.php">Browse Catalog</button>
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
<?php endif; ?>

<?php
include '_foot.php';