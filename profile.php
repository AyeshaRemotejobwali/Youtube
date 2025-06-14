<?php
session_start();
require_once 'db.php';

// Enable error logging for debugging
ini_set('display_errors', 0); // Disable for production
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("Unauthorized access attempt to profile.php");
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user = null;
$videos = [];
$error_message = '';

try {
    // Fetch user details
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        error_log("User not found for ID: $user_id");
        $error_message = "User not found. Please log in again.";
        session_destroy();
        header("Location: login.php");
        exit;
    }

    // Check if videos table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'videos'");
    if ($table_check->rowCount() == 0) {
        $error_message = "Videos table not found. Please ensure the database is set up correctly.";
    } else {
        // Fetch user videos
        $stmt = $conn->prepare("SELECT * FROM videos WHERE user_id = ? ORDER BY upload_date DESC");
        $stmt->execute([$user_id]);
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($videos)) {
            $error_message = "You haven't uploaded any videos yet.";
        }
        error_log("Fetched " . count($videos) . " videos for user ID: $user_id");
    }
} catch (PDOException $e) {
    error_log("Database error in profile.php: " . $e->getMessage());
    $error_message = "Error loading profile. Please try again later.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - YouTube Clone</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
        body { background: #f8f8f8; }
        .container { padding: 20px; max-width: 1200px; margin: auto; }
        .profile-info { 
            margin-bottom: 20px; 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); 
        }
        .profile-info h2 { font-size: 24px; margin-bottom: 10px; color: #333; }
        .profile-info p { color: #606060; margin-bottom: 10px; }
        .profile-info button { 
            padding: 8px 16px; 
            background: #ff0000; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            margin-right: 10px; 
            transition: background 0.2s; 
        }
        .profile-info button:hover { background: #cc0000; }
        .video-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); 
            gap: 20px; 
        }
        .video-card { 
            background: white; 
            border-radius: 8px; 
            overflow: hidden; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); 
            transition: transform 0.2s; 
        }
        .video-card:hover { transform: scale(1.03); }
        .video-card img { width: 100%; height: 150px; object-fit: cover; }
        .video-card h3 { font-size: 16px; padding: 10px; color: #333; }
        .video-card p { font-size: 14px; color: #606060; padding: 0 10px 10px; }
        .video-card button { 
            margin: 10px; 
            padding: 8px 16px; 
            background: #ff0000; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            transition: background 0.2s; 
        }
        .video-card button:hover { background: #cc0000; }
        .error-message { 
            text-align: center; 
            color: #ff0000; 
            padding: 20px; 
            font-size: 18px; 
        }
        @media (max-width: 600px) { 
            .video-grid { grid-template-columns: 1fr; }
            .profile-info button { width: 100%; margin: 5px 0; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="profile-info">
            <h2><?php echo htmlspecialchars($user['username'] ?? 'User'); ?>'s Profile</h2>
            <p>Email: <?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></p>
            <button onclick="redirect('upload.php')">Upload New Video</button>
            <button onclick="redirect('logout.php')">Logout</button>
        </div>
        <h3>Your Videos</h3>
        <?php if ($error_message): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php else: ?>
            <div class="video-grid">
                <?php foreach ($videos as $video): ?>
                    <div class="video-card">
                        <img src="<?php echo htmlspecialchars($video['thumbnail'] ?? 'https://via.placeholder.com/250x150?text=No+Thumbnail'); ?>" alt="Thumbnail">
                        <h3><?php echo htmlspecialchars($video['title'] ?? 'Untitled'); ?></h3>
                        <p><?php echo $video['views'] ?? 0; ?> views • Uploaded on <?php echo $video['upload_date'] ?? 'N/A'; ?></p>
                        <button onclick="deleteVideo(<?php echo $video['id']; ?>)">Delete</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <script>
        function redirect(url) {
            window.location.href = url;
        }

        function deleteVideo(videoId) {
            if (confirm('Are you sure you want to delete this video?')) {
                fetch('delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'video_id=' + videoId
                }).then(response => {
                    if (response.ok) {
                        location.reload();
                    } else {
                        alert('Error deleting video. Please try again.');
                    }
                }).catch(error => {
                    console.error('Delete error:', error);
                    alert('Error deleting video. Please try again.');
                });
            }
        }
    </script>
</body>
</html>
