<?php
require_once __DIR__ . '/admin_auth.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── helpers ──────────────────────────────────────────────────────────────
function saveImages(PDO $pdo, int $vehicle_id, array $files, bool $replaceAll = false): void {
    if ($replaceAll) {
        // Delete old image files
        $old = $pdo->prepare("SELECT image_path FROM vehicle_images WHERE vehicle_id = ?");
        $old->execute([$vehicle_id]);
        foreach ($old->fetchAll() as $row) {
            $p = __DIR__ . '/../../backend/' . $row['image_path'];
            if (file_exists($p)) unlink($p);
        }
        $pdo->prepare("DELETE FROM vehicle_images WHERE vehicle_id = ?")->execute([$vehicle_id]);
    }

    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    $dir     = __DIR__ . '/../../backend/uploads/vehicles/';
    $order   = 0;

    foreach ($files['tmp_name'] as $i => $tmp) {
        if (empty($tmp) || $files['error'][$i] !== UPLOAD_ERR_OK) continue;
        if (!in_array($files['type'][$i], $allowed)) continue;
        if ($files['size'][$i] > 8 * 1024 * 1024) continue;

        $ext      = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
        $filename = uniqid('veh_', true) . '.' . $ext;
        if (!move_uploaded_file($tmp, $dir . $filename)) continue;

        $isPrimary = ($order === 0 && $replaceAll) ? 1 : 0;
        $pdo->prepare("INSERT INTO vehicle_images (vehicle_id, image_path, is_primary, sort_order) VALUES (?,?,?,?)")
            ->execute([$vehicle_id, 'uploads/vehicles/' . $filename, $isPrimary, $order]);
        $order++;
    }

    // If no primary set yet, make first one primary
    $hasPrimary = $pdo->prepare("SELECT COUNT(*) FROM vehicle_images WHERE vehicle_id=? AND is_primary=1");
    $hasPrimary->execute([$vehicle_id]);
    if ((int)$hasPrimary->fetchColumn() === 0) {
        $first = $pdo->prepare("SELECT id FROM vehicle_images WHERE vehicle_id=? ORDER BY sort_order ASC LIMIT 1");
        $first->execute([$vehicle_id]);
        $fid = $first->fetchColumn();
        if ($fid) $pdo->prepare("UPDATE vehicle_images SET is_primary=1 WHERE id=?")->execute([$fid]);
    }
}

switch ($method) {

    case 'GET':
        if (!empty($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id=?");
            $stmt->execute([$_GET['id']]);
            $v = $stmt->fetch();
            if (!$v) { http_response_code(404); echo json_encode(["success"=>false,"message"=>"Not found"]); exit; }
            $imgs = $pdo->prepare("SELECT * FROM vehicle_images WHERE vehicle_id=? ORDER BY is_primary DESC, sort_order ASC");
            $imgs->execute([$v['id']]);
            $v['images'] = $imgs->fetchAll();
            foreach ($v['images'] as &$img) {
                if (!str_starts_with($img['image_path'],'http')) $img['image_path'] = '/api/'.$img['image_path'];
            }
            $v['features'] = $v['features'] ? json_decode($v['features'],true) : [];
            echo json_encode(["success"=>true,"data"=>$v]);
        } else {
            $stmt = $pdo->query("SELECT v.*, (SELECT image_path FROM vehicle_images WHERE vehicle_id=v.id AND is_primary=1 LIMIT 1) AS primary_image FROM vehicles v ORDER BY v.created_at DESC");
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) {
                if ($r['primary_image'] && !str_starts_with($r['primary_image'],'http')) $r['primary_image']='/api/'.$r['primary_image'];
            }
            echo json_encode(["success"=>true,"data"=>$rows]);
        }
        break;

    case 'POST':
        $required = ['name','type','fuel_type','seats','price_per_day'];
        foreach ($required as $f) {
            if (empty($_POST[$f])) { http_response_code(400); echo json_encode(["success"=>false,"message"=>"Missing: $f"]); exit; }
        }
        $features = json_encode(array_filter(array_map('trim', explode(',', $_POST['features'] ?? ''))));
        $pdo->prepare("
            INSERT INTO vehicles (name,type,fuel_type,seats,transmission,year,doors,mileage,luggage_capacity,pickup_location,description,features,price_per_day,available)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1)
        ")->execute([
            $_POST['name'], $_POST['type'], $_POST['fuel_type'], (int)$_POST['seats'],
            $_POST['transmission'] ?? 'Manual', $_POST['year'] ?: null, $_POST['doors'] ?: null,
            $_POST['mileage'] ?? null, $_POST['luggage_capacity'] ?? null,
            $_POST['pickup_location'] ?? null, $_POST['description'] ?? null,
            $features, (float)$_POST['price_per_day'],
        ]);
        $newId = (int)$pdo->lastInsertId();
        if (!empty($_FILES['images']['tmp_name'][0])) saveImages($pdo, $newId, $_FILES['images'], true);
        echo json_encode(["success"=>true,"message"=>"Vehicle added","id"=>$newId]);
        break;

    case 'PUT':
        // PUT with JSON (toggle available / simple field update)
        $body = json_decode(file_get_contents("php://input"), true);
        $id   = $body['id'] ?? null;
        if (!$id) { http_response_code(400); echo json_encode(["success"=>false,"message"=>"ID required"]); exit; }
        $allowed = ['name','type','fuel_type','seats','transmission','year','doors','mileage','luggage_capacity','pickup_location','description','features','price_per_day','available'];
        $fields=[]; $values=[];
        foreach ($allowed as $f) {
            if (array_key_exists($f,$body)) { $fields[]="$f=?"; $values[]=$body[$f]; }
        }
        if (empty($fields)) { http_response_code(400); echo json_encode(["success"=>false,"message"=>"Nothing to update"]); exit; }
        $values[]=$id;
        $pdo->prepare("UPDATE vehicles SET ".implode(',',$fields)." WHERE id=?")->execute($values);
        echo json_encode(["success"=>true,"message"=>"Updated"]);
        break;

    case 'DELETE':
        $id = $_GET['id'] ?? null;
        if (!$id) { http_response_code(400); echo json_encode(["success"=>false,"message"=>"ID required"]); exit; }
        // Delete image files
        $imgs = $pdo->prepare("SELECT image_path FROM vehicle_images WHERE vehicle_id=?");
        $imgs->execute([$id]);
        foreach ($imgs->fetchAll() as $row) {
            $p = __DIR__.'/../../backend/'.$row['image_path'];
            if (file_exists($p)) unlink($p);
        }
        $pdo->prepare("DELETE FROM vehicles WHERE id=?")->execute([$id]);
        echo json_encode(["success"=>true,"message"=>"Deleted"]);
        break;

    default:
        http_response_code(405);
        echo json_encode(["success"=>false,"message"=>"Method not allowed"]);
}
