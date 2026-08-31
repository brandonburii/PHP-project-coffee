<?php
include '../_base.php';

auth('Member');

$order_id = $_GET['order_id'] ?? null;
if (!$order_id) {
    echo json_encode(['products' => []]);
    exit;
}

// Verify order belongs to user
$stm = $_db->prepare('SELECT * FROM `order` WHERE id = ? AND user_id = ?');
$stm->execute([$order_id, $_user->id]);
$order = $stm->fetch();

if (!$order) {
    echo json_encode(['products' => []]);
    exit;
}

// Get products from order
$stm = $_db->prepare('
    SELECT i.product_id as id, p.name
    FROM item i
    JOIN product p ON i.product_id = p.id
    WHERE i.order_id = ?
');
$stm->execute([$order_id]);
$products = $stm->fetchAll(PDO::FETCH_OBJ);

echo json_encode(['products' => $products]);
?>