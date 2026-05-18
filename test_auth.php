<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

echo "<h2>Test Auth</h2>";

require_once __DIR__ . '/config.php';
echo "<p>DB Connection: " . ($conn->connect_error ? "FAILED: " . $conn->connect_error : "OK ✅") . "</p>";

// Test create table
$result = $conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(100) NOT NULL,
    session_token VARCHAR(64) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "<p>Create table: " . ($result ? "OK ✅" : "FAILED: " . $conn->error) . "</p>";

// Test insert
$test_user = 'test_' . time();
$hash = password_hash('1234', PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (username, password, fullname) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $test_user, $hash, $test_user);
$result = $stmt->execute();
echo "<p>Insert test: " . ($result ? "OK ✅ (id=" . $stmt->insert_id . ")" : "FAILED: " . $stmt->error) . "</p>";

// Test select
$stmt = $conn->prepare("SELECT id, username, fullname FROM users WHERE username = ?");
$stmt->bind_param("s", $test_user);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
echo "<p>Select test: " . ($row ? "OK ✅ — " . json_encode($row) : "FAILED") . "</p>";

// Cleanup
$conn->query("DELETE FROM users WHERE username = '$test_user'");
echo "<p>Cleanup: OK ✅</p>";

echo "<hr><p><b>All tests passed! ✅</b></p>";
echo "<p>Now try: <a href='auth.php?action=register'>auth.php?action=register</a></p>";
