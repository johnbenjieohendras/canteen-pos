<?php

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$search = trim($_GET['search'] ?? '');

if ($search !== '') {

    $stmt = $pdo->prepare("
        SELECT id, username, full_name, role, status, created_at
        FROM users
        WHERE username LIKE ?
           OR full_name LIKE ?
        ORDER BY id DESC
    ");

    $keyword = "%{$search}%";

    $stmt->execute([
        $keyword,
        $keyword
    ]);

} else {

    $stmt = $pdo->query("
        SELECT id, username, full_name, role, status, created_at
        FROM users
        ORDER BY id DESC
    ");
}

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>User Management | Canteen POS</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f4f6f9;
    font-family: Arial, Helvetica, sans-serif;
    color: #212529;
}

.container {
    max-width: 1300px;
    margin: 30px auto;
    padding: 0 20px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    gap: 15px;
}

.header h1 {
    margin: 0;
    font-size: 28px;
}

.header p {
    color: #6c757d;
    margin-top: 6px;
}

.actions {
    display: flex;
    gap: 8px;
}

.btn {
    display: inline-block;
    padding: 10px 15px;
    border-radius: 6px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.btn-primary {
    background: #0d6efd;
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-warning {
    background: #ffc107;
    color: #212529;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 3px 12px rgba(0,0,0,.06);
    margin-bottom: 20px;
}

.search-form {
    display: flex;
    gap: 10px;
}

.search-form input {
    flex: 1;
    padding: 10px;
    border: 1px solid #ced4da;
    border-radius: 6px;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #212529;
    color: white;
    padding: 12px;
    text-align: left;
    font-size: 13px;
}

td {
    padding: 12px;
    border-bottom: 1px solid #dee2e6;
    font-size: 14px;
}

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.badge-admin {
    background: #e7f1ff;
    color: #0d6efd;
}

.badge-cashier {
    background: #e8f7ee;
    color: #198754;
}

.badge-inventory {
    background: #fff3cd;
    color: #856404;
}

.badge-active {
    background: #d1e7dd;
    color: #0f5132;
}

.badge-inactive {
    background: #f8d7da;
    color: #842029;
}

.actions-cell {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.empty {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

</style>

</head>

<body>

<div class="container">

    <div class="header">

        <div>

            <h1>
                👤 User Management
            </h1>

            <p>
                Manage system users, roles and access
            </p>

        </div>

        <div class="actions">

            <a
                href="../index.php"
                class="btn btn-secondary"
            >
                ← Back Office
            </a>

            <a
                href="add.php"
                class="btn btn-primary"
            >
                + Add User
            </a>

        </div>

    </div>


    <div class="card">

        <form
            method="GET"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                placeholder="Search username or full name..."
                value="<?= htmlspecialchars($search) ?>"
            >

            <button
                type="submit"
                class="btn btn-primary"
            >
                🔍 Search
            </button>

            <?php if ($search !== ''): ?>

                <a
                    href="index.php"
                    class="btn btn-secondary"
                >
                    Clear
                </a>

            <?php endif; ?>

        </form>

    </div>


    <div class="card">

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>#</th>

                        <th>Username</th>

                        <th>Full Name</th>

                        <th>Role</th>

                        <th>Status</th>

                        <th>Created</th>

                        <th>Actions</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (!$users): ?>

                    <tr>

                        <td
                            colspan="7"
                            class="empty"
                        >
                            No users found.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($users as $user): ?>

                        <tr>

                            <td>
                                <?= (int)$user['id'] ?>
                            </td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars(
                                        $user['username']
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $user['full_name']
                                ) ?>
                            </td>

                            <td>

                                <span
                                    class="badge badge-<?= htmlspecialchars(
                                        $user['role']
                                    ) ?>"
                                >
                                    <?= ucfirst(
                                        $user['role']
                                    ) ?>
                                </span>

                            </td>

                            <td>

                                <?php if ($user['status'] === 'active'): ?>

                                    <span class="badge badge-active">
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-inactive">
                                        Inactive
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= date(
                                    'M d, Y',
                                    strtotime(
                                        $user['created_at']
                                    )
                                ) ?>
                            </td>

                            <td>

                                <div class="actions-cell">

                                    <a
                                        href="edit.php?id=<?= (int)$user['id'] ?>"
                                        class="btn btn-warning"
                                    >
                                        Edit
                                    </a>

                                    <?php if (
                                        (int)$user['id']
                                        !==
                                        (int)$_SESSION['user_id']
                                    ): ?>

                                        <a
                                            href="toggle.php?id=<?= (int)$user['id'] ?>"
                                            class="btn btn-secondary"
                                            onclick="return confirm('Change this user status?')"
                                        >
                                            <?= $user['status'] === 'active'
                                                ? 'Deactivate'
                                                : 'Activate'
                                            ?>
                                        </a>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>

</html>