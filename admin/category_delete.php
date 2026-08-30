<?php
include '../_base.php';

auth('Admin');

if (is_post()) {
    $id = req('id');
    // Prevent deletion if products reference this tag
    $stm = $_db->prepare('SELECT COUNT(*) FROM product WHERE tag = (SELECT name FROM category WHERE id = ?)');
    $stm->execute([$id]);
    $count = $stm->fetchColumn();
    if ($count > 0) {
        temp('info', 'Cannot delete category because products reference it.');
    } else {
        $stm = $_db->prepare('DELETE FROM category WHERE id = ?');
        $stm->execute([$id]);
        audit('Categories', 'Category Deleted', "Deleted category id: $id");
        temp('info', 'Category deleted');
    }
}

redirect('category_list.php');
