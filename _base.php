<?php

// ============================================================================
// PHP Setups
// ============================================================================

date_default_timezone_set('Asia/Kuala_Lumpur');
session_start();

// ============================================================================
// General Page Functions
// ============================================================================

// Is GET request?
function is_get() {
    return $_SERVER['REQUEST_METHOD'] == 'GET';
}

// Is POST request?
function is_post() {
    return $_SERVER['REQUEST_METHOD'] == 'POST';
}

// Obtain GET parameter
function get($key, $value = null) {
    $value = $_GET[$key] ?? $value;
    return is_array($value) ? array_map('trim', $value) : trim($value);
}

// Obtain POST parameter
function post($key, $value = null) {
    $value = $_POST[$key] ?? $value;
    return is_array($value) ? array_map('trim', $value) : trim($value);
}

// Obtain REQUEST (GET and POST) parameter
function req($key, $value = null) {
    $value = $_REQUEST[$key] ?? $value;
    return is_array($value) ? array_map('trim', $value) : trim($value);
}

// Redirect to URL
function redirect($url = null) {
    $url ??= $_SERVER['REQUEST_URI'];
    header("Location: $url");
    exit();
}

// Set or get temporary session variable
function temp($key, $value = null) {
    if ($value !== null) {
        $_SESSION["temp_$key"] = $value;
    }
    else {
        $value = $_SESSION["temp_$key"] ?? null;
        unset($_SESSION["temp_$key"]);
        return $value;
    }
}

// Obtain uploaded file --> cast to object
function get_file($key) {
    $f = $_FILES[$key] ?? null;
    
    if ($f && $f['error'] == 0) {
        return (object)$f;
    }

    return null;
}

// Crop, resize and save photo
function save_photo($f, $folder, $width = 200, $height = 200) {
    $photo = uniqid() . '.jpg';
    
    require_once __DIR__ . '/lib/SimpleImage.php';
    $img = new SimpleImage();
    $img->fromFile($f->tmp_name)
        ->thumbnail($width, $height)
        ->toFile("$folder/$photo", 'image/jpeg');

    return $photo;
}

// Is money?
function is_money($value) {
    return preg_match('/^\-?\d+(\.\d{1,2})?$/', $value);
}

// Is email?
function is_email($value) {
    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}

// Is date?
function is_date($value, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $value);
    return $d && $d->format($format) == $value;
}

// Is time?
function is_time($value, $format = 'H:i') {
    $d = DateTime::createFromFormat($format, $value);
    return $d && $d->format($format) == $value;
}

// Return year list items (for <select>)
function get_years($min, $max, $reverse = false) {
    $arr = range($min, $max);
    if ($reverse) {
        $arr = array_reverse($arr);
    }
    return array_combine($arr, $arr);
}

// Return month list items (for <select>)
function get_months() {
    return [
        1  => 'January',   2  => 'February', 3  => 'March',     4  => 'April',
        5  => 'May',       6  => 'June',     7  => 'July',      8  => 'August',
        9  => 'September', 10 => 'October',  11 => 'November',  12 => 'December',
    ];
}

// Return TRUE if ALL array elements meet the condition given
function array_all($arr, $fn) {
    foreach ($arr as $k => $v) {
        if (!$fn($v, $k)) {
            return false;
        }
    }
    return true;
}

// Return local root path
function root($path = '') {
    return "$_SERVER[DOCUMENT_ROOT]/$path";
}

// Return base url (host + port)
function base($path = '') {
    return "http://$_SERVER[SERVER_NAME]:$_SERVER[SERVER_PORT]/$path";
}

// ============================================================================
// HTML Helpers
// ============================================================================

// Placeholder for TODO
function TODO() {
    echo '<span>TODO</span>';
}

// Return 'active' when the given path matches the current page (for nav highlight)
function is_active($path) {
    $current = strtok($_SERVER['REQUEST_URI'], '?');
    if ($path === '/') {
        return $current === '/' || $current === '/index.php' ? 'active' : '';
    }
    return $current === $path ? 'active' : '';
}

