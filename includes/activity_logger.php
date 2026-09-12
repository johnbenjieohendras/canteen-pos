<?php

if (!function_exists('log_activity')) {

    function log_activity(
        PDO $pdo,
        string $action,
        string $module,
        string $description = ''
    ): void {

        try {

            $user_id = $_SESSION['user_id'] ?? null;
            $username = $_SESSION['username'] ?? 'System';

            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

            $sql = "
                INSERT INTO audit_logs (
                    user_id,
                    username,
                    action,
                    module,
                    description,
                    ip_address
                )
                VALUES (
                    :user_id,
                    :username,
                    :action,
                    :module,
                    :description,
                    :ip_address
                )
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':user_id'     => $user_id,
                ':username'    => $username,
                ':action'      => $action,
                ':module'      => $module,
                ':description' => $description,
                ':ip_address'  => $ip_address
            ]);

        } catch (Throwable $e) {

            /*
             * Do not stop the system if activity logging fails.
             */

            error_log(
                "Activity Log Error: " . $e->getMessage()
            );
        }

    }

}