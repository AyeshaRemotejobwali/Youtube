<?php
require_once 'db.php';

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate form inputs
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

        if (empty($username) || empty($email) || empty($password)) {
            throw new Exception("All fields are required.");
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }

        // Validate username (e.g., alphanumeric, 3-50 characters)
        if (!preg_match('/^[a-zA-Z0-9]{3,50}$/', $username)) {
            throw new Exception("Username must be 3-50 characters and alphanumeric.");
        }

        // Validate password (e.g., minimum 6 characters)
        if (strlen($password) < 6) {
            throw new Exception("Password must be at least 6 characters long.");
        }

        // Check if users table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'users'");
        if ($table_check->rowCount() == 0) {
            throw new Exception("Users table not found. Please set up the database.");
        }

        // Check for existing username or email
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->rowCount() > 0) {
            throw new Exception("Username or email already exists.");
        }

        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$username, $password_hash, $email]);

        error_log("User registered successfully: $username");
        $success = "Account created successfully! Redirecting to login...";
        header("Refresh: 2; url=login.php");
        // Note: JavaScript redirection is used below for consistency with your requirement

    } catch (Exception $e) {
        error_log("Signup error: " . $e->getMessage());
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - YouTube Clone</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
        body { background: #f8f8f8; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .form-container { 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
            width: 100%; 
            max-width: 400px; 
        }
        .form-container h2 { margin-bottom: 20px; text-align: center; color: #333; }
        .form-container input { 
            width: 100%; 
            padding: 10px; 
            margin: 10px 0; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
            font-size: 16px; 
        }
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
        .form-container p { text-align: center; margin-top: 10px; }
        .error, .success { 
            text-align: center; 
            padding: 10px; 
            margin-bottom: 10px; 
            border-radius: 4px; 
        }
        .error { color: #ff0000; background: #ffe6e6; }
        .success { color: #008000; background: #e6ffe6; }
        .form-container a { color: #ff0000; text-decoration: none; }
        .form-container a:hover { text-decoration: underline; }
        @media (max-width: 600px) { 
            .form-container { padding: 15px; max-width: 90%; }
            .form-container input, .form-container button { font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Sign Up</h2>
        <?php if ($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="success"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Sign Up</button>
            <p>Already have an account? <a href="#" onclick="redirect('login.php')">Login</a></p>
        </form>
    </div>
    <script>
        function redirect(url) {
            window.location.href = url;
        }
    </script>
</body>
</html>
