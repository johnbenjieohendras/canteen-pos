<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/database.php";

$id = (int)($_GET['id'] ?? 0);


if ($id <= 0) {

    header(
        "Location: index.php?error=" .
        urlencode("Invalid category.")
    );

    exit;
}


$stmt = $pdo->prepare("
    SELECT id, category_name
    FROM categories
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$category = $stmt->fetch();


if (!$category) {

    header(
        "Location: index.php?error=" .
        urlencode("Category not found.")
    );

    exit;
}


try {

    $stmt = $pdo->prepare("
        UPDATE categories
        SET status = 'inactive'
        WHERE id = ?
    ");

    $stmt->execute([
        $id
    ]);


    header(
        "Location: index.php?success=" .
        urlencode(
            $category['category_name'] .
            " has been deactivated."
        )
    );

    exit;


} catch (PDOException $e) {

    header(
        "Location: index.php?error=" .
        urlencode(
            "Unable to deactivate category."
        )
    );

    exit;
}