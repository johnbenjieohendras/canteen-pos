<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/header.php";

$pageTitle = "Suppliers";

$search = trim($_GET['search'] ?? '');


// ============================================================
// SUPPLIERS
// ============================================================

$sql = "
    SELECT
        s.id,
        s.supplier_code,
        s.supplier_name,
        s.contact_person,
        s.contact_number,
        s.email,
        s.address,
        s.status,
        s.created_at,

        COUNT(DISTINCT p.id) AS product_count

    FROM suppliers s

    LEFT JOIN products p
        ON p.supplier_id = s.id

    WHERE 1 = 1
";

$params = [];


// SEARCH

if ($search !== '') {

    $sql .= "
        AND (
            s.supplier_code LIKE ?
            OR s.supplier_name LIKE ?
            OR s.contact_person LIKE ?
            OR s.contact_number LIKE ?
            OR s.email LIKE ?
        )
    ";

    $value = "%{$search}%";

    $params[] = $value;
    $params[] = $value;
    $params[] = $value;
    $params[] = $value;
    $params[] = $value;
}


$sql .= "
    GROUP BY
        s.id,
        s.supplier_code,
        s.supplier_name,
        s.contact_person,
        s.contact_number,
        s.email,
        s.address,
        s.status,
        s.created_at

    ORDER BY s.supplier_name ASC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$suppliers = $stmt->fetchAll();


// ============================================================
// SUMMARY
// ============================================================

$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total_suppliers,

        SUM(
            CASE
                WHEN status = 'active'
                THEN 1
                ELSE 0
            END
        ) AS active_suppliers,

        SUM(
            CASE
                WHEN status = 'inactive'
                THEN 1
                ELSE 0
            END
        ) AS inactive_suppliers

    FROM suppliers
");

