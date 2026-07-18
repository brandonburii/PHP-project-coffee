<?php
include '_base.php';

// ----------------------------------------------------------------------------

// (1) Input date range
// TODO

if (is_post()) {
    $date = req('date');
    $time = req('time');

    // Validate: date
    if ($date == '') {
        $_err['date'] = 'Required';
    }
    else if (0) { // TODO
        $_err['date'] = 'Invalid date';
    }
    else if (0) { // TODO
        $_err['date'] = "Must between $min to $max";
    }

    // Validate: time
    if ($time == '') {
        $_err['time'] = 'Required';
    }
    else if (0) { // TODO
        $_err['time'] = 'Invalid time';
    }
    else if (0) { // TODO
        $_err['time'] = 'Must between 9am to 5pm';
    }

    if (!$_err) {
        $output = "Date = $date. Time = $time";
    }
}

// ----------------------------------------------------------------------------

$_title = 'Demo 1 : DateTime Input';
include '_head.php';
?>

<form method="post" class="form" novalidate>
    <label for="date">Date</label>
    <!-- (2) Date picker -->
    <?= html_text('date') // TODO ?>
    <?= err('date') ?>

    <label for="time">Time</label>
    <!-- (3) Time picker -->
    <?= html_text('time') // TODO ?>
    <?= err('time') ?>

    <section>
        <button>Submit</button>
        <button type="reset">Reset</button>
    </section>
</form>

<p><?= $output ?? '' ?></p>

<?php
include '_foot.php';