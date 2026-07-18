<?php
include '_base.php';

// ----------------------------------------------------------------------------

if (is_post()) {
    // (1) Truncate table
    // TODO

    $stm = $_db->prepare('INSERT INTO holiday (date, name) VALUES (?, ?)');
    $n = 0;

    // (2) Insert records from CSV file
    // TODO

    temp('info', "$n record(s) restored");
}

redirect('demo3.php');

// ----------------------------------------------------------------------------