// Return an inline SVG line icon (presentation helper for nav/buttons)
function icon($name) {
    $p = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'products'  => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5"/><path d="M12 13v8"/>',
        'cart'      => '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.4 12.3a1 1 0 0 0 1 .7h9.7a1 1 0 0 0 1-.8L23 6H6"/>',
        'orders'    => '<path d="M7 2h8l5 5v14a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/><path d="M14 2v6h6"/><path d="M9 13h7M9 17h7"/>',
        'profile'   => '<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/>',
        'audit'     => '<path d="M9 3h6a1 1 0 0 1 1 1v1h2a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h2V4a1 1 0 0 1 1-1z"/><path d="M9 12h6M9 16h4"/>',
        'voucher'   => '<path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z"/><path d="M14 6v12" stroke-dasharray="2 2"/>',
        'rewards'   => '<path d="M12 2l2.4 7.4H22l-6 4.6 2.3 7L12 16.8 5.7 21 8 14 2 9.4h7.6z"/>',
        'stock'     => '<path d="M3 7h18v14H3z"/><path d="M3 7l3-4h12l3 4"/><path d="M8 11h8M8 15h5"/>',
        'reports'   => '<path d="M4 19V5"/><path d="M4 19h16"/><path d="M8 16V10"/><path d="M12 16V7"/><path d="M16 16v-4"/>',
        'members'   => '<circle cx="9" cy="8" r="3.2"/><path d="M2 20c0-3.5 3-5.5 7-5.5"/><circle cx="17.5" cy="9" r="2.6"/><path d="M14.5 20c0-2.6 2-4.2 5.5-4.2"/>',
        'home'      => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V10"/>',
        'login'     => '<path d="M15 3h4a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/>',
        'register'  => '<circle cx="9" cy="8" r="4"/><path d="M2.5 20c0-4 3.5-6 6.5-6"/><path d="M19 8v6M22 11h-6"/>',
        'logout'    => '<path d="M9 3H5a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
    ][$name] ?? '';
    return "<svg class='ico' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' aria-hidden='true'>$p</svg>";
}

// Encode HTML special characters
function encode($value) {
    return htmlentities($value);
}

// Generate <input type='hidden'>
function html_hidden($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    $id = preg_match('/id=[\'"](.+?)[\'"]/', $attr) ? '' : "id='$key'";
    echo "<input type='hidden' $id name='$key' value='$value' $attr>";
}

// Generate <input type='text'>
function html_text($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    $id = preg_match('/id=[\'"](.+?)[\'"]/', $attr) ? '' : "id='$key'";
    echo "<input type='text' $id name='$key' value='$value' $attr>";
}

// Generate <input type='password'>
function html_password($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    $id = preg_match('/id=[\'"](.+?)[\'"]/', $attr) ? '' : "id='$key'";
    echo "<input type='password' $id name='$key' value='$value' $attr>";
}

// Generate <input type='number'>
function html_number($key, $min = '', $max = '', $step = '', $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    $id = preg_match('/id=[\'"](.+?)[\'"]/', $attr) ? '' : "id='$key'";
    echo "<input type='number' $id name='$key' value='$value'
                 min='$min' max='$max' step='$step' $attr>";
}

// Generate <input type='search'>
function html_search($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    $id = preg_match('/id=[\'"](.+?)[\'"]/', $attr) ? '' : "id='$key'";
    echo "<input type='search' $id name='$key' value='$value' $attr>";
}

// Generate <input type='date'>
function html_date($key, $min = '', $max = '', $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    $id = preg_match('/id=[\'"](.+?)[\'"]/', $attr) ? '' : "id='$key'";
    echo "<input type='date' $id name='$key' value='$value'
                 min='$min' max='$max' $attr>";
}

// Generate <input type='time'>
function html_time($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    $id = preg_match('/id=[\'"](.+?)[\'"]/', $attr) ? '' : "id='$key'";
    echo "<input type='time' $id name='$key' value='$value' $attr>";
}

// Generate <textarea>
function html_textarea($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    $id = preg_match('/id=[\'"](.+?)[\'"]/', $attr) ? '' : "id='$key'";
    echo "<textarea $id name='$key' $attr>$value</textarea>";
}

