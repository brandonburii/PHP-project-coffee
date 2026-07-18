<?php
include '_base.php';

// ----------------------------------------------------------------------------

$items = [
    'APP' => 'Apple',
    'BNN' => 'Banana',
    'CCN' => 'Coconut',
    'DRN' => 'Durian',
];

if (is_post()) {
    // (2) Read fruits[]
    $fruits = []; // TODO

    // (3) Validate fruits[]
    if (1) { // TODO
        $_err['fruits'] = 'Required';
    }
    else if (0) { // TODO
        $_err['fruits'] = 'Not an array';
    }
    else if (0) { // TODO
        $_err['fruits'] = 'Invalid item found';
    }
    else if (0) { // TODO
        $_err['fruits'] = 'Minimum 2 fruits';
    }

    if (!$_err) {
        $output = 'You have selected: ' . implode(', ', $fruits);
    }
}

// ----------------------------------------------------------------------------

$_title = 'Demo 5 : Checkbox List (EXTRA)';
include '_head.php';
?>

<form method="post" class="form">
    <label for="fruits">Fruits</label>
    <!-- (1) Checkbox list -->
    <?= TODO() // TODO ?>
    <?= err('fruits') ?>

    <section>
        <button>Submit</button>
        <button type="reset">Reset</button>
    </section>
</form>

<p><?= $output ?? '' ?></p>

<?php
include '_foot.php';