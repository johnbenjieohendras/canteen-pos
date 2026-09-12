<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/database.php";

$pageTitle = "Categories";

$search = trim($_GET['search'] ?? '');

if ($search !== '') {

    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.category_name,
            c.description,
            c.status,
            c.created_at,
            COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN products p
            ON p.category_id = c.id
            AND p.status = 'active'
        WHERE c.category_name LIKE ?
        GROUP BY
            c.id,
            c.category_name,
            c.description,
            c.status,
            c.created_at
        ORDER BY c.id DESC
    ");

    $stmt->execute([
        "%{$search}%"
    ]);

} else {

    $stmt = $pdo->query("
        SELECT
            c.id,
            c.category_name,
            c.description,
            c.status,
            c.created_at,
            COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN products p
            ON p.category_id = c.id
            AND p.status = 'active'
        GROUP BY
            c.id,
            c.category_name,
            c.description,
            c.status,
            c.created_at
        ORDER BY c.id DESC
    ");
}

$categories = $stmt->fetchAll();

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";

?>

<main class="main-content">

    <header class="topbar">

        <div class="page-heading">

            <h4>
                Categories
            </h4>

            <small>
                Manage product categories
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


        <?php if (isset($_GET['success'])): ?>

            <div class="alert alert-success alert-dismissible fade show">

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

            <div class="alert alert-danger alert-dismissible fade show">

                <i class="bi bi-exclamation-triangle me-2"></i>

                <?= htmlspecialchars($_GET['error']) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        <?php endif; ?>


        <div class="dashboard-card">


            <!-- HEADER -->

            <div class="dashboard-card-header">

                <div>

                    <h6>
                        Category List
                    </h6>

                    <small class="text-muted">

                        <?= number_format(count($categories)) ?>
                        categor<?= count($categories) === 1 ? 'y' : 'ies' ?>

                    </small>

                </div>


                <a
                    href="add.php"
                    class="btn btn-primary"
                >

                    <i class="bi bi-plus-lg me-1"></i>

                    Add Category

                </a>

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
                                placeholder="Search category..."
                                value="<?= htmlspecialchars($search) ?>"
                            >

                        </div>

                    </div>


                    <div class="col-md-2">

                        <button
                            type="submit"
                            class="btn btn-dark w-100"
                        >
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
                                #
                            </th>

                            <th>
                                Category
                            </th>

                            <th>
                                Description
                            </th>

                            <th>
                                Products
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Created
                            </th>

                            <th class="text-end">
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (count($categories) > 0): ?>

                        <?php foreach ($categories as $index => $category): ?>

                            <tr>

                                <td>
                                    <?= $index + 1 ?>
                                </td>


                                <td>

                                    <div class="d-flex align-items-center">

                                        <div
                                            class="category-icon me-3"
                                        >

                                            <i class="bi bi-tag-fill"></i>

                                        </div>


                                        <div>

                                            <div class="fw-semibold">

                                                <?= htmlspecialchars(
                                                    $category['category_name']
                                                ) ?>

                                            </div>

                                            <small class="text-muted">

                                                ID:
                                                <?= $category['id'] ?>

                                            </small>

                                        </div>

                                    </div>

                                </td>


                                <td>

                                    <?php if (
                                        !empty($category['description'])
                                    ): ?>

                                        <?= htmlspecialchars(
                                            $category['description']
                                        ) ?>

                                    <?php else: ?>

                                        <span class="text-muted">
                                            No description
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <span class="badge bg-primary-subtle text-primary">

                                        <?= number_format(
                                            $category['product_count']
                                        ) ?>

                                        product<?= (
                                            $category['product_count'] != 1
                                        ) ? 's' : '' ?>

                                    </span>

                                </td>


                                <td>

                                    <?php if (
                                        $category['status'] === 'active'
                                    ): ?>

                                        <span class="badge bg-success">

                                            Active

                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-secondary">

                                            Inactive

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?= !empty($category['created_at'])
                                        ? date(
                                            'M d, Y',
                                            strtotime(
                                                $category['created_at']
                                            )
                                        )
                                        : '—'
                                    ?>

                                </td>


                                <td class="text-end">

                                    <div class="btn-group">

                                        <a
                                            href="edit.php?id=<?= $category['id'] ?>"
                                            class="btn btn-sm btn-outline-primary"
                                            title="Edit"
                                        >

                                            <i class="bi bi-pencil"></i>

                                        </a>


                                        <a
                                            href="delete.php?id=<?= $category['id'] ?>"
                                            class="btn btn-sm btn-outline-danger"
                                            title="Deactivate"
                                            onclick="return confirm('Are you sure you want to deactivate this category?')"
                                        >

                                            <i class="bi bi-trash"></i>

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
                                    class="bi bi-tags fs-1 text-muted"
                                ></i>

                                <h6 class="mt-3">
                                    No categories found
                                </h6>

                                <p class="text-muted">

                                    Create your first product category.

                                </p>

                                <a
                                    href="add.php"
                                    class="btn btn-primary"
                                >

                                    <i class="bi bi-plus-lg me-1"></i>

                                    Add Category

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


<?php require_once __DIR__ . "/../includes/footer.php"; ?>