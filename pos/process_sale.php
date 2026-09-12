<?php

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {

    header("Location: ../login.php");
    exit;

}


$user_id = $_SESSION['user_id'];

$role = $_SESSION['role'] ?? '';


if (!in_array($role, ['admin', 'cashier'])) {

    die("Access denied.");

}


/*
|--------------------------------------------------------------------------
| Validate POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: index.php");
    exit;

}


if (empty($_POST['cart'])) {

    die("Cart is empty.");

}


$cart =
    json_decode($_POST['cart'], true);


if (!is_array($cart) || count($cart) === 0) {

    die("Invalid cart.");

}


$payment_method =
    $_POST['payment_method'] ?? 'CASH';


$allowedPayments = [
    'CASH',
    'GCASH',
    'CARD',
    'OTHER'
];


if (!in_array($payment_method, $allowedPayments)) {

    die("Invalid payment method.");

}


$amount_tendered =
    (float) ($_POST['amount_tendered'] ?? 0);


/*
|--------------------------------------------------------------------------
| Get Open Session
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM cashier_sessions
    WHERE user_id = ?
      AND status = 'open'
    ORDER BY id DESC
    LIMIT 1
");

$stmt->execute([$user_id]);

$session =
    $stmt->fetch(PDO::FETCH_ASSOC);


if (!$session) {

    die("No open cashier session. Please open a cashier session first.");

}


$session_id =
    $session['id'];


/*
|--------------------------------------------------------------------------
| Start Database Transaction
|--------------------------------------------------------------------------
*/

$pdo->beginTransaction();


try {

    $subtotal = 0;

    $validatedItems = [];


    /*
    |--------------------------------------------------------------------------
    | Validate Products From Database
    |--------------------------------------------------------------------------
    */

    foreach ($cart as $item) {

        $product_id =
            (int) ($item['id'] ?? 0);

        $quantity =
            (float) ($item['quantity'] ?? 0);


        if ($product_id <= 0 || $quantity <= 0) {

            throw new Exception(
                "Invalid product or quantity."
            );

        }


        /*
        | Lock product row
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                product_name,
                cost_price,
                selling_price,
                stock,
                status
            FROM products
            WHERE id = ?
            FOR UPDATE
        ");

        $stmt->execute([$product_id]);

        $product =
            $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$product) {

            throw new Exception(
                "Product not found."
            );

        }


        if ($product['status'] !== 'active') {

            throw new Exception(
                "Product is inactive: "
                . $product['product_name']
            );

        }


        if ($quantity > (float) $product['stock']) {

            throw new Exception(
                "Insufficient stock for: "
                . $product['product_name']
            );

        }


        $price =
            (float) $product['selling_price'];


        $lineTotal =
            $price * $quantity;


        $subtotal += $lineTotal;


        $validatedItems[] = [

            'id' =>
                $product['id'],

            'name' =>
                $product['product_name'],

            'quantity' =>
                $quantity,

            'cost_price' =>
                (float) $product['cost_price'],

            'selling_price' =>
                $price,

            'stock_before' =>
                (float) $product['stock'],

            'subtotal' =>
                $lineTotal

        ];

    }


    /*
    |--------------------------------------------------------------------------
    | Calculate Total
    |--------------------------------------------------------------------------
    */

    $discount = 0;

    $tax = 0;

    $total =
        $subtotal - $discount + $tax;


    /*
    |--------------------------------------------------------------------------
    | Validate Payment
    |--------------------------------------------------------------------------
    */

    if ($payment_method === 'CASH') {

        if ($amount_tendered < $total) {

            throw new Exception(
                "Insufficient payment."
            );

        }

    } else {

        $amount_tendered =
            $total;

    }


    $change =
        $amount_tendered - $total;


    /*
    |--------------------------------------------------------------------------
    | Generate Transaction Number
    |--------------------------------------------------------------------------
    */

    $transaction_no =
        'TXN-' .
        date('Ymd-His') .
        '-' .
        strtoupper(
            substr(
                uniqid(),
                -4
            )
        );


    /*
    |--------------------------------------------------------------------------
    | Insert Sale
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO sales
        (
            transaction_no,
            cashier_session_id,
            cashier_id,
            subtotal,
            discount,
            tax,
            total_amount,
            amount_tendered,
            change_amount,
            payment_method,
            status
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'COMPLETED'
        )
    ");


    $stmt->execute([

        $transaction_no,

        $session_id,

        $user_id,

        $subtotal,

        $discount,

        $tax,

        $total,

        $amount_tendered,

        $change,

        $payment_method

    ]);


    $sale_id =
        $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | Insert Sale Items + Deduct Stock
    |--------------------------------------------------------------------------
    */

    foreach ($validatedItems as $item) {

        /*
        | Sale Item
        */

        $stmt = $pdo->prepare("
            INSERT INTO sale_items
            (
                sale_id,
                product_id,
                product_name,
                quantity,
                cost_price,
                selling_price,
                discount,
                subtotal
            )
            VALUES
            (?, ?, ?, ?, ?, ?, 0, ?)
        ");


        $stmt->execute([

            $sale_id,

            $item['id'],

            $item['name'],

            $item['quantity'],

            $item['cost_price'],

            $item['selling_price'],

            $item['subtotal']

        ]);


        /*
        | Deduct Product Stock
        */

        $stock_before =
            $item['stock_before'];


        $stock_after =
            $stock_before -
            $item['quantity'];


        $stmt = $pdo->prepare("
            UPDATE products
            SET stock = ?
            WHERE id = ?
        ");


        $stmt->execute([

            $stock_after,

            $item['id']

        ]);


        /*
        | Inventory Movement
        */

        $stmt = $pdo->prepare("
            INSERT INTO inventory_movements
            (
                product_id,
                user_id,
                movement_type,
                reference_type,
                reference_id,
                quantity,
                stock_before,
                stock_after,
                remarks
            )
            VALUES
            (
                ?,
                ?,
                'SALE',
                'SALE',
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");


        $stmt->execute([

            $item['id'],

            $user_id,

            $sale_id,

            $item['quantity'],

            $stock_before,

            $stock_after,

            'POS Sale ' . $transaction_no

        ]);

    }


    /*
    |--------------------------------------------------------------------------
    | Audit Log
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO audit_logs
        (
            user_id,
            action,
            module,
            reference_id,
            description,
            ip_address
        )
        VALUES
        (?, 'SALE', 'POS', ?, ?, ?)
    ");


    $stmt->execute([

        $user_id,

        $sale_id,

        'Completed POS sale ' .
        $transaction_no .
        ' - Total ₱' .
        number_format($total, 2),

        $_SERVER['REMOTE_ADDR'] ?? null

    ]);


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Receipt
    |--------------------------------------------------------------------------
    */

    header(
        "Location: receipt.php?id=" .
        $sale_id
    );

    exit;


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }


    die(
        "<h3>Transaction Failed</h3>" .
        "<p>" .
        htmlspecialchars($e->getMessage()) .
        "</p>" .
        "<a href='index.php'>Return to POS</a>"
    );

}