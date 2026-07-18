<?php
include '_base.php';

// ----------------------------------------------------------------------------

// (1) Populate select list items
// TODO
$years  = ['TODO'];
$months = ['TODO'];

// (2) Inputs and validations
// TODO

// TODO

// (3) Start and end dates
// TODO

// (4) Adjustments ($a = Monday, $b = Sunday)
// TODO

// (6) Read holiday records between $a and $b
// TODO

// ----------------------------------------------------------------------------

$_title = 'Demo 4 : Holiday Calendar';
include '_head.php';
?>

<style>
    .cal {
        display: grid;
        grid: auto / repeat(7, 1fr);
        gap: 1px;
    }

    .cal > h3 {
        outline: 1px solid #333;
        margin: 0;
        padding: 5px;
        text-align: center;
        background: #666;
        color: #fff;
    }

    .cal > div {
        outline: 1px solid #333;
        padding: 5px;
        min-height: 75px;
    }

    .cal > div.x {
        background-color: #ccc;
    }
</style>

<form>
    <?= html_select('year',  $years,  null) ?>
    <?= html_select('month', $months, null) ?>
</form>

<br>

<div class="cal">
    <h3>Monday</h3>
    <h3>Tuesday</h3>
    <h3>Wednesday</h3>
    <h3>Thursday</h3>
    <h3>Friday</h3>
    <h3>Saturday</h3>
    <h3>Sunday</h3>

    <?php
    // (5) Display each day between $a and $b
    // (7) Display holiday name (if any)
    // TODO
    ?>
</div>

<script>
    $('select').on('change', e => $(e.target.form).submit());
</script>

<?php
include '_foot.php';