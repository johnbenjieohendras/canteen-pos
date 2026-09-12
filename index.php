<?php

require_once __DIR__ . '/includes/auth.php';

require_admin();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php';

$pageTitle = "Dashboard";


// ============================================================
// SAVE CASH IN DRAWER
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_dashboard_funds'])) {

    $cashInDrawer = isset($_POST['cash_in_drawer'])
        ? (float) $_POST['cash_in_drawer']
        : 0;

    if ($cashInDrawer < 0) {
        $cashInDrawer = 0;
    }


    // Check if dashboard_funds record exists

    $stmt = $pdo->query("
        SELECT id
        FROM dashboard_funds
        WHERE id = 1
        LIMIT 1
    ");

    $fundRow = $stmt->fetch();


    if ($fundRow) {

        $stmt = $pdo->prepare("
            UPDATE dashboard_funds
            SET cash_in_drawer = ?
            WHERE id = 1
        ");

        $stmt->execute([
            $cashInDrawer
        ]);

    } else {

        $stmt = $pdo->prepare("
            INSERT INTO dashboard_funds
            (
                id,
                cash_in_drawer,
                total_profit
            )
            VALUES
            (
                1,
                ?,
                0
            )
        ");

        $stmt->execute([
            $cashInDrawer
        ]);
    }


    header("Location: index.php?updated=1");

    exit;
}


// ============================================================
// GET CASH IN DRAWER
// ============================================================

$stmt = $pdo->query("
    SELECT
        cash_in_drawer
    FROM dashboard_funds
    WHERE id = 1
    LIMIT 1
");

$dashboardFunds = $stmt->fetch();


$cashInDrawer = isset($dashboardFunds['cash_in_drawer'])
    ? (float) $dashboardFunds['cash_in_drawer']
    : 0;


// ============================================================
// TOTAL PROFIT
//
// AUTOMATIC FROM ACTUAL COMPLETED SALES
//
// Profit:
// (Selling Price - Cost Price) × Quantity
// ============================================================

$stmt = $pdo->query("
    SELECT
        COALESCE(
            SUM(
                si.quantity *
                (
                    COALESCE(p.selling_price, 0)
                    -
                    COALESCE(p.cost_price, 0)
                )
            ),
            0
        ) AS total_profit

    FROM sale_items si

    INNER JOIN sales s
        ON s.id = si.sale_id

    INNER JOIN products p
        ON p.id = si.product_id

    WHERE s.status = 'COMPLETED'
");

$totalProfit = (float) $stmt->fetch()['total_profit'];


// ============================================================
// TOTAL AVAILABLE
// ============================================================

$totalAvailableCash = $cashInDrawer + $totalProfit;


// ============================================================
// TOTAL ACTIVE PRODUCTS
// ============================================================

$stmt = $pdo->query("
    SELECT COUNT(*) AS total
    FROM products
    WHERE status = 'active'
");

$totalProducts = (int) $stmt->fetch()['total'];


// ============================================================
// TOTAL STOCK
// ============================================================

$stmt = $pdo->query("
    SELECT
        COALESCE(SUM(stock), 0) AS total
    FROM products
    WHERE status = 'active'
");

$totalStock = $stmt->fetch()['total'];


// ============================================================
// TODAY'S SALES
// ============================================================

$stmt = $pdo->query("
    SELECT
        COALESCE(SUM(total_amount), 0) AS total
    FROM sales
    WHERE status = 'COMPLETED'
    AND DATE(transaction_date) = CURDATE()
");

$todaySales = (float) $stmt->fetch()['total'];


// ============================================================
// TODAY'S TRANSACTIONS
// ============================================================

$stmt = $pdo->query("
    SELECT COUNT(*) AS total
    FROM sales
    WHERE status = 'COMPLETED'
    AND DATE(transaction_date) = CURDATE()
");

$todayTransactions = (int) $stmt->fetch()['total'];


// ============================================================
// LOW STOCK
// ============================================================

$stmt = $pdo->query("
    SELECT COUNT(*) AS total
    FROM products
    WHERE status = 'active'
    AND stock <= reorder_level
");

$lowStock = (int) $stmt->fetch()['total'];


// ============================================================
// RECENT SALES
// ============================================================

$stmt = $pdo->query("
    SELECT
        s.transaction_no,
        s.total_amount,
        s.payment_method,
        s.transaction_date,
        u.full_name AS cashier

    FROM sales s

    INNER JOIN users u
        ON u.id = s.cashier_id

    WHERE s.status = 'COMPLETED'

    ORDER BY s.id DESC

    LIMIT 8
");

$recentSales = $stmt->fetchAll();


// ============================================================
// LOW STOCK PRODUCTS
// ============================================================

$stmt = $pdo->query("
    SELECT
        product_code,
        product_name,
        stock,
        reorder_level,
        unit

    FROM products

    WHERE status = 'active'

    AND stock <= reorder_level

    ORDER BY stock ASC

    LIMIT 8
");

$lowStockProducts = $stmt->fetchAll();


require_once __DIR__ . "/includes/header.php";

require_once __DIR__ . "/includes/sidebar.php";

?>


<main class="main-content">


    <!-- ========================================================
         TOPBAR
    ========================================================= -->

    <header class="topbar">
        
        <div class="page-heading">

            <h4>
                Dashboard
            </h4>

            <small>
                Canteen POS Back Office
            </small>

        </div>


        <div>

            <button
                class="btn btn-outline-secondary d-lg-none"
                onclick="toggleSidebar()"
            >

                <i class="bi bi-list"></i>

            </button>

        </div>

    </header>


    <!-- ========================================================
         CONTENT
    ========================================================= -->

    <section class="content-area">


        <!-- ====================================================
             WELCOME
        ===================================================== -->

        <div class="mb-4">

            <h5 class="fw-bold mb-1">

                Good day,
                <?= htmlspecialchars($_SESSION['full_name']) ?>!

            </h5>


            <p class="text-muted mb-0">

                Here's what's happening in your canteen today.

            </p>

        </div>


        <!-- ====================================================
             SUCCESS MESSAGE
        ===================================================== -->

        <?php if (isset($_GET['updated'])): ?>

            <div class="alert alert-success alert-dismissible fade show">

                <i class="bi bi-check-circle me-2"></i>

                Cash in Drawer updated successfully.

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        <?php endif; ?>


        <!-- ====================================================
             STATISTICS
        ===================================================== -->

        <div class="row g-4 mb-4">


            <!-- TODAY'S SALES -->

            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="stat-icon bg-primary-subtle text-primary">

                        <i class="bi bi-cash-stack"></i>

                    </div>


                    <div class="stat-title">

                        Today's Sales

                    </div>


                    <div class="stat-value">

                        ₱<?= number_format($todaySales, 2) ?>

                    </div>

                </div>

            </div>


            <!-- TRANSACTIONS -->

            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="stat-icon bg-success-subtle text-success">

                        <i class="bi bi-receipt"></i>

                    </div>


                    <div class="stat-title">

                        Transactions Today

                    </div>


                    <div class="stat-value">

                        <?= number_format($todayTransactions) ?>

                    </div>

                </div>

            </div>


            <!-- PRODUCTS -->

            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="stat-icon bg-info-subtle text-info">

                        <i class="bi bi-box-seam"></i>

                    </div>


                    <div class="stat-title">

                        Active Products

                    </div>


                    <div class="stat-value">

                        <?= number_format($totalProducts) ?>

                    </div>

                </div>

            </div>


            <!-- LOW STOCK -->

            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="stat-icon bg-danger-subtle text-danger">

                        <i class="bi bi-exclamation-triangle"></i>

                    </div>


                    <div class="stat-title">

                        Low Stock Items

                    </div>


                    <div class="stat-value">

                        <?= number_format($lowStock) ?>

                    </div>

                </div>

            </div>

        </div>


        <!-- ====================================================
             CASH AND PROFIT
        ===================================================== -->

        <div class="row g-4 mb-4">


            <!-- CASH IN DRAWER -->

            <div class="col-lg-4 col-md-6">

                <div class="stat-card">


                    <div class="stat-icon bg-success-subtle text-success">

                        <i class="bi bi-safe2"></i>

                    </div>


                    <div class="stat-title">

                        Cash in Drawer

                    </div>


                    <div class="stat-value">

                        ₱<?= number_format($cashInDrawer, 2) ?>

                    </div>


                    <button
                        class="btn btn-sm btn-outline-success mt-3"
                        data-bs-toggle="modal"
                        data-bs-target="#dashboardFundsModal"
                    >

                        <i class="bi bi-pencil-square me-1"></i>

                        Edit Cash

                    </button>

                </div>

            </div>


            <!-- TOTAL PROFIT -->

            <div class="col-lg-4 col-md-6">

                <div class="stat-card">


                    <div class="stat-icon bg-primary-subtle text-primary">

                        <i class="bi bi-graph-up-arrow"></i>

                    </div>


                    <div class="stat-title">

                        Total Profit

                    </div>


                    <div class="stat-value">

                        ₱<?= number_format($totalProfit, 2) ?>

                    </div>


                    <div class="text-muted small mt-3">

                        <i class="bi bi-calculator me-1"></i>

                        Automatically calculated from actual completed sales.

                    </div>

                </div>

            </div>


        </div>


        <!-- ====================================================
             MAIN ROW
        ===================================================== -->

        <div class="row g-4">


            <!-- =================================================
                 RECENT SALES
            ================================================== -->

            <div class="col-xl-8">

                <div class="dashboard-card">


                    <div class="dashboard-card-header">

                        <h6>
                            Recent Sales
                        </h6>


                        <a
                            href="sales/index.php"
                            class="btn btn-sm btn-outline-primary"
                        >

                            View All

                        </a>

                    </div>


                    <div class="table-responsive">

                        <table class="table table-hover">


                            <thead>

                                <tr>

                                    <th>
                                        Transaction
                                    </th>

                                    <th>
                                        Cashier
                                    </th>

                                    <th>
                                        Payment
                                    </th>

                                    <th>
                                        Amount
                                    </th>

                                    <th>
                                        Date
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php if (count($recentSales) > 0): ?>


                                <?php foreach ($recentSales as $sale): ?>


                                    <tr>


                                        <td>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $sale['transaction_no']
                                                ) ?>

                                            </strong>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $sale['cashier']
                                            ) ?>

                                        </td>


                                        <td>

                                            <span class="badge bg-light text-dark">

                                                <?= htmlspecialchars(
                                                    $sale['payment_method']
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <strong>

                                                ₱<?= number_format(
                                                    $sale['total_amount'],
                                                    2
                                                ) ?>

                                            </strong>

                                        </td>


                                        <td>

                                            <?= date(
                                                'M d, Y h:i A',
                                                strtotime(
                                                    $sale['transaction_date']
                                                )
                                            ) ?>

                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                            <?php else: ?>


                                <tr>

                                    <td
                                        colspan="5"
                                        class="text-center text-muted py-4"
                                    >

                                        No sales transactions yet.

                                    </td>

                                </tr>


                            <?php endif; ?>


                            </tbody>

                        </table>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 LOW STOCK
            ================================================== -->

            <div class="col-xl-4">

                <div class="dashboard-card">


                    <div class="dashboard-card-header">

                        <h6>
                            Low Stock
                        </h6>


                        <a
                            href="inventory/index.php"
                            class="btn btn-sm btn-outline-danger"
                        >

                            Inventory

                        </a>

                    </div>


                    <div class="dashboard-card-body p-0">


                        <?php if (count($lowStockProducts) > 0): ?>


                            <div class="list-group list-group-flush">


                                <?php foreach ($lowStockProducts as $product): ?>


                                    <div class="list-group-item px-3 py-3">


                                        <div class="d-flex justify-content-between">


                                            <div>

                                                <strong>

                                                    <?= htmlspecialchars(
                                                        $product['product_name']
                                                    ) ?>

                                                </strong>


                                                <div class="text-muted small">

                                                    <?= htmlspecialchars(
                                                        $product['product_code']
                                                    ) ?>

                                                </div>

                                            </div>


                                            <div class="text-end">

                                                <strong class="text-danger">

                                                    <?= number_format(
                                                        $product['stock'],
                                                        0
                                                    ) ?>

                                                    <?= htmlspecialchars(
                                                        $product['unit']
                                                    ) ?>

                                                </strong>


                                                <div class="text-muted small">

                                                    Reorder:

                                                    <?= number_format(
                                                        $product['reorder_level'],
                                                        0
                                                    ) ?>

                                                </div>

                                            </div>


                                        </div>

                                    </div>


                                <?php endforeach; ?>


                            </div>


                        <?php else: ?>


                            <div class="text-center text-muted py-5">

                                <i
                                    class="bi bi-check-circle fs-2 text-success"
                                ></i>


                                <p class="mt-2 mb-0">

                                    All products have sufficient stock.

                                </p>

                            </div>


                        <?php endif; ?>


                    </div>

                </div>

            </div>


        </div>


        <!-- ====================================================
             QUICK ACTIONS
        ===================================================== -->

        <div class="dashboard-card mt-4">


            <div class="dashboard-card-header">

                <h6>
                    Quick Actions
                </h6>

            </div>


            <div class="dashboard-card-body">


                <div class="row g-3">


                    <!-- OPEN CASHIER -->

                    <div class="col-lg-3 col-md-6">

                        <a
                            href="pos/index.php"
                            class="btn btn-primary w-100 py-3"
                        >

                            <i class="bi bi-cart-plus me-2"></i>

                            Open Cashier

                        </a>

                    </div>


                    <!-- ADD PRODUCT -->

                    <div class="col-lg-3 col-md-6">

                        <a
                            href="products/add.php"
                            class="btn btn-outline-primary w-100 py-3"
                        >

                            <i class="bi bi-plus-circle me-2"></i>

                            Add Product

                        </a>

                    </div>


                    <!-- STOCK IN -->

                    <div class="col-lg-3 col-md-6">

                        <a
                            href="inventory/stock_in.php"
                            class="btn btn-outline-success w-100 py-3"
                        >

                            <i class="bi bi-box-arrow-in-down me-2"></i>

                            Stock In

                        </a>

                    </div>


                    <!-- SALES REPORT -->

                    <div class="col-lg-3 col-md-6">

                        <a
                            href="sales/reports.php"
                            class="btn btn-outline-dark w-100 py-3"
                        >

                            <i class="bi bi-bar-chart-line me-2"></i>

                            Sales Report

                        </a>

                    </div>


                </div>

            </div>

        </div>


    </section>

</main>


<!-- ============================================================
     EDIT CASH MODAL
============================================================= -->

<div
    class="modal fade"
    id="dashboardFundsModal"
    tabindex="-1"
>


    <div class="modal-dialog">


        <div class="modal-content">


            <form method="POST">


                <input
                    type="hidden"
                    name="save_dashboard_funds"
                    value="1"
                >


                <!-- HEADER -->

                <div class="modal-header">


                    <h5 class="modal-title">

                        Edit Cash in Drawer

                    </h5>


                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>


                </div>


                <!-- BODY -->

                <div class="modal-body">


                    <div class="mb-3">


                        <label class="form-label fw-semibold">

                            <i class="bi bi-safe2 me-1"></i>

                            Cash in Drawer

                        </label>


                        <div class="input-group">


                            <span class="input-group-text">

                                ₱

                            </span>


                            <input
                                type="number"
                                class="form-control"
                                name="cash_in_drawer"
                                step="0.01"
                                min="0"
                                value="<?= htmlspecialchars(
                                    number_format(
                                        $cashInDrawer,
                                        2,
                                        '.',
                                        ''
                                    )
                                ) ?>"
                                required
                            >


                        </div>


                    </div>


                    <!-- PROFIT INFO -->

                    <div class="alert alert-info mb-0">


                        <div class="d-flex">


                            <i class="bi bi-info-circle me-2 mt-1"></i>


                            <div>


                                <strong>
                                    Total Profit is automatic.
                                </strong>


                                <br>


                                Profit is calculated using:

                                <br>


                                <strong>
                                    (Selling Price − Cost Price) × Quantity
                                </strong>


                                <br>


                                from completed sales only.


                            </div>


                        </div>


                    </div>


                </div>


                <!-- FOOTER -->

                <div class="modal-footer">


                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >

                        Cancel

                    </button>


                    <button
                        type="submit"
                        class="btn btn-success"
                    >

                        <i class="bi bi-check-circle me-1"></i>

                        Save Cash

                    </button>


                </div>


            </form>


        </div>


    </div>


</div>


<?php

require_once __DIR__ . "/includes/footer.php";

?>