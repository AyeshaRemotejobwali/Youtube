<?php
session_start();
require_once 'db.php';

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Validate video ID
$video_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$video_id) {
    error_log("Invalid or missing video ID");
    header("Location: index.php");
    exit;
}

$error_message = '';
$video = null;
$comments = [];
$is_liked = false;
$is_subscribed = false;

try {
    // Check if tables exist
    $table_check = $conn->query("SHOW TABLES LIKE 'videos'");
    if ($table_check->rowCount() == 0) {
        throw new Exception("Videos table not found. Please set up the database.");
    }

    // Fetch video details
    $stmt = $conn->prepare("
        SELECT videos.*, users.username 
        FROM videos 
        LEFT JOIN users ON videos.user_id = users.id 
        WHERE videos.id = ?
    ");
    $stmt->execute([$video_id]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video) {
        error_log("Video not found for ID: $video_id");
        header("Location: index.php");
        exit;
    }

    // Update views
    $stmt = $conn->prepare("UPDATE videos SET views = views + 1 WHERE id = ?");
    $stmt->execute([$video_id]);

    // Fetch comments
    $table_check = $conn->query("SHOW TABLES LIKE 'comments'");
    if ($table_check->rowCount() > 0) {
        $stmt = $conn->prepare("
            SELECT comments.*, users.username 
            FROM comments 
            LEFT JOIN users ON comments.user_id = users.id 
            WHERE comments.video_id = ? 
            ORDER BY comments.created_at DESC
        ");
        $stmt->execute([$video_id]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Check like and subscription status
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $table_check = $conn->query("SHOW TABLES LIKE 'likes'");
        if ($table_check->rowCount() > 0) {
            $stmt = $conn->prepare("SELECT * FROM likes WHERE user_id = ? AND video_id = ?");
            $stmt->execute([$user_id, $video_id]);
            $is_liked = $stmt->rowCount() > 0;
        }

        $table_check = $conn->query("SHOW TABLES LIKE 'subscriptions'");
        if ($table_check->rowCount() > 0) {
            $stmt = $conn->prepare("SELECT * FROM subscriptions WHERE subscriber_id = ? AND channel_id = ?");
            $stmt->execute([$user_id, $video['user_id']]);
            $is_subscribed = $stmt->rowCount() > 0;
        }
    }

    // Handle comment submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment']) && isset($_SESSION['user_id'])) {
        $comment = filter_input(INPUT_POST, 'comment', FILTER_SANITIZE_STRING);
        if (empty($comment)) {
            throw new Exception("Comment cannot be empty.");
        }
        $stmt = $conn->prepare("
            INSERT INTO comments (user_id, video_id, comment, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $video_id, $comment]);
        error_log("Comment added by user ID: " . $_SESSION['user_id'] . " for video ID: $video_id");
        header("Location: watch.php?id=$video_id");
        exit;
    }

} catch (Exception $e) {
    error_log("Error in watch.php: " . $e->getMessage());
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($video['title'] ?? 'Video'); ?> - YouTube Clone</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
        body { background: #f8f8f8; }
        .container { padding: 20px; max-width: 1200px; margin: auto; }
        .video-player { width: 100%; max-width: 800px; margin-bottom: 20px; }
        .video-player video { width: 100%; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .video-info h1 { font-size: 24px; margin-bottom: 10px; color: #333; }
        .video-info p { color: #606060; margin-bottom: 10px; }
        .action-buttons { margin: 10px 0; }
        .action-buttons button { 
            padding: 8px 16px; 
            margin-right: 10px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 14px; 
            transition: background 0.2s; 
        }
        .like-btn { background: <?php echo $is_liked ? '#ff0000' : '#ccc'; ?>; color: white; }
        .subscribe-btn { background: <?php echo $is_subscribed ? '#ccc' : '#ff0000'; ?>; color: white; }
        .action-buttons button:hover { opacity: 0.9; }
        .comments-section { margin-top: 20px; }
        .comment-form textarea { 
            width: 100%; 
            padding: 10px; 
            margin-bottom: 10px; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
            resize: vertical; 
            font-size: 14px; 
        }
        .comment-form button { 
            padding: 8px 16px; 
            background: #ff0000; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            transition: background 0.2s; 
        }
        .comment-form button:hover { background: #cc0000; }
        .comment { border-top: 1px solid #ccc; padding: 10px 0; }
        .comment p { font-size: 14px; color: #333; }
        .comment p strong { color: #000; }
        .related-videos { margin-top: 20px; }
        .related-videos .video-card { 
            display: flex; 
            gap: 10px; 
            margin-bottom: 10px; 
            cursor: pointer; 
            transition: transform 0.2s; 
        }
        .related-videos .video-card:hover { transform: scale(1.02); }
        .related-videos img { width: 120px; height: 80px; object-fit: cover; border-radius: 4px; }
        .error-message { 
            text-align: center; 
            color: #ff0000; 
            padding: 20px; 
            font-size: 18px; 
            background: #ffe6e6; 
            border-radius: 4px; 
        }
        @media (max-width: 600px) { 
            .video-player { max-width: 100%; }
            .action-buttons button { width: 100%; margin: 5px 0; }
            .related-videos img { width: 100px; height: 70px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($error_message): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php elseif ($video): ?>
            <div class="video-player">
                <video controls>
                    <source src="<?php echo htmlspecialchars($video['video_url'] ?? ''); ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </div>
            <div class="video-info">
                <h1><?php echo htmlspecialchars($video['title'] ?? 'Untitled'); ?></h1>
                <p>by <?php echo htmlspecialchars($video['username'] ?? 'Unknown'); ?> • <?php echo $video['views'] ?? 0; ?> views • Uploaded on <?php echo $video['upload_date'] ?? 'N/A'; ?></p>
                <p><?php echo htmlspecialchars($video['description'] ?? 'No description'); ?></p>
            </div>
            <div class="action-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <button class="like-btn" onclick="toggleLike(<?php echo $video_id; ?>)"><?php echo $is_liked ? 'Unlike' : 'Like'; ?></button>
                    <button class="subscribe-btn" onclick="toggleSubscribe(<?php echo $video['user_id']; ?>)"><?php echo $is_subscribed ? 'Unsubscribe' : 'Subscribe'; ?></button>
                <?php endif; ?>
            </div>
            <div class="comments-section">
                <h3>Comments</h3>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <form class="comment-form" method="POST">
                        <textarea name="comment" placeholder="Add a comment..." required></textarea>
                        <button type="submit">Comment</button>
                    </form>
                <?php endif; ?>
                <?php foreach ($comments as $comment): ?>
                    <div class="comment">
                        <p><strong><?php echo htmlspecialchars($comment['username'] ?? 'Unknown'); ?></strong> • <?php echo $comment['created_at'] ?? 'N/A'; ?></p>
                        <p><?php echo htmlspecialchars($comment['comment'] ?? ''); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="related-videos">
                <h3>Related Videos</h3>
                <?php
                try {
                    $stmt = $conn->prepare("
                        SELECT videos.*, users.username 
                        FROM videos 
                        LEFT JOIN users ON videos.user_id = users.id 
                        WHERE videos.id != ? 
                        ORDER BY RAND() LIMIT 3
                    ");
                    $stmt->execute([$video_id]);
                    $related = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($related as $rel): ?>
                        <div class="video-card" onclick="redirect('watch.php?id=<?php echo $rel['id']; ?>')">
                            <img src="<?php echo htmlspecialchars($rel['thumbnail'] ?? 'https://via.placeholder.com/120x80?text=No+Thumbnail'); ?>" alt="Thumbnail">
                            <div>
                                <h4><?php echo htmlspecialchars($rel['title'] ?? 'Untitled'); ?></h4>
                                <p><?php echo htmlspecialchars($rel['username'] ?? 'Unknown'); ?> • <?php echo $rel['views'] ?? 0; ?> views</p>
                            </div>
                        </div>
                    <?php endforeach;
                } catch (Exception $e) {
                    error_log("Related videos error: " . $e->getMessage());
                    echo '<p class="error-message">Error loading related videos.</p>';
                }
                ?>
            </div>
        <?php endif; ?>
    </div>
    <script>
        function redirect(url) {
            window.location.href = url;
        }

        function toggleLike(videoId) {
            fetch('like.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'video_id=' + videoId
            }).then(response => {
                if (response.ok) {
                    location.reload();
                } else {
                    alert('Error toggling like. Please try again.');
                }
            }).catch(error => {
                console.error('Like error:', error);
                alert('Error toggling like. Please try again.');
            });
        }

        function toggleSubscribe(channelId) {
            fetch('subscribe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'channel_id=' + channelId
            }).then(response => {
                if (response.ok) {
                    location.reload();
                } else {
                    alert('Error toggling subscription. Please try again.');
                }
            }).catch(error => {
                console.error('Subscribe error:', error);
                alert('Error toggling subscription. Please try again.');
            });
        }
    </script>
</body>
</html>
