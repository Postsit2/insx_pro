<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

try {
    require_once __DIR__ . '/config.php';
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Config error: ' . $e->getMessage()]);
    exit;
}

if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed: ' . ($conn->connect_error ?? 'unknown')]);
    exit;
}

// สร้างตาราง users ถ้ายังไม่มี
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(100) NOT NULL,
    session_token VARCHAR(64) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$action = $_GET['action'] ?? '';

try {
switch ($action) {

    // ===== REGISTER =====
    case 'register':
        $input = json_decode(file_get_contents('php://input'), true);
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');
        $fullname = trim($input['fullname'] ?? '');

        if (!$username || !$password || !$fullname) {
            echo json_encode(['success' => false, 'error' => 'กรุณากรอกข้อมูลให้ครบ']);
            exit;
        }
        if (strlen($password) < 4) {
            echo json_encode(['success' => false, 'error' => 'รหัสผ่านต้องมีอย่างน้อย 4 ตัวอักษร']);
            exit;
        }

        // เช็ค username ซ้ำ
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'ชื่อผู้ใช้นี้ถูกใช้แล้ว']);
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, fullname, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("sss", $username, $hash, $fullname);
        $stmt->execute();

        $user_id = $stmt->insert_id;
        echo json_encode(['success' => true, 'user' => ['id' => $user_id, 'username' => $username, 'fullname' => $fullname]]);
        break;

    // ===== LOGIN =====
    case 'login':
        $input = json_decode(file_get_contents('php://input'), true);
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');

        if (!$username || !$password) {
            echo json_encode(['success' => false, 'error' => 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id, username, password, fullname FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง']);
            exit;
        }

        $user = $result->fetch_assoc();
        if (!password_verify($password, $user['password'])) {
            echo json_encode(['success' => false, 'error' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง']);
            exit;
        }

        // สร้าง session token
        $token = bin2hex(random_bytes(32));
        $stmt = $conn->prepare("UPDATE users SET session_token = ?, last_login = NOW() WHERE id = ?");
        $stmt->bind_param("si", $token, $user['id']);
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'user' => ['id' => $user['id'], 'username' => $user['username'], 'fullname' => $user['fullname']],
            'token' => $token
        ]);
        break;

    // ===== LOGOUT =====
    case 'logout':
        $input = json_decode(file_get_contents('php://input'), true);
        $user_id = intval($input['user_id'] ?? 0);
        if ($user_id) {
            $stmt = $conn->prepare("UPDATE users SET session_token = NULL WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
        }
        echo json_encode(['success' => true]);
        break;

    // ===== CHECK SESSION =====
    case 'checkSession':
        $token = $_GET['token'] ?? '';
        if (!$token) {
            echo json_encode(['success' => false]);
            exit;
        }
        $stmt = $conn->prepare("SELECT id, username, fullname FROM users WHERE session_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            echo json_encode(['success' => true, 'user' => $result->fetch_assoc()]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;

    // ===== GET ALL USERS =====
    case 'getUsers':
        $result = $conn->query("SELECT id, username, fullname, created_at, last_login FROM users ORDER BY created_at DESC");
        echo json_encode(['success' => true, 'users' => $result->fetch_all(MYSQLI_ASSOC)]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
}
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