$summary = $stmt->fetch();


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
                Suppliers
            </h4>

            <small>
                Manage your canteen suppliers
            </small>

        </div>


        <a
            href="add.php"
            class="btn btn-primary"
        >

            <i class="bi bi-plus-lg me-1"></i>

            Add Supplier

        </a>

    </header>


    <section class="content-area">


        <!-- =================================================
             ALERTS
        ================================================== -->

        <?php if (isset($_GET['success'])): ?>

            <div
                class="alert alert-success alert-dismissible fade show"
            >

                <i class="bi bi-check-circle me-2"></i>

                <?= htmlspecialchars(
                    $_GET['success']
                ) ?>


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

                <?= htmlspecialchars(
                    $_GET['error']
                ) ?>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        <?php endif; ?>


        <!-- =================================================
             SUMMARY
        ================================================== -->

        <div class="row g-3 mb-4">


            <!-- TOTAL -->

            <div class="col-md-4">

                <div class="stat-card">

                    <div
                        class="stat-icon"
                        style="
                            background:#eff6ff;
                            color:#2563eb;
                        "
                    >

                        <i class="bi bi-truck"></i>

                    </div>


                    <div class="stat-title">
                        Total Suppliers
                    </div>


                    <div class="stat-value">

                        <?= number_format(
                            $summary['total_suppliers'] ?? 0
                        ) ?>

                    </div>

                </div>

            </div>


            <!-- ACTIVE -->

            <div class="col-md-4">

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
                        Active Suppliers
                    </div>


                    <div class="stat-value">

                        <?= number_format(
                            $summary['active_suppliers'] ?? 0
                        ) ?>

                    </div>

                </div>

            </div>


            <!-- INACTIVE -->

            <div class="col-md-4">

                <div class="stat-card">

                    <div
                        class="stat-icon"
                        style="
                            background:#f3f4f6;
                            color:#6b7280;
                        "
                    >

                        <i class="bi bi-pause-circle"></i>

                    </div>


                    <div class="stat-title">
                        Inactive Suppliers
                    </div>


                    <div class="stat-value">

                        <?= number_format(
                            $summary['inactive_suppliers'] ?? 0
                        ) ?>

                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
             SUPPLIER LIST
        ================================================== -->

        <div class="dashboard-card">


            <div
                class="dashboard-card-header"
            >

                <div>

                    <h6>
                        Supplier List
                    </h6>

                    <small class="text-muted">

                        <?= number_format(
                            count($suppliers)
                        ) ?>

                        supplier(s)

                    </small>

                </div>

            </div>


            <!-- SEARCH -->

            <div class="p-3 border-bottom">

                <form
                    method="GET"
                    class="row g-2"
                >

                    <div class="col-md-10">

                        <div class="input-group">

                            <span class="input-group-text">

                                <i class="bi bi-search"></i>

                            </span>


                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="Search supplier, code, contact..."
                                value="<?= htmlspecialchars(
                                    $search
                                ) ?>"
                            >

                        </div>

                    </div>


                    <div class="col-md-2">

                        <button
                            type="submit"
                            class="btn btn-dark w-100"
                        >

                            <i class="bi bi-search me-1"></i>

                            Search

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
                                Supplier
                            </th>

                            <th>
                                Contact Person
                            </th>

                            <th>
                                Contact
                            </th>

                            <th>
                                Email
                            </th>

                            <th>
                                Products
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


                    <?php if ($suppliers): ?>


                        <?php foreach (
                            $suppliers as $supplier
                        ): ?>


                            <tr>


                                <!-- SUPPLIER -->

                                <td>

                                    <div
                                        class="d-flex align-items-center"
                                    >

                                        <div
                                            class="inventory-product-icon"
                                        >

                                            <i class="bi bi-truck"></i>

                                        </div>


                                        <div>

                                            <div
                                                class="fw-semibold"
                                            >

                                                <?= htmlspecialchars(
                                                    $supplier[
                                                        'supplier_name'
                                                    ]
                                                ) ?>

                                            </div>


                                            <small
                                                class="text-muted"
                                            >

                                                <?= htmlspecialchars(
                                                    $supplier[
                                                        'supplier_code'
                                                    ]
                                                ) ?>

                                            </small>

                                        </div>

                                    </div>

                                </td>


                                <!-- CONTACT PERSON -->

                                <td>

                                    <?= htmlspecialchars(
                                        $supplier[
                                            'contact_person'
                                        ]
                                        ?? '—'
                                    ) ?>

                                </td>


                                <!-- CONTACT -->

                                <td>

                                    <?= htmlspecialchars(
                                        $supplier[
                                            'contact_number'
                                        ]
                                        ?? '—'
                                    ) ?>

                                </td>


                                <!-- EMAIL -->

                                <td>

                                    <?= htmlspecialchars(
                                        $supplier[
                                            'email'
                                        ]
                                        ?? '—'
                                    ) ?>

                                </td>


                                <!-- PRODUCTS -->

                                <td>

                                    <span
                                        class="badge bg-light text-dark"
                                    >

                                        <?= number_format(
                                            $supplier[
                                                'product_count'
                                            ]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php if (
                                        $supplier['status']
                                        === 'active'
                                    ): ?>

                                        <span
                                            class="badge bg-success"
                                        >
                                            Active
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="badge bg-secondary"
                                        >
                                            Inactive
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- ACTION -->

                                <td class="text-end">

                                    <div
                                        class="btn-group"
                                    >


                                        <a
                                            href="edit.php?id=<?= $supplier['id'] ?>"
                                            class="btn btn-sm btn-outline-primary"
                                            title="Edit"
                                        >

                                            <i
                                                class="bi bi-pencil"
                                            ></i>

                                        </a>


                                        <a
                                            href="toggle_status.php?id=<?= $supplier['id'] ?>"
                                            class="btn btn-sm btn-outline-warning"
                                            title="Change Status"
                                            onclick="return confirm(
                                                'Are you sure you want to change this supplier status?'
                                            );"
                                        >

                                            <?php if (
                                                $supplier['status']
                                                === 'active'
                                            ): ?>

                                                <i
                                                    class="bi bi-pause"
                                                ></i>

                                            <?php else: ?>

                                                <i
                                                    class="bi bi-play"
                                                ></i>

                                            <?php endif; ?>

                                        </a>


                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="7"
                                class="text-center py-5"
                            >

                                <i
                                    class="bi bi-truck fs-1 text-muted"
                                ></i>


                                <h6 class="mt-3">
                                    No suppliers found
                                </h6>


                                <p class="text-muted">
                                    Add your first supplier.
                                </p>


                                <a
                                    href="add.php"
                                    class="btn btn-primary"
                                >

                                    <i
                                        class="bi bi-plus-lg me-1"
                                    ></i>

                                    Add Supplier

                                </a>

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