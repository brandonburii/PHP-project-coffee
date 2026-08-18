<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?? 'Untitled' ?></title>
    <link rel="shortcut icon" href="/images/favicon.png">
    <link rel="stylesheet" href="/css/app.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="/js/app.js"></script>
</head>
<body>
    <!-- Flash message -->
    <div id="info"><?= temp('info') ?></div>

    <header>
        <h1><a href="/">Specialty Coffee & Tea</a></h1>

        <?php if ($_user): ?>
            <div class="user-info" style="display: flex; align-items: center; gap: 10px;">
                <span>Welcome, <b><?= encode($_user->name) ?></b> (<?= $_user->role ?>)</span>
                <img src="/photos/<?= $_user->photo ?>" class="avatar" style="width: 36px; height: 36px; border-radius: 5px; border: 1px solid #333; object-fit: cover;">
                <a href="/logout.php" style="color: #F5F1E8; text-decoration: underline; font-weight: bold;">Logout</a>
            </div>
        <?php endif ?>
    </header>

    <div id="wrapper">
        <nav>
            <?php if ($_user?->role == 'Admin'): ?>
                <a href="/" class="<?= is_active('/') ?>"><?= icon('dashboard') ?><span>Dashboard</span></a>
                <a href="/admin/member_list.php" class="<?= is_active('/admin/member_list.php') ?>"><?= icon('members') ?><span>Member Maintenance</span></a>
                <a href="/admin/admin_list.php" class="<?= is_active('/admin/admin_list.php') ?>"><?= icon('members') ?><span>Admin Management</span></a>
                <a href="/admin/product_list.php" class="<?= is_active('/admin/product_list.php') ?>"><?= icon('products') ?><span>Product Maintenance</span></a>
                <a href="/admin/stock_history.php" class="<?= is_active('/admin/stock_history.php') ?>"><?= icon('stock') ?><span>Stock History</span></a>
                <a href="/admin/voucher_list.php" class="<?= is_active('/admin/voucher_list.php') ?>"><?= icon('voucher') ?><span>Voucher Maintenance</span></a>
                <a href="/admin/reward_list.php" class="<?= is_active('/admin/reward_list.php') ?>"><?= icon('rewards') ?><span>Reward Maintenance</span></a>
                <a href="/order/history.php" class="<?= is_active('/order/history.php') ?>"><?= icon('orders') ?><span>Order Management</span></a>
                <a href="/admin/audit_log.php" class="<?= is_active('/admin/audit_log.php') ?>"><?= icon('reports') ?><span>Audit Log</span></a>
                <a href="/admin/reports.php" class="<?= is_active('/admin/reports.php') ?>"><?= icon('reports') ?><span>Sales Reports</span></a>
                <a href="/user/profile.php" class="<?= is_active('/user/profile.php') ?>"><?= icon('profile') ?><span>Profile</span></a>
                <a href="/logout.php"><?= icon('logout') ?><span>Logout</span></a>
            <?php elseif ($_user?->role == 'Member'): ?>
                <a href="/" class="<?= is_active('/') ?>"><?= icon('home') ?><span>Home</span></a>
                <a href="/product/list.php" class="<?= is_active('/product/list.php') ?>"><?= icon('products') ?><span>Products</span></a>
                <a href="/order/cart.php" class="<?= is_active('/order/cart.php') ?>">
                    <?= icon('cart') ?><span>Shopping Cart</span>
                    <?php
                        $cart = get_cart();
                        $count = count($cart);
                        if ($count) echo "<span class='nav-badge'>$count</span>";
                    ?>
                </a>
                <a href="/order/history.php" class="<?= is_active('/order/history.php') ?>"><?= icon('orders') ?><span>Order History</span></a>
                <a href="/reward/list.php" class="<?= is_active('/reward/list.php') ?>"><?= icon('rewards') ?><span>Rewards</span></a>
                <a href="/user/profile.php" class="<?= is_active('/user/profile.php') ?>"><?= icon('profile') ?><span>Profile</span></a>
                <a href="/logout.php"><?= icon('logout') ?><span>Logout</span></a>
            <?php else: ?>
                <a href="/" class="<?= is_active('/') ?>"><?= icon('home') ?><span>Home</span></a>
                <a href="/product/list.php" class="<?= is_active('/product/list.php') ?>"><?= icon('products') ?><span>Products</span></a>
                <a href="/order/cart.php" class="<?= is_active('/order/cart.php') ?>">
                    <?= icon('cart') ?><span>Shopping Cart</span>
                    <?php
                        $cart = get_cart();
                        $count = count($cart);
                        if ($count) echo "<span class='nav-badge'>$count</span>";
                    ?>
                </a>
                <a href="/user/register.php" class="<?= is_active('/user/register.php') ?>"><?= icon('register') ?><span>Register</span></a>
                <a href="/login.php" class="<?= is_active('/login.php') ?>"><?= icon('login') ?><span>Login</span></a>
            <?php endif ?>
        </nav>

        <main>
            <?php if (isset($_breadcrumbs) && is_array($_breadcrumbs)): ?>
                <div class="breadcrumbs" style="font-size: 0.9rem; color: #666; margin-bottom: 15px;">
                    <?php 
                    $crumbs = [];
                    foreach ($_breadcrumbs as $label => $link) {
                        if ($link) {
                            $crumbs[] = "<a href='$link' style='color: #5C4033; text-decoration: none; font-weight: bold;'>$label</a>";
                        } else {
                            $crumbs[] = "<span style='color: #333;'>$label</span>";
                        }
                    }
                    echo implode(' &gt; ', $crumbs);
                    ?>
                </div>
            <?php endif ?>
            <h1><?= $_title ?? 'Untitled' ?></h1>