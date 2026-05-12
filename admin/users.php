<?php
/**
 * Users CRUD API (Admin)
 *
 * GET    /api/admin/users.php          - List all users (supports ?search=)
 * GET    /api/admin/users.php?id=1     - Get single user
 * PUT    /api/admin/users.php          - Update a user
 * DELETE /api/admin/users.php?id=1     - Delete a user
 *
 * Requires: X-Admin-Id header
 */

require_once __DIR__ . '/admin_auth.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ─── LIST / GET SINGLE ───────────────────────────────
    case 'GET':
        if (!empty($_GET['id'])) {
            // Single user
            $stmt = $pdo->prepare("SELECT id, google_id, email, username, given_name, family_name, picture, email_verified, created_at, last_login FROM users WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $user = $stmt->fetch();

            if (!$user) {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "User not found"]);
                exit;
            }
            echo json_encode(["success" => true, "data" => $user]);
        } else {
            // List all users with optional search
            $search = trim($_GET['search'] ?? '');
            if ($search) {
                $stmt = $pdo->prepare("SELECT id, google_id, email, username, given_name, family_name, picture, email_verified, created_at, last_login FROM users WHERE username LIKE ? OR email LIKE ? ORDER BY created_at DESC");
                $like = "%$search%";
                $stmt->execute([$like, $like]);
            } else {
                $stmt = $pdo->query("SELECT id, google_id, email, username, given_name, family_name, picture, email_verified, created_at, last_login FROM users ORDER BY created_at DESC");
            }
            $users = $stmt->fetchAll();
            echo json_encode(["success" => true, "data" => $users]);
        }
        break;

    // ─── UPDATE ──────────────────────────────────────────
    case 'PUT':
        $body = json_decode(file_get_contents("php://input"), true);
        $id = $body['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "User ID is required"]);
            exit;
        }

        // Check user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "User not found"]);
            exit;
        }

        // Build dynamic update
        $allowed = ['username', 'given_name', 'family_name', 'email', 'email_verified'];
        $fields = [];
        $values = [];
        foreach ($allowed as $field) {
            if (isset($body[$field])) {
                $fields[] = "$field = ?";
                $values[] = $body[$field];
            }
        }

        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "No valid fields to update"]);
            exit;
        }

        $values[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $pdo->prepare($sql)->execute($values);

        echo json_encode(["success" => true, "message" => "User updated successfully"]);
        break;

    // ─── DELETE ──────────────────────────────────────────
    case 'DELETE':
        $id = $_GET['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "User ID is required"]);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "User not found"]);
            exit;
        }

        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        echo json_encode(["success" => true, "message" => "User deleted successfully"]);
        break;

    default:
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Method not allowed"]);
}
