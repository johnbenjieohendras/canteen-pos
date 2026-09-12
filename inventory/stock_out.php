<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/database.php";

$pageTitle = "Stock Out";

$errors = [];

$productId = (int)($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
$quantity = (float)($_POST['quantity'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$notes = trim($_POST['notes'] ?? '');


// ============================================================
// GET PRODUCT
// ============================================================

$product = null;

if ($productId > 0) {

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.product_code,
            p.product_name,
            p.unit,
            p.stock,
            p.reorder_level,
            p.cost_price,
            p.selling_price,
            c.category_name
        FROM products p
        LEFT JOIN categories c
            ON c.id = p.category_id
        WHERE p.id = ?
        AND p.status = 'active'
        LIMIT 1
    ");

    $stmt->execute([
        $productId
    ]);

    $product = $stmt->fetch();
}


// ============================================================
// SAVE STOCK OUT
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$product) {
        $errors[] = "Invalid product selected.";
    }

    if ($quantity <= 0) {
        $errors[] = "Quantity must be greater than zero.";
    }

    if ($reason === '') {
        $errors[] = "Please select a reason.";
    }


    // --------------------------------------------------------
    // Check stock
    // --------------------------------------------------------

    if (!$errors) {

        $stmt = $pdo->prepare("
            SELECT
                id,
                product_name,
                stock,
                unit
            FROM products
            WHERE id = ?
            AND status = 'active'
            FOR UPDATE
        ");

        /*
         * NOTE:
         * FOR UPDATE is only effective inside a transaction.
         * We start the transaction below after validation.
         */
    }


    // ========================================================
    // TRANSACTION
    // ========================================================

    if (!$errors) {

        try {

            $pdo->beginTransaction();


            // ------------------------------------------------
            // Lock product row
            // ------------------------------------------------

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    product_name,
                    stock,
                    unit
                FROM products
                WHERE id = ?
                AND status = 'active'
                FOR UPDATE
            ");

            $stmt->execute([
                $productId
            ]);

            $lockedProduct =
                $stmt->fetch();


            if (!$lockedProduct) {

                throw new Exception(
                    "Product no longer exists or is inactive."
                );
            }


            $stockBefore =
                (float)$lockedProduct['stock'];


            // ------------------------------------------------
            // Prevent negative stock
            // ------------------------------------------------

            if ($quantity > $stockBefore) {

                throw new Exception(
                    "Insufficient stock. Current stock is " .
                    number_format(
                        $stockBefore,
                        3
                    ) .
                    " " .
                    $lockedProduct['unit'] .
                    "."
                );
            }


            $stockAfter =
                $stockBefore - $quantity;


            // ------------------------------------------------
            // Update stock
            // ------------------------------------------------

            $stmt = $pdo->prepare("
                UPDATE products
                SET stock = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $stockAfter,
                $productId
            ]);


            // ------------------------------------------------
            // Inventory movement
            // ------------------------------------------------

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
                    'ADJUSTMENT_SUBTRACT',
                    'MANUAL_STOCK_OUT',
                    NULL,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $remarks =
                $reason;

            if ($notes !== '') {
                $remarks .= " - " . $notes;
            }


            $stmt->execute([
                $productId,
                $_SESSION['user_id'],
                $quantity,
                $stockBefore,
                $stockAfter,
                $remarks
            ]);


            // ------------------------------------------------
            // Audit Log
            // ------------------------------------------------

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
                    'STOCK_OUT',
                    'Inventory',
                    ?,
                    ?,
                    ?
                )
            ");


            $description =
                "Stock Out: " .
                $lockedProduct['product_name'] .
                " | Quantity: " .
                number_format(
                    $quantity,
                    3
                ) .
                " " .
                $lockedProduct['unit'] .
                " | Reason: " .
                $reason;


            $stmt->execute([
                $_SESSION['user_id'],
                $productId,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);


            // ------------------------------------------------
            // Commit
            // ------------------------------------------------

            $pdo->commit();


            header(
                "Location: index.php?success=" .
                urlencode(
                    "Stock Out completed successfully."
                )
            );

            exit;


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] =
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
                Stock Out
            </h4>

            <small>
                Remove or adjust inventory stock
            </small>

        </div>


        <a
            href="index.php"
            class="btn btn-outline-secondary"
        >

            <i class="bi bi-arrow-left me-1"></i>

            Back to Inventory

        </a>

    </header>


    <section class="content-area">


        <!-- =================================================
             ERRORS
        ================================================== -->

        <?php if ($errors): ?>

            <div class="alert alert-danger">

                <div class="fw-bold mb-1">

                    <i class="bi bi-exclamation-triangle me-2"></i>

                    Unable to complete Stock Out

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


        <?php if (!$product): ?>


            <div class="dashboard-card">

                <div class="text-center py-5">

                    <i
                        class="bi bi-box-seam fs-1 text-muted"
                    ></i>

                    <h5 class="mt-3">
                        Product not found
                    </h5>

                    <p class="text-muted">
                        Please select a valid product from Inventory.
                    </p>

                    <a
                        href="index.php"
                        class="btn btn-primary"
                    >

                        Back to Inventory

                    </a>

                </div>

            </div>


        <?php else: ?>


            <form
                method="POST"
                id="stockOutForm"
            >

                <input
                    type="hidden"
                    name="product_id"
                    value="<?= $product['id'] ?>"
                >


                <!-- =================================================
                     PRODUCT INFORMATION
                ================================================== -->

                <div class="dashboard-card mb-4">


                    <div class="dashboard-card-header">

                        <div>

                            <h6>
                                Product Information
                            </h6>

                            <small class="text-muted">
                                Selected inventory item
                            </small>

                        </div>

                    </div>


                    <div class="dashboard-card-body">


                        <div class="row g-3">


                            <!-- PRODUCT -->

                            <div class="col-md-6">

                                <label class="form-label">
                                    Product
                                </label>

                                <div class="form-control bg-light">

                                    <strong>
                                        <?= htmlspecialchars(
                                            $product['product_name']
                                        ) ?>
                                    </strong>

                                    <span class="text-muted">

                                        —

                                        <?= htmlspecialchars(
                                            $product['product_code']
                                        ) ?>

                                    </span>

                                </div>

                            </div>


                            <!-- CATEGORY -->

                            <div class="col-md-3">

                                <label class="form-label">
                                    Category
                                </label>

                                <div class="form-control bg-light">

                                    <?= htmlspecialchars(
                                        $product['category_name']
                                        ?? 'Uncategorized'
                                    ) ?>

                                </div>

                            </div>


                            <!-- UNIT -->

                            <div class="col-md-3">

                                <label class="form-label">
                                    Unit
                                </label>

                                <div class="form-control bg-light">

                                    <?= htmlspecialchars(
                                        $product['unit']
                                    ) ?>

                                </div>

                            </div>


                        </div>

                    </div>

                </div>


                <!-- =================================================
                     STOCK INFORMATION
                ================================================== -->

                <div class="row g-4 mb-4">


                    <!-- CURRENT STOCK -->

                    <div class="col-md-4">

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
                                Current Stock
                            </div>


                            <div
                                class="stat-value"
                                id="currentStock"
                            >

                                <?= number_format(
                                    (float)$product['stock'],
                                    3
                                ) ?>

                            </div>


                            <small class="text-muted">

                                <?= htmlspecialchars(
                                    $product['unit']
                                ) ?>

                            </small>

                        </div>

                    </div>


                    <!-- REORDER LEVEL -->

                    <div class="col-md-4">

                        <div class="stat-card">

                            <div
                                class="stat-icon"
                                style="
                                    background:#fff7ed;
                                    color:#ea580c;
                                "
                            >

                                <i
                                    class="bi bi-exclamation-triangle"
                                ></i>

                            </div>


                            <div class="stat-title">
                                Reorder Level
                            </div>


                            <div class="stat-value">

                                <?= number_format(
                                    (float)$product[
                                        'reorder_level'
                                    ],
                                    3
                                ) ?>

                            </div>


                            <small class="text-muted">

                                <?= htmlspecialchars(
                                    $product['unit']
                                ) ?>

                            </small>

                        </div>

                    </div>


                    <!-- REMAINING -->

                    <div class="col-md-4">

                        <div class="stat-card">

                            <div
                                class="stat-icon"
                                style="
                                    background:#f0fdf4;
                                    color:#16a34a;
                                "
                            >

                                <i class="bi bi-box-arrow-down"></i>

                            </div>


                            <div class="stat-title">
                                Remaining Stock
                            </div>


                            <div
                                class="stat-value"
                                id="remainingStock"
                            >

                                <?= number_format(
                                    (float)$product['stock'],
                                    3
                                ) ?>

                            </div>


                            <small class="text-muted">

                                After adjustment

                            </small>

                        </div>

                    </div>


                </div>


                <!-- =================================================
                     STOCK OUT FORM
                ================================================== -->

                <div class="dashboard-card mb-4">


                    <div class="dashboard-card-header">

                        <div>

                            <h6>
                                Stock Out Details
                            </h6>

                            <small class="text-muted">
                                Specify the quantity and reason
                            </small>

                        </div>

                    </div>


                    <div class="dashboard-card-body">


                        <div class="row g-3">


                            <!-- QUANTITY -->

                            <div class="col-md-4">

                                <label class="form-label">

                                    Quantity

                                    <span class="text-danger">
                                        *
                                    </span>

                                </label>


                                <div class="input-group">

                                    <input
                                        type="number"
                                        name="quantity"
                                        id="quantity"
                                        class="form-control"
                                        min="0.001"
                                        max="<?= htmlspecialchars(
                                            $product['stock']
                                        ) ?>"
                                        step="0.001"
                                        value="<?= $quantity > 0
                                            ? htmlspecialchars(
                                                $quantity
                                            )
                                            : ''
                                        ?>"
                                        required
                                    >


                                    <span class="input-group-text">

                                        <?= htmlspecialchars(
                                            $product['unit']
                                        ) ?>

                                    </span>

                                </div>


                                <small
                                    class="text-muted"
                                >

                                    Maximum:

                                    <?= number_format(
                                        (float)$product['stock'],
                                        3
                                    ) ?>

                                </small>

                            </div>


                            <!-- REASON -->

                            <div class="col-md-4">

                                <label class="form-label">

                                    Reason

                                    <span class="text-danger">
                                        *
                                    </span>

                                </label>


                                <select
                                    name="reason"
                                    class="form-select"
                                    required
                                >

                                    <option value="">
                                        Select Reason
                                    </option>

                                    <option
                                        value="Damaged"
                                        <?= $reason === 'Damaged'
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        Damaged
                                    </option>

                                    <option
                                        value="Expired"
                                        <?= $reason === 'Expired'
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        Expired
                                    </option>

                                    <option
                                        value="Wastage"
                                        <?= $reason === 'Wastage'
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        Wastage
                                    </option>

                                    <option
                                        value="Missing"
                                        <?= $reason === 'Missing'
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        Missing
                                    </option>

                                    <option
                                        value="Inventory Adjustment"
                                        <?= $reason ===
                                            'Inventory Adjustment'
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        Inventory Adjustment
                                    </option>

                                    <option
                                        value="Other"
                                        <?= $reason === 'Other'
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        Other
                                    </option>

                                </select>

                            </div>


                            <!-- NOTES -->

                            <div class="col-md-4">

                                <label class="form-label">
                                    Notes
                                </label>


                                <input
                                    type="text"
                                    name="notes"
                                    class="form-control"
                                    placeholder="Optional notes..."
                                    value="<?= htmlspecialchars(
                                        $notes
                                    ) ?>"
                                >

                            </div>


                        </div>

                    </div>

                </div>


                <!-- =================================================
                     WARNING
                ================================================== -->

                <div class="alert alert-warning">

                    <i class="bi bi-exclamation-triangle me-2"></i>

                    <strong>Important:</strong>

                    Stock Out will permanently deduct the entered
                    quantity from the current inventory.

                </div>


                <!-- =================================================
                     BUTTONS
                ================================================== -->

                <div
                    class="d-flex justify-content-end gap-2"
                >

                    <a
                        href="index.php"
                        class="btn btn-light"
                    >
                        Cancel
                    </a>


                    <button
                        type="submit"
                        class="btn btn-danger px-4"
                        id="saveButton"
                    >

                        <i class="bi bi-dash-circle me-1"></i>

                        Save Stock Out

                    </button>

                </div>


            </form>


        <?php endif; ?>


    </section>

</main>


<script>

const currentStock =
    <?= (float)$product['stock'] ?>;


const quantityInput =
    document.getElementById(
        'quantity'
    );


const remainingStock =
    document.getElementById(
        'remainingStock'
    );


const saveButton =
    document.getElementById(
        'saveButton'
    );


// ============================================================
// UPDATE REMAINING STOCK
// ============================================================

if (quantityInput) {

    quantityInput.addEventListener(
        'input',
        function() {

            const quantity =
                parseFloat(
                    this.value
                ) || 0;


            const remaining =
                currentStock - quantity;


            remainingStock.textContent =
                Math.max(
                    remaining,
                    0
                ).toFixed(3);


            if (
                quantity > currentStock
            ) {

                remainingStock.classList.add(
                    'text-danger'
                );

                saveButton.disabled =
                    true;

            } else {

                remainingStock.classList.remove(
                    'text-danger'
                );

                saveButton.disabled =
                    false;

            }

        }
    );

}


// ============================================================
// FORM VALIDATION
// ============================================================

const form =
    document.getElementById(
        'stockOutForm'
    );


if (form) {

    form.addEventListener(
        'submit',
        function(event) {

            const quantity =
                parseFloat(
                    quantityInput.value
                ) || 0;


            if (quantity <= 0) {

                event.preventDefault();

                alert(
                    'Quantity must be greater than zero.'
                );

                return;
            }


            if (
                quantity > currentStock
            ) {

                event.preventDefault();

                alert(
                    'Insufficient stock. You cannot deduct more than the current stock.'
                );

                return;
            }


            const confirmed =
                confirm(
                    'Are you sure you want to deduct ' +
                    quantity.toFixed(3) +
                    ' ' +
                    '<?= htmlspecialchars($product['unit']) ?>' +
                    ' from inventory?'
                );


            if (!confirmed) {

                event.preventDefault();

            }

        }
    );

}

</script>


<?php

require_once __DIR__ . "/../includes/footer.php";

?>