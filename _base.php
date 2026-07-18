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
    $_SESSION['user'] = $user;
    redirect($url);
}

// Logout user
function logout($url = '/') {
    unset($_SESSION['user']);
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
    global $_db, $_user;

    $user_id    = $_user->id ?? null;
    $username   = $_user->name ?? null;
    $user_role  = $_user->role ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $stm = $_db->prepare('
        INSERT INTO audit_log (user_id, username, user_role, action, module, description, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stm->execute([$user_id, $username, $user_role, $action, $module, $description, $ip_address]);
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

// Get shopping cart
function get_cart() {
    return $_SESSION['cart'] ?? [];
}

// Set shopping cart
function set_cart($cart = []) {
    $_SESSION['cart'] = $cart;
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