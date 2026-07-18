<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');

if (is_post()) {
    $id = req('id');

    // Check if the product is referenced in any orders
    $stm = $_db->prepare('SELECT COUNT(*) FROM item WHERE product_id = ?');
    $stm->execute([$id]);
    $referenced_count = $stm->fetchColumn();

    if ($referenced_count > 0) {
        temp('info', 'Cannot delete this product because it is referenced in ' . $referenced_count . ' order item(s).');
    }
    else {
        // Fetch photo name to clean up file
        $stm = $_db->prepare('SELECT photo FROM product WHERE id = ?');
        $stm->execute([$id]);
        $photo_name = $stm->fetchColumn();

        // Delete from database
        $stm = $_db->prepare('DELETE FROM product WHERE id = ?');
        $stm->execute([$id]);

        audit('Products', 'Product Deleted', "Deleted product ID: $id");

        // Delete photo file if not default
        if ($photo_name && $photo_name !== '0.jpg' && file_exists("../photos/$photo_name")) {
            unlink("../photos/$photo_name");
        }

        temp('info', 'Product deleted successfully');
    }
}

redirect('product_list.php');
