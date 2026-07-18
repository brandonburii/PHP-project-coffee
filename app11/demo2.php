<?php
include '_base.php';

// ----------------------------------------------------------------------------

if (is_get()) {
    // (1) Default date
    // TODO
}

if (is_post()) {
    $date = req('date');
    $name = req('name');

    // Validate: date
    if ($date == '') {
        $_err['date'] = 'Required';
    }
    else if (!is_date($date)) {
        $_err['date'] = 'Invalid date';
    }

    // Validate: name
    if ($name == '') {
        $_err['name'] = 'Required';
    }
    else if (strlen($name) > 100) {
        $_err['name'] = 'Maximum 100 characters';
    }

    if (!$_err) {
        $stm = $_db->prepare('INSERT INTO holiday (date, name) VALUES (?, ?)');
        $stm->execute([$date, $name]);
        temp('info', 'Record inserted');
        redirect();
    }
}

// ----------------------------------------------------------------------------

$_title = 'Demo 2 : Holiday Insert';
include '_head.php';
?>

<form class="form" method="post" novalidate>
    <label for="date">Date</label>
    <?= html_date('date') ?>
    <?= err('date') ?>

    <label for="name">Name</label>
    <?= html_text('name', 'maxlength="100"') ?>
    <?= err('name') ?>

    <section>
        <button>Submit</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php
include '_foot.php';