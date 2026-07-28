<?php
include '../_base.php';

auth('Admin');

$code = req('code');
$stm = $_db->prepare('SELECT * FROM voucher WHERE code = ?');
$stm->execute([$code]);
$v = $stm->fetch();
if (!$v) redirect('voucher_list.php');

if (is_get()) {
    $description = $v->description ?? '';
    $type        = $v->type;
    $value       = $v->value;
    $min_spend   = $v->min_spend ?? '0.00';
    $start_date  = $v->start_date ?? date('Y-m-d');
    $expiry      = $v->expiry;
    $max_usage   = $v->max_usage;
    $active      = $v->active;
}

if (is_post()) {
    $description = req('description');
    $type        = req('type');
    $value       = req('value');
    $min_spend   = req('min_spend');
    $start_date  = req('start_date');
    $expiry      = req('expiry');
    $max_usage   = req('max_usage');
    $active      = req('active');

    if ($description == '') {
        $_err['description'] = 'Required';
    }

    if ($type != 'percent' && $type != 'fixed') {
        $_err['type'] = 'Invalid type';
    }

    if ($value == '' || !is_numeric($value) || $value <= 0) {
        $_err['value'] = 'Must be a positive number';
    }
    else if ($type == 'percent' && $value > 100) {
        $_err['value'] = 'Percentage cannot exceed 100';
    }

    if ($min_spend === '' || !is_numeric($min_spend) || $min_spend < 0) {
        $_err['min_spend'] = 'Must be 0 or greater';
    }

    if ($start_date == '' || !is_date($start_date)) {
        $_err['start_date'] = 'Invalid date';
    }

    if ($expiry == '' || !is_date($expiry)) {
        $_err['expiry'] = 'Invalid date';
    }
    else if ($start_date != '' && is_date($start_date) && strtotime($expiry) < strtotime($start_date)) {
        $_err['expiry'] = 'Must be on or after start date';
    }

    if ($max_usage !== '' && $max_usage !== null) {
        if (filter_var($max_usage, FILTER_VALIDATE_INT) === false || $max_usage < 1) {
            $_err['max_usage'] = 'Leave blank for unlimited, or enter a positive integer';
        }
    }

    if (!$_err) {
        $max = ($max_usage === '' || $max_usage === null) ? null : (int) $max_usage;

        $stm = $_db->prepare('
            UPDATE voucher SET
                description = ?, type = ?, value = ?, min_spend = ?,
                start_date = ?, expiry = ?, max_usage = ?, active = ?
            WHERE code = ?
        ');
        $stm->execute([
            $description, $type, $value, $min_spend,
            $start_date, $expiry, $max, $active ? 1 : 0, $code
        ]);

        audit('Vouchers', 'Voucher Updated', "Updated voucher: $code ($type $value)");
        temp('info', 'Voucher updated successfully');
        redirect('voucher_list.php');
    }
}

$_breadcrumbs = [
    'Dashboard' => '/',
    'Voucher Maintenance' => 'voucher_list.php',
    'Edit Voucher' => '',
];
$_title = 'Admin | Edit Voucher';
include '../_head.php';
?>

<form method="post" class="form">
    <label for="code">Voucher Code</label>
    <b class="voucher-badge"><?= encode($v->code) ?></b>

    <label for="description">Description</label>
    <?= html_text('description', 'maxlength="255"') ?>
    <?= err('description') ?>

    <label for="type">Discount Type</label>
    <?= html_select('type', ['percent' => 'Percentage (%)', 'fixed' => 'Fixed Amount (RM)'], null) ?>
    <?= err('type') ?>

    <label for="value">Discount Value</label>
    <?= html_text('value', 'maxlength="10"') ?>
    <?= err('value') ?>

    <label for="min_spend">Minimum Spend (RM)</label>
    <?= html_text('min_spend', 'maxlength="10"') ?>
    <?= err('min_spend') ?>

    <label for="start_date">Start Date</label>
    <?= html_date('start_date') ?>
    <?= err('start_date') ?>

    <label for="expiry">Expiry Date</label>
    <?= html_date('expiry') ?>
    <?= err('expiry') ?>

    <label for="max_usage">Maximum Usage</label>
    <?= html_number('max_usage', 1, 999999, 1, 'placeholder="Leave blank = unlimited"') ?>
    <?= err('max_usage') ?>

    <label>Current Usage</label>
    <b><?= (int) ($v->usage_count ?? 0) ?></b>

    <label for="active">Status</label>
    <?= html_checkbox('active', 'Active (enabled)') ?>
    <?= err('active') ?>

    <section>
        <button>Update Voucher</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php include '../_foot.php';
