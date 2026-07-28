<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');

// ----------------------------------------------------------------------------
// Export inventory (products) to CSV
// ----------------------------------------------------------------------------

$stm = $_db->query('
    SELECT p.id, p.name, p.description, p.price, p.stock, p.photo,
           COALESCE(SUM(i.unit), 0) AS units_sold
    FROM product p
    LEFT JOIN item i ON i.product_id = p.id
    GROUP BY p.id, p.name, p.description, p.price, p.stock, p.photo
    ORDER BY p.id ASC
');
$rows = $stm->fetchAll();

audit('Admin', 'Exported Inventory CSV', 'Downloaded inventory CSV (' . count($rows) . ' row(s))');

$filename = 'inventory_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// UTF-8 BOM for Excel
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'Product ID',
    'Name',
    'Description',
    'Price (RM)',
    'Stock',
    'Units Sold',
    'Stock Status',
    'Photo',
]);

foreach ($rows as $r) {
    $status = 'OK';
    if ($r->stock == 0) {
        $status = 'Out of Stock';
    }
    else if ($r->stock < 5) {
        $status = 'Low Stock';
    }

    fputcsv($out, [
        $r->id,
        $r->name,
        $r->description,
        sprintf('%.2f', $r->price),
        $r->stock,
        $r->units_sold,
        $status,
        $r->photo,
    ]);
}

fclose($out);
exit;
