<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/database.php";

$pageTitle = "Products";

$search = trim($_GET['search'] ?? '');

if ($search !== '') {

    $stmt = $pdo->prepare("
        SELECT
            p.*,
            c.category_name,
            s.supplier_name
        FROM products p
        LEFT JOIN categories c
            ON c.id = p.category_id
        LEFT JOIN suppliers s
            ON s.id = p.supplier_id
        WHERE
            p.product_name LIKE ?
            OR p.product_code LIKE ?
            OR p.barcode LIKE ?
        ORDER BY p.id DESC
    ");

    $keyword = "%{$search}%";

    $stmt->execute([
        $keyword,
        $keyword,
        $keyword
    ]);

} else {

    $stmt = $pdo->query("
        SELECT
            p.*,
            c.category_name,
            s.supplier_name
        FROM products p
        LEFT JOIN categories c
            ON c.id = p.category_id
        LEFT JOIN suppliers s
            ON s.id = p.supplier_id
        ORDER BY p.id DESC
    ");
}

$products = $stmt->fetchAll();

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";

?>

<main class="main-content">

    <header class="topbar">

        <div class="page-heading">

            <h4>Products</h4>

            <small>
                Manage your canteen products and pricing
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

            <div class="dashboard-card-header">

                <div>

                    <h6>
                        Product List
                    </h6>

                    <small class="text-muted">

                        <?= number_format(count($products)) ?>
                        product(s)

                    </small>

                </div>


                <a
                    href="add.php"
                    class="btn btn-primary"
                >

                    <i class="bi bi-plus-lg me-1"></i>

                    Add Product

                </a>

            </div>


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
                                placeholder="Search product name, code or barcode..."
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


            <div class="table-responsive">

                <table class="table table-hover">

                    <thead>

                        <tr>

                            <th>#</th>

                            <th>Product</th>

                            <th>Category</th>

                            <th>Supplier</th>

                            <th>Cost</th>

                            <th>Selling Price</th>

                            <th>Stock</th>

                            <th>Status</th>

                            <th class="text-end">Action</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (count($products) > 0): ?>

                        <?php foreach ($products as $index => $product): ?>

                            <?php

                            $stock = (float)$product['stock'];
                            $reorder = (float)$product['reorder_level'];

                            if ($stock <= 0) {

                                $stockClass = 'text-danger';
                                $stockBadge = 'bg-danger';
                                $stockText = 'Out of Stock';

                            } elseif ($stock <= $reorder) {

                                $stockClass = 'text-warning';
                                $stockBadge = 'bg-warning text-dark';
                                $stockText = 'Low Stock';

                            } else {

                                $stockClass = 'text-success';
                                $stockBadge = 'bg-success';
                                $stockText = 'In Stock';

                            }

                            ?>

                            <tr>

                                <td>
                                    <?= $index + 1 ?>
                                </td>


                                <td>

                                    <div class="fw-semibold">

                                        <?= htmlspecialchars(
                                            $product['product_name']
                                        ) ?>

                                    </div>

                                    <small class="text-muted">

                                        <?= htmlspecialchars(
                                            $product['product_code']
                                        ) ?>

                                        <?php if (!empty($product['barcode'])): ?>

                                            ·
                                            <?= htmlspecialchars(
                                                $product['barcode']
                                            ) ?>

                                        <?php endif; ?>

                                    </small>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $product['category_name']
                                        ?? 'Uncategorized'
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $product['supplier_name']
                                        ?? '—'
                                    ) ?>

                                </td>


                                <td>

                                    ₱<?= number_format(
                                        $product['cost_price'],
                                        2
                                    ) ?>

                                </td>


                                <td>

                                    <strong>

                                        ₱<?= number_format(
                                            $product['selling_price'],
                                            2
                                        ) ?>

                                    </strong>

                                </td>


                                <td>

                                    <span class="<?= $stockClass ?> fw-bold">

                                        <?= number_format(
                                            $stock,
                                            3
                                        ) ?>

                                        <?= htmlspecialchars(
                                            $product['unit']
                                        ) ?>

                                    </span>

                                    <div>

                                        <small class="text-muted">

                                            Reorder:
                                            <?= number_format(
                                                $reorder,
                                                3
                                            ) ?>

                                        </small>

                                    </div>

                                </td>


                                <td>

                                    <span class="badge <?= $stockBadge ?>">

                                        <?= $stockText ?>

                                    </span>

                                </td>


                                <td class="text-end">

                                    <div class="btn-group">

                                        <a
                                            href="edit.php?id=<?= $product['id'] ?>"
                                            class="btn btn-sm btn-outline-primary"
                                            title="Edit"
                                        >

                                            <i class="bi bi-pencil"></i>

                                        </a>


                                        <a
                                            href="delete.php?id=<?= $product['id'] ?>"
                                            class="btn btn-sm btn-outline-danger"
                                            title="Delete"
                                            onclick="return confirm('Are you sure you want to deactivate this product?')"
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
                                colspan="9"
                                class="text-center py-5"
                            >

                                <i
                                    class="bi bi-box-seam fs-1 text-muted"
                                ></i>

                                <h6 class="mt-3">
                                    No products found
                                </h6>

                                <p class="text-muted">

                                    Add your first canteen product.

                                </p>

                                <a
                                    href="add.php"
                                    class="btn btn-primary"
                                >
                                    Add Product
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