<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/database.php";

$pageTitle = "Add Supplier";

$errors = [];

$supplierCode = trim($_POST['supplier_code'] ?? '');
$supplierName = trim($_POST['supplier_name'] ?? '');
$contactPerson = trim($_POST['contact_person'] ?? '');
$contactNumber = trim($_POST['contact_number'] ?? '');
$email = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');


// ============================================================
// SAVE
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
        $errors[] = "Please enter a valid email address.";
    }


    // Check duplicate code

    if (!$errors) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM suppliers
            WHERE supplier_code = ?
            LIMIT 1
        ");

        $stmt->execute([
            $supplierCode
        ]);

        if ($stmt->fetch()) {

            $errors[] =
                "Supplier code already exists.";
        }
    }


    // Save

    if (!$errors) {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO suppliers
                (
                    supplier_code,
                    supplier_name,
                    contact_person,
                    contact_number,
                    email,
                    address,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'active'
                )
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
                    : null
            ]);


            $supplierId =
                $pdo->lastInsertId();


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
                    'CREATE',
                    'Suppliers',
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $_SESSION['user_id'],
                $supplierId,
                "Created supplier: " .
                $supplierName .
                " (" .
                $supplierCode .
                ")",
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);


            header(
                "Location: index.php?success=" .
                urlencode(
                    "Supplier added successfully."
                )
            );

            exit;


        } catch (Throwable $e) {

            $errors[] =
                "Unable to save supplier: " .
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
                Add Supplier
            </h4>

            <small>
                Create a new supplier
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


            <div class="dashboard-card">


                <div class="dashboard-card-header">

                    <div>

                        <h6>
                            Supplier Information
                        </h6>

                        <small class="text-muted">
                            Enter supplier details
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
                                placeholder="SUP-003"
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
                                placeholder="Supplier company name"
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
                                placeholder="Contact person"
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
                                placeholder="09XXXXXXXXX"
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
                                placeholder="supplier@example.com"
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
                                placeholder="Supplier address"
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

                    Save Supplier

                </button>

            </div>


        </form>


    </section>

</main>


<?php

require_once __DIR__ . "/../includes/footer.php";

?>