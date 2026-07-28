<?php
include '../_base.php';

// ----------------------------------------------------------------------------

auth('Admin');

if (is_post()) {
    // (1) Delete orders (and items). Reset auto increment
    $_db->exec('DELETE FROM item');
    $_db->exec('DELETE FROM `order`');
    $_db->exec('ALTER TABLE `order` AUTO_INCREMENT = 1');

    audit('Orders', 'Orders Reset', 'Admin reset all orders and items');
    temp('info', 'Orders and items reset successfully');
}

redirect('history.php');

// ----------------------------------------------------------------------------
