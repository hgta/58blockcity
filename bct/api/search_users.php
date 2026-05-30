<?php
require_once '../config/database.php';
require_once '../classes/User.php';

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$user = new User($pdo);
$results = $user->searchUsers($query);

echo json_encode($results);
?>