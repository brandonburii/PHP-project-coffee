<?php
include '../_base.php';

auth('Admin');

ensure_category_table();

// Heuristic mapping rules
$rules = [
    'Tea' => ['matcha', 'tea', 'earl', 'jasmine'],
    'Equipment' => ['press', 'dripper', 'v60', 'french press'],
    'Accessories' => ['mug', 'tumbler', 'cup'],
    'Coffee' => ['coffee', 'espresso', 'blend', 'ethiopia', 'colombia', 'brazil', 'house'],
];

// Normalize helper
function norm($s) {
    return strtolower(trim($s));
}

$stm = $_db->query('SELECT id, name FROM product');
$prods = $stm->fetchAll(PDO::FETCH_OBJ);

$created = 0;
$updated = 0;

// Ensure category exists helper
function ensure_cat_by_name($name) {
    global $_db, $created;
    $check = $_db->prepare('SELECT id FROM category WHERE name = ?');
    $check->execute([$name]);
    if ($check->fetchColumn()) return true;
    $slug = slugify($name);
    $ins = $_db->prepare('INSERT INTO category (name, slug, active, sort_order) VALUES (?, ?, 1, 0)');
    $ins->execute([$name, $slug]);
    $created++;
    return true;
}

foreach ($prods as $p) {
    $lname = norm($p->name);
    $assigned = null;
    foreach ($rules as $cat => $kw) {
        foreach ($kw as $k) {
            if (str_contains($lname, $k)) {
                $assigned = $cat;
                break 2;
            }
        }
    }
    if (!$assigned) $assigned = 'Misc';

    // create category if missing
    ensure_cat_by_name($assigned);

    // update product tag if different
    $u = $_db->prepare('UPDATE product SET tag = ? WHERE id = ?');
    $u->execute([$assigned, $p->id]);
    if ($u->rowCount() > 0) $updated++;
}

audit('Categories', 'Generated from products', "Created $created categories, updated $updated products");
temp('info', "Created $created categories, updated $updated products");
redirect('category_list.php');
