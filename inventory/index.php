<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/database.php";

$pageTitle = "Inventory";

$search = trim($_GET['search'] ?? '');
$categoryId = (int)($_GET['category_id'] ?? 0);
$stockStatus = $_GET['stock_status'] ?? '';


// ============================================================
// INVENTORY SUMMARY
// ============================================================

$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total_items,

        SUM(
            CASE
                WHEN stock > reorder_level
                THEN 1
                ELSE 0
            END
        ) AS in_stock,

        SUM(
            CASE
                WHEN stock > 0
                AND stock <= reorder_level
                THEN 1
                ELSE 0
            END
        ) AS low_stock,

        SUM(
            CASE
                WHEN stock <= 0
                THEN 1
                ELSE 0
            END
        ) AS out_of_stock

    FROM products

    WHERE status = 'active'
");

$summary = $stmt->fetch();


// ============================================================
// CATEGORIES
// ============================================================

$stmt = $pdo->query("
    SELECT
        id,
        category_name
    FROM categories
    WHERE status = 'active'
    ORDER BY category_name ASC
");

$categories = $stmt->fetchAll();


// ============================================================
// PRODUCTS QUERY
// ============================================================

$sql = "
    SELECT
        p.id,
        p.product_code,
        p.barcode,
        p.product_name,
        p.category_id,
        p.unit,
        p.cost_price,
        p.selling_price,
        p.stock,
        p.reorder_level,
        p.status,

        c.category_name

    FROM products p

    LEFT JOIN categories c
        ON c.id = p.category_id

    WHERE p.status = 'active'
";

$params = [];


// SEARCH

if ($search !== '') {

    $sql .= "
        AND (
            p.product_code LIKE ?
            OR p.barcode LIKE ?
            OR p.product_name LIKE ?
        )
    ";

    $searchValue = "%{$search}%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


// CATEGORY

if ($categoryId > 0) {

    $sql .= "
        AND p.category_id = ?
    ";

    $params[] = $categoryId;
}


// STOCK STATUS

if ($stockStatus === 'in_stock') {

    $sql .= "
        AND p.stock > p.reorder_level
    ";

} elseif ($stockStatus === 'low_stock') {

    $sql .= "
        AND p.stock > 0
        AND p.stock <= p.reorder_level
    ";

} elseif ($stockStatus === 'out_of_stock') {

    $sql .= "
        AND p.stock <= 0
    ";
}


$sql .= "
    ORDER BY p.product_name ASC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$products = $stmt->fetchAll();


require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";

?>


<main class="main-content">


    <!-- =====================================================
         TOPBAR
    ====================================================== -->

    <header class="topbar">

        <div class="page-heading">

            <h4>
                Inventory
            </h4>

            <small>
                Monitor and manage product stock
            </small>

        </div>


        <div class="d-flex gap-2">

            <a
                
                class="btn btn-outline-secondary"
            >

                <button
                onclick="window.print()"
                class="btn btn-print"
            >
                🖨 Print
            </button>


            <a
                href="stock_in.php"
                class="btn btn-primary"
            >

                <i class="bi bi-box-arrow-in-down me-1"></i>

                Stock In

            </a>

        </div>

    </header>


    <!-- =====================================================
         CONTENT
    ====================================================== -->

    <section class="content-area">


        <!-- =================================================
             ALERTS
        ================================================== -->

        <?php if (isset($_GET['success'])): ?>

            <div
                class="alert alert-success alert-dismissible fade show"
            >

                <i class="bi bi-check-circle me-2"></i>

                <?= htmlspecialchars($_GET['success']) ?>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        <?php endif; ?>


        <?php if (isset($_GET['error'])): ?>

            <div
                class="alert alert-danger alert-dismissible fade show"
            >

                <i class="bi bi-exclamation-triangle me-2"></i>

                <?= htmlspecialchars($_GET['error']) ?>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        <?php endif; ?>


        <!-- =================================================
             SUMMARY CARDS
        ================================================== -->

        <div class="row g-3 mb-4">


            <!-- TOTAL -->

            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div
                        class="stat-icon"
                        style="
                            background:#eff6ff;
                            color:#2563eb;
                        "
                    >

                        <i class="bi bi-box-seam"></i>

                    </div>


                    <div class="stat-title">
                        Total Products
                    </div>


                    <div class="stat-value">

                        <?= number_format(
                            $summary['total_items'] ?? 0
                        ) ?>

                    </div>

                </div>

            </div>


            <!-- IN STOCK -->

            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div
                        class="stat-icon"
                        style="
                            background:#f0fdf4;
                            color:#16a34a;
                        "
                    >

                        <i class="bi bi-check-circle"></i>

                    </div>


                    <div class="stat-title">
                        In Stock
                    </div>


                    <div class="stat-value">

                        <?= number_format(
                            $summary['in_stock'] ?? 0
                        ) ?>

                    </div>

                </div>

            </div>


            <!-- LOW STOCK -->

            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div
                        class="stat-icon"
                        style="
                            background:#fff7ed;
                            color:#ea580c;
                        "
                    >

                        <i class="bi bi-exclamation-triangle"></i>

                    </div>


                    <div class="stat-title">
                        Low Stock
                    </div>


                    <div class="stat-value">

                        <?= number_format(
                            $summary['low_stock'] ?? 0
                        ) ?>

                    </div>

                </div>

            </div>


            <!-- OUT OF STOCK -->

            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div
                        class="stat-icon"
                        style="
                            background:#fef2f2;
                            color:#dc2626;
                        "
                    >

                        <i class="bi bi-x-circle"></i>

                    </div>


                    <div class="stat-title">
                        Out of Stock
                    </div>


                    <div class="stat-value">

                        <?= number_format(
                            $summary['out_of_stock'] ?? 0
                        ) ?>

                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
             INVENTORY TABLE
        ================================================== -->

        <div class="dashboard-card">


            <!-- HEADER -->

            <div class="dashboard-card-header">

                <div>

                    <h6>
                        Current Inventory
                    </h6>

                    <small class="text-muted">

                        <?= number_format(
                            count($products)
                        ) ?>

                        product(s)

                    </small>

                </div>

            </div>


            <!-- FILTERS -->

            <div class="p-3 border-bottom">


                <form
                    method="GET"
                    class="row g-2"
                >


                    <!-- SEARCH -->

                    <div class="col-lg-5">

                        <div class="input-group">

                            <span class="input-group-text">

                                <i class="bi bi-search"></i>

                            </span>


                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="Search product, code or barcode..."
                                value="<?= htmlspecialchars(
                                    $search
                                ) ?>"
                            >

                        </div>

                    </div>


                    <!-- CATEGORY -->

                    <div class="col-lg-3">

                        <select
                            name="category_id"
                            class="form-select"
                        >

                            <option value="">
                                All Categories
                            </option>


                            <?php foreach (
                                $categories as $category
                            ): ?>

                                <option
                                    value="<?= $category['id'] ?>"
                                    <?= $categoryId ==
                                        $category['id']
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


                    <!-- STOCK STATUS -->

                    <div class="col-lg-2">

                        <select
                            name="stock_status"
                            class="form-select"
                        >

                            <option value="">
                                All Stock
                            </option>

                            <option
                                value="in_stock"
                                <?= $stockStatus === 'in_stock'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                In Stock
                            </option>

                            <option
                                value="low_stock"
                                <?= $stockStatus === 'low_stock'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                Low Stock
                            </option>

                            <option
                                value="out_of_stock"
                                <?= $stockStatus === 'out_of_stock'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                Out of Stock
                            </option>

                        </select>

                    </div>


                    <!-- BUTTON -->

                    <div class="col-lg-2">

                        <button
                            type="submit"
                            class="btn btn-dark w-100"
                        >

                            <i class="bi bi-funnel me-1"></i>

                            Filter

                        </button>

                    </div>


                </form>

            </div>


            <!-- TABLE -->

            <div class="table-responsive">

                <table class="table table-hover">

                    <thead>

                        <tr>

                            <th>
                                Product
                            </th>

                            <th>
                                Category
                            </th>

                            <th>
                                Cost
                            </th>

                            <th>
                                Selling
                            </th>

                            <th>
                                Current Stock
                            </th>

                            <th>
                                Reorder Level
                            </th>

                            <th>
                                Status
                            </th>

                            <th class="text-end">
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if ($products): ?>


                        <?php foreach (
                            $products as $product
                        ): ?>


                            <?php

                            $stock =
                                (float)$product['stock'];

                            $reorder =
                                (float)$product['reorder_level'];


                            if ($stock <= 0) {

                                $statusLabel =
                                    "Out of Stock";

                                $statusClass =
                                    "bg-danger";

                                $stockClass =
                                    "text-danger";

                            } elseif (
                                $stock <= $reorder
                            ) {

                                $statusLabel =
                                    "Low Stock";

                                $statusClass =
                                    "bg-warning text-dark";

                                $stockClass =
                                    "text-warning";

                            } else {

                                $statusLabel =
                                    "In Stock";

                                $statusClass =
                                    "bg-success";

                                $stockClass =
                                    "text-success";
                            }

                            ?>


                            <tr>


                                <!-- PRODUCT -->

                                <td>

                                    <div
                                        class="d-flex align-items-center"
                                    >

                                        <div
                                            class="inventory-product-icon"
                                        >

                                            <i class="bi bi-box"></i>

                                        </div>


                                        <div>

                                            <div
                                                class="fw-semibold"
                                            >

                                                <?= htmlspecialchars(
                                                    $product['product_name']
                                                ) ?>

                                            </div>


                                            <small
                                                class="text-muted"
                                            >

                                                <?= htmlspecialchars(
                                                    $product['product_code']
                                                ) ?>


                                                <?php if (
                                                    !empty(
                                                        $product['barcode']
                                                    )
                                                ): ?>

                                                    ·

                                                    <?= htmlspecialchars(
                                                        $product['barcode']
                                                    ) ?>

                                                <?php endif; ?>

                                            </small>

                                        </div>

                                    </div>

                                </td>


                                <!-- CATEGORY -->

                                <td>

                                    <?= htmlspecialchars(
                                        $product['category_name']
                                        ?? 'Uncategorized'
                                    ) ?>

                                </td>


                                <!-- COST -->

                                <td>

                                    ₱<?= number_format(
                                        (float)$product['cost_price'],
                                        2
                                    ) ?>

                                </td>


                                <!-- SELLING -->

                                <td>

                                    ₱<?= number_format(
                                        (float)$product['selling_price'],
                                        2
                                    ) ?>

                                </td>


                                <!-- STOCK -->

                                <td>

                                    <strong
                                        class="<?= $stockClass ?>"
                                    >

                                        <?= number_format(
                                            $stock,
                                            3
                                        ) ?>

                                    </strong>


                                    <small
                                        class="text-muted"
                                    >

                                        <?= htmlspecialchars(
                                            $product['unit']
                                        ) ?>

                                    </small>

                                </td>


                                <!-- REORDER -->

                                <td>

                                    <?= number_format(
                                        $reorder,
                                        3
                                    ) ?>

                                    <?= htmlspecialchars(
                                        $product['unit']
                                    ) ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="badge <?= $statusClass ?>"
                                    >

                                        <?= $statusLabel ?>

                                    </span>

                                </td>


                                <!-- ACTION -->

                                <td class="text-end">

                                    <div
                                        class="btn-group"
                                    >


                                        <a
                                            href="stock_in.php?product_id=<?= $product['id'] ?>"
                                            class="btn btn-sm btn-outline-success"
                                            title="Stock In"
                                        >

                                            <i class="bi bi-plus-lg"></i>

                                        </a>


                                        <a
                                            href="stock_out.php?product_id=<?= $product['id'] ?>"
                                            class="btn btn-sm btn-outline-danger"
                                            title="Stock Out"
                                        >

                                            <i class="bi bi-dash-lg"></i>

                                        </a>


                                        
                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="8"
                                class="text-center py-5"
                            >

                                <i
                                    class="bi bi-box-seam fs-1 text-muted"
                                ></i>


                                <h6 class="mt-3">

                                    No products found

                                </h6>


                                <p class="text-muted mb-0">

                                    Try changing your search
                                    or filters.

                                </p>

                            </td>

                        </tr>


                    <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </div>


    </section>

</main>


<?php

require_once __DIR__ . "/../includes/footer.php";

?>