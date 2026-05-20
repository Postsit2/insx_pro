<?php
// byethost22 MySQL config
$dbHost = 'sql212.byetcluster.com';
$dbName = 'b22_41949493_usedata';
$dbUser = 'b22_41949493';
$dbPass = 'yAi003250';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'DB connection failed: ' . $conn->connect_error]));
}
$conn->set_charset("utf8mb4");
