<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');

if (is_post()) {
    $code = req('code');

    if (is_exists($code, 'voucher', 'code')) {
        $stm = $_db->prepare('DELETE FROM voucher WHERE code = ?');
        $stm->execute([$code]);

        audit('Vouchers', 'Voucher Deleted', "Deleted voucher: $code");

        temp('info', 'Voucher deleted successfully');
    }
}

redirect('voucher_list.php');
