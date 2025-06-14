<?php
session_start();
require_once 'db.php';

// Enable error logging for debugging
ini_set('display_errors', 0); // Disable for production to avoid exposing errors
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Initialize videos array
$videos = [];
$error_message = '';

try {
    // Check if tables exist
    $table_check = $conn->query("SHOW TABLES LIKE 'videos'");
    if ($table_check->rowCount() == 0) {
        $error_message = "Videos table not found. Please ensure the database is set up correctly.";
    } else {
        // Use prepared statement for safety
        $stmt = $conn->prepare("
            SELECT videos.*, users.username 
            FROM videos 
            LEFT JOIN users ON videos.user_id = users.id 
            ORDER BY videos.views DESC LIMIT 6
        ");
        $stmt->execute();
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($videos)) {
            $error_message = "No videos found. Upload some videos to get started!";
        }
        error_log("Query executed successfully, fetched " . count($videos) . " videos");
    }
} catch (PDOException $e) {
    error_log("Query error: " . $e->getMessage());
    $error_message = "Error fetching videos. Please try again later.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YouTube Clone - Home</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
        body { background: #f8f8f8; }
        .header { 
            background: #ff0000; 
            color: white; 
            padding: 10px 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            position: fixed; 
            width: 100%; 
            z-index: 1000; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .header img { height: 40px; }
        .search-bar { flex-grow: 1; margin: 0 20px; }
        .search-bar input { 
            width: 100%; 
            padding: 8px 15px; 
            border: none; 
            border-radius: 20px; 
            font-size: 16px; 
            outline: none; 
        }
        .user-menu a { 
            color: white; 
            margin-left: 15px; 
            text-decoration: none; 
            font-weight: bold; 
            transition: color 0.2s; 
        }
        .user-menu a:hover { color: #ddd; }
        .container { padding: 80px 20px; max-width: 1200px; margin: auto; }
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
            cursor: pointer; 
            transition: transform 0.2s; 
        }
        .video-card:hover { transform: scale(1.03); }
        .video-card img { width: 100%; height: 150px; object-fit: cover; }
        .video-card h3 { font-size: 16px; padding: 10px; color: #333; }
        .video-card p { font-size: 14px; color: #606060; padding: 0 10px 10px; }
        .error-message { 
            text-align: center; 
            color: #ff0000; 
            padding: 20px; 
            font-size: 18px; 
        }
        @media (max-width: 600px) { 
            .video-grid { grid-template-columns: 1fr; }
            .search-bar input { font-size: 14px; }
            .header { flex-wrap: wrap; padding: 10px; }
            .user-menu a { margin: 5px; }
        }
    </style>
</head>
<body>
    <header class="header">
        <img src="https://via.placeholder.com/100x40?text=Logo" alt="Logo">
        <div class="search-bar">
            <input type="text" id="search" placeholder="Search videos..." onkeyup="searchVideos()">
        </div>
        <div class="user-menu">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="#" onclick="redirect('profile.php')">Profile</a>
                <a href="#" onclick="redirect('upload.php')">Upload</a>
                <a href="#" onclick="redirect('logout.php')">Logout</a>
            <?php else: ?>
                <a href="#" onclick="redirect('login.php')">Login</a>
                <a href="#" onclick="redirect('signup.php')">Sign Up</a>
            <?php endif; ?>
        </div>
    </header>
    <div class="container">
        <h2>Trending Videos</h2>
        <?php if ($error_message): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php else: ?>
            <div class="video-grid" id="video-grid">
                <?php foreach ($videos as $video): ?>
                    <div class="video-card" onclick="redirect('watch.php?id=<?php echo $video['id']; ?>')">
                        <img src="<?php echo htmlspecialchars($video['thumbnail'] ?? 'https://via.placeholder.com/250x150?text=No+Thumbnail'); ?>" alt="Thumbnail">
                        <h3><?php echo htmlspecialchars($video['title'] ?? 'Untitled'); ?></h3>
                        <p>by <?php echo htmlspecialchars($video['username'] ?? 'Unknown'); ?> • <?php echo $video['views'] ?? 0; ?> views</p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <script>
        function redirect(url) {
            window.location.href = url;
        }

        function searchVideos() {
            let query = document.getElementById('search').value;
            if (query.length > 2) {
                fetch('search.php?q=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        let grid = document.getElementById('video-grid');
                        grid.innerHTML = '';
                        if (data.length === 0) {
                            grid.innerHTML = '<p class="error-message">No videos found for "' + query + '"</p>';
                        } else {
                            data.forEach(video => {
                                grid.innerHTML += `
                                    <div class="video-card" onclick="redirect('watch.php?id=${video.id}')">
                                        <img src="${video.thumbnail || 'https://via.placeholder.com/250x150?text=No+Thumbnail'}" alt="Thumbnail">
                                        <h3>${video.title || 'Untitled'}</h3>
                                        <p>by ${video.username || 'Unknown'} • ${video.views || 0} views</p>
                                    </div>`;
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        document.getElementById('video-grid').innerHTML = '<p class="error-message">Error searching videos. Please try again.</p>';
                    });
            }
        }
    </script>
</body>
</html>
