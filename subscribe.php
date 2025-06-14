<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id']) || !isset($_POST['channel_id'])) {
    exit;
}

$subscriber_id = $_SESSION['user_id'];
$channel_id = $_POST['channel_id'];

$stmt = $conn->prepare("SELECT * FROM subscriptions WHERE subscriber_id = ? AND channel_id = ?");
$stmt->execute([$subscriber_id, $channel_id]);
if ($stmt->rowCount() > 0) {
    $stmt = $conn->prepare("DELETE FROM subscriptions WHERE subscriber_id = ? AND channel_id = ?");
    $stmt->execute([$subscriber_id, $channel_id]);
} else {
    $stmt = $conn->prepare("INSERT INTO subscriptions (subscriber_id, channel_id) VALUES (?, ?)");
    $stmt->execute([$subscriber_id, $channel_id]);
}
?>
