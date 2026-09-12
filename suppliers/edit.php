<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/database.php";

$pageTitle = "Edit Supplier";

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$errors = [];


// ============================================================
// GET SUPPLIER
// ============================================================

$stmt = $pdo->prepare("
    SELECT *
    FROM suppliers
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $id
]);

$supplier = $stmt->fetch();


if (!$supplier) {

    header(
        "Location: index.php?error=" .
        urlencode("Supplier not found.")
    );

    exit;
}


// ============================================================
// FORM VALUES
// ============================================================

$supplierCode =
    trim(
        $_POST['supplier_code']
        ?? $supplier['supplier_code']
    );

$supplierName =
    trim(
        $_POST['supplier_name']
        ?? $supplier['supplier_name']
    );

$contactPerson =
    trim(
        $_POST['contact_person']
        ?? ($supplier['contact_person'] ?? '')
    );

$contactNumber =
    trim(
        $_POST['contact_number']
        ?? ($supplier['contact_number'] ?? '')
    );

$email =
    trim(
        $_POST['email']
        ?? ($supplier['email'] ?? '')
    );

$address =
    trim(
        $_POST['address']
        ?? ($supplier['address'] ?? '')
    );


// ============================================================
// UPDATE
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($supplierCode === '') {
        $errors[] = "Supplier code is required.";
    }

    if ($supplierName === '') {
        $errors[] = "Supplier name is required.";
    }

    if (
        $email !== '' &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $errors[] = "Invalid email address.";
    }


    // Duplicate code

    if (!$errors) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM suppliers
            WHERE supplier_code = ?
            AND id != ?
            LIMIT 1
        ");

        $stmt->execute([
            $supplierCode,
            $id
        ]);

        if ($stmt->fetch()) {

            $errors[] =
                "Supplier code already exists.";
        }
    }


    if (!$errors) {

        try {

            $stmt = $pdo->prepare("
                UPDATE suppliers
                SET
                    supplier_code = ?,
                    supplier_name = ?,
                    contact_person = ?,
                    contact_number = ?,
                    email = ?,
                    address = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $supplierCode,
                $supplierName,
                $contactPerson !== ''
                    ? $contactPerson
                    : null,
                $contactNumber !== ''
                    ? $contactNumber
                    : null,
                $email !== ''
                    ? $email
                    : null,
                $address !== ''
                    ? $address
                    : null,
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
                    'UPDATE',
                    'Suppliers',
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $_SESSION['user_id'],
                $id,
                "Updated supplier: " .
                $supplierName .
                " (" .
                $supplierCode .
                ")",
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);


            header(
                "Location: index.php?success=" .
                urlencode(
                    "Supplier updated successfully."
                )
            );

            exit;


        } catch (Throwable $e) {

            $errors[] =
                "Unable to update supplier: " .
                $e->getMessage();
        }
    }
}


require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";

?>


<main class="main-content">


    <header class="topbar">

        <div class="page-heading">

            <h4>
                Edit Supplier
            </h4>

            <small>
                Update supplier information
            </small>

        </div>


        <a
            href="index.php"
            class="btn btn-outline-secondary"
        >

            <i class="bi bi-arrow-left me-1"></i>

            Back

        </a>

    </header>


    <section class="content-area">


        <?php if ($errors): ?>

            <div class="alert alert-danger">

                <ul class="mb-0">

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= htmlspecialchars($error) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <form method="POST">

            <input
                type="hidden"
                name="id"
                value="<?= $id ?>"
            >


            <div class="dashboard-card">


                <div class="dashboard-card-header">

                    <div>

                        <h6>
                            Supplier Information
                        </h6>

                        <small class="text-muted">
                            Update supplier details
                        </small>

                    </div>

                </div>


                <div class="dashboard-card-body">


                    <div class="row g-3">


                        <div class="col-md-4">

                            <label class="form-label">

                                Supplier Code
                                <span class="text-danger">*</span>

                            </label>

                            <input
                                type="text"
                                name="supplier_code"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $supplierCode
                                ) ?>"
                                required
                            >

                        </div>


                        <div class="col-md-8">

                            <label class="form-label">

                                Supplier Name
                                <span class="text-danger">*</span>

                            </label>

                            <input
                                type="text"
                                name="supplier_name"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $supplierName
                                ) ?>"
                                required
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Contact Person
                            </label>

                            <input
                                type="text"
                                name="contact_person"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $contactPerson
                                ) ?>"
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Contact Number
                            </label>

                            <input
                                type="text"
                                name="contact_number"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $contactNumber
                                ) ?>"
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Email
                            </label>

                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $email
                                ) ?>"
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Address
                            </label>

                            <input
                                type="text"
                                name="address"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $address
                                ) ?>"
                            >

                        </div>


                    </div>

                </div>

            </div>


            <div
                class="d-flex justify-content-end gap-2 mt-4"
            >

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

                    Update Supplier

                </button>

            </div>


        </form>


    </section>

</main>


<?php

require_once __DIR__ . "/../includes/footer.php";

?>