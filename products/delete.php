<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/database.php";

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {

    header(
        "Location: index.php?error=" .
        urlencode("Invalid product.")
    );

    exit;
}


$stmt = $pdo->prepare("
    SELECT id, product_name
    FROM products
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$product = $stmt->fetch();

if (!$product) {

    header(
        "Location: index.php?error=" .
        urlencode("Product not found.")
    );

    exit;
}


try {

    $stmt = $pdo->prepare("
        UPDATE products
        SET status = 'inactive'
        WHERE id = ?
    ");

    $stmt->execute([$id]);


    header(
        "Location: index.php?success=" .
        urlencode(
            $product['product_name'] .
            " has been deactivated."
        )
    );

    exit;

} catch (PDOException $e) {

    header(
        "Location: index.php?error=" .
        urlencode("Unable to deactivate product.")
    );

    exit;
}