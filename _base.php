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

// Flash message helper: set or get a one-time message
function flash($msg = null) {
    if ($msg !== null) {
        temp('flash', $msg);
    } else {
        return temp('flash');
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

// Crop, resize and save photo (falls back to plain copy if GD is unavailable)
function save_photo($f, $folder, $width = 200, $height = 200) {
    $photo = uniqid() . '.jpg';
    $dest  = "$folder/$photo";

    if (extension_loaded('gd')) {
        require_once __DIR__ . '/lib/SimpleImage.php';
        $img = new SimpleImage();
        $img->fromFile($f->tmp_name)
            ->thumbnail($width, $height)
            ->toFile($dest, 'image/jpeg');
    }
    else {
        // GD missing — store original upload so register/create still works
        if (!move_uploaded_file($f->tmp_name, $dest)) {
            copy($f->tmp_name, $dest);
        }
    }

    return $photo;
}

// Resolve product/user photo path (default placeholder when missing)
function photo_url($photo, $fallback = '0.jpg') {
    $photo = trim((string) $photo);
    if ($photo == '' || $photo == 'null') {
        return $fallback;
    }
    // Check in products folder first, then photos folder
    $path = __DIR__ . '/products/' . $photo;
    if (is_file($path)) {
        return $photo;
    }
    $path = __DIR__ . '/rewards/' . $photo;
    if (is_file($path)) {
        return $photo;
    }
    $path = __DIR__ . '/photos/' . $photo;
    if (is_file($path)) {
        return $photo;
    }
    return $fallback;
}

// Return the web path of a photo, checking products/ then rewards/ then photos/ folders.
// Pass $folder ('products'|'rewards'|'photos') to force a specific folder first.
function photo_src($photo, $fallback = '0.jpg', $folder = null) {
    $photo = trim((string) $photo);
    if ($photo != '' && $photo != 'null') {
        $folders = $folder ? [$folder] : ['products', 'rewards', 'photos'];
        foreach ($folders as $f) {
            if (is_file(__DIR__ . '/' . $f . '/' . $photo)) {
                return '/' . $f . '/' . rawurlencode($photo);
            }
        }
    }
    $fallbackFolder = $folder ?: 'photos';
    return '/' . $fallbackFolder . '/' . $fallback;
}

// Get all product images by product name
function get_product_images($product_name) {
    $product_name = trim((string) $product_name);
    if ($product_name == '') {
        return [];
    }
    
    $folder = __DIR__ . '/products/';
    $images = [];
    
    if (is_dir($folder)) {
        $files = scandir($folder);
        foreach ($files as $file) {
            // Match files that start with product name (case-insensitive)
            if (stripos($file, $product_name) === 0) {
                $images[] = $file;
            }
        }
    }
    
    sort($images);
    return $images;
}

// Get all reward images by reward name
function get_reward_images($reward_name) {
    $reward_name = trim((string) $reward_name);
    if ($reward_name == '') {
        return [];
    }
    
    $folder = __DIR__ . '/rewards/';
    $images = [];
    
    if (is_dir($folder)) {
        $files = scandir($folder);
        foreach ($files as $file) {
            if (stripos($file, $reward_name) === 0) {
                $images[] = $file;
            }
        }
    }
    
    sort($images);
    return $images;
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

function audit_diff($before = null, $after = null) {
    $before = is_array($before) ? $before : (is_object($before) ? (array) $before : []);
    $after  = is_array($after)  ? $after  : (is_object($after)  ? (array) $after  : []);

    $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
    $old = [];
    $new = [];

    foreach ($keys as $k) {
        $b = $before[$k] ?? null;
        $a = $after[$k] ?? null;

        // Compare as strings so 1 and "1" are treated equally
        if ((string) $b !== (string) $a) {
            $old[$k] = $b;
            $new[$k] = $a;
        }
    }

    return [$old, $new];
}

function client_ip() {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($ip != '') {
        $parts = explode(',', $ip);
        $ip = trim($parts[0]);
    }
    if ($ip == '') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    return $ip ?: null;
}

function audit($module, $action, $description = '', $before = null, $after = null, $options = []) {
    global $_db, $_user;

    try {
        $keep_all = (bool) ($options['keep_all'] ?? false);
        if ($keep_all) {
            $old = is_array($before) ? $before : (is_object($before) ? (array) $before : []);
            $new = is_array($after)  ? $after  : (is_object($after)  ? (array) $after  : []);
        }
        else {
            [$old, $new] = audit_diff($before, $after);
        }

        $before_json = $old ? json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $after_json  = $new ? json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        $stm = $_db->prepare('
            INSERT INTO audit_log
                (user_id, username, role, module, action, description, before_data, after_data, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ');
        $stm->execute([
            $_user->id   ?? null,
            $_user->name ?? null,
            $_user->role ?? null,
            $module,
            $action,
            $description ?: null,
            $before_json,
            $after_json,
            client_ip(),
        ]);
    }
    catch (Exception $e) {
        // Keep user flow working, but never silently lose accountability failures.
        // Server-side only — do not show SQL/DB details to end users.
        error_log(sprintf(
            '[AUDIT FAILURE] module=%s action=%s user_id=%s: %s',
            $module,
            $action,
            $_user->id ?? 'guest',
            $e->getMessage()
        ));
    }
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
    // NULL expiry = never expires
    if (!empty($v->expiry) && strtotime($v->expiry) < strtotime(date('Y-m-d'))) {
        return ['ok' => false, 'error' => 'Voucher has expired', 'voucher' => null];
    }
    if ((float) ($v->min_spend ?? 0) > 0 && $subtotal < (float) $v->min_spend) {
        return ['ok' => false, 'error' => 'Minimum spend is RM ' . sprintf('%.2f', $v->min_spend), 'voucher' => null];
    }
    if ($v->max_usage !== null && $v->max_usage !== '' &&
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
    $stm = $_db->prepare('SELECT usage_count FROM voucher WHERE code = ?');
    $stm->execute([$code]);
    $before = (int) $stm->fetchColumn();

    $stm = $_db->prepare('UPDATE voucher SET usage_count = usage_count + 1 WHERE code = ?');
    $stm->execute([$code]);

    $stm = $_db->prepare('SELECT usage_count FROM voucher WHERE code = ?');
    $stm->execute([$code]);
    $after = (int) $stm->fetchColumn();

    audit(
        'Vouchers',
        'Voucher Used',
        "Voucher used at checkout: $code",
        ['code' => $code, 'usage_count' => $before],
        ['code' => $code, 'usage_count' => $after],
        ['keep_all' => true]
    );
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
        $stock_before = (int) $r->stock;

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
        audit(
            'Reward Points',
            'Points Redeemed',
            "Redeemed reward {$r->id} ({$r->name})",
            [
                'user_id' => $_user->id,
                'reward_id' => $r->id,
                'reward_name' => $r->name,
                'points_before' => $pts,
                'points_changed' => -(int) $r->points,
                'points_after' => $pts - (int) $r->points,
                'reward_stock' => $stock_before,
                'reason' => 'Reward redemption',
            ],
            [
                'user_id' => $_user->id,
                'reward_id' => $r->id,
                'reward_name' => $r->name,
                'points_before' => $pts,
                'points_changed' => -(int) $r->points,
                'points_after' => $pts - (int) $r->points,
                'reward_stock' => $stock_before - 1,
                'reason' => 'Reward redemption',
            ],
            ['keep_all' => true]
        );
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

// Return top selling products ordered by units sold
function top_selling_products($limit = 5) {
    global $_db;
    $limit = (int) $limit;
    if ($limit < 1) $limit = 5;

    // Use a safe integer-cast for LIMIT to avoid injection
    $sql = "
        SELECT p.id, p.name, p.photo,
               COALESCE(SUM(i.unit), 0) AS units_sold,
               COALESCE(SUM(i.subtotal), 0) AS revenue
        FROM product p
        LEFT JOIN item i ON i.product_id = p.id
        GROUP BY p.id, p.name, p.photo
        ORDER BY units_sold DESC
        LIMIT " . $limit . "
    ";

    $stm = $_db->query($sql);
    return $stm->fetchAll();
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




// ============================================================================
// Auto-login check for "Remember Me" cookie
// ============================================================================

if (!isset($_SESSION['user']) && isset($_COOKIE['remember_token'])) {
    // Check if lockout is active
    $is_locked = false;
    if (isset($_SESSION['lockout_time'])) {
        $time_passed = time() - $_SESSION['lockout_time'];
        if ($time_passed < 180) {
            $is_locked = true;
        } else {
            unset($_SESSION['lockout_time']);
            $_SESSION['login_attempts'] = 0;
        }
    }

    if (!$is_locked) {
        $token = $_COOKIE['remember_token'];
        
        $stm = $_db->prepare('SELECT * FROM user WHERE remember_token = ?');
        $stm->execute([$token]);
        $u = $stm->fetch();

        if ($u) {
            if ((int)$u->active) {
                login($u);
                audit('Auth', 'Remember Me Login', "Automatically logged in using remember token for: {$u->email}");
            } else {
                setcookie('remember_token', '', time() - 3600, '/');
                $stm = $_db->prepare('UPDATE user SET remember_token = NULL WHERE id = ?');
                $stm->execute([$u->id]);
                audit('Auth', 'Remember Me Revoked', "Revoked remember token for disabled user: {$u->email}");
            }
        }
    }
}
// ============================================================================
// Category Maintenance Helpers
// ============================================================================

function ensure_category_table() {
    global $_db;
    $_db->exec("CREATE TABLE IF NOT EXISTS category (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function slugify($text) {
    $text = preg_replace('~[^\\pL0-9]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-a-z0-9]+~', '', strtolower($text));
    $text = trim($text, '-');
    return $text === '' ? 'n-a' : $text;
}

function cache_categories($force = false) {
    static $cache = null;
    if ($cache !== null && !$force) return $cache;
    ensure_category_table();
    global $_db;
    $stm = $_db->query('SELECT * FROM category ORDER BY sort_order, name');
    $cache = $stm->fetchAll();
    return $cache;
}

function get_categories($opts = []) {
    ensure_category_table();
    global $_db;
    $where = [];
    $params = [];
    if (isset($opts['active'])) {
        $where[] = 'active = ?';
        $params[] = $opts['active'] ? 1 : 0;
    }
    $sql = 'SELECT * FROM category' . (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)) . ' ORDER BY sort_order, name';
    $stm = $_db->prepare($sql);
    $stm->execute($params);
    return $stm->fetchAll();
}

function get_category($id) {
    ensure_category_table();
    global $_db;
    $stm = $_db->prepare('SELECT * FROM category WHERE id = ?');
    $stm->execute([$id]);
    return $stm->fetch();
}

function validate_category($data) {
    $err = [];
    $name = trim($data['name'] ?? '');
    if ($name === '') $err['name'] = 'Required';
    return [$err, $name];
}

function create_category($data) {
    ensure_category_table();
    global $_db;
    [$err, $name] = validate_category($data);
    if ($err) return ['ok' => false, 'error' => $err];
    $slug = trim($data['slug'] ?? slugify($name));
    $active = isset($data['active']) && $data['active'] ? 1 : 0;
    $sort = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;
    $stm = $_db->prepare('INSERT INTO category (name, slug, active, sort_order) VALUES (?, ?, ?, ?)');
    $stm->execute([$name, $slug, $active, $sort]);
    $id = (int) $_db->lastInsertId();
    audit('Categories', 'Category Created', "Created category: $name (id:$id)");
    return ['ok' => true, 'id' => $id];
}

function update_category($id, $data) {
    ensure_category_table();
    global $_db;
    [$err, $name] = validate_category($data);
    if ($err) return ['ok' => false, 'error' => $err];
    $slug = trim($data['slug'] ?? slugify($name));
    $active = isset($data['active']) && $data['active'] ? 1 : 0;
    $sort = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;
    $stm = $_db->prepare('UPDATE category SET name = ?, slug = ?, active = ?, sort_order = ? WHERE id = ?');
    $stm->execute([$name, $slug, $active, $sort, $id]);
    audit('Categories', 'Category Updated', "Updated category: $name (id:$id)");
    return ['ok' => true];
}

function category_in_use($id) {
    ensure_category_table();
    $c = get_category($id);
    if (!$c) return false;
    global $_db;
    $stm = $_db->prepare('SELECT COUNT(*) FROM product WHERE tag = ?');
    $stm->execute([$c->name]);
    return (int) $stm->fetchColumn() > 0;
}

function reassign_products($from_id, $to_id) {
    ensure_category_table();
    $from = get_category($from_id);
    $to = get_category($to_id);
    if (!$from || !$to) return ['ok' => false, 'error' => 'Invalid category id'];
    global $_db;
    $stm = $_db->prepare('UPDATE product SET tag = ? WHERE tag = ?');
    $stm->execute([$to->name, $from->name]);
    audit('Categories', 'Reassigned Products', "Reassigned products from {$from->name} to {$to->name}");
    return ['ok' => true, 'rows' => $stm->rowCount()];
}

function delete_category($id, $force = false) {
    ensure_category_table();
    if (category_in_use($id) && !$force) return ['ok' => false, 'error' => 'Category in use'];
    global $_db;
    $stm = $_db->prepare('DELETE FROM category WHERE id = ?');
    $stm->execute([$id]);
    audit('Categories', 'Category Deleted', "Deleted category id: $id");
    return ['ok' => true];
}

function get_category_select($name = 'category', $selected = null, $attr = '') {
    $cats = get_categories(['active' => 1]);
    $html = "<select name='$name' $attr>";
    $html .= "<option value=''>- Select One -</option>";
    foreach ($cats as $c) {
        $sel = ($selected == $c->name) ? 'selected' : '';
        $html .= "<option value='{$c->name}' $sel>" . encode($c->name) . "</option>";
    }
    $html .= "</select>";
    return $html;
}

function reorder_categories($ids) {
    ensure_category_table();
    global $_db;
    $i = 0;
    foreach ($ids as $id) {
        $stm = $_db->prepare('UPDATE category SET sort_order = ? WHERE id = ?');
        $stm->execute([$i++, $id]);
    }
    audit('Categories', 'Categories Reordered', 'Reordered categories');
    return ['ok' => true];
}

function toggle_category_active($id, $state) {
    ensure_category_table();
    global $_db;
    $stm = $_db->prepare('UPDATE category SET active = ? WHERE id = ?');
    $stm->execute([$state ? 1 : 0, $id]);
    audit('Categories', 'Toggled Active', "Category $id active={$state}");
    return ['ok' => true];
}

function export_categories_csv() {
    $cats = get_categories();
    $out = fopen('php://memory', 'r+');
    fputcsv($out, ['id', 'name', 'slug', 'active', 'sort_order']);
    foreach ($cats as $c) {
        fputcsv($out, [$c->id, $c->name, $c->slug, $c->active, $c->sort_order]);
    }
    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);
    return $csv;
}

function import_categories_csv($csv) {
    ensure_category_table();
    $tmp = tmpfile();
    fwrite($tmp, $csv);
    rewind($tmp);
    $r = 0;
    while (($row = fgetcsv($tmp)) !== false) {
        if ($row[0] === 'id' || empty($row[1])) continue; // skip header or invalid
        $name = $row[1];
        $slug = $row[2] ?? slugify($name);
        $active = isset($row[3]) ? (int)$row[3] : 1;
        $sort = isset($row[4]) ? (int)$row[4] : 0;
        // Upsert by name
        global $_db;
        $stm = $_db->prepare('SELECT id FROM category WHERE name = ?');
        $stm->execute([$name]);
        $exist = $stm->fetchColumn();
        if ($exist) {
            $stm = $_db->prepare('UPDATE category SET slug = ?, active = ?, sort_order = ? WHERE id = ?');
            $stm->execute([$slug, $active, $sort, $exist]);
        } else {
            $stm = $_db->prepare('INSERT INTO category (name, slug, active, sort_order) VALUES (?, ?, ?, ?)');
            $stm->execute([$name, $slug, $active, $sort]);
        }
        $r++;
    }
    fclose($tmp);
    audit('Categories', 'Imported CSV', "Imported $r categories");
    return ['ok' => true, 'rows' => $r];
}

// ============================================================================
// Order cancellation
// ============================================================================

function ensure_order_columns() {
    global $_db;
    try {
        $check = $_db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order' AND COLUMN_NAME = 'status'");
        $check->execute();
        $has = (int) $check->fetchColumn();
        if ($has === 0) {
            $_db->exec("ALTER TABLE `order` ADD COLUMN `status` varchar(20) NOT NULL DEFAULT 'completed'");
        }
        $check = $_db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order' AND COLUMN_NAME = 'cancelled_at'");
        $check->execute();
        if ((int)$check->fetchColumn() === 0) {
            $_db->exec("ALTER TABLE `order` ADD COLUMN `cancelled_at` datetime DEFAULT NULL");
        }
        $check = $_db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order' AND COLUMN_NAME = 'cancelled_by'");
        $check->execute();
        if ((int)$check->fetchColumn() === 0) {
            $_db->exec("ALTER TABLE `order` ADD COLUMN `cancelled_by` int(11) DEFAULT NULL");
        }
        $check = $_db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order' AND COLUMN_NAME = 'cancel_reason'");
        $check->execute();
        if ((int)$check->fetchColumn() === 0) {
            $_db->exec("ALTER TABLE `order` ADD COLUMN `cancel_reason` varchar(255) DEFAULT NULL");
        }
    } catch (Exception $e) {
        // If the table itself doesn't exist or ALTER fails, swallow and allow calling code to handle errors
    }
}

function is_order_cancellable($order_id) {
    global $_db;
    ensure_order_columns();
    try {
        $stm = $_db->prepare('SELECT status FROM `order` WHERE id = ?');
        $stm->execute([$order_id]);
        $s = $stm->fetchColumn();
        // User may request cancellation only while order is still 'completed'
        // ('pending' = awaiting admin approval, 'cancelled'/'refunded' = final)
        return $s !== false ? $s === 'completed' : true;
    } catch (Exception $e) {
        return true; // If we can't read status, assume cancellable to avoid blocking user
    }
}

// (User) Request order cancellation — status becomes 'pending' until admin approves
function request_cancel_order($order_id, $reason = null) {
    global $_db, $_user;

    $stm = $_db->prepare('SELECT * FROM `order` WHERE id = ?');
    $stm->execute([$order_id]);
    $o = $stm->fetch();
    if (!$o) return ['ok' => false, 'error' => 'Order not found'];
    if (($o->status ?? 'completed') !== 'completed') {
        return ['ok' => false, 'error' => 'Order cannot be cancelled'];
    }

    $cancel_reason = $reason ? substr($reason, 0, 255) : null;
    $stm = $_db->prepare('UPDATE `order` SET status = ?, cancelled_at = NOW(), cancelled_by = ?, cancel_reason = ? WHERE id = ?');
    $stm->execute(['pending', $_user->id ?? null, $cancel_reason, $order_id]);

    audit('Orders', 'Cancellation Requested', "Order $order_id cancellation requested by " . ($_user->id ?? 'system') . ($reason ? " Reason: $reason" : ''));
    return ['ok' => true];
}

// (Admin) Approve a cancellation — restock items and refund points.
// Works on a 'pending' request (approval) or directly on a 'completed' order (admin instant cancel).
function approve_cancel_order($order_id) {
    global $_db, $_user;

    $stm = $_db->prepare('SELECT * FROM `order` WHERE id = ? FOR UPDATE');
    $stm->execute([$order_id]);
    $o = $stm->fetch();
    if (!$o) return ['ok' => false, 'error' => 'Order not found'];
    if (!in_array(($o->status ?? ''), ['pending', 'completed'])) {
        return ['ok' => false, 'error' => 'Order cannot be cancelled'];
    }

    try {
        $_db->beginTransaction();

        // Mark order as cancelled (approved by admin)
        $upd = $_db->prepare('UPDATE `order` SET status = ?, cancelled_at = NOW(), cancelled_by = ? WHERE id = ?');
        $upd->execute(['cancelled', $_user->id ?? null, $order_id]);

        // Restock items and log stock history
        $stm = $_db->prepare('SELECT * FROM item WHERE order_id = ?');
        $stm->execute([$order_id]);
        $items = $stm->fetchAll();
        foreach ($items as $it) {
            $pstm = $_db->prepare('SELECT stock FROM product WHERE id = ? FOR UPDATE');
            $pstm->execute([$it->product_id]);
            $old = (int) $pstm->fetchColumn();
            $new = $old + (int) $it->unit;
            $ustm = $_db->prepare('UPDATE product SET stock = ? WHERE id = ?');
            $ustm->execute([$new, $it->product_id]);
            log_stock($it->product_id, 'edited', $old, $new);
        }

        // Refund points used (simple restore)
        if (!empty($o->points_used)) {
            $ust = $_db->prepare('UPDATE user SET points = points + ? WHERE id = ?');
            $ust->execute([(int)$o->points_used, $o->user_id]);
        }

        $_db->commit();

        audit('Orders', 'Cancellation Approved', "Order $order_id cancellation approved by " . ($_user->id ?? 'system'));
        return ['ok' => true];
    }
    catch (Exception $e) {
        $_db->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// (Admin) Reject a pending cancellation — restore order to 'completed'
function reject_cancel_order($order_id) {
    global $_db, $_user;

    $stm = $_db->prepare('SELECT * FROM `order` WHERE id = ?');
    $stm->execute([$order_id]);
    $o = $stm->fetch();
    if (!$o) return ['ok' => false, 'error' => 'Order not found'];
    if (($o->status ?? '') !== 'pending') return ['ok' => false, 'error' => 'Order is not pending cancellation'];

    $stm = $_db->prepare('UPDATE `order` SET status = ?, cancelled_at = NULL, cancelled_by = NULL, cancel_reason = NULL WHERE id = ?');
    $stm->execute(['completed', $order_id]);

    audit('Orders', 'Cancellation Rejected', "Order $order_id cancellation rejected by " . ($_user->id ?? 'system'));
    return ['ok' => true];
}

// ============================================================================
// Stock Order (Admin) — admin restock from supplier (distinct from member purchase orders)
// ============================================================================

// Ensure stock_order tables exist, plus extra columns (supplier / expected delivery)
function ensure_stock_order_columns() {
    global $_db;
    try {
        // Create base tables when missing (fresh databases)
        $_db->exec("CREATE TABLE IF NOT EXISTS stock_order (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            created_by INT NOT NULL,
            datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            note VARCHAR(255) DEFAULT NULL,
            supplier VARCHAR(100) DEFAULT NULL,
            expected_at DATETIME DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            received_at DATETIME DEFAULT NULL,
            received_by INT DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $_db->exec("CREATE TABLE IF NOT EXISTS stock_order_item (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            stock_order_id INT NOT NULL,
            product_id VARCHAR(10) NOT NULL,
            qty INT NOT NULL DEFAULT 0,
            price DECIMAL(10,2) DEFAULT NULL,
            KEY idx_stock_order (stock_order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $check = $_db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_order' AND COLUMN_NAME = 'supplier'");
        $check->execute();
        if ((int)$check->fetchColumn() === 0) {
            $_db->exec("ALTER TABLE `stock_order` ADD COLUMN `supplier` varchar(100) DEFAULT NULL");
        }
        $check = $_db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_order' AND COLUMN_NAME = 'expected_at'");
        $check->execute();
        if ((int)$check->fetchColumn() === 0) {
            $_db->exec("ALTER TABLE `stock_order` ADD COLUMN `expected_at` datetime DEFAULT NULL");
        }
    } catch (Exception $e) {
        // Table may not exist yet — ignore
    }
}

function create_stock_order($created_by, $items = [], $note = null, $supplier = null, $expected_at = null) {
    global $_db;
    ensure_stock_order_columns();
    try {
        $_db->beginTransaction();
        $stm = $_db->prepare('INSERT INTO stock_order (created_by, note, supplier, expected_at) VALUES (?, ?, ?, ?)');
        $stm->execute([$created_by, $note, $supplier, $expected_at]);
        $order_id = $_db->lastInsertId();

        $istm = $_db->prepare('INSERT INTO stock_order_item (stock_order_id, product_id, qty, price) VALUES (?, ?, ?, ?)');
        foreach ($items as $it) {
            $pid = $it['product_id'] ?? null;
            $qty = (int) ($it['qty'] ?? 0);
            $price = isset($it['price']) ? (float)$it['price'] : null;
            if (!$pid || $qty <= 0) continue;
            $istm->execute([$order_id, $pid, $qty, $price]);
        }

        $_db->commit();
        audit('Stock Orders', 'Created stock order', "Stock order $order_id created by $created_by");
        return ['ok' => true, 'id' => $order_id];
    } catch (Exception $e) {
        $_db->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function receive_stock_order($order_id, $received_by) {
    global $_db, $_user;
    try {
        $_db->beginTransaction();

        $stm = $_db->prepare('SELECT * FROM stock_order WHERE id = ? FOR UPDATE');
        $stm->execute([$order_id]);
        $o = $stm->fetch();
        if (!$o) throw new Exception('Stock order not found');
        if ($o->status === 'received') throw new Exception('Stock order already received');

        $itstm = $_db->prepare('SELECT * FROM stock_order_item WHERE stock_order_id = ?');
        $itstm->execute([$order_id]);
        $items = $itstm->fetchAll();

        foreach ($items as $it) {
            $pstm = $_db->prepare('SELECT stock FROM product WHERE id = ? FOR UPDATE');
            $pstm->execute([$it->product_id]);
            $old = (int) $pstm->fetchColumn();
            $new = $old + (int) $it->qty;
            $ustm = $_db->prepare('UPDATE product SET stock = ? WHERE id = ?');
            $ustm->execute([$new, $it->product_id]);
            log_stock($it->product_id, 'added', $old, $new);
        }

        $ust = $_db->prepare('UPDATE stock_order SET status = ?, received_at = NOW(), received_by = ? WHERE id = ?');
        $ust->execute(['received', $received_by, $order_id]);

        $_db->commit();
        audit('Stock Orders', 'Received stock order', "Stock order $order_id received by $received_by");
        return ['ok' => true];
    } catch (Exception $e) {
        $_db->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}
