<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');

$products = $_db->query('
    SELECT p.id, p.name, p.price, p.stock,
           COALESCE(SUM(i.unit), 0) AS units_sold
    FROM product p
    LEFT JOIN item i ON i.product_id = p.id
    GROUP BY p.id, p.name, p.price, p.stock
    ORDER BY p.id ASC
')->fetchAll();

$low = 0;
$out = 0;
foreach ($products as $p) {
    if ($p->stock == 0) $out++;
    else if ($p->stock < 5) $low++;
}

audit('Admin', 'Viewed Inventory Print Report', 'Opened printable inventory report');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Report — Specialty Coffee & Tea</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; margin: 32px; }
        h1 { margin: 0 0 4px; color: #5C4033; }
        .meta { color: #666; margin-bottom: 20px; font-size: 14px; }
        .summary { margin-bottom: 18px; }
        .summary span { margin-right: 18px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 8px 10px; text-align: left; font-size: 13px; }
        th { background: #5C4033; color: #F5F1E8; }
        .right { text-align: right; }
        .low { color: #9A6A16; font-weight: bold; }
        .out { color: #B23A48; font-weight: bold; }
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

    <h1>Inventory Report</h1>
    <div class="meta">Specialty Coffee &amp; Tea · Generated <?= date('Y-m-d H:i') ?></div>

    <div class="summary">
        <span><b>Products:</b> <?= count($products) ?></span>
        <span><b>Low Stock (&lt;5):</b> <?= $low ?></span>
        <span><b>Out of Stock:</b> <?= $out ?></span>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th class="right">Price (RM)</th>
                <th class="right">Stock</th>
                <th class="right">Units Sold</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
            <?php
                $cls = '';
                $status = 'OK';
                if ($p->stock == 0) { $cls = 'out'; $status = 'Out of Stock'; }
                else if ($p->stock < 5) { $cls = 'low'; $status = 'Low Stock'; }
            ?>
            <tr>
                <td><?= encode($p->id) ?></td>
                <td><?= encode($p->name) ?></td>
                <td class="right"><?= sprintf('%.2f', $p->price) ?></td>
                <td class="right <?= $cls ?>"><?= $p->stock ?></td>
                <td class="right"><?= $p->units_sold ?></td>
                <td class="<?= $cls ?>"><?= $status ?></td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</body>
</html>
