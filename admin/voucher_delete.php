<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');

if (is_post()) {
    $code = req('code');

    if (is_exists($code, 'voucher', 'code')) {
        $stm = $_db->prepare('SELECT * FROM voucher WHERE code = ?');
        $stm->execute([$code]);
        $old = $stm->fetch();

        $stm = $_db->prepare('DELETE FROM voucher WHERE code = ?');
        $stm->execute([$code]);

        audit(
            'Vouchers',
            'Voucher Deleted',
            "Deleted voucher: $code",
            $old ? (array) $old : ['code' => $code],
            null
        );

        temp('info', 'Voucher deleted successfully');
    }
}

redirect('voucher_list.php');
