<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';

$voteId = intval($_GET['vote_id'] ?? 0);
$vote = $_GET['vote'] ?? '';

if ($voteId > 0 && in_array($vote, ['yes', 'no']) && isLoggedIn()) {
    $field = $vote == 'yes' ? 'yes_votes' : 'no_votes';
    $stmt = $pdo->prepare("UPDATE expansion_votes SET {$field} = {$field} + 1 WHERE id = ?");
    $stmt->execute([$voteId]);
}

$back = $_SERVER['HTTP_REFERER'] ?? '../city/map.php';
header("Location: {$back}");
exit();
