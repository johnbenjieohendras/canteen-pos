<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/database.php";

$pageTitle = "Edit Category";

$id = (int)($_GET['id'] ?? 0);


if ($id <= 0) {

    header(
        "Location: index.php?error=" .
        urlencode("Invalid category.")
    );

    exit;
}


// Get category

$stmt = $pdo->prepare("
    SELECT *
    FROM categories
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$category = $stmt->fetch();


if (!$category) {

    header(
        "Location: index.php?error=" .
        urlencode("Category not found.")
    );

    exit;
}


$errors = [];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $categoryName =
        trim($_POST['category_name'] ?? '');

    $description =
        trim($_POST['description'] ?? '');

    $status =
        $_POST['status'] ?? 'active';


    if ($categoryName === '') {

        $errors[] =
            "Category name is required.";

    }


    if (!in_array(
        $status,
        ['active', 'inactive'],
        true
    )) {

        $status = 'active';

    }


    // Duplicate

    if (!$errors) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM categories
            WHERE category_name = ?
            AND id != ?
            LIMIT 1
        ");

        $stmt->execute([
            $categoryName,
            $id
        ]);

        if ($stmt->fetch()) {

            $errors[] =
                "Category name already exists.";

        }

    }


    if (!$errors) {

        try {

            $stmt = $pdo->prepare("
                UPDATE categories
                SET
                    category_name = ?,
                    description = ?,
                    status = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $categoryName,
                $description !== ''
                    ? $description
                    : null,
                $status,
                $id
            ]);


            header(
                "Location: index.php?success=" .
                urlencode(
                    "Category successfully updated."
                )
            );

            exit;


        } catch (PDOException $e) {

            $errors[] =
                "Unable to update category: " .
                $e->getMessage();

        }

    }


    $category['category_name'] =
        $categoryName;

    $category['description'] =
        $description;

    $category['status'] =
        $status;

}


require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";

?>


<main class="main-content">


    <header class="topbar">

        <div class="page-heading">

            <h4>
                Edit Category
            </h4>

            <small>
                Update category information
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
                                Category ID:
                                <?= $category['id'] ?>
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
                                    value="<?= htmlspecialchars(
                                        $category['category_name']
                                    ) ?>"
                                    required
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
                                ><?= htmlspecialchars(
                                    $category['description'] ?? ''
                                ) ?></textarea>

                            </div>


                            <div class="mb-4">

                                <label class="form-label">
                                    Status
                                </label>


                                <select
                                    name="status"
                                    class="form-select"
                                >

                                    <option
                                        value="active"
                                        <?= $category['status']
                                            === 'active'
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        Active
                                    </option>


                                    <option
                                        value="inactive"
                                        <?= $category['status']
                                            === 'inactive'
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        Inactive
                                    </option>

                                </select>

                            </div>


                            <div class="d-flex gap-2">

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >

                                    <i class="bi bi-check-lg me-1"></i>

                                    Update Category

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

            </div>

        </div>

    </section>

</main>


<?php require_once __DIR__ . "/../includes/footer.php"; ?>