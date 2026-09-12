<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/database.php";

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {

    header(
        "Location: index.php?error=" .
        urlencode("Invalid supplier.")
    );

    exit;
}


try {

    // Get current supplier

    $stmt = $pdo->prepare("
        SELECT
            id,
            supplier_name,
            status
        FROM suppliers
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $id
    ]);

    $supplier = $stmt->fetch();


    if (!$supplier) {

        throw new Exception(
            "Supplier not found."
        );
    }


    $newStatus =
        $supplier['status'] === 'active'
            ? 'inactive'
            : 'active';


    // Update

    $stmt = $pdo->prepare("
        UPDATE suppliers
        SET status = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $newStatus,
        $id
    ]);


    // Audit

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
        (
            ?,
            'STATUS_CHANGE',
            'Suppliers',
            ?,
            ?,
            ?
        )
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $id,
        "Changed supplier " .
        $supplier['supplier_name'] .
        " status to " .
        strtoupper($newStatus),
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);


    header(
        "Location: index.php?success=" .
        urlencode(
            "Supplier status updated successfully."
        )
    );

    exit;


} catch (Throwable $e) {

    header(
        "Location: index.php?error=" .
        urlencode(
            $e->getMessage()
        )
    );

    exit;
}