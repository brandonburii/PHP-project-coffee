<?php
include '../_base.php';

auth('Admin');

if (is_get()) {
    $active     = 1;
    $min_spend  = '0.00';
    $start_date = date('Y-m-d');
    $type       = 'percent';
}

if (is_post()) {
    $btn = req('btn');

    // Auto-generate code button (no validation — just fill the code field)
    if ($btn == 'generate') {
        $code        = generate_voucher_code(8);
        $description = req('description');
        $type        = req('type') ?: 'percent';
        $value       = req('value');
        $min_spend   = req('min_spend');
        $start_date  = req('start_date');
        $expiry      = req('expiry');
        $max_usage   = req('max_usage');
        $active      = req('active');
    }
    else {
        $code        = strtoupper(trim(req('code')));
        $description = trim(req('description'));
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

        // Description optional

        if ($type != 'percent' && $type != 'fixed') {
            $_err['type'] = 'Required';
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

        // Min spend optional — blank means RM 0.00
        if ($min_spend === '' || $min_spend === null) {
            $min_spend = '0.00';
        }
        else if (!is_numeric($min_spend) || $min_spend < 0) {
            $_err['min_spend'] = 'Must be 0 or greater';
        }

        if ($start_date == '') {
            $_err['start_date'] = 'Required';
        }
        else if (!is_date($start_date)) {
            $_err['start_date'] = 'Invalid date';
        }

        // Expiry optional — blank means never expires
        if ($expiry !== '' && $expiry !== null) {
            if (!is_date($expiry)) {
                $_err['expiry'] = 'Invalid date';
            }
            else if (strtotime($expiry) < strtotime(date('Y-m-d'))) {
                $_err['expiry'] = 'Cannot be in the past';
            }
            else if ($start_date != '' && is_date($start_date) && strtotime($expiry) < strtotime($start_date)) {
                $_err['expiry'] = 'Must be on or after start date';
            }
        }

        if ($max_usage !== '' && $max_usage !== null) {
            if (filter_var($max_usage, FILTER_VALIDATE_INT) === false || (int) $max_usage < 1) {
                $_err['max_usage'] = 'Leave blank for unlimited, or enter a positive integer';
            }
        }

        if (!$_err) {
            $max = ($max_usage === '' || $max_usage === null) ? null : (int) $max_usage;
            $exp = ($expiry === '' || $expiry === null) ? null : $expiry;
            $desc = ($description === '') ? null : $description;

            $stm = $_db->prepare('
                INSERT INTO voucher
                    (code, description, type, value, min_spend, start_date, expiry, max_usage, usage_count, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
            ');
            $stm->execute([
                $code, $desc, $type, $value, $min_spend, $start_date, $exp, $max, $active ? 1 : 0
            ]);

            audit(
                'Vouchers',
                'Voucher Created',
                "Created voucher: $code ($type $value)",
                null,
                [
                    'code' => $code,
                    'description' => $desc,
                    'type' => $type,
                    'value' => (float) $value,
                    'min_spend' => (float) $min_spend,
                    'start_date' => $start_date,
                    'expiry' => $exp,
                    'max_usage' => $max,
                    'usage_count' => 0,
                    'active' => $active ? 1 : 0,
                ]
            );
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
    <label for="code">Voucher Code <span class="req">*</span></label>
    <div class="form-field">
        <div class="field-row">
            <?= html_text('code', 'maxlength="20" placeholder="e.g. WELCOME10" data-upper') ?>
            <button type="submit" name="btn" value="generate" class="secondary">Auto Generate</button>
        </div>
        <p class="field-hint">Option 1: Auto Generate · Option 2: type a custom code</p>
    </div>
    <?= err('code') ?>

    <label for="description">Description</label>
    <div class="form-field">
        <?= html_text('description', 'maxlength="255" placeholder="e.g. Happy Birthday offer"') ?>
        <p class="field-hint">Optional</p>
    </div>
    <?= err('description') ?>

    <label for="type">Discount Type <span class="req">*</span></label>
    <?= html_select('type', ['percent' => 'Percentage (%)', 'fixed' => 'Fixed Amount (RM)'], null) ?>
    <?= err('type') ?>

    <label for="value">Discount Value <span class="req">*</span></label>
    <?= html_text('value', 'maxlength="10" placeholder="e.g. 10 or 15.00"') ?>
    <?= err('value') ?>

    <label for="min_spend">Minimum Spend (RM)</label>
    <div class="form-field">
        <?= html_text('min_spend', 'maxlength="10" placeholder="0.00"') ?>
        <p class="field-hint">Optional — cart must reach this amount to use the voucher</p>
    </div>
    <?= err('min_spend') ?>

    <label for="start_date">Start Date <span class="req">*</span></label>
    <?= html_date('start_date') ?>
    <?= err('start_date') ?>

    <label for="expiry">Expiry Date</label>
    <div class="form-field">
        <?= html_date('expiry') ?>
        <p class="field-hint">Optional — leave blank for never expires</p>
    </div>
    <?= err('expiry') ?>

    <label for="max_usage">Maximum Usage</label>
    <div class="form-field">
        <?= html_number('max_usage', 1, 999999, 1, 'placeholder="Leave blank = unlimited"') ?>
        <p class="field-hint">Optional — how many times this code can be used in total</p>
    </div>
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
