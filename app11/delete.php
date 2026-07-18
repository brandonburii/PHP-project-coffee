<?php
include '_base.php';

// ----------------------------------------------------------------------------

if (is_post()) {
    // (1) Handle multiple ids
    // TODO
    $id = req('id');

    $stm = $_db->prepare('DELETE FROM holiday WHERE id = ?');
    $n = 0;

    // (2) Delete mutiple records
    // TODO    
    $n += $stm->execute([$id]);
    
    temp('info', "$n record(s) deleted");
}

redirect('demo3.php');

// ----------------------------------------------------------------------------
