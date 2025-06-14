<?php
require_once 'db.php';
$query = isset($_GET['q']) ? $_GET['q'] : '';
$videos = [];

if ($query) {
    $stmt = $conn->prepare("SELECT videos.*, users.username FROM videos JOIN users ON videos.user_id = users.id WHERE videos.title LIKE ? OR videos.description LIKE ? ORDER BY videos.views DESC");
    $searchTerm = "%$query%";
    $stmt->execute([$searchTerm, $searchTerm]);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

header('Content-Type: application/json');
echo json_encode($videos);
?>
