<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');

$total_orders  = (int) $_db->query('SELECT COUNT(*) FROM `order`')->fetchColumn();
$total_revenue = (float) $_db->query('SELECT COALESCE(SUM(total), 0) FROM `order`')->fetchColumn();
$avg_order     = $total_orders > 0 ? $total_revenue / $total_orders : 0;

$monthly = $_db->query("
    SELECT DATE_FORMAT(datetime, '%Y-%m') AS period,
           COUNT(*) AS orders,
           COALESCE(SUM(total), 0) AS revenue,
           COALESCE(AVG(total), 0) AS aov
    FROM `order`
    GROUP BY DATE_FORMAT(datetime, '%Y-%m')
    ORDER BY period ASC
")->fetchAll();

$top = $_db->query("
    SELECT p.id, p.name,
           COALESCE(SUM(i.unit), 0) AS units_sold,
           COALESCE(SUM(i.subtotal), 0) AS revenue
    FROM product p
    JOIN item i ON i.product_id = p.id
    GROUP BY p.id, p.name
    ORDER BY units_sold DESC
    LIMIT 10
")->fetchAll();

audit('Admin', 'Viewed Sales Print Report', 'Opened printable sales summary');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Summary — Specialty Coffee & Tea</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; margin: 32px; }
        h1 { margin: 0 0 4px; color: #5C4033; }
        h2 { color: #5C4033; margin-top: 28px; }
        .meta { color: #666; margin-bottom: 20px; font-size: 14px; }
        .kpis { display: flex; gap: 24px; margin-bottom: 8px; flex-wrap: wrap; }
        .kpi { border: 1px solid #ccc; padding: 12px 18px; border-radius: 6px; min-width: 140px; }
        .kpi b { display: block; font-size: 18px; color: #5C4033; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 8px 10px; text-align: left; font-size: 13px; }
        th { background: #5C4033; color: #F5F1E8; }
        .right { text-align: right; }
        .actions { margin-bottom: 16px; }
        button { padding: 8px 14px; margin-right: 8px; cursor: pointer; }
        @media print {
            .actions { display: none; }
            body { margin: 12px; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button onclick="window.print()">Print / Save as PDF</button>
        <button onclick="location='/admin/reports.php'">Back to Reports</button>
    </div>

    <h1>Sales Summary</h1>
    <div class="meta">Specialty Coffee &amp; Tea · Generated <?= date('Y-m-d H:i') ?></div>

    <div class="kpis">
        <div class="kpi">Total Revenue<b>RM <?= sprintf('%.2f', $total_revenue) ?></b></div>
        <div class="kpi">Total Orders<b><?= $total_orders ?></b></div>
        <div class="kpi">Avg Order Value<b>RM <?= sprintf('%.2f', $avg_order) ?></b></div>
    </div>

    <h2>Monthly Breakdown</h2>
    <?php if (empty($monthly)): ?>
        <p>No orders recorded.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th class="right">Orders</th>
                <th class="right">Revenue (RM)</th>
                <th class="right">Avg Order (RM)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($monthly as $r): ?>
            <tr>
                <td><?= $r->period ?></td>
                <td class="right"><?= $r->orders ?></td>
                <td class="right"><?= sprintf('%.2f', $r->revenue) ?></td>
                <td class="right"><?= sprintf('%.2f', $r->aov) ?></td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
    <?php endif ?>

    <h2>Top Products</h2>
    <?php if (empty($top)): ?>
        <p>No product sales recorded.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th class="right">Units Sold</th>
                <th class="right">Revenue (RM)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($top as $p): ?>
            <tr>
                <td><?= encode($p->id) ?></td>
                <td><?= encode($p->name) ?></td>
                <td class="right"><?= $p->units_sold ?></td>
                <td class="right"><?= sprintf('%.2f', $p->revenue) ?></td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
    <?php endif ?>
</body>
</html>
