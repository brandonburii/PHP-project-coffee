<?php

include '../_base.php';

auth('Member', 'Admin');

header('Content-Type: application/json');


// =========================================================
// GET DATA
// =========================================================

$order_id = req('order_id');

$email = trim(
    req('email')
);


// =========================================================
// VALIDATE ORDER ID
// =========================================================

if (!ctype_digit((string)$order_id)) {

    echo json_encode([

        'ok' => false,

        'message' =>
            'Invalid order ID.'

    ]);

    exit;

}


// =========================================================
// VALIDATE EMAIL
// =========================================================

if (
    empty($email) ||
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {

    echo json_encode([

        'ok' => false,

        'message' =>
            'Please enter a valid email address.'

    ]);

    exit;

}


// =========================================================
// GET ORDER
// =========================================================

if ($_user->role == 'Admin') {

    $stm = $_db->prepare('
        SELECT
            o.*,
            u.name AS customer_name
        FROM `order` o

        JOIN user u
            ON o.user_id = u.id

        WHERE o.id = ?
    ');

    $stm->execute([
        $order_id
    ]);

}
else {

    $stm = $_db->prepare('
        SELECT
            o.*,
            u.name AS customer_name
        FROM `order` o

        JOIN user u
            ON o.user_id = u.id

        WHERE o.id = ?

        AND o.user_id = ?
    ');

    $stm->execute([

        $order_id,

        $_user->id

    ]);

}


$order = $stm->fetch();


if (!$order) {

    echo json_encode([

        'ok' => false,

        'message' =>
            'Order not found.'

    ]);

    exit;

}


// =========================================================
// GET ORDER ITEMS
// =========================================================

$stm = $_db->prepare('
    SELECT
        i.*,
        p.name

    FROM item i

    JOIN product p
        ON i.product_id = p.id

    WHERE i.order_id = ?

    ORDER BY i.id
');

$stm->execute([
    $order_id
]);

$items = $stm->fetchAll();


// =========================================================
// BUILD ITEM ROWS
// =========================================================

$item_rows = '';


foreach ($items as $item) {

    $name = htmlspecialchars(
        $item->name,
        ENT_QUOTES,
        'UTF-8'
    );


    $qty = (int)$item->unit;


    $price = number_format(
        $item->price,
        2
    );


    $subtotal = number_format(
        $item->subtotal,
        2
    );


    $item_rows .= "

        <tr>

            <td style=\"
                padding:10px;
                border-bottom:1px solid #eee;
            \">

                {$name}

            </td>


            <td style=\"
                padding:10px;
                text-align:center;
                border-bottom:1px solid #eee;
            \">

                {$qty}

            </td>


            <td style=\"
                padding:10px;
                text-align:right;
                border-bottom:1px solid #eee;
            \">

                RM {$price}

            </td>


            <td style=\"
                padding:10px;
                text-align:right;
                border-bottom:1px solid #eee;
            \">

                RM {$subtotal}

            </td>

        </tr>

    ";

}


// =========================================================
// CUSTOMER NAME
// =========================================================

$customer_name = htmlspecialchars(

    $order->customer_name,

    ENT_QUOTES,

    'UTF-8'

);


// =========================================================
// VOUCHER
// =========================================================

$voucher_html = '';


if (!empty($order->voucher_code)) {

    $voucher = htmlspecialchars(

        $order->voucher_code,

        ENT_QUOTES,

        'UTF-8'

    );


    $voucher_html = "

        <tr>

            <td colspan=\"3\"
                style=\"padding:8px;\">

                Voucher

            </td>


            <td style=\"
                padding:8px;
                text-align:right;
            \">

                {$voucher}

            </td>

        </tr>

    ";

}


// =========================================================
// EMAIL BODY
// =========================================================

$email_body = '

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<title>
E-Receipt
</title>

</head>


<body style="
    margin:0;
    padding:30px;
    background:#f5f5f5;
    font-family:Arial,Helvetica,sans-serif;
">


<div style="
    max-width:700px;
    margin:auto;
    background:white;
    padding:35px;
    border-radius:10px;
">


    <!-- HEADER -->

    <div style="
        text-align:center;
        border-bottom:2px dashed #ddd;
        padding-bottom:20px;
    ">

        <div style="
            font-size:40px;
        ">
            ☕
        </div>


        <h1>
            Coffee Shop
        </h1>


        <p style="
            color:#777;
        ">
            Thank you for your purchase!
        </p>

    </div>


    <!-- TITLE -->

    <div style="
        text-align:center;
        padding:20px;
    ">

        <h2>
            E-RECEIPT
        </h2>


        <p>
            Receipt #' .
            (int)$order->id .
            '
        </p>

    </div>


    <!-- CUSTOMER -->

    <div style="
        background:#fafafa;
        padding:15px;
        border-radius:8px;
        margin-bottom:25px;
    ">


        <p>

            <strong>
                Customer:
            </strong>

            ' .
            $customer_name .
            '

        </p>


        <p>

            <strong>
                Email:
            </strong>

            ' .
            htmlspecialchars(
                $email,
                ENT_QUOTES,
                'UTF-8'
            ) .
            '

        </p>


        <p>

            <strong>
                Date:
            </strong>

            ' .
            date(
                'd/m/Y h:i A',
                strtotime(
                    $order->datetime
                )
            ) .
            '

        </p>


    </div>


    <!-- PRODUCTS -->

    <table style="
        width:100%;
        border-collapse:collapse;
    ">


        <thead>

            <tr style="
                background:#f5f5f5;
            ">


                <th style="
                    padding:10px;
                    text-align:left;
                ">

                    Product

                </th>


                <th style="
                    padding:10px;
                ">

                    Qty

                </th>


                <th style="
                    padding:10px;
                    text-align:right;
                ">

                    Price

                </th>


                <th style="
                    padding:10px;
                    text-align:right;
                ">

                    Total

                </th>


            </tr>

        </thead>


        <tbody>

            ' .
            $item_rows .
            '

        </tbody>


    </table>


    <!-- SUMMARY -->

    <table style="
        width:100%;
        margin-top:25px;
        border-collapse:collapse;
    ">


        <tr>

            <td
                colspan="3"
                style="padding:8px;"
            >

                Subtotal

            </td>


            <td
                style="
                    padding:8px;
                    text-align:right;
                "
            >

                RM ' .
                number_format(
                    $order->subtotal,
                    2
                ) .
                '

            </td>

        </tr>


        <tr>

            <td
                colspan="3"
                style="padding:8px;"
            >

                Discount

            </td>


            <td
                style="
                    padding:8px;
                    text-align:right;
                    color:#b00020;
                "
            >

                - RM ' .
                number_format(
                    $order->discount,
                    2
                ) .
                '

            </td>

        </tr>


        ' .
        $voucher_html .
        '


        <tr>

            <td
                colspan="3"
                style="
                    padding:15px 8px;
                    border-top:2px solid #333;
                    font-size:18px;
                    font-weight:bold;
                "
            >

                Total

            </td>


            <td
                style="
                    padding:15px 8px;
                    border-top:2px solid #333;
                    text-align:right;
                    font-size:18px;
                    font-weight:bold;
                "
            >

                RM ' .
                number_format(
                    $order->total,
                    2
                ) .
                '

            </td>

        </tr>


    </table>


    <!-- POINTS -->

    <div style="
        margin-top:25px;
        padding:15px;
        background:#fff8e8;
        border-radius:8px;
        text-align:center;
    ">

        ⭐ Points Used:

        <strong>

            ' .
            (int)$order->points_used .
            '

        </strong>


        &nbsp;&nbsp;


        ⭐ Points Earned:

        <strong>

            ' .
            (int)$order->points_earned .
            '

        </strong>

    </div>


    <!-- FOOTER -->

    <div style="
        margin-top:30px;
        padding-top:20px;
        border-top:2px dashed #ddd;
        text-align:center;
        color:#777;
    ">


        <p>
            Thank you for shopping with us!
        </p>


        <p>
            Please keep this email for your records.
        </p>


        <small>
            This is an electronically generated receipt.
        </small>


    </div>


</div>

</body>

</html>
';


// =========================================================
// EMAIL SUBJECT
// =========================================================

$subject =
    'E-Receipt #' .
    $order->id .
    ' - Coffee Shop';


// =========================================================
// EMAIL HEADERS
// =========================================================

$headers =
    "MIME-Version: 1.0\r\n";

$headers .=
    "Content-Type: text/html; charset=UTF-8\r\n";

$headers .=
    "From: Coffee Shop <no-reply@yourdomain.com>\r\n";


// =========================================================
// SEND EMAIL
// =========================================================

$sent = mail(

    $email,

    $subject,

    $email_body,

    $headers

);


// =========================================================
// RESULT
// =========================================================

if ($sent) {

    audit(

        'Orders',

        'E-receipt sent',

        "E-receipt sent for order ID: " .
        $order_id .
        " to " .
        $email

    );


    echo json_encode([

        'ok' => true,

        'message' =>
            'E-receipt sent successfully.'

    ]);

}
else {

    echo json_encode([

        'ok' => false,

        'message' =>
            'Unable to send email. Please check your mail server configuration.'

    ]);

}