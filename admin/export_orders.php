<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');

// ----------------------------------------------------------------------------
// Export all orders to CSV
// ----------------------------------------------------------------------------

$stm = $_db->query('
    SELECT o.id, o.datetime, o.count, o.subtotal, o.discount, o.total,
           o.points_earned, o.points_used, o.voucher_code,
           u.name AS member_name, u.email AS member_email
    FROM `order` o
    JOIN user u ON o.user_id = u.id
    ORDER BY o.id ASC
');
$rows = $stm->fetchAll();

audit('Admin', 'Exported Orders CSV', 'Downloaded orders CSV (' . count($rows) . ' row(s))');

$filename = 'orders_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// UTF-8 BOM for Excel
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'Order ID',
    'Datetime',
    'Member Name',
    'Member Email',
    'Items Count',
    'Subtotal (RM)',
    'Discount (RM)',
    'Total (RM)',
    'Points Earned',
    'Points Used',
    'Voucher Code',
]);

foreach ($rows as $r) {
    fputcsv($out, [
        $r->id,
        $r->datetime,
        $r->member_name,
        $r->member_email,
        $r->count,
        sprintf('%.2f', $r->subtotal ?? 0),
        sprintf('%.2f', $r->discount ?? 0),
        sprintf('%.2f', $r->total),
        $r->points_earned ?? 0,
        $r->points_used ?? 0,
        $r->voucher_code ?? '',
    ]);
}

fclose($out);
exit;
