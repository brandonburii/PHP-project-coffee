<?php
include '../_base.php';

auth('Admin');

ensure_category_table();

// Find distinct non-empty tags from products
$stm = $_db->query("SELECT DISTINCT tag FROM product WHERE tag IS NOT NULL AND tag != '' ORDER BY tag");
$tags = $stm->fetchAll(PDO::FETCH_COLUMN);

$added = 0;
foreach ($tags as $t) {
    // Skip empty
    $name = trim($t);
    if ($name === '') continue;

    // Check exists
    $check = $_db->prepare('SELECT id FROM category WHERE name = ?');
    $check->execute([$name]);
    if ($check->fetchColumn()) continue;

    $slug = slugify($name);
    $ins = $_db->prepare('INSERT INTO category (name, slug, active, sort_order) VALUES (?, ?, 1, 0)');
    $ins->execute([$name, $slug]);
    $added++;
}

audit('Categories', 'Migrated from product tags', "Created $added categories from product tags");
temp('info', "Created $added categories from product tags");
redirect('category_list.php');
