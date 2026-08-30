<?php
include '../_base.php';

auth('Admin');

if (is_post()) {
    $id = (int) req('id');
    // Do not allow deletion of self or last admin
    $self = $_SESSION['user']->id ?? null;

    $stm = $_db->prepare("SELECT COUNT(*) FROM user WHERE role = 'Admin'");
    $stm->execute();
    $total_admins = (int) $stm->fetchColumn();

    if ($id === $self) {
        temp('info', 'Cannot delete yourself.');
    } else if ($total_admins <= 1) {
        temp('info', 'Cannot delete the last admin account.');
    } else {
        $stm = $_db->prepare('DELETE FROM user WHERE id = ? AND role = ?');
        $stm->execute([$id, 'Admin']);
        audit('Admin', 'Admin Deleted', "Deleted admin id: $id");
        temp('info', 'Admin deleted');
    }
}

redirect('admin_list.php');
