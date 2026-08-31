<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?? 'Untitled' ?></title>
    <link rel="shortcut icon" href="/images/icon.png">
    
    <!-- ============================================
         EXTERNAL CSS
         ============================================ -->
    <!-- Swiper.js CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    
    <!-- Your main CSS -->
    <link rel="stylesheet" href="/css/app.css">
    
    
    <!--EXTERNAL JS-->
    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    
    <!-- Swiper.js -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    
    <!-- Your main JS -->
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
                <img src="<?= photo_src($_user->photo) ?>" class="avatar" style="width: 36px; height: 36px; border-radius: 5px; border: 1px solid #333; object-fit: cover;">
                <a href="/logout.php" style="color: #F5F1E8; text-decoration: underline; font-weight: bold;">Logout</a>
            </div>
        <?php endif ?>
    </header>

    <div id="wrapper">
        <nav<?= $_user?->role == 'Admin' ? ' class="nav-admin"' : '' ?>>
            <?php if ($_user?->role == 'Admin'):
                $nav_current = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
                $nav_chevron = "<svg class='nav-chevron' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' aria-hidden='true'><polyline points='6 9 12 15 18 9'></polyline></svg>";
                $nav_groups = [
                    'management' => ['/admin/member_', '/admin/admin_'],
                    'catalog'    => ['/admin/product_', '/admin/category_'],
                    'inventory'  => ['/admin/stock_', '/admin/order_stock'],
                    'sales'      => ['/order/', '/admin/reports'],
                    'marketing'  => ['/admin/voucher_', '/admin/reward_'],
                    'system'     => ['/admin/audit_'],
                ];
                $nav_open = [];
                foreach ($nav_groups as $g => $prefixes) {
                    $nav_open[$g] = false;
                    foreach ($prefixes as $prefix) {
                        if (str_starts_with($nav_current, $prefix)) {
                            $nav_open[$g] = true;
                            break;
                        }
                    }
                }
            ?>
                <a href="/" class="<?= is_active('/') ?>"><?= icon('dashboard') ?><span>Dashboard</span></a>

                <details class="nav-group" data-group="management"<?= $nav_open['management'] ? ' open data-force-open="1"' : '' ?>>
                    <summary>Management <?= $nav_chevron ?></summary>
                    <div class="nav-group-body">
                        <div class="nav-group-inner">
                            <a href="/admin/member_list.php" class="<?= is_active_match(['/admin/member_']) ?>"><?= icon('members') ?><span>Member Maintenance</span></a>
                            <a href="/admin/admin_list.php" class="<?= is_active_match(['/admin/admin_']) ?>"><?= icon('members') ?><span>Admin Management</span></a>
                        </div>
                    </div>
                </details>

                <details class="nav-group" data-group="catalog"<?= $nav_open['catalog'] ? ' open data-force-open="1"' : '' ?>>
                    <summary>Catalog <?= $nav_chevron ?></summary>
                    <div class="nav-group-body">
                        <div class="nav-group-inner">
                            <a href="/admin/product_list.php" class="<?= is_active_match(['/admin/product_']) ?>"><?= icon('products') ?><span>Product Maintenance</span></a>
                            <a href="/admin/category_list.php" class="<?= is_active_match(['/admin/category_']) ?>"><?= icon('products') ?><span>Category Maintenance</span></a>
                        </div>
                    </div>
                </details>

                <details class="nav-group" data-group="inventory"<?= $nav_open['inventory'] ? ' open data-force-open="1"' : '' ?>>
                    <summary>Inventory <?= $nav_chevron ?></summary>
                    <div class="nav-group-body">
                        <div class="nav-group-inner">
                            <a href="/admin/stock_history.php" class="<?= is_active_match(['/admin/stock_']) ?>"><?= icon('stock') ?><span>Stock History</span></a>
                            <a href="/admin/order_stock.php" class="<?= is_active_match(['/admin/order_stock']) ?>"><?= icon('stock') ?><span>Order Stock</span></a>
                        </div>
                    </div>
                </details>

                <details class="nav-group" data-group="sales"<?= $nav_open['sales'] ? ' open data-force-open="1"' : '' ?>>
                    <summary>Sales &amp; Orders <?= $nav_chevron ?></summary>
                    <div class="nav-group-body">
                        <div class="nav-group-inner">
                            <a href="/order/history.php" class="<?= is_active_match(['/order/history.php', '/order/detail.php']) ?>"><?= icon('orders') ?><span>Order Management</span></a>
                            <a href="/admin/reports.php" class="<?= is_active_match(['/admin/reports']) ?>"><?= icon('reports') ?><span>Sales Reports</span></a>
                        </div>
                    </div>
                </details>

                <details class="nav-group" data-group="marketing"<?= $nav_open['marketing'] ? ' open data-force-open="1"' : '' ?>>
                    <summary>Marketing <?= $nav_chevron ?></summary>
                    <div class="nav-group-body">
                        <div class="nav-group-inner">
                            <a href="/admin/voucher_list.php" class="<?= is_active_match(['/admin/voucher_']) ?>"><?= icon('voucher') ?><span>Voucher Maintenance</span></a>
                            <a href="/admin/reward_list.php" class="<?= is_active_match(['/admin/reward_']) ?>"><?= icon('rewards') ?><span>Reward Maintenance</span></a>
                        </div>
                    </div>
                </details>

                <details class="nav-group" data-group="system"<?= $nav_open['system'] ? ' open data-force-open="1"' : '' ?>>
                    <summary>System <?= $nav_chevron ?></summary>
                    <div class="nav-group-body">
                        <div class="nav-group-inner">
                            <a href="/admin/audit_log.php" class="<?= is_active_match(['/admin/audit_']) ?>"><?= icon('reports') ?><span>Audit Log</span></a>
                        </div>
                    </div>
                </details>

                <div class="nav-footer">
                    <a href="/user/profile.php" class="<?= is_active('/user/profile.php') ?>"><?= icon('profile') ?><span>Profile</span></a>
                    <a href="/logout.php"><?= icon('logout') ?><span>Logout</span></a>
                </div>
            <?php elseif ($_user?->role == 'Member'): ?>
                <a href="/" class="<?= is_active('/') ?>"><?= icon('home') ?><span>Home</span></a>
                <a href="/product/list.php" class="<?= is_active('/product/list.php') ?>"><?= icon('products') ?><span>Products</span></a>
                <a href="/product/wishlist.php" class="<?= is_active('/product/wishlist.php') ?>"><?= icon('wishlist') ?><span>Wishlist</span></a>
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
                <a href="/product/wishlist.php" class="<?= is_active('/product/wishlist.php') ?>"><?= icon('wishlist') ?><span>Wishlist</span></a>
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