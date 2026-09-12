<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| REQUIRE LOGIN
|--------------------------------------------------------------------------
*/

function require_login()
{
    if (!isset($_SESSION['user_id'])) {

        header("Location: /canteen_pos/login.php");

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

function current_user()
{
    return [
        'id'        => $_SESSION['user_id'] ?? null,
        'username'  => $_SESSION['username'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? null,
        'role'      => $_SESSION['role'] ?? null
    ];
}


/*
|--------------------------------------------------------------------------
| CURRENT ROLE
|--------------------------------------------------------------------------
*/

function current_role()
{
    return $_SESSION['role'] ?? null;
}


/*
|--------------------------------------------------------------------------
| CHECK ROLE
|--------------------------------------------------------------------------
*/

function has_role($roles)
{
    require_login();

    if (!is_array($roles)) {
        $roles = [$roles];
    }

    return in_array(
        current_role(),
        $roles,
        true
    );
}


/*
|--------------------------------------------------------------------------
| REQUIRE ROLE
|--------------------------------------------------------------------------
*/

function require_role($roles)
{
    require_login();

    if (!is_array($roles)) {
        $roles = [$roles];
    }

    /*
    |--------------------------------------------------------------------------
    | Authorized
    |--------------------------------------------------------------------------
    */

    if (in_array(current_role(), $roles, true)) {
        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | CASHIER
    |
    | Cashier is ONLY allowed to use POS.
    |--------------------------------------------------------------------------
    */

    if (current_role() === 'cashier') {

        header(
            "Location: /canteen_pos/pos/index.php"
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | INVENTORY USER
    |--------------------------------------------------------------------------
    */

    if (current_role() === 'inventory') {

        header(
            "Location: /canteen_pos/inventory/index.php"
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | OTHER UNAUTHORIZED USERS
    |--------------------------------------------------------------------------
    */

    http_response_code(403);

    ?>

    <!DOCTYPE html>

    <html lang="en">

    <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title>Access Denied</title>


        <style>

            * {
                box-sizing: border-box;
            }


            body {

                margin: 0;

                min-height: 100vh;

                display: flex;

                align-items: center;

                justify-content: center;

                background: #f4f6f9;

                font-family:
                    Arial,
                    Helvetica,
                    sans-serif;
            }


            .access-box {

                width: 430px;

                max-width: 90%;

                background: white;

                padding: 45px 35px;

                text-align: center;

                border-radius: 16px;

                box-shadow:
                    0 10px 35px
                    rgba(0,0,0,.12);
            }


            .icon {

                font-size: 60px;

                margin-bottom: 15px;
            }


            h1 {

                margin: 0 0 12px;

                color: #dc3545;

                font-size: 28px;
            }


            p {

                color: #6c757d;

                line-height: 1.6;

                margin-bottom: 25px;
            }


            .btn {

                display: inline-block;

                padding: 12px 22px;

                border-radius: 8px;

                background: #212529;

                color: white;

                text-decoration: none;

                font-weight: 600;
            }


            .btn:hover {

                background: #343a40;
            }

        </style>

    </head>


    <body>

        <div class="access-box">

            <div class="icon">
                🚫
            </div>


            <h1>
                Access Denied
            </h1>


            <p>

                You do not have permission
                to access this module.

            </p>


            <a
                href="/canteen_pos/login.php"
                class="btn"
            >
                Return to Login
            </a>

        </div>

    </body>

    </html>

    <?php

    exit;
}


/*
|--------------------------------------------------------------------------
| REQUIRE ADMIN
|--------------------------------------------------------------------------
*/

function require_admin()
{
    require_role('admin');
}


/*
|--------------------------------------------------------------------------
| REQUIRE CASHIER
|--------------------------------------------------------------------------
*/

function require_cashier()
{
    require_role('cashier');
}


/*
|--------------------------------------------------------------------------
| REQUIRE INVENTORY
|--------------------------------------------------------------------------
*/

function require_inventory()
{
    require_role([
        'admin',
        'inventory'
    ]);
}


/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
*/

function logout_user()
{
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    header(
        "Location: /canteen_pos/login.php"
    );

    exit;
}