<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id']) || !isset($_POST['video_id'])) {
    exit;
}

$user_id = $_SESSION['user_id'];
$video_id = $_POST['video_id'];

$stmt = $conn->prepare("SELECT * FROM videos WHERE id = ? AND user_id = ?");
$stmt->execute([$video_id, $user_id]);
$video = $stmt->fetch();

if ($video) {
    unlink($video['video_url']);
    unlink($video['thumbnail']);
    $conn->query("DELETE FROM videos WHERE id = $video_id");
    $conn->query("DELETE FROM comments WHERE video_id = $video_id");
    $conn->query("DELETE FROM likes WHERE video_id = $video_id");
}
?>