// Generate SINGLE <input type='checkbox'>
function html_checkbox($key, $label = '', $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    $status = $value == 1 ? 'checked' : '';
    $id = preg_match('/id=[\'"](.+?)[\'"]/', $attr) ? '' : "id='$key'";
    echo "<label><input type='checkbox' $id name='$key' value='1' $status $attr>$label</label>";
}

// Generate <input type='checkbox'> list
function html_checkboxes($key, $items, $br = false) {
    $values = $GLOBALS[$key] ?? [];
    if (!is_array($values)) $values = [];
    echo '<div>';
    foreach ($items as $id => $text) {
        $state = in_array($id, $values) ? 'checked' : '';
        echo "<label><input type='checkbox' id='{$key}_$id' name='{$key}[]' value='$id' $state>$text</label>";
        if ($br) {
            echo '<br>';
        }
    }
    echo '</div>';
}

// Generate <input type='radio'> list
function html_radios($key, $items, $br = false) {
    $value = encode($GLOBALS[$key] ?? '');
    echo '<div>';
    foreach ($items as $id => $text) {
        $state = $id == $value ? 'checked' : '';
        echo "<label><input type='radio' id='{$key}_$id' name='$key' value='$id' $state>$text</label>";
        if ($br) {
            echo '<br>';
        }
    }
    echo '</div>';
}

// Generate <select>
function html_select($key, $items, $default = '- Select One -', $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    $id = preg_match('/id=[\'"](.+?)[\'"]/', $attr) ? '' : "id='$key'";
    echo "<select $id name='$key' $attr>";
    if ($default !== null) {
        echo "<option value=''>$default</option>";
    }
    foreach ($items as $id_val => $text) {
        $state = $id_val == $value ? 'selected' : '';
        echo "<option value='$id_val' $state>$text</option>";
    }
    echo '</select>';
}

// Generate <input type='file'>
function html_file($key, $accept = '', $attr = '') {
    $id = preg_match('/id=[\'"](.+?)[\'"]/', $attr) ? '' : "id='$key'";
    echo "<input type='file' $id name='$key' accept='$accept' $attr>";
}

// Generate table headers <th>
function table_headers($fields, $sort, $dir, $href = '') {
    foreach ($fields as $k => $v) {
        $d = 'asc'; // Default direction
        $c = '';    // Default class
        
        if ($k == $sort) {
            $d = $dir == 'asc' ? 'desc' : 'asc';
            $c = $dir;
        }

        echo "<th><a href='?sort=$k&dir=$d&$href' class='$c'>$v</a></th>";
    }
}

// ============================================================================
// Error Handlings
// ============================================================================

// Global error array
$_err = [];

// Generate <span class='err'>
function err($key) {
    global $_err;
    if ($_err[$key] ?? false) {
        echo "<span class='err'>$_err[$key]</span>";
    }
    else {
        echo '<span></span>';
    }
}

// ============================================================================
// Security
// ============================================================================

// Global user object
$_user = $_SESSION['user'] ?? null;

// Login user
function login($user, $url = '/') {
    // Permanent cart: merge guest session cart with saved DB cart (Members only)
    if (($user->role ?? '') == 'Member') {
        $session_cart = $_SESSION['cart'] ?? [];
        $db_cart      = load_cart_db($user->id);
        $merged       = merge_carts($db_cart, $session_cart);
        save_cart_db($user->id, $merged);
        $_SESSION['cart'] = $merged;
    }

    $_SESSION['user'] = $user;
    redirect($url);
}

// Logout user
function logout($url = '/') {
    unset($_SESSION['user']);
    // Clear session cart only — DB cart stays so it restores on next login
    unset($_SESSION['cart']);
    redirect($url);
}

// Authorization
function auth(...$roles) {
    global $_user;
    if ($_user) {
        if ($roles) {
            if (in_array($_user->role, $roles)) {
                return; // OK
            }
        }
        else {
            return; // OK
        }
    }
    
    redirect('/login.php');
}

// ============================================================================
// Audit Logging
// ============================================================================

function audit($module, $action, $description = '') {
    // Audit logging disabled
}

// ============================================================================
// Email Functions
// ============================================================================

// Demo Accounts:
// --------------
// AACS3173@gmail.com           npsg gzfd pnio aylm
// BAIT2173.email@gmail.com     ytwo bbon lrvw wclr
// liaw.casual@gmail.com        wtpa kjxr dfcb xkhg
// liawcv1@gmail.com            obyj shnv prpa kzvj

// Initialize and return mail object
function get_mail() {
    require_once __DIR__ . '/lib/PHPMailer.php';
    require_once __DIR__ . '/lib/SMTP.php';

    $m = new PHPMailer(true);
    $m->isSMTP();
    $m->SMTPAuth = true;
    $m->Host = 'smtp.gmail.com';
    $m->Port = 587;
    $m->Username = 'AACS3173@gmail.com';
    $m->Password = 'npsg gzfd pnio aylm';
    $m->CharSet = 'utf-8';
    $m->setFrom($m->Username, '😺 Admin');

    return $m;
}

// ============================================================================
// Shopping Cart
// ============================================================================

// Get shopping cart (session working copy)
function get_cart() {
    return $_SESSION['cart'] ?? [];
}

// Set shopping cart (session + DB for logged-in members)
function set_cart($cart = []) {
    global $_user;
    $_SESSION['cart'] = $cart;

    // Permanent cart: keep DB in sync for Members
    if ($_user && ($_user->role ?? '') == 'Member') {
        save_cart_db($_user->id, $cart);
    }
}

function update_cart($id, $unit) {
    global $_db;
    $cart = get_cart();

    if (is_exists($id, 'product', 'id')) {
        $stm = $_db->prepare('SELECT stock FROM product WHERE id = ?');
        $stm->execute([$id]);
        $stock = $stm->fetchColumn();

        $max = min($stock, 10);

        if ($unit >= 1 && $unit <= $max) {
            $cart[$id] = $unit;
            ksort($cart);
        } else {
            unset($cart[$id]);
        }
    } else {
        unset($cart[$id]);
    }

    set_cart($cart);
}

// Load cart rows from DB for a member (product_id => unit)
function load_cart_db($user_id) {
    global $_db;
    $stm = $_db->prepare('SELECT product_id, unit FROM cart WHERE user_id = ? ORDER BY product_id');
    $stm->execute([$user_id]);
    $cart = [];
    foreach ($stm->fetchAll() as $row) {
        $cart[$row->product_id] = (int) $row->unit;
    }
    return $cart;
}

// Replace all cart rows in DB for a member
function save_cart_db($user_id, $cart = []) {
    global $_db;

    $stm = $_db->prepare('DELETE FROM cart WHERE user_id = ?');
    $stm->execute([$user_id]);

    if (!$cart) {
        return;
    }

    $stm = $_db->prepare('INSERT INTO cart (user_id, product_id, unit) VALUES (?, ?, ?)');
    foreach ($cart as $product_id => $unit) {
        $stm->execute([$user_id, $product_id, $unit]);
    }
}

// Merge two carts; when both have the same product, keep the higher unit
function merge_carts($a, $b) {
    foreach ($b as $id => $unit) {
        $a[$id] = max((int) ($a[$id] ?? 0), (int) $unit);
    }
    ksort($a);
    return $a;
}

// ============================================================================
// Reward Points
// ============================================================================

// Read a setting value (with default)
function get_setting($key, $default = '') {
    global $_db;
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $stm = $_db->prepare('SELECT `value` FROM setting WHERE `key` = ?');
        $stm->execute([$key]);
        $v = $stm->fetchColumn();
        $cache[$key] = ($v === false) ? $default : $v;
    }
    catch (Exception $e) {
        $cache[$key] = $default;
    }
    return $cache[$key];
}

// Points earned per RM1 spent (configurable)
function points_rate() {
    $r = (float) get_setting('points_per_rm', '1');
    return $r > 0 ? $r : 1;
}

// RM value of 1 point when redeeming at checkout (configurable)
function point_cash_rate() {
    $r = (float) get_setting('point_value_rm', '0.10');
    return $r > 0 ? $r : 0.10;
}

// Points earned for a given RM amount
function points_earned($amount) {
    return (int) floor($amount * points_rate());
}

