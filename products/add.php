<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . '/../includes/activity_logger.php';

$pageTitle = "Add Product";

$errors = [];


// Get categories

$categories = $pdo->query("
    SELECT id, category_name
    FROM categories
    WHERE status = 'active'
    ORDER BY category_name
")->fetchAll();


// Get suppliers

$suppliers = $pdo->query("
    SELECT id, supplier_name
    FROM suppliers
    WHERE status = 'active'
    ORDER BY supplier_name
")->fetchAll();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $productCode = trim($_POST['product_code'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $productName = trim($_POST['product_name'] ?? '');

    $categoryId = !empty($_POST['category_id'])
        ? (int)$_POST['category_id']
        : null;

    $supplierId = !empty($_POST['supplier_id'])
        ? (int)$_POST['supplier_id']
        : null;

    $unit = trim($_POST['unit'] ?? 'pcs');

    $costPrice = (float)($_POST['cost_price'] ?? 0);
    $sellingPrice = (float)($_POST['selling_price'] ?? 0);
    $stock = (float)($_POST['stock'] ?? 0);
    $reorderLevel = (float)($_POST['reorder_level'] ?? 5);


    // Validation

    if ($productCode === '') {
        $errors[] = "Product code is required.";
    }

    if ($productName === '') {
        $errors[] = "Product name is required.";
    }

    if ($unit === '') {
        $errors[] = "Unit is required.";
    }

    if ($costPrice < 0) {
        $errors[] = "Cost price cannot be negative.";
    }

    if ($sellingPrice < 0) {
        $errors[] = "Selling price cannot be negative.";
    }

    if ($stock < 0) {
        $errors[] = "Stock cannot be negative.";
    }

    if ($reorderLevel < 0) {
        $errors[] = "Reorder level cannot be negative.";
    }


    // Check duplicate product code

    if (!$errors) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM products
            WHERE product_code = ?
            LIMIT 1
        ");

        $stmt->execute([$productCode]);

        if ($stmt->fetch()) {
            $errors[] = "Product code already exists.";
        }

    }


    // Check duplicate barcode

    if (!$errors && $barcode !== '') {

        $stmt = $pdo->prepare("
            SELECT id
            FROM products
            WHERE barcode = ?
            LIMIT 1
        ");

        $stmt->execute([$barcode]);

        if ($stmt->fetch()) {
            $errors[] = "Barcode already exists.";
        }

    }


    // Insert

    if (!$errors) {

        try {

            $pdo->beginTransaction();


            $stmt = $pdo->prepare("
                INSERT INTO products
                (
                    product_code,
                    barcode,
                    product_name,
                    category_id,
                    supplier_id,
                    unit,
                    cost_price,
                    selling_price,
                    stock,
                    reorder_level,
                    status
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active'
                )
            ");


            $stmt->execute([

                $productCode,
                $barcode !== '' ? $barcode : null,
                $productName,
                $categoryId,
                $supplierId,
                $unit,
                $costPrice,
                $sellingPrice,
                $stock,
                $reorderLevel

            ]);


            $productId = $pdo->lastInsertId();


            // Record initial stock

            if ($stock > 0) {

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
                        ?, ?, 'STOCK_IN',
                        'NEW_PRODUCT',
                        ?,
                        ?, 0, ?,
                        'Initial stock when product was created'
                    )
                ");


                $stmt->execute([

                    $productId,
                    $_SESSION['user_id'],
                    $productId,
                    $stock,
                    $stock

                ]);

            }


            $pdo->commit();


            header(
                "Location: index.php?success=" .
                urlencode("Product successfully added.")
            );

            exit;


        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = "Unable to save product: " . $e->getMessage();
        }

    }

}

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";

?>

<main class="main-content">

    <header class="topbar">

        <div class="page-heading">

            <h4>Add Product</h4>

            <small>
                Create a new canteen product
            </small>

        </div>

        <button
            class="btn btn-outline-secondary d-lg-none"
            onclick="toggleSidebar()"
        >
            <i class="bi bi-list"></i>
        </button>

    </header>


    <section class="content-area">

        <?php if ($errors): ?>

            <div class="alert alert-danger">

                <strong>
                    Please correct the following:
                </strong>

                <ul class="mb-0 mt-2">

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= htmlspecialchars($error) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <form method="POST">

            <div class="row g-4">


                <!-- BASIC INFORMATION -->

                <div class="col-lg-8">

                    <div class="dashboard-card">

                        <div class="dashboard-card-header">

                            <h6>
                                Product Information
                            </h6>

                        </div>


                        <div class="dashboard-card-body">

                            <div class="row g-3">


                                <div class="col-md-6">

                                    <label class="form-label">
                                        Product Code
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input
                                        type="text"
                                        name="product_code"
                                        class="form-control"
                                        placeholder="e.g. BEV-001"
                                        value="<?= htmlspecialchars(
                                            $_POST['product_code'] ?? ''
                                        ) ?>"
                                        required
                                    >

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">
                                        Barcode
                                    </label>

                                    <input
                                        type="text"
                                        name="barcode"
                                        class="form-control"
                                        placeholder="Scan or enter barcode"
                                        value="<?= htmlspecialchars(
                                            $_POST['barcode'] ?? ''
                                        ) ?>"
                                    >

                                </div>


                                <div class="col-12">

                                    <label class="form-label">
                                        Product Name
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input
                                        type="text"
                                        name="product_name"
                                        class="form-control"
                                        placeholder="e.g. Coke 1.5L"
                                        value="<?= htmlspecialchars(
                                            $_POST['product_name'] ?? ''
                                        ) ?>"
                                        required
                                    >

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">
                                        Category
                                    </label>

                                    <select
                                        name="category_id"
                                        class="form-select"
                                    >

                                        <option value="">
                                            Select Category
                                        </option>

                                        <?php foreach ($categories as $category): ?>

                                            <option
                                                value="<?= $category['id'] ?>"
                                                <?= (
                                                    ($_POST['category_id'] ?? '')
                                                    == $category['id']
                                                )
                                                    ? 'selected'
                                                    : ''
                                                ?>
                                            >

                                                <?= htmlspecialchars(
                                                    $category['category_name']
                                                ) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">
                                        Supplier
                                    </label>

                                    <select
                                        name="supplier_id"
                                        class="form-select"
                                    >

                                        <option value="">
                                            Select Supplier
                                        </option>

                                        <?php foreach ($suppliers as $supplier): ?>

                                            <option
                                                value="<?= $supplier['id'] ?>"
                                                <?= (
                                                    ($_POST['supplier_id'] ?? '')
                                                    == $supplier['id']
                                                )
                                                    ? 'selected'
                                                    : ''
                                                ?>
                                            >

                                                <?= htmlspecialchars(
                                                    $supplier['supplier_name']
                                                ) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <div class="col-md-4">

                                    <label class="form-label">
                                        Unit
                                    </label>

                                    <select
                                        name="unit"
                                        class="form-select"
                                    >

                                        <?php

                                        $units = [
                                            'pcs',
                                            'piece',
                                            'pack',
                                            'bottle',
                                            'can',
                                            'box',
                                            'cup',
                                            'kg',
                                            'gram',
                                            'liter'
                                        ];

                                        ?>

                                        <?php foreach ($units as $unitOption): ?>

                                            <option
                                                value="<?= $unitOption ?>"
                                                <?= (
                                                    ($_POST['unit'] ?? 'pcs')
                                                    === $unitOption
                                                )
                                                    ? 'selected'
                                                    : ''
                                                ?>
                                            >

                                                <?= ucfirst($unitOption) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <div class="col-md-4">

                                    <label class="form-label">
                                        Cost Price
                                    </label>

                                    <div class="input-group">

                                        <span class="input-group-text">
                                            ₱
                                        </span>

                                        <input
                                            type="number"
                                            name="cost_price"
                                            class="form-control"
                                            step="0.01"
                                            min="0"
                                            value="<?= htmlspecialchars(
                                                $_POST['cost_price'] ?? '0.00'
                                            ) ?>"
                                        >

                                    </div>

                                </div>


                                <div class="col-md-4">

                                    <label class="form-label">
                                        Selling Price
                                    </label>

                                    <div class="input-group">

                                        <span class="input-group-text">
                                            ₱
                                        </span>

                                        <input
                                            type="number"
                                            name="selling_price"
                                            class="form-control"
                                            step="0.01"
                                            min="0"
                                            value="<?= htmlspecialchars(
                                                $_POST['selling_price'] ?? '0.00'
                                            ) ?>"
                                        >

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- INVENTORY -->

                <div class="col-lg-4">

                    <div class="dashboard-card">

                        <div class="dashboard-card-header">

                            <h6>
                                Inventory Settings
                            </h6>

                        </div>


                        <div class="dashboard-card-body">

                            <div class="mb-3">

                                <label class="form-label">
                                    Initial Stock
                                </label>

                                <input
                                    type="number"
                                    name="stock"
                                    class="form-control"
                                    step="0.001"
                                    min="0"
                                    value="<?= htmlspecialchars(
                                        $_POST['stock'] ?? '0'
                                    ) ?>"
                                >

                                <small class="text-muted">
                                    Starting quantity for this product.
                                </small>

                            </div>


                            <div class="mb-3">

                                <label class="form-label">
                                    Reorder Level
                                </label>

                                <input
                                    type="number"
                                    name="reorder_level"
                                    class="form-control"
                                    step="0.001"
                                    min="0"
                                    value="<?= htmlspecialchars(
                                        $_POST['reorder_level'] ?? '5'
                                    ) ?>"
                                >

                                <small class="text-muted">
                                    Dashboard will flag products at or below
                                    this quantity.
                                </small>

                            </div>

                        </div>

                    </div>


                    <div class="dashboard-card mt-4">

                        <div class="dashboard-card-body">

                            <div class="d-grid gap-2">

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >

                                    <i class="bi bi-check-lg me-1"></i>

                                    Save Product

                                </button>


                                <a
                                    href="index.php"
                                    class="btn btn-light"
                                >

                                    Cancel

                                </a>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </form>

    </section>

</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>