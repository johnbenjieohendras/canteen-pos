<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/database.php";

$pageTitle = "Edit Product";

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {

    header("Location: index.php?error=" . urlencode("Invalid product."));
    exit;
}


// Get product

$stmt = $pdo->prepare("
    SELECT *
    FROM products
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$product = $stmt->fetch();

if (!$product) {

    header("Location: index.php?error=" . urlencode("Product not found."));
    exit;
}


// Categories

$categories = $pdo->query("
    SELECT id, category_name
    FROM categories
    WHERE status = 'active'
    ORDER BY category_name
")->fetchAll();


// Suppliers

$suppliers = $pdo->query("
    SELECT id, supplier_name
    FROM suppliers
    WHERE status = 'active'
    ORDER BY supplier_name
")->fetchAll();


$errors = [];


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
    $reorderLevel = (float)($_POST['reorder_level'] ?? 5);


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

    if ($reorderLevel < 0) {
        $errors[] = "Reorder level cannot be negative.";
    }


    // Duplicate product code

    if (!$errors) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM products
            WHERE product_code = ?
            AND id != ?
            LIMIT 1
        ");

        $stmt->execute([
            $productCode,
            $id
        ]);

        if ($stmt->fetch()) {
            $errors[] = "Product code already exists.";
        }

    }


    // Duplicate barcode

    if (!$errors && $barcode !== '') {

        $stmt = $pdo->prepare("
            SELECT id
            FROM products
            WHERE barcode = ?
            AND id != ?
            LIMIT 1
        ");

        $stmt->execute([
            $barcode,
            $id
        ]);

        if ($stmt->fetch()) {
            $errors[] = "Barcode already exists.";
        }

    }


    if (!$errors) {

        try {

            $stmt = $pdo->prepare("
                UPDATE products
                SET
                    product_code = ?,
                    barcode = ?,
                    product_name = ?,
                    category_id = ?,
                    supplier_id = ?,
                    unit = ?,
                    cost_price = ?,
                    selling_price = ?,
                    reorder_level = ?
                WHERE id = ?
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
                $reorderLevel,
                $id

            ]);


            header(
                "Location: index.php?success=" .
                urlencode("Product successfully updated.")
            );

            exit;

        } catch (PDOException $e) {

            $errors[] =
                "Unable to update product: " .
                $e->getMessage();

        }

    }


    // Keep entered values

    $product['product_code'] = $productCode;
    $product['barcode'] = $barcode;
    $product['product_name'] = $productName;
    $product['category_id'] = $categoryId;
    $product['supplier_id'] = $supplierId;
    $product['unit'] = $unit;
    $product['cost_price'] = $costPrice;
    $product['selling_price'] = $sellingPrice;
    $product['reorder_level'] = $reorderLevel;

}


require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";

?>

<main class="main-content">

    <header class="topbar">

        <div class="page-heading">

            <h4>
                Edit Product
            </h4>

            <small>
                Update product information
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
                                    </label>

                                    <input
                                        type="text"
                                        name="product_code"
                                        class="form-control"
                                        value="<?= htmlspecialchars(
                                            $product['product_code']
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
                                        value="<?= htmlspecialchars(
                                            $product['barcode'] ?? ''
                                        ) ?>"
                                    >

                                </div>


                                <div class="col-12">

                                    <label class="form-label">
                                        Product Name
                                    </label>

                                    <input
                                        type="text"
                                        name="product_name"
                                        class="form-control"
                                        value="<?= htmlspecialchars(
                                            $product['product_name']
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
                                                    $product['category_id']
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
                                                    $product['supplier_id']
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

                                    <input
                                        type="text"
                                        name="unit"
                                        class="form-control"
                                        value="<?= htmlspecialchars(
                                            $product['unit']
                                        ) ?>"
                                    >

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
                                                $product['cost_price']
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
                                                $product['selling_price']
                                            ) ?>"
                                        >

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <div class="col-lg-4">

                    <div class="dashboard-card">

                        <div class="dashboard-card-header">

                            <h6>
                                Inventory
                            </h6>

                        </div>


                        <div class="dashboard-card-body">

                            <div class="mb-3">

                                <label class="form-label">
                                    Current Stock
                                </label>

                                <input
                                    type="text"
                                    class="form-control bg-light"
                                    value="<?= number_format(
                                        $product['stock'],
                                        3
                                    ) ?>"
                                    readonly
                                >

                                <small class="text-muted">
                                    Use Stock In or Inventory Adjustment
                                    to change stock.
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
                                        $product['reorder_level']
                                    ) ?>"
                                >

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

                                    Update Product

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