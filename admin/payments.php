<?php
/**
 * Payments CRUD API (Admin)
 *
 * GET    /api/admin/payments.php            - List all payments (supports ?status=, ?user_id=, ?booking_id=)
 * GET    /api/admin/payments.php?id=1       - Get single payment
 * POST   /api/admin/payments.php            - Create a payment
 * PUT    /api/admin/payments.php            - Update a payment
 * DELETE /api/admin/payments.php?id=1       - Delete a payment
 *
 * Requires: X-Admin-Id header
 */

require_once __DIR__ . '/admin_auth.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ─── LIST / GET SINGLE ───────────────────────────────
    case 'GET':
        if (!empty($_GET['id'])) {
            $stmt = $pdo->prepare("
                SELECT p.*, u.username as user_name, u.email as user_email,
                       b.start_date, b.end_date, b.total_amount as booking_total,
                       v.name as vehicle_name
                FROM payments p
                LEFT JOIN users u ON p.user_id = u.id
                LEFT JOIN bookings b ON p.booking_id = b.id
                LEFT JOIN vehicles v ON b.vehicle_id = v.id
                WHERE p.id = ?
            ");
            $stmt->execute([$_GET['id']]);
            $payment = $stmt->fetch();

            if (!$payment) {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Payment not found"]);
                exit;
            }
            echo json_encode(["success" => true, "data" => $payment]);
        } else {
            $conditions = [];
            $params = [];

            if (!empty($_GET['status'])) {
                $conditions[] = "p.status = ?";
                $params[] = $_GET['status'];
            }
            if (!empty($_GET['user_id'])) {
                $conditions[] = "p.user_id = ?";
                $params[] = $_GET['user_id'];
            }
            if (!empty($_GET['booking_id'])) {
                $conditions[] = "p.booking_id = ?";
                $params[] = $_GET['booking_id'];
            }

            $sql = "
                SELECT p.*, u.username as user_name, u.email as user_email,
                       b.start_date, b.end_date, v.name as vehicle_name
                FROM payments p
                LEFT JOIN users u ON p.user_id = u.id
                LEFT JOIN bookings b ON p.booking_id = b.id
                LEFT JOIN vehicles v ON b.vehicle_id = v.id
            ";
            if ($conditions) {
                $sql .= " WHERE " . implode(' AND ', $conditions);
            }
            $sql .= " ORDER BY p.created_at DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $payments = $stmt->fetchAll();

            echo json_encode(["success" => true, "data" => $payments]);
        }
        break;

    // ─── CREATE ──────────────────────────────────────────
    case 'POST':
        $body = json_decode(file_get_contents("php://input"), true);

        // Validate required fields
        $required = ['booking_id', 'user_id', 'amount', 'payment_method'];
        $missing = [];
        foreach ($required as $field) {
            if (!isset($body[$field]) || $body[$field] === '') {
                $missing[] = $field;
            }
        }
        if ($missing) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Missing required fields: " . implode(', ', $missing)]);
            exit;
        }

        // Validate amount is positive
        if ((float) $body['amount'] <= 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Amount must be a positive number"]);
            exit;
        }

        // Validate payment method
        $valid_methods = ['cash', 'card', 'esewa', 'khalti', 'bank_transfer'];
        if (!in_array($body['payment_method'], $valid_methods)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid payment method. Allowed: " . implode(', ', $valid_methods)]);
            exit;
        }

        // Validate booking exists
        $stmt = $pdo->prepare("SELECT id, user_id FROM bookings WHERE id = ?");
        $stmt->execute([$body['booking_id']]);
        $booking = $stmt->fetch();
        if (!$booking) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Booking not found"]);
            exit;
        }

        // Validate user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$body['user_id']]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "User not found"]);
            exit;
        }

        $status = $body['status'] ?? 'pending';
        $paid_at = ($status === 'completed') ? date('Y-m-d H:i:s') : null;

        $stmt = $pdo->prepare("
            INSERT INTO payments (booking_id, user_id, amount, payment_method, status, transaction_id, paid_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            (int) $body['booking_id'],
            (int) $body['user_id'],
            (float) $body['amount'],
            $body['payment_method'],
            $status,
            trim($body['transaction_id'] ?? ''),
            $paid_at,
        ]);

        // If payment is completed, confirm the booking
        if ($status === 'completed') {
            $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ? AND status = 'pending'")
                ->execute([$body['booking_id']]);
        }

        $new_id = $pdo->lastInsertId();
        echo json_encode(["success" => true, "message" => "Payment created successfully", "id" => (int) $new_id]);
        break;

    // ─── UPDATE ──────────────────────────────────────────
    case 'PUT':
        $body = json_decode(file_get_contents("php://input"), true);
        $id = $body['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Payment ID is required"]);
            exit;
        }

        // Check payment exists
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Payment not found"]);
            exit;
        }

        // Validate payment method if provided
        if (isset($body['payment_method'])) {
            $valid_methods = ['cash', 'card', 'esewa', 'khalti', 'bank_transfer'];
            if (!in_array($body['payment_method'], $valid_methods)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Invalid payment method. Allowed: " . implode(', ', $valid_methods)]);
                exit;
            }
        }

        // Validate status if provided
        if (isset($body['status'])) {
            $valid_statuses = ['pending', 'completed', 'failed', 'refunded'];
            if (!in_array($body['status'], $valid_statuses)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Invalid status. Allowed: " . implode(', ', $valid_statuses)]);
                exit;
            }

            // Auto-set paid_at when marking as completed
            if ($body['status'] === 'completed' && $existing['status'] !== 'completed') {
                $body['paid_at'] = date('Y-m-d H:i:s');

                // Confirm the booking
                $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ? AND status = 'pending'")
                    ->execute([$existing['booking_id']]);
            }
        }

        $allowed = ['amount', 'payment_method', 'status', 'transaction_id', 'paid_at'];
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
        $sql = "UPDATE payments SET " . implode(', ', $fields) . " WHERE id = ?";
        $pdo->prepare($sql)->execute($values);

        echo json_encode(["success" => true, "message" => "Payment updated successfully"]);
        break;

    // ─── DELETE ──────────────────────────────────────────
    case 'DELETE':
        $id = $_GET['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Payment ID is required"]);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM payments WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Payment not found"]);
            exit;
        }

        $pdo->prepare("DELETE FROM payments WHERE id = ?")->execute([$id]);
        echo json_encode(["success" => true, "message" => "Payment deleted successfully"]);
        break;

    default:
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Method not allowed"]);
}
