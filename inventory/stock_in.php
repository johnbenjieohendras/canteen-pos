<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/database.php";

$pageTitle = "Stock In";

$errors = [];

$selectedSupplier = (int)($_POST['supplier_id'] ?? 0);
$referenceNo = trim($_POST['reference_no'] ?? '');
$notes = trim($_POST['notes'] ?? '');

$oldItems = $_POST['items'] ?? [];


// ============================================================
// SUPPLIERS
// ============================================================

$stmt = $pdo->query("
    SELECT
        id,
        supplier_code,
        supplier_name
    FROM suppliers
    WHERE status = 'active'
    ORDER BY supplier_name ASC
");

$suppliers = $stmt->fetchAll();


// ============================================================
// PRODUCTS
// ============================================================

$stmt = $pdo->query("
    SELECT
        p.id,
        p.product_code,
        p.product_name,
        p.unit,
        p.cost_price,
        p.stock,
        c.category_name
    FROM products p
    LEFT JOIN categories c
        ON c.id = p.category_id
    WHERE p.status = 'active'
    ORDER BY p.product_name ASC
");

$products = $stmt->fetchAll();


// ============================================================
// SAVE STOCK IN
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --------------------------------------------------------
    // BASIC VALIDATION
    // --------------------------------------------------------

    if ($selectedSupplier <= 0) {

        $errors[] = "Please select a supplier.";

    }


    if (!$oldItems || !is_array($oldItems)) {

        $errors[] = "Please add at least one product.";

    }


    // --------------------------------------------------------
    // VALIDATE SUPPLIER
    // --------------------------------------------------------

    if (!$errors) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM suppliers
            WHERE id = ?
            AND status = 'active'
            LIMIT 1
        ");

        $stmt->execute([
            $selectedSupplier
        ]);

        if (!$stmt->fetch()) {

            $errors[] = "Selected supplier is invalid.";

        }

    }


    // --------------------------------------------------------
    // PROCESS ITEMS
    // --------------------------------------------------------

    $cleanItems = [];
    $totalAmount = 0;

    if (!$errors) {

        foreach ($oldItems as $item) {

            $productId =
                (int)($item['product_id'] ?? 0);

            $quantity =
                (float)($item['quantity'] ?? 0);

            $costPrice =
                (float)($item['cost_price'] ?? 0);


            // ------------------------------------------------
            // PRODUCT ID
            // ------------------------------------------------

            if ($productId <= 0) {

                continue;

            }


            // ------------------------------------------------
            // QUANTITY
            // ------------------------------------------------

            if ($quantity <= 0) {

                $errors[] =
                    "Quantity must be greater than zero.";

                break;

            }


            // ------------------------------------------------
            // COST PRICE
            // ------------------------------------------------

            if ($costPrice < 0) {

                $errors[] =
                    "Cost price cannot be negative.";

                break;

            }


            // ------------------------------------------------
            // CHECK PRODUCT
            // ------------------------------------------------

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    product_name,
                    unit,
                    stock
                FROM products
                WHERE id = ?
                AND status = 'active'
                LIMIT 1
            ");

            $stmt->execute([
                $productId
            ]);

            $product = $stmt->fetch();


            if (!$product) {

                $errors[] =
                    "One of the selected products is invalid.";

                break;

            }


            // ------------------------------------------------
            // SUBTOTAL
            // ------------------------------------------------

            $subtotal = round(
                $quantity * $costPrice,
                2
            );


            // ------------------------------------------------
            // CLEAN ITEM
            // ------------------------------------------------

            $cleanItems[] = [

                'product_id' =>
                    $productId,

                'product_name' =>
                    $product['product_name'],

                'unit' =>
                    $product['unit'],

                'quantity' =>
                    $quantity,

                'cost_price' =>
                    $costPrice,

                'subtotal' =>
                    $subtotal

            ];


            $totalAmount += $subtotal;

        }


        // ----------------------------------------------------
        // NO VALID ITEMS
        // ----------------------------------------------------

        if (!$cleanItems && !$errors) {

            $errors[] =
                "Please add at least one valid product.";

        }


        // ----------------------------------------------------
        // ROUND TOTAL
        // ----------------------------------------------------

        $totalAmount = round(
            $totalAmount,
            2
        );

    }


    // ========================================================
    // DATABASE TRANSACTION
    // ========================================================

    if (!$errors) {

        try {

            // ------------------------------------------------
            // START TRANSACTION
            // ------------------------------------------------

            $pdo->beginTransaction();


            // =================================================
            // GET CASH IN DRAWER
            // =================================================

            $getCash = $pdo->prepare("
                SELECT
                    cash_in_drawer
                FROM dashboard_funds
                WHERE id = 1
                LIMIT 1
                FOR UPDATE
            ");

            $getCash->execute();

            $cashData = $getCash->fetch();


            if (!$cashData) {

                throw new Exception(
                    "Dashboard funds record was not found."
                );

            }


            $currentCash =
                (float)$cashData['cash_in_drawer'];


            // =================================================
            // CHECK AVAILABLE CASH
            // =================================================

            if ($totalAmount > $currentCash) {

                throw new Exception(

                    "Insufficient Cash in Drawer. " .

                    "Required: ₱" .
                    number_format(
                        $totalAmount,
                        2
                    ) .

                    " | Available: ₱" .
                    number_format(
                        $currentCash,
                        2
                    )

                );

            }


            // =================================================
            // GENERATE STOCK IN NUMBER
            // =================================================

            $stockInNo =
                'SI-' .
                date('Ymd-His') .
                '-' .
                random_int(100, 999);


            // =================================================
            // INSERT STOCK IN HEADER
            // =================================================

            $stmt = $pdo->prepare("
                INSERT INTO stock_ins
                (
                    stock_in_no,
                    supplier_id,
                    user_id,
                    total_amount,
                    reference_no,
                    notes,
                    stock_in_date
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    NOW()
                )
            ");

            $stmt->execute([

                $stockInNo,

                $selectedSupplier,

                $_SESSION['user_id'],

                $totalAmount,

                $referenceNo !== ''
                    ? $referenceNo
                    : null,

                $notes !== ''
                    ? $notes
                    : null

            ]);


            $stockInId =
                $pdo->lastInsertId();


            // =================================================
            // PREPARED STATEMENTS
            // =================================================


            // Insert stock item

            $insertItem = $pdo->prepare("
                INSERT INTO stock_in_items
                (
                    stock_in_id,
                    product_id,
                    quantity,
                    cost_price,
                    subtotal
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");


            // Get product stock

            $getStock = $pdo->prepare("
                SELECT
                    stock,
                    product_name
                FROM products
                WHERE id = ?
                AND status = 'active'
                FOR UPDATE
            ");


            // Update product

            $updateProduct = $pdo->prepare("
                UPDATE products
                SET
                    stock = stock + ?,
                    cost_price = ?
                WHERE id = ?
            ");


            // Inventory movement

            $insertMovement = $pdo->prepare("
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
                    'STOCK_IN',
                    'STOCK_IN',
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");


            // =================================================
            // PROCESS EVERY PRODUCT
            // =================================================

            foreach ($cleanItems as $item) {

                $productId =
                    $item['product_id'];

                $quantity =
                    $item['quantity'];

                $costPrice =
                    $item['cost_price'];

                $subtotal =
                    $item['subtotal'];


                // ------------------------------------------------
                // LOCK PRODUCT ROW
                // ------------------------------------------------

                $getStock->execute([
                    $productId
                ]);

                $stockData =
                    $getStock->fetch();


                if (!$stockData) {

                    throw new Exception(
                        "Product not found."
                    );

                }


                // ------------------------------------------------
                // STOCK BEFORE
                // ------------------------------------------------

                $stockBefore =
                    (float)$stockData['stock'];


                // ------------------------------------------------
                // STOCK AFTER
                // ------------------------------------------------

                $stockAfter =
                    $stockBefore + $quantity;


                // ------------------------------------------------
                // INSERT STOCK IN ITEM
                // ------------------------------------------------

                $insertItem->execute([

                    $stockInId,

                    $productId,

                    $quantity,

                    $costPrice,

                    $subtotal

                ]);


                // ------------------------------------------------
                // UPDATE PRODUCT STOCK
                // ------------------------------------------------

                $updateProduct->execute([

                    $quantity,

                    $costPrice,

                    $productId

                ]);


                // ------------------------------------------------
                // INSERT INVENTORY MOVEMENT
                // ------------------------------------------------

                $insertMovement->execute([

                    $productId,

                    $_SESSION['user_id'],

                    $stockInId,

                    $quantity,

                    $stockBefore,

                    $stockAfter,

                    "Stock In: {$stockInNo}"

                ]);

            }


            // =================================================
            // DEDUCT CASH IN DRAWER
            // =================================================

            $newCash =
                round(
                    $currentCash - $totalAmount,
                    2
                );


            $updateCash = $pdo->prepare("
                UPDATE dashboard_funds
                SET
                    cash_in_drawer = ?
                WHERE id = 1
            ");


            $updateCash->execute([
                $newCash
            ]);


            // =================================================
            // AUDIT LOG
            // =================================================

            $audit = $pdo->prepare("
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
                    'STOCK_IN',
                    'Inventory',
                    ?,
                    ?,
                    ?
                )
            ");


            $auditDescription =
                "Created Stock In {$stockInNo}. " .
                "Total Cost: ₱" .
                number_format(
                    $totalAmount,
                    2
                ) .
                ". " .
                "Cash in Drawer deducted: ₱" .
                number_format(
                    $totalAmount,
                    2
                ) .
                ". " .
                "Remaining Cash: ₱" .
                number_format(
                    $newCash,
                    2
                );


            $audit->execute([

                $_SESSION['user_id'],

                $stockInId,

                $auditDescription,

                $_SERVER['REMOTE_ADDR'] ?? null

            ]);


            // =================================================
            // COMMIT
            // =================================================

            $pdo->commit();


            // =================================================
            // SUCCESS
            // =================================================

            header(
                "Location: index.php?success=" .
                urlencode(

                    "Stock In {$stockInNo} saved successfully. " .
                    "₱" .
                    number_format(
                        $totalAmount,
                        2
                    ) .
                    " deducted from Cash in Drawer."

                )
            );

            exit;


        } catch (Throwable $e) {

            // ------------------------------------------------
            // ROLLBACK
            // ------------------------------------------------

            if ($pdo->inTransaction()) {

                $pdo->rollBack();

            }


            $errors[] =
                "Unable to save Stock In: " .
                $e->getMessage();

        }

    }

}


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
                Stock In
            </h4>

            <small>
                Receive new inventory stock
            </small>

        </div>


        <div>

            <a
                href="index.php"
                class="btn btn-outline-secondary"
            >

                <i class="bi bi-arrow-left me-1"></i>

                Back to Inventory

            </a>

        </div>

    </header>


    <section class="content-area">


        <!-- =================================================
             ERRORS
        ================================================== -->

        <?php if ($errors): ?>

            <div class="alert alert-danger">

                <div class="fw-bold mb-1">

                    <i
                        class="bi bi-exclamation-triangle me-1"
                    ></i>

                    Please correct the following:

                </div>


                <ul class="mb-0">

                    <?php foreach ($errors as $error): ?>

                        <li>

                            <?= htmlspecialchars($error) ?>

                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <!-- =================================================
             FORM
        ================================================== -->

        <form
            method="POST"
            id="stockInForm"
        >


            <!-- =================================================
                 STOCK IN INFORMATION
            ================================================== -->

            <div class="dashboard-card mb-4">


                <div class="dashboard-card-header">

                    <div>

                        <h6>
                            Stock In Information
                        </h6>

                        <small class="text-muted">
                            Enter receiving details
                        </small>

                    </div>

                </div>


                <div class="dashboard-card-body">


                    <div class="row g-3">


                        <!-- SUPPLIER -->

                        <div class="col-md-4">

                            <label class="form-label">

                                Supplier

                                <span class="text-danger">
                                    *
                                </span>

                            </label>


                            <select
                                name="supplier_id"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select Supplier
                                </option>


                                <?php foreach (
                                    $suppliers as $supplier
                                ): ?>

                                    <option
                                        value="<?= $supplier['id'] ?>"
                                        <?= (
                                            $selectedSupplier ==
                                            $supplier['id']
                                        )
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >

                                        <?= htmlspecialchars(
                                            $supplier['supplier_name']
                                        ) ?>

                                        -

                                        <?= htmlspecialchars(
                                            $supplier['supplier_code']
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- REFERENCE -->

                        <div class="col-md-4">

                            <label class="form-label">

                                Reference No.

                            </label>


                            <input
                                type="text"
                                name="reference_no"
                                class="form-control"
                                placeholder="Invoice / DR number"
                                value="<?= htmlspecialchars(
                                    $referenceNo
                                ) ?>"
                            >

                        </div>


                        <!-- DATE -->

                        <div class="col-md-4">

                            <label class="form-label">

                                Date

                            </label>


                            <input
                                type="text"
                                class="form-control"
                                value="<?= date(
                                    'M d, Y h:i A'
                                ) ?>"
                                readonly
                            >

                        </div>


                        <!-- NOTES -->

                        <div class="col-12">

                            <label class="form-label">

                                Notes

                            </label>


                            <textarea
                                name="notes"
                                class="form-control"
                                rows="2"
                                placeholder="Optional notes..."
                            ><?= htmlspecialchars(
                                $notes
                            ) ?></textarea>

                        </div>


                    </div>

                </div>

            </div>


            <!-- =================================================
                 PRODUCTS
            ================================================== -->

            <div class="dashboard-card mb-4">


                <div class="dashboard-card-header">

                    <div>

                        <h6>
                            Products
                        </h6>

                        <small class="text-muted">
                            Add products received
                        </small>

                    </div>


                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        onclick="addProductRow()"
                    >

                        <i class="bi bi-plus-lg me-1"></i>

                        Add Product

                    </button>

                </div>


                <div class="table-responsive">


                    <table
                        class="table"
                        id="stockInTable"
                    >

                        <thead>

                            <tr>

                                <th style="width:38%">
                                    Product
                                </th>

                                <th style="width:15%">
                                    Current Stock
                                </th>

                                <th style="width:15%">
                                    Quantity
                                </th>

                                <th style="width:15%">
                                    Cost Price
                                </th>

                                <th style="width:12%">
                                    Subtotal
                                </th>

                                <th style="width:5%">
                                </th>

                            </tr>

                        </thead>


                        <tbody id="stockInItems">


                        <?php

                        if ($oldItems):

                            foreach (
                                $oldItems as $index => $item
                            ):

                                $productId =
                                    (int)(
                                        $item['product_id']
                                        ?? 0
                                    );

                                $quantity =
                                    (float)(
                                        $item['quantity']
                                        ?? 0
                                    );

                                $costPrice =
                                    (float)(
                                        $item['cost_price']
                                        ?? 0
                                    );

                        ?>

                            <tr class="stock-row">

                                <td>

                                    <select
                                        name="items[<?= $index ?>][product_id]"
                                        class="form-select product-select"
                                        onchange="updateProductInfo(this)"
                                        required
                                    >

                                        <option value="">
                                            Select Product
                                        </option>


                                        <?php foreach (
                                            $products as $product
                                        ): ?>

                                            <option
                                                value="<?= $product['id'] ?>"
                                                data-stock="<?= $product['stock'] ?>"
                                                data-cost="<?= $product['cost_price'] ?>"
                                                <?= (
                                                    $productId ==
                                                    $product['id']
                                                )
                                                    ? 'selected'
                                                    : ''
                                                ?>
                                            >

                                                <?= htmlspecialchars(
                                                    $product['product_name']
                                                ) ?>

                                                —

                                                <?= htmlspecialchars(
                                                    $product['product_code']
                                                ) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </td>


                                <td>

                                    <span class="current-stock">
                                        —
                                    </span>

                                </td>


                                <td>

                                    <input
                                        type="number"
                                        name="items[<?= $index ?>][quantity]"
                                        class="form-control quantity-input"
                                        min="0.001"
                                        step="0.001"
                                        value="<?= $quantity ?>"
                                        oninput="calculateRow(this)"
                                        required
                                    >

                                </td>


                                <td>

                                    <input
                                        type="number"
                                        name="items[<?= $index ?>][cost_price]"
                                        class="form-control cost-input"
                                        min="0"
                                        step="0.01"
                                        value="<?= $costPrice ?>"
                                        oninput="calculateRow(this)"
                                        required
                                    >

                                </td>


                                <td>

                                    <strong class="row-subtotal">

                                        ₱0.00

                                    </strong>

                                </td>


                                <td>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        onclick="removeProductRow(this)"
                                    >

                                        <i class="bi bi-trash"></i>

                                    </button>

                                </td>

                            </tr>

                        <?php

                            endforeach;

                        endif;

                        ?>


                        </tbody>


                        <tfoot>

                            <tr>

                                <td
                                    colspan="4"
                                    class="text-end fw-bold"
                                >

                                    TOTAL

                                </td>


                                <td>

                                    <strong
                                        id="grandTotal"
                                        class="fs-5"
                                    >

                                        ₱0.00

                                    </strong>

                                </td>


                                <td></td>

                            </tr>

                        </tfoot>


                    </table>

                </div>

            </div>


            <!-- =================================================
                 PAYMENT / CASH INFORMATION
            ================================================== -->

            <div class="dashboard-card mb-4">

                <div class="dashboard-card-body">

                    <div class="row align-items-center">

                        <div class="col-md-6">

                            <div class="text-muted small">

                                STOCK IN TOTAL COST

                            </div>

                            <div
                                class="fs-3 fw-bold"
                                id="totalCostDisplay"
                            >

                                ₱0.00

                            </div>

                            <small class="text-muted">

                                This amount will be deducted from
                                Cash in Drawer.

                            </small>

                        </div>


                        <div class="col-md-6 text-md-end mt-3 mt-md-0">

                            <div class="text-muted small">

                                CALCULATION

                            </div>

                            <div class="fw-semibold">

                                Quantity × Cost Price

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 ACTIONS
            ================================================== -->

            <div class="d-flex justify-content-end gap-2">

                <a
                    href="index.php"
                    class="btn btn-light"
                >

                    Cancel

                </a>


                <button
                    type="submit"
                    class="btn btn-primary px-4"
                >

                    <i class="bi bi-check-lg me-1"></i>

                    Save Stock In

                </button>

            </div>


        </form>

    </section>

</main>


<script>


// ============================================================
// ROW INDEX
// ============================================================

let rowIndex =
    <?= count($oldItems) ?>;


// ============================================================
// ADD PRODUCT
// ============================================================

function addProductRow() {

    const tbody =
        document.getElementById(
            'stockInItems'
        );


    const row =
        document.createElement('tr');


    row.className =
        'stock-row';


    row.innerHTML = `

        <td>

            <select
                name="items[${rowIndex}][product_id]"
                class="form-select product-select"
                onchange="updateProductInfo(this)"
                required
            >

                <option value="">
                    Select Product
                </option>

                <?php foreach ($products as $product): ?>

                    <option
                        value="<?= $product['id'] ?>"
                        data-stock="<?= $product['stock'] ?>"
                        data-cost="<?= $product['cost_price'] ?>"
                    >

                        <?= htmlspecialchars(
                            $product['product_name']
                        ) ?>

                        —

                        <?= htmlspecialchars(
                            $product['product_code']
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </td>


        <td>

            <span class="current-stock">
                —
            </span>

        </td>


        <td>

            <input
                type="number"
                name="items[${rowIndex}][quantity]"
                class="form-control quantity-input"
                min="0.001"
                step="0.001"
                value="1"
                oninput="calculateRow(this)"
                required
            >

        </td>


        <td>

            <input
                type="number"
                name="items[${rowIndex}][cost_price]"
                class="form-control cost-input"
                min="0"
                step="0.01"
                value="0.00"
                oninput="calculateRow(this)"
                required
            >

        </td>


        <td>

            <strong class="row-subtotal">

                ₱0.00

            </strong>

        </td>


        <td>

            <button
                type="button"
                class="btn btn-sm btn-outline-danger"
                onclick="removeProductRow(this)"
            >

                <i class="bi bi-trash"></i>

            </button>

        </td>

    `;


    tbody.appendChild(row);


    rowIndex++;

}


// ============================================================
// UPDATE PRODUCT INFORMATION
// ============================================================

function updateProductInfo(select) {

    const row =
        select.closest(
            '.stock-row'
        );


    const option =
        select.options[
            select.selectedIndex
        ];


    if (!option) {
        return;
    }


    const stock =
        option.dataset.stock || 0;


    const cost =
        option.dataset.cost || 0;


    row.querySelector(
        '.current-stock'
    ).textContent =
        parseFloat(stock).toFixed(3);


    const costInput =
        row.querySelector(
            '.cost-input'
        );


    if (
        !costInput.value ||
        parseFloat(costInput.value) === 0
    ) {

        costInput.value =
            parseFloat(cost).toFixed(2);

    }


    calculateRow(
        costInput
    );

}


// ============================================================
// CALCULATE ROW
// ============================================================

function calculateRow(input) {

    const row =
        input.closest(
            '.stock-row'
        );


    if (!row) {
        return;
    }


    const quantity =
        parseFloat(
            row.querySelector(
                '.quantity-input'
            )?.value
        ) || 0;


    const cost =
        parseFloat(
            row.querySelector(
                '.cost-input'
            )?.value
        ) || 0;


    const subtotal =
        quantity * cost;


    row.querySelector(
        '.row-subtotal'
    ).textContent =

        '₱' +

        subtotal.toLocaleString(
            'en-PH',
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );


    calculateGrandTotal();

}


// ============================================================
// GRAND TOTAL
// ============================================================

function calculateGrandTotal() {

    let total = 0;


    document
        .querySelectorAll(
            '.stock-row'
        )
        .forEach(
            function(row) {

                const quantity =
                    parseFloat(
                        row.querySelector(
                            '.quantity-input'
                        )?.value
                    ) || 0;


                const cost =
                    parseFloat(
                        row.querySelector(
                            '.cost-input'
                        )?.value
                    ) || 0;


                total +=
                    quantity * cost;

            }
        );


    document.getElementById(
        'grandTotal'
    ).textContent =

        '₱' +

        total.toLocaleString(
            'en-PH',
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );


    const totalCostDisplay =
        document.getElementById(
            'totalCostDisplay'
        );


    if (totalCostDisplay) {

        totalCostDisplay.textContent =

            '₱' +

            total.toLocaleString(
                'en-PH',
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );

    }

}


// ============================================================
// REMOVE ROW
// ============================================================

function removeProductRow(button) {

    const row =
        button.closest(
            '.stock-row'
        );


    if (!row) {
        return;
    }


    row.remove();


    calculateGrandTotal();

}


// ============================================================
// FORM VALIDATION
// ============================================================

document
    .getElementById(
        'stockInForm'
    )
    .addEventListener(
        'submit',
        function(event) {

            const rows =
                document.querySelectorAll(
                    '.stock-row'
                );


            if (rows.length === 0) {

                event.preventDefault();


                alert(
                    'Please add at least one product.'
                );


                return;

            }


            let valid = true;


            rows.forEach(
                function(row) {

                    const product =
                        row.querySelector(
                            '.product-select'
                        )?.value;


                    const quantity =
                        parseFloat(
                            row.querySelector(
                                '.quantity-input'
                            )?.value
                        ) || 0;


                    const cost =
                        parseFloat(
                            row.querySelector(
                                '.cost-input'
                            )?.value
                        ) || 0;


                    if (
                        !product ||
                        quantity <= 0 ||
                        cost < 0
                    ) {

                        valid = false;

                    }

                }
            );


            if (!valid) {

                event.preventDefault();


                alert(
                    'Please check all product, quantity and cost fields.'
                );

            }

        }
    );


// ============================================================
// INITIAL CALCULATION
// ============================================================

document
    .querySelectorAll(
        '.product-select'
    )
    .forEach(
        function(select) {

            if (select.value) {

                updateProductInfo(
                    select
                );

            }

        }
    );


calculateGrandTotal();

</script>


<?php

require_once __DIR__ . "/../includes/footer.php";

?>