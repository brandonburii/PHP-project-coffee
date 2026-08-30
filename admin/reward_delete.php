<?php
include '../_base.php';

auth('Admin');

if (is_post()) {
    $id = req('id');

    $stm = $_db->prepare('SELECT COUNT(*) FROM reward_redemption WHERE reward_id = ?');
    $stm->execute([$id]);
    if ($stm->fetchColumn() > 0) {
        temp('info', 'Cannot delete — this reward has redemption history. Disable it instead.');
    }
    else {
        $stm = $_db->prepare('SELECT * FROM reward WHERE id = ?');
        $stm->execute([$id]);
        $old = $stm->fetch();
        $photo = $old->photo ?? null;

        $stm = $_db->prepare('DELETE FROM reward WHERE id = ?');
        $stm->execute([$id]);

        if ($photo && $photo !== '0.jpg' && file_exists("../photos/$photo")) {
            unlink("../photos/$photo");
        }

        audit(
            'Rewards',
            'Reward Deleted',
            "Deleted reward ID $id",
            $old ? (array) $old : ['id' => $id],
            null
        );
        temp('info', 'Reward deleted successfully');
    }
}

redirect('reward_list.php');
