<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

// ===== DB CONFIG =====
$dbHost = 'sql212.byetcluster.com';
$dbName = 'b22_41949493_usedata';
$dbUser = 'b22_41949493';
$dbPass = 'yAi003250';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {

    // ===== GET ALL DATA =====
    case 'getData':
        $stmt = $pdo->query("SELECT * FROM cable_points ORDER BY created_at DESC");
        $rows = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $rows]);
        break;

    // ===== GET SUGGESTIONS (autocomplete) จากตาราง DATA =====
    case 'getSuggestions':
        try {
            $stmt = $pdo->query("SELECT DISTINCT `สายจดหน่วย` FROM DATA WHERE `สายจดหน่วย` IS NOT NULL AND `สายจดหน่วย` != ''");
            $suggestions = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['success' => true, 'suggestions' => $suggestions]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage(), 'suggestions' => []]);
        }
        break;

    // ===== GET LINE DATA จากตาราง DATA (สำหรับ auto-fill) =====
    case 'getLineData':
        $line = $_GET['line'] ?? '';
        if (!$line) {
            echo json_encode(['success' => false, 'found' => false, 'error' => 'Missing line']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM DATA WHERE `สายจดหน่วย` = ? LIMIT 1");
            $stmt->execute([$line]);
            $row = $stmt->fetch();
            if ($row) {
                echo json_encode(['success' => true, 'found' => true, 'data' => $row]);
            } else {
                echo json_encode(['success' => true, 'found' => false]);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'found' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ===== GET DATA TABLE (ตาราง DATA) =====
    case 'getDataTable':
        // สร้างตาราง DATA ถ้ายังไม่มี
        $pdo->exec("CREATE TABLE IF NOT EXISTS DATA (
            id INT AUTO_INCREMENT PRIMARY KEY,
            `หมวด` VARCHAR(100) DEFAULT NULL,
            `สายจดหน่วย` VARCHAR(200) DEFAULT NULL,
            `ตำบล` VARCHAR(100) DEFAULT NULL,
            `พื้นที่ทำงาน` VARCHAR(100) DEFAULT NULL,
            `หมู่บ้าน` VARCHAR(200) DEFAULT NULL,
            `หมายเหตุ` TEXT DEFAULT NULL,
            `สถาณะ` VARCHAR(50) DEFAULT NULL,
            `คำแนะนำการวิ่ง` VARCHAR(200) DEFAULT NULL,
            `พิกัดต้นสาย` VARCHAR(100) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $stmt = $pdo->query("SELECT `หมวด`, `สายจดหน่วย`, `ตำบล`, `พื้นที่ทำงาน`, `หมู่บ้าน`, `หมายเหตุ`, `สถาณะ`, `คำแนะนำการวิ่ง`, `พิกัดต้นสาย` FROM DATA ORDER BY `สายจดหน่วย` ASC");
        $rows = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $rows]);
        break;

    // ===== APPEND (INSERT) =====
    case 'append':
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if ($data) {
            $data = $data['data'] ?? $data;
        }
        if (!$data) {
            echo json_encode(['success' => false, 'error' => 'No data']);
            exit;
        }

        // สร้างตารางถ้ายังไม่มี (รองรับ column ใหม่)
        $pdo->exec("CREATE TABLE IF NOT EXISTS cable_points (
            id VARCHAR(50) PRIMARY KEY,
            date DATE DEFAULT NULL,
            category VARCHAR(100) DEFAULT NULL,
            line_unit VARCHAR(200) DEFAULT NULL,
            tambon VARCHAR(100) DEFAULT NULL,
            work_area VARCHAR(100) DEFAULT NULL,
            village VARCHAR(200) DEFAULT NULL,
            note TEXT DEFAULT NULL,
            status VARCHAR(50) DEFAULT 'รอดำเนินการ',
            run_advice VARCHAR(200) DEFAULT NULL,
            coords VARCHAR(100) DEFAULT NULL,
            lat DECIMAL(10,7) DEFAULT NULL,
            lng DECIMAL(10,7) DEFAULT NULL,
            images JSON DEFAULT NULL,
            added_by VARCHAR(100) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $pdo->prepare("INSERT INTO cable_points (id, date, category, line_unit, tambon, work_area, village, note, status, run_advice, coords, lat, lng, images, added_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $coords = isset($data['พิกัดต้นสาย']) ? explode(',', $data['พิกัดต้นสาย']) : [null, null];
        $lat = isset($coords[0]) ? trim($coords[0]) : null;
        $lng = isset($coords[1]) ? trim($coords[1]) : null;
        $images = isset($data['images']) ? json_encode($data['images']) : '[]';

        $stmt->execute([
            $data['id'] ?? uniqid(),
            $data['วันที่'] ?? null,
            $data['หมวด'] ?? null,
            $data['สายจดหน่วย'] ?? null,
            $data['ตำบล'] ?? null,
            $data['พื้นที่ทำงาน'] ?? null,
            $data['หมู่บ้าน'] ?? null,
            $data['หมายเหตุ'] ?? null,
            $data['STATUS'] ?? 'รอดำเนินการ',
            $data['คำแนะนำการวิ่ง'] ?? null,
            $data['พิกัดต้นสาย'] ?? null, $lat, $lng, $images,
            $data['added_by'] ?? null
        ]);
        echo json_encode(['success' => true]);
        break;

    // ===== UPDATE STATUS =====
    case 'updateStatus':
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!$data || !isset($data['id']) || !isset($data['status'])) {
            echo json_encode(['success' => false, 'error' => 'Missing id or status']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE cable_points SET status = ? WHERE id = ?");
        $stmt->execute([$data['status'], $data['id']]);
        echo json_encode(['success' => true]);
        break;

    // ===== DELETE =====
    case 'delete':
        $id = $_GET['id'] ?? '';
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'Missing id']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM cable_points WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
}
