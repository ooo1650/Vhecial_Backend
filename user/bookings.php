<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: fetch bookings for a user ────────────────────────────────────────
if ($method === 'GET') {
    $email = trim($_GET['email'] ?? '');
    if (!$email) { http_response_code(400); die(json_encode(["success"=>false,"message"=>"Email required"])); }

    $stmt = $pdo->prepare("
        SELECT b.*, v.name AS vehicle_name, v.type AS vehicle_type,
               v.fuel_type, v.seats, v.price_per_day,
               (SELECT image_path FROM vehicle_images
                WHERE vehicle_id = v.id AND is_primary = 1 LIMIT 1) AS vehicle_image
        FROM bookings b
        JOIN vehicles v ON b.vehicle_id = v.id
        JOIN users u ON b.user_id = u.id
        WHERE u.email = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$email]);
    $bookings = $stmt->fetchAll();

    foreach ($bookings as &$b) {
        if ($b['vehicle_image'] && !str_starts_with($b['vehicle_image'], 'http'))
            $b['vehicle_image'] = '/api/' . $b['vehicle_image'];
    }

    echo json_encode(["success" => true, "bookings" => $bookings]);
    exit;
}

// ── POST: create a new booking ────────────────────────────────────────────
if ($method === 'POST') {
    $body = json_decode(file_get_contents("php://input"), true);

    $email            = trim($body['email']             ?? '');
    $vehicle_id       = (int)($body['vehicle_id']       ?? 0);
    $start_date       = trim($body['start_date']        ?? '');
    $end_date         = trim($body['end_date']          ?? '');
    $pickup_location  = trim($body['pickup_location']   ?? '');
    $dropoff_location = trim($body['dropoff_location']  ?? '');
    $contact_phone    = trim($body['contact_phone']     ?? '');
    $payment_method   = trim($body['payment_method']    ?? 'esewa');
    $total_price      = (float)($body['total_price']    ?? 0);

    $errors = [];
    if (!$email)           $errors[] = "Email is required";
    if (!$vehicle_id)      $errors[] = "Vehicle is required";
    if (!$start_date)      $errors[] = "Start date is required";
    if (!$end_date)        $errors[] = "End date is required";
    if (!$pickup_location) $errors[] = "Pick-up location is required";
    if (!$contact_phone)   $errors[] = "Contact phone is required";
    if ($total_price <= 0) $errors[] = "Invalid total price";

    if ($errors) {
        http_response_code(400);
        die(json_encode(["success" => false, "message" => implode('. ', $errors)]));
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) { http_response_code(404); die(json_encode(["success"=>false,"message"=>"User not found"])); }

    $stmt = $pdo->prepare("SELECT id FROM vehicles WHERE id = ? AND available = 1");
    $stmt->execute([$vehicle_id]);
    if (!$stmt->fetch()) {
        http_response_code(400);
        die(json_encode(["success"=>false,"message"=>"Vehicle is not available"]));
    }

    $pdo->prepare("
        INSERT INTO bookings
            (user_id, vehicle_id, start_date, end_date, total_price,
             pickup_location, dropoff_location, contact_phone, payment_method, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ")->execute([
        $user['id'], $vehicle_id, $start_date, $end_date, $total_price,
        $pickup_location, $dropoff_location, $contact_phone, $payment_method
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Booking request submitted",
        "id"      => (int)$pdo->lastInsertId()
    ]);
    exit;
}

// ── PUT: cancel or edit a booking ─────────────────────────────────────────
if ($method === 'PUT') {
    $body   = json_decode(file_get_contents("php://input"), true);
    $id     = (int)($body['id']     ?? 0);
    $email  = trim($body['email']   ?? '');
    $action = trim($body['action']  ?? 'cancel'); // 'cancel' | 'edit'

    if (!$id || !$email) {
        http_response_code(400);
        die(json_encode(["success"=>false,"message"=>"ID and email required"]));
    }

    // Verify ownership
    $stmt = $pdo->prepare("
        SELECT b.id, b.status FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE b.id = ? AND u.email = ?
    ");
    $stmt->execute([$id, $email]);
    $booking = $stmt->fetch();

    if (!$booking) {
        http_response_code(404);
        die(json_encode(["success"=>false,"message"=>"Booking not found"]));
    }

    // ── Cancel ──
    if ($action === 'cancel') {
        if (in_array($booking['status'], ['completed','cancelled'])) {
            http_response_code(400);
            die(json_encode(["success"=>false,"message"=>"Cannot cancel a {$booking['status']} booking"]));
        }
        $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$id]);
        echo json_encode(["success" => true, "message" => "Booking cancelled. Your advance will be fully refunded."]);
        exit;
    }

    // ── Edit ──
    if ($action === 'edit') {
        // Only editable when pending or pending_review
        if (!in_array($booking['status'], ['pending','pending_review'])) {
            http_response_code(400);
            die(json_encode(["success"=>false,"message"=>"Only pending bookings can be edited"]));
        }

        $pickup   = trim($body['pickup_location']  ?? '');
        $dropoff  = trim($body['dropoff_location'] ?? '');
        $phone    = trim($body['contact_phone']    ?? '');
        $start    = trim($body['start_date']       ?? '');
        $end      = trim($body['end_date']         ?? '');
        $total    = (float)($body['total_price']   ?? 0);

        if (!$pickup || !$phone || !$start || !$end) {
            http_response_code(400);
            die(json_encode(["success"=>false,"message"=>"Pick-up location, phone, and dates are required"]));
        }

        $pdo->prepare("
            UPDATE bookings
            SET pickup_location  = ?,
                dropoff_location = ?,
                contact_phone    = ?,
                start_date       = ?,
                end_date         = ?,
                total_price      = ?,
                status           = 'pending',
                admin_note       = NULL
            WHERE id = ?
        ")->execute([$pickup, $dropoff ?: null, $phone, $start, $end, $total > 0 ? $total : null, $id]);

        echo json_encode(["success" => true, "message" => "Booking updated and resubmitted for review."]);
        exit;
    }

    http_response_code(400);
    echo json_encode(["success"=>false,"message"=>"Unknown action"]);
    exit;
}

http_response_code(405);
echo json_encode(["success" => false, "message" => "Method not allowed"]);
