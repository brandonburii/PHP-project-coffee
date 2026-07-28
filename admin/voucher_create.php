<?php
include '../_base.php';

auth('Admin');

if (is_get()) {
    $active    = 1;
    $min_spend = '0.00';
    $start_date = date('Y-m-d');
}

if (is_post()) {
    $btn = req('btn');

    // Auto-generate code button
    if ($btn == 'generate') {
        $code = generate_voucher_code(8);
        // Keep other posted fields
        $description = req('description');
        $type        = req('type');
        $value       = req('value');
        $min_spend   = req('min_spend');
        $start_date  = req('start_date');
        $expiry      = req('expiry');
        $max_usage   = req('max_usage');
        $active      = req('active');
    }
    else {
        $code        = strtoupper(trim(req('code')));
        $description = req('description');
        $type        = req('type');
        $value       = req('value');
        $min_spend   = req('min_spend');
        $start_date  = req('start_date');
        $expiry      = req('expiry');
        $max_usage   = req('max_usage');
        $active      = req('active');

        if ($code == '') {
            $_err['code'] = 'Required';
        }
        else if (!preg_match('/^[A-Z0-9]{3,20}$/', $code)) {
            $_err['code'] = '3-20 letters/numbers only';
        }
        else if (!is_unique($code, 'voucher', 'code')) {
            $_err['code'] = 'Code already exists';
        }

        if ($description == '') {
            $_err['description'] = 'Required';
        }

        if ($type != 'percent' && $type != 'fixed') {
            $_err['type'] = 'Invalid type';
        }

        if ($value == '') {
            $_err['value'] = 'Required';
        }
        else if (!is_numeric($value) || $value <= 0) {
            $_err['value'] = 'Must be a positive number';
        }
        else if ($type == 'percent' && $value > 100) {
            $_err['value'] = 'Percentage cannot exceed 100';
        }

        if ($min_spend === '' || !is_numeric($min_spend) || $min_spend < 0) {
            $_err['min_spend'] = 'Must be 0 or greater';
        }

        if ($start_date == '') {
            $_err['start_date'] = 'Required';
        }
        else if (!is_date($start_date)) {
            $_err['start_date'] = 'Invalid date';
        }

        if ($expiry == '') {
            $_err['expiry'] = 'Required';
        }
        else if (!is_date($expiry)) {
            $_err['expiry'] = 'Invalid date';
        }
        else if (strtotime($expiry) < strtotime(date('Y-m-d'))) {
            $_err['expiry'] = 'Cannot be in the past';
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
                INSERT INTO voucher
                    (code, description, type, value, min_spend, start_date, expiry, max_usage, usage_count, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
            ');
            $stm->execute([
                $code, $description, $type, $value, $min_spend, $start_date, $expiry, $max, $active ? 1 : 0
            ]);

            audit('Vouchers', 'Voucher Created', "Created voucher: $code ($type $value)");
            temp('info', 'Voucher created successfully');
            redirect('voucher_list.php');
        }
    }
}

$_breadcrumbs = [
    'Dashboard' => '/',
    'Voucher Maintenance' => 'voucher_list.php',
    'Create Voucher' => '',
];
$_title = 'Admin | Create Voucher';
include '../_head.php';
?>

<form method="post" class="form">
    <label for="code">Voucher Code</label>
    <div style="display:flex; gap:8px; align-items:flex-start; flex-wrap:wrap;">
        <?= html_text('code', 'maxlength="20" placeholder="e.g. WELCOME10" data-upper style="flex:1; min-width:180px;"') ?>
        <button type="submit" name="btn" value="generate" class="secondary">Auto Generate</button>
    </div>
    <p style="margin:4px 0 0; font-size:.82rem; color:var(--muted);">Option 1: Auto Generate · Option 2: type a custom code</p>
    <?= err('code') ?>

    <label for="description">Description</label>
    <?= html_text('description', 'maxlength="255" placeholder="e.g. Happy Birthday offer"') ?>
    <?= err('description') ?>

    <label for="type">Discount Type</label>
    <?= html_select('type', ['percent' => 'Percentage (%)', 'fixed' => 'Fixed Amount (RM)'], null) ?>
    <?= err('type') ?>

    <label for="value">Discount Value</label>
    <?= html_text('value', 'maxlength="10" placeholder="e.g. 10 or 15.00"') ?>
    <?= err('value') ?>

    <label for="min_spend">Minimum Spend (RM)</label>
    <?= html_text('min_spend', 'maxlength="10" placeholder="0.00"') ?>
    <?= err('min_spend') ?>

    <label for="start_date">Start Date</label>
    <?= html_date('start_date') ?>
    <?= err('start_date') ?>

    <label for="expiry">Expiry Date</label>
    <?= html_date('expiry', date('Y-m-d')) ?>
    <?= err('expiry') ?>

    <label for="max_usage">Maximum Usage</label>
    <?= html_number('max_usage', 1, 999999, 1, 'placeholder="Leave blank = unlimited"') ?>
    <?= err('max_usage') ?>

    <label for="active">Status</label>
    <?= html_checkbox('active', 'Active (enabled)') ?>
    <?= err('active') ?>

    <section>
        <button name="btn" value="save">Create Voucher</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php include '../_foot.php';