// RM discount value of a given number of points
function points_value($points) {
    return (float) $points * point_cash_rate();
}

// Fresh points balance for a user
function get_user_points($user_id = null) {
    global $_db, $_user;
    $user_id = $user_id ?? ($_user->id ?? null);
    if (!$user_id) return 0;
    $stm = $_db->prepare('SELECT points FROM user WHERE id = ?');
    $stm->execute([$user_id]);
    return (int) $stm->fetchColumn();
}

// ============================================================================
// Vouchers
// ============================================================================

// Generate a unique random voucher code
function generate_voucher_code($length = 8) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
    } while (!is_unique($code, 'voucher', 'code'));
    return $code;
}

// Validate voucher against all rules; returns [ok, error, voucher]
function validate_voucher($code, $subtotal = 0) {
    global $_db;

    $code = strtoupper(trim($code));
    if ($code == '') {
        return ['ok' => false, 'error' => 'Required', 'voucher' => null];
    }

    $stm = $_db->prepare('SELECT * FROM voucher WHERE code = ?');
    $stm->execute([$code]);
    $v = $stm->fetch();

    if (!$v) {
        return ['ok' => false, 'error' => 'Voucher not found', 'voucher' => null];
    }
    if (!$v->active) {
        return ['ok' => false, 'error' => 'Voucher is disabled', 'voucher' => null];
    }
    if (!empty($v->start_date) && strtotime($v->start_date) > strtotime(date('Y-m-d'))) {
        return ['ok' => false, 'error' => 'Voucher is not active yet', 'voucher' => null];
    }
    if (strtotime($v->expiry) < strtotime(date('Y-m-d'))) {
        return ['ok' => false, 'error' => 'Voucher has expired', 'voucher' => null];
    }
    if (isset($v->min_spend) && (float) $v->min_spend > 0 && $subtotal < (float) $v->min_spend) {
        return ['ok' => false, 'error' => 'Minimum spend is RM ' . sprintf('%.2f', $v->min_spend), 'voucher' => null];
    }
    if (isset($v->max_usage) && $v->max_usage !== null && $v->max_usage !== '' &&
        (int) $v->usage_count >= (int) $v->max_usage) {
        return ['ok' => false, 'error' => 'Voucher usage limit reached', 'voucher' => null];
    }

    return ['ok' => true, 'error' => null, 'voucher' => $v];
}

// Return a voucher object if valid for the given subtotal, else null
function get_valid_voucher($code, $subtotal = 0) {
    $r = validate_voucher($code, $subtotal);
    return $r['ok'] ? $r['voucher'] : null;
}

// Compute the RM discount a voucher gives for a given subtotal
function voucher_discount($voucher, $subtotal) {
    if (!$voucher) {
        return 0.00;
    }

    $d = $voucher->type == 'percent'
        ? $subtotal * $voucher->value / 100
        : $voucher->value;

    return (float) min($d, $subtotal);
}

// Increment voucher usage count after successful checkout
function voucher_use($code) {
    global $_db;
    $stm = $_db->prepare('UPDATE voucher SET usage_count = usage_count + 1 WHERE code = ?');
    $stm->execute([$code]);
}

// ============================================================================
// Rewards Catalog
// ============================================================================

