<?php
include '../_base.php';

// ----------------------------------------------------------------------------

// (1) Authorization (member)
auth('Member');

if (is_post()) {
    // (2) Get shopping cart (reject if empty)
    $cart = get_cart();
    if (empty($cart)) {
        redirect('cart.php');
    }

    // ------------------------------------------
    // DB transaction (insert order and items)
    // ------------------------------------------

    // (A) Begin transaction
    $_db->beginTransaction();

    try {
        // (B) Insert order, keep order id
        $stm = $_db->prepare('INSERT INTO `order` (datetime, count, total, user_id) VALUES (NOW(), 0, 0.00, ?)');
        $stm->execute([$_user->id]);
        $order_id = $_db->lastInsertId();

        // (C) Insert items
        $count = 0;
        $total = 0.00;

        $stm_prod = $_db->prepare('SELECT price, stock FROM product WHERE id = ? FOR UPDATE');
        $stm_item = $_db->prepare('INSERT INTO item (order_id, product_id, price, unit, subtotal) VALUES (?, ?, ?, ?, ?)');
        $stm_deduct = $_db->prepare('UPDATE product SET stock = stock - ? WHERE id = ?');

        foreach ($cart as $id => $unit) {
            $stm_prod->execute([$id]);
            $p = $stm_prod->fetch();
            if ($p) {
                if ($p->stock < $unit) {
                    throw new Exception("Product '{$id}' is out of stock or has insufficient quantity (Available: {$p->stock})");
                }

                $subtotal = $p->price * $unit;
                $count += $unit;
                $total += $subtotal;

                $stm_item->execute([$order_id, $id, $p->price, $unit, $subtotal]);
                $stm_deduct->execute([$unit, $id]);
            }
        }

        // (D) Update order (count and total)
        $stm_update = $_db->prepare('UPDATE `order` SET count = ?, total = ? WHERE id = ?');
        $stm_update->execute([$count, $total, $order_id]);

        // (E) Commit transcation
        $_db->commit();

        audit('Orders', 'Checkout completed', "Checked out order ID $order_id with count: $count, total: $total");

        // (3) Clear shopping cart
        set_cart();

        // (4) Redirect to detail.php?id=XXX
        temp('info', 'Checkout successful');
        redirect("detail.php?id=$order_id");
    }
    catch (Exception $e) {
        $_db->rollBack();
        temp('info', 'Checkout failed: ' . $e->getMessage());
        redirect('cart.php');
    }
}

redirect('cart.php');
