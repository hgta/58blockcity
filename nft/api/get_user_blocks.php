<?php
require_once '../../config/database.php';
require_once '../../classes/Block.php';
require_once '../includes/auth.php';

checkLogin();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['city_id'])) {
    $userId = $_SESSION['user_id'];
    $cityId = $_GET['city_id'];
    
    $block = new Block($pdo);
    $userBlocks = $block->getUserBlocksByCity($userId, $cityId);
    
    header('Content-Type: application/json');
    echo json_encode($userBlocks);
}

?>