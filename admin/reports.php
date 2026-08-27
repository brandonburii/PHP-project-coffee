<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');

// ----------------------------------------------------------------------------
// KPI summary
// ----------------------------------------------------------------------------

$total_orders  = (int) $_db->query('SELECT COUNT(*) FROM `order`')->fetchColumn();
$total_revenue = (float) $_db->query('SELECT COALESCE(SUM(total), 0) FROM `order`')->fetchColumn();
$avg_order     = $total_orders > 0 ? $total_revenue / $total_orders : 0;
$total_items   = (int) $_db->query('SELECT COALESCE(SUM(count), 0) FROM `order`')->fetchColumn();

// Today / this week / this month
$today_revenue = (float) $_db->query("
    SELECT COALESCE(SUM(total), 0) FROM `order`
    WHERE DATE(datetime) = CURDATE()
")->fetchColumn();

$week_revenue = (float) $_db->query("
    SELECT COALESCE(SUM(total), 0) FROM `order`
    WHERE YEARWEEK(datetime, 1) = YEARWEEK(CURDATE(), 1)
")->fetchColumn();

$month_revenue = (float) $_db->query("
    SELECT COALESCE(SUM(total), 0) FROM `order`
    WHERE YEAR(datetime) = YEAR(CURDATE()) AND MONTH(datetime) = MONTH(CURDATE())
")->fetchColumn();

// Daily sales (last 14 days)
$daily = $_db->query("
    SELECT DATE(datetime) AS period,
           COUNT(*) AS orders,
           COALESCE(SUM(total), 0) AS revenue
    FROM `order`
    WHERE datetime >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    GROUP BY DATE(datetime)
    ORDER BY period ASC
")->fetchAll();

// Weekly sales (last 8 weeks)
$weekly = $_db->query("
    SELECT YEARWEEK(datetime, 1) AS yw,
           MIN(DATE(datetime)) AS period_start,
           MAX(DATE(datetime)) AS period_end,
           COUNT(*) AS orders,
           COALESCE(SUM(total), 0) AS revenue
    FROM `order`
    WHERE datetime >= DATE_SUB(CURDATE(), INTERVAL 56 DAY)
    GROUP BY YEARWEEK(datetime, 1)
    ORDER BY yw ASC
")->fetchAll();

// Monthly sales (last 12 months)
$monthly = $_db->query("
    SELECT DATE_FORMAT(datetime, '%Y-%m') AS period,
           COUNT(*) AS orders,
           COALESCE(SUM(total), 0) AS revenue
    FROM `order`
    WHERE datetime >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY DATE_FORMAT(datetime, '%Y-%m')
    ORDER BY period ASC
")->fetchAll();

// Top products by units sold
$top_products = top_selling_products(5);

// Max revenue for simple bar scaling
$daily_max   = 0;
$weekly_max  = 0;
$monthly_max = 0;
foreach ($daily as $r)   { $daily_max   = max($daily_max,   (float) $r->revenue); }
foreach ($weekly as $r)  { $weekly_max  = max($weekly_max,  (float) $r->revenue); }
foreach ($monthly as $r) { $monthly_max = max($monthly_max, (float) $r->revenue); }

audit('Admin', 'Viewed Sales Reports', 'Viewed sales analytics dashboard');

$_breadcrumbs = [
    'Dashboard' => '/',
    'Sales Reports' => '',
];
$_title = 'Admin | Sales Reports';
include '../_head.php';
?>

<style>
    .bar-track {
        height: 8px;
        background: var(--beige);
        border-radius: 20px;
        overflow: hidden;
        min-width: 80px;
    }
    .bar-fill {
        height: 100%;
        background: var(--coffee);
        border-radius: 20px;
    }
    .export-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 24px;
    }
</style>

<div class="export-row">
    <button data-get="export_orders.php">Export Orders (CSV)</button>
    <button data-get="export_inventory.php">Export Inventory (CSV)</button>
    <button class="secondary" data-get="inventory_print.php">Inventory Report (Print / PDF)</button>
    <button class="secondary" data-get="sales_print.php">Sales Summary (Print / PDF)</button>
</div>

<!-- KPI cards -->
<div class="dashboard-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 28px;">
    <div class="stat-card" style="background:#EAE6DC; border:1px solid #5C4033; padding:18px; border-radius:8px; text-align:center;">
        <h3 style="margin:0; color:#5C4033; font-size:.95rem;">💰 Total Revenue</h3>
        <p style="font-size:1.6rem; font-weight:bold; margin:8px 0 0;">RM <?= sprintf('%.2f', $total_revenue) ?></p>
    </div>
    <div class="stat-card" style="background:#EAE6DC; border:1px solid #5C4033; padding:18px; border-radius:8px; text-align:center;">
        <h3 style="margin:0; color:#5C4033; font-size:.95rem;">🛒 Total Orders</h3>
        <p style="font-size:1.6rem; font-weight:bold; margin:8px 0 0;"><?= $total_orders ?></p>
    </div>
    <div class="stat-card" style="background:#EAE6DC; border:1px solid #5C4033; padding:18px; border-radius:8px; text-align:center;">
        <h3 style="margin:0; color:#5C4033; font-size:.95rem;">📦 Items Sold</h3>
        <p style="font-size:1.6rem; font-weight:bold; margin:8px 0 0;"><?= $total_items ?></p>
    </div>
    <div class="stat-card" style="background:#EAE6DC; border:1px solid #5C4033; padding:18px; border-radius:8px; text-align:center;">
        <h3 style="margin:0; color:#5C4033; font-size:.95rem;">📊 Avg Order Value</h3>
        <p style="font-size:1.6rem; font-weight:bold; margin:8px 0 0;">RM <?= sprintf('%.2f', $avg_order) ?></p>
    </div>
</div>

<div class="dashboard-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 30px;">
    <div class="card" style="text-align:center;">
        <div style="color:var(--muted); font-size:.85rem;">Today</div>
        <div style="font-size:1.3rem; font-weight:700; color:var(--coffee);">RM <?= sprintf('%.2f', $today_revenue) ?></div>
    </div>
    <div class="card" style="text-align:center;">
        <div style="color:var(--muted); font-size:.85rem;">This Week</div>
        <div style="font-size:1.3rem; font-weight:700; color:var(--coffee);">RM <?= sprintf('%.2f', $week_revenue) ?></div>
    </div>
    <div class="card" style="text-align:center;">
        <div style="color:var(--muted); font-size:.85rem;">This Month</div>
        <div style="font-size:1.3rem; font-weight:700; color:var(--coffee);">RM <?= sprintf('%.2f', $month_revenue) ?></div>
    </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:28px; align-items:start;">

    <!-- Daily -->
    <div>
        <h2>Daily Sales <span style="font-size:.85rem; color:var(--muted); font-weight:500;">(Last 14 days)</span></h2>
        <?php if (empty($daily)): ?>
            <div class="empty-state"><span class="emoji">📈</span><p class="title">No daily sales yet</p></div>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th class="right">Orders</th>
                    <th class="right">Revenue (RM)</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daily as $r): ?>
                <?php $pct = $daily_max > 0 ? ($r->revenue / $daily_max * 100) : 0; ?>
                <tr>
                    <td><?= $r->period ?></td>
                    <td class="right"><?= $r->orders ?></td>
                    <td class="right"><?= sprintf('%.2f', $r->revenue) ?></td>
                    <td style="width:120px;"><div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;"></div></div></td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
        <?php endif ?>
    </div>

    <!-- Weekly -->
    <div>
        <h2>Weekly Sales <span style="font-size:.85rem; color:var(--muted); font-weight:500;">(Last 8 weeks)</span></h2>
        <?php if (empty($weekly)): ?>
            <div class="empty-state"><span class="emoji">📈</span><p class="title">No weekly sales yet</p></div>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Week</th>
                    <th class="right">Orders</th>
                    <th class="right">Revenue (RM)</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($weekly as $r): ?>
                <?php $pct = $weekly_max > 0 ? ($r->revenue / $weekly_max * 100) : 0; ?>
                <tr>
                    <td><?= $r->period_start ?> → <?= $r->period_end ?></td>
                    <td class="right"><?= $r->orders ?></td>
                    <td class="right"><?= sprintf('%.2f', $r->revenue) ?></td>
                    <td style="width:120px;"><div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;"></div></div></td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
        <?php endif ?>
    </div>
</div>

<br>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:28px; align-items:start;">

    <!-- Monthly -->
    <div>
        <h2>Monthly Sales <span style="font-size:.85rem; color:var(--muted); font-weight:500;">(Last 12 months)</span></h2>
        <?php if (empty($monthly)): ?>
            <div class="empty-state"><span class="emoji">📈</span><p class="title">No monthly sales yet</p></div>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th class="right">Orders</th>
                    <th class="right">Revenue (RM)</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($monthly as $r): ?>
                <?php $pct = $monthly_max > 0 ? ($r->revenue / $monthly_max * 100) : 0; ?>
                <tr>
                    <td><?= $r->period ?></td>
                    <td class="right"><?= $r->orders ?></td>
                    <td class="right"><?= sprintf('%.2f', $r->revenue) ?></td>
                    <td style="width:120px;"><div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;"></div></div></td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
        <?php endif ?>
    </div>

    <!-- Top products -->
    <div>
        <h2>Top Products <span style="font-size:.85rem; color:var(--muted); font-weight:500;">(by units sold)</span></h2>
        <?php if (empty($top_products) || (int)$top_products[0]->units_sold === 0): ?>
            <div class="empty-state"><span class="emoji">☕</span><p class="title">No sales yet</p></div>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="right">Units</th>
                    <th class="right">Revenue (RM)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_products as $p): ?>
                <?php if ((int)$p->units_sold === 0) continue; ?>
                <tr>
                    <td>
                        <img src="/photos/<?= $p->photo ?>" alt=""
                             style="width:36px;height:36px;object-fit:cover;border-radius:6px;vertical-align:middle;margin-right:8px;border:1px solid var(--line);">
                        <?= encode($p->name) ?>
                    </td>
                    <td class="right"><?= $p->units_sold ?></td>
                    <td class="right"><?= sprintf('%.2f', $p->revenue) ?></td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
        <?php endif ?>
    </div>
</div>

<?php
include '../_foot.php';