// Redeem a reward item for the current member (transaction)
function redeem_reward($reward_id) {
    global $_db, $_user;

    if (!$_user || ($_user->role ?? '') != 'Member') {
        return ['ok' => false, 'error' => 'Login as member required'];
    }

    $_db->beginTransaction();
    try {
        $stm = $_db->prepare('SELECT * FROM reward WHERE id = ? FOR UPDATE');
        $stm->execute([$reward_id]);
        $r = $stm->fetch();

        if (!$r || !$r->active) {
            throw new Exception('Reward not available');
        }
        if ((int) $r->stock < 1) {
            throw new Exception('Reward out of stock');
        }

        $stm = $_db->prepare('SELECT points FROM user WHERE id = ? FOR UPDATE');
        $stm->execute([$_user->id]);
        $pts = (int) $stm->fetchColumn();

        if ($pts < (int) $r->points) {
            throw new Exception('Insufficient points');
        }

        $stm = $_db->prepare('UPDATE user SET points = points - ? WHERE id = ?');
        $stm->execute([$r->points, $_user->id]);

        $stm = $_db->prepare('UPDATE reward SET stock = stock - 1 WHERE id = ?');
        $stm->execute([$reward_id]);

        $stm = $_db->prepare('
            INSERT INTO reward_redemption (user_id, reward_id, points, status)
            VALUES (?, ?, ?, ?)
        ');
        $stm->execute([$_user->id, $reward_id, $r->points, 'completed']);

        $_db->commit();

        $_SESSION['user']->points = $pts - (int) $r->points;
        audit('Rewards', 'Reward Redeemed', "User {$_user->id} redeemed reward {$r->id} ({$r->name}) for {$r->points} pts");
        return ['ok' => true, 'error' => null, 'reward' => $r];
    }
    catch (Exception $e) {
        $_db->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// ============================================================================
// Stock History
// ============================================================================

// Log a stock movement (added / edited / sold)
function log_stock($product_id, $action, $old_stock, $new_stock) {
    global $_db, $_user;

    $stm = $_db->prepare('
        INSERT INTO stock_history
            (product_id, action, old_stock, new_stock, change_qty, user_id, username)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stm->execute([
        $product_id,
        $action,
        (int) $old_stock,
        (int) $new_stock,
        (int) $new_stock - (int) $old_stock,
        $_user->id   ?? null,
        $_user->name ?? null,
    ]);
}

// ============================================================================
// Product Pricing (Flash Sale)
// ============================================================================

// Is the product currently on flash sale?
function is_on_sale($p) {
    if (!$p) {
        return false;
    }
    // Don't use empty() on sale_price — "0.00" would be treated as empty
    if ($p->sale_price === null || $p->sale_price === '' ||
        empty($p->sale_start) || empty($p->sale_end)) {
        return false;
    }
    $now = time();
    return $now >= strtotime($p->sale_start) && $now <= strtotime($p->sale_end);
}

// Effective selling price (sale price if active, else normal price)
function product_price($p) {
    return is_on_sale($p) ? (float) $p->sale_price : (float) $p->price;
}

// ============================================================================
// Recently Viewed
// ============================================================================

// Remember a product view (keep last 10, newest first)
function add_recent($product_id) {
    $arr = $_SESSION['recent'] ?? [];
    $arr = array_values(array_filter($arr, fn($id) => $id != $product_id));
    array_unshift($arr, $product_id);
    $_SESSION['recent'] = array_slice($arr, 0, 10);
}

// Return recent product IDs
function get_recent() {
    return $_SESSION['recent'] ?? [];
}

// ============================================================================
// Product Comparison
// ============================================================================

// Get compare list (product IDs)
function get_compare() {
    return $_SESSION['compare'] ?? [];
}

// Toggle a product in the compare list (max 3)
function toggle_compare($product_id) {
    $arr = get_compare();
    if (in_array($product_id, $arr)) {
        $arr = array_values(array_filter($arr, fn($id) => $id != $product_id));
    }
    else {
        if (count($arr) >= 3) {
            return false; // full
        }
        $arr[] = $product_id;
    }
    $_SESSION['compare'] = $arr;
    return true;
}

// ============================================================================
// Database Setups and Functions
// ============================================================================

// Global PDO object
$_db = new PDO('mysql:dbname=db10', 'root', '', [
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
]);

// Is unique?
function is_unique($value, $table, $field) {
    global $_db;
    $stm = $_db->prepare("SELECT COUNT(*) FROM $table WHERE $field = ?");
    $stm->execute([$value]);
    return $stm->fetchColumn() == 0;
}

// Is exists?
function is_exists($value, $table, $field) {
    global $_db;
    $stm = $_db->prepare("SELECT COUNT(*) FROM $table WHERE $field = ?");
    $stm->execute([$value]);
    return $stm->fetchColumn() > 0;
}

// ============================================================================
// Global Constants and Variables
// ============================================================================

// Range 1-10
$_units = array_combine(range(1, 10), range(1, 10));