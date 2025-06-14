<?php
session_start();
require_once 'db.php';

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("Unauthorized access attempt to upload.php");
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate form inputs
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
        $video = $_FILES['video'] ?? null;
        $thumbnail = $_FILES['thumbnail'] ?? null;

        if (empty($title) || empty($description) || empty($video) || empty($thumbnail)) {
            throw new Exception("All fields are required.");
        }

        // Validate file types
        $allowed_video_types = ['video/mp4', 'video/webm', 'video/ogg'];
        $allowed_image_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($video['type'], $allowed_video_types)) {
            throw new Exception("Invalid video format. Only MP4, WebM, or OGG allowed.");
        }
        if (!in_array($thumbnail['type'], $allowed_image_types)) {
            throw new Exception("Invalid thumbnail format. Only JPEG, PNG, or GIF allowed.");
        }

        // Validate file sizes (e.g., 100MB for video, 5MB for thumbnail)
        $max_video_size = 100 * 1024 * 1024; // 100MB
        $max_thumbnail_size = 5 * 1024 * 1024; // 5MB
        if ($video['size'] > $max_video_size) {
            throw new Exception("Video file is too large. Maximum size is 100MB.");
        }
        if ($thumbnail['size'] > $max_thumbnail_size) {
            throw new Exception("Thumbnail file is too large. Maximum size is 5MB.");
        }

        // Check for upload errors
        if ($video['error'] !== UPLOAD_ERR_OK || $thumbnail['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error: " . $video['error'] . " (video), " . $thumbnail['error'] . " (thumbnail)");
        }

        // Create directories if they don't exist
        $video_dir = 'Uploads/Videos';
        $thumbnail_dir = 'Uploads/Thumbnails';
        if (!is_dir($video_dir)) {
            mkdir($video_dir, 0755, true) or throw new Exception("Failed to create Videos directory.");
        }
        if (!is_dir($thumbnail_dir)) {
            mkdir($thumbnail_dir, 0755, true) or throw new Exception("Failed to create Thumbnails directory.");
        }

        // Generate unique file paths
        $video_ext = pathinfo($video['name'], PATHINFO_EXTENSION);
        $thumbnail_ext = pathinfo($thumbnail['name'], PATHINFO_EXTENSION);
        $videoPath = $video_dir . '/' . uniqid('vid_') . '.' . $video_ext;
        $thumbnailPath = $thumbnail_dir . '/' . uniqid('thumb_') . '.' . $thumbnail_ext;

        // Move uploaded files
        if (!move_uploaded_file($video['tmp_name'], $videoPath)) {
            throw new Exception("Failed to upload video file.");
        }
        if (!move_uploaded_file($thumbnail['tmp_name'], $thumbnailPath)) {
            throw new Exception("Failed to upload thumbnail file.");
        }

        // Check if videos table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'videos'");
        if ($table_check->rowCount() == 0) {
            throw new Exception("Videos table not found. Please set up the database.");
        }

        // Insert into database
        $stmt = $conn->prepare("
            INSERT INTO videos (user_id, title, description, video_url, thumbnail, upload_date) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $title, $description, $videoPath, $thumbnailPath]);
        error_log("Video uploaded successfully by user ID: " . $_SESSION['user_id']);
        $success = "Video uploaded successfully!";
        header("Location: profile.php");
        exit;

    } catch (Exception $e) {
        error_log("Upload error: " . $e->getMessage());
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Video - YouTube Clone</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
        body { background: #f8f8f8; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .form-container { 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
            width: 100%; 
            max-width: 500px; 
        }
        .form-container h2 { margin-bottom: 20px; text-align: center; color: #333; }
        .form-container input, 
        .form-container textarea { 
            width: 100%; 
            padding: 10px; 
            margin: 10px 0; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
            font-size: 16px; 
        }
        .form-container textarea { resize: vertical; min-height: 100px; }
        .form-container button { 
            width: 100%; 
            padding: 10px; 
            background: #ff0000; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 16px; 
            transition: background 0.2s; 
        }
        .form-container button:hover { background: #cc0000; }
        .error, .success { 
            text-align: center; 
            padding: 10px; 
            margin-bottom: 10px; 
            border-radius: 4px; 
        }
        .error { color: #ff0000; background: #ffe6e6; }
        .success { color: #008000; background: #e6ffe6; }
        @media (max-width: 600px) { 
            .form-container { padding: 15px; max-width: 90%; }
            .form-container input, .form-container textarea { font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Upload Video</h2>
        <?php if ($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="success"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="text" name="title" placeholder="Video Title" required>
            <textarea name="description" placeholder="Description" required></textarea>
            <input type="file" name="video" accept="video/mp4,video/webm,video/ogg" required>
            <input type="file" name="thumbnail" accept="image/jpeg,image/png,image/gif" required>
            <button type="submit">Upload</button>
        </form>
    </div>
    <script>
        function redirect(url) {
            window.location.href = url;
        }
    </script>
</body>
</html>
