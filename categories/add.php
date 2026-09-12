<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/database.php";

$pageTitle = "Add Category";

$errors = [];

$categoryName = '';
$description = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $categoryName = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');


    if ($categoryName === '') {

        $errors[] = "Category name is required.";

    }


    // Check duplicate

    if (!$errors) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM categories
            WHERE category_name = ?
            LIMIT 1
        ");

        $stmt->execute([
            $categoryName
        ]);

        if ($stmt->fetch()) {

            $errors[] =
                "Category name already exists.";

        }

    }


    // Save

    if (!$errors) {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO categories
                (
                    category_name,
                    description,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    'active'
                )
            ");

            $stmt->execute([
                $categoryName,
                $description !== ''
                    ? $description
                    : null
            ]);


            header(
                "Location: index.php?success=" .
                urlencode(
                    "Category successfully added."
                )
            );

            exit;


        } catch (PDOException $e) {

            $errors[] =
                "Unable to save category: " .
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
                Add Category
            </h4>

            <small>
                Create a new product category
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


        <div class="row justify-content-center">

            <div class="col-lg-7">

                <div class="dashboard-card">


                    <div class="dashboard-card-header">

                        <div>

                            <h6>
                                Category Information
                            </h6>

                            <small class="text-muted">
                                Enter the category details below.
                            </small>

                        </div>

                    </div>


                    <div class="dashboard-card-body">

                        <form method="POST">


                            <div class="mb-4">

                                <label class="form-label">

                                    Category Name

                                    <span class="text-danger">
                                        *
                                    </span>

                                </label>


                                <input
                                    type="text"
                                    name="category_name"
                                    class="form-control"
                                    placeholder="e.g. Beverages"
                                    value="<?= htmlspecialchars(
                                        $categoryName
                                    ) ?>"
                                    required
                                    autofocus
                                >

                            </div>


                            <div class="mb-4">

                                <label class="form-label">

                                    Description

                                </label>


                                <textarea
                                    name="description"
                                    class="form-control"
                                    rows="4"
                                    placeholder="Optional category description..."
                                ><?= htmlspecialchars(
                                    $description
                                ) ?></textarea>

                            </div>


                            <div class="d-flex gap-2">

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >

                                    <i class="bi bi-check-lg me-1"></i>

                                    Save Category

                                </button>


                                <a
                                    href="index.php"
                                    class="btn btn-light"
                                >

                                    Cancel

                                </a>

                            </div>


                        </form>

                    </div>

                </div>


                <!-- EXAMPLES -->

                <div class="dashboard-card mt-4">

                    <div class="dashboard-card-body">

                        <h6 class="fw-bold mb-3">

                            Suggested Categories

                        </h6>


                        <div class="d-flex flex-wrap gap-2">

                            <span class="badge bg-light text-dark border">
                                Beverages
                            </span>

                            <span class="badge bg-light text-dark border">
                                Snacks
                            </span>

                            <span class="badge bg-light text-dark border">
                                Meals
                            </span>

                            <span class="badge bg-light text-dark border">
                                Rice Meals
                            </span>

                            <span class="badge bg-light text-dark border">
                                Desserts
                            </span>

                            <span class="badge bg-light text-dark border">
                                School Supplies
                            </span>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </section>

</main>


<?php require_once __DIR__ . "/../includes/footer.php"; ?>