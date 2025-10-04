<?php
require 'db.php';

// Ensure session is started (redundant check for robustness)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
        error_log("Login failed: Empty username or password");
    } else {
        try {
            // Case-insensitive username query
            $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?)");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Debug: Check if user was found
            if (!$user) {
                $error = "No user found with username '$username'. Please check your username or sign up.";
                error_log("Login failed: No user found for username '$username'");
            } elseif (!password_verify($password, $user['password'])) {
                $error = "Incorrect password. Please try again.";
                error_log("Login failed: Incorrect password for username '$username', stored hash: {$user['password']}");
            } else {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                // Debug: Log successful login
                error_log("Login successful for username: {$user['username']}, user_id: {$user['id']}");
                // PHP redirect with JavaScript fallback
                header("Location: index.php");
                echo "<script>window.location.href = 'index.php';</script>";
                exit;
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            error_log("Login database error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SocialSphere</title>
    <style>
        :root {
            --primary-color: #3b5998;
            --accent-color: #ff9900;
            --bg-color: #f0f2f5;
            --text-color: #333;
            --animation-duration: 0.5s;
        }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(45deg, var(--primary-color), #8b9dc3);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--text-color);
        }
        .container {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
            animation: slideIn var(--animation-duration) ease-out;
        }
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        h2 {
            text-align: center;
            position: relative;
        }
        h2::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 2px;
            background: var(--accent-color);
            transition: width var(--animation-duration);
        }
        h2:hover::after {
            width: 100px;
        }
        input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
        button {
            width: 100%;
            padding: 10px;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: transform var(--animation-duration), box-shadow var(--animation-duration);
        }
        button:hover {
            transform: scale(1.05);
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
        }
        .error {
            color: red;
            text-align: center;
            margin: 10px 0;
            font-size: 14px;
        }
        a {
            color: var(--primary-color);
            text-decoration: none;
            display: block;
            text-align: center;
            margin-top: 10px;
        }
        a:hover {
            text-decoration: underline;
            animation: shake 0.3s;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        @media (max-width: 600px) {
            .container { width: 90%; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Login to SocialSphere</h2>
        <?php if (isset($error)) echo "<p class='error'>" . htmlspecialchars($error) . "</p>"; ?>
        <?php if (isset($_GET['signup']) && $_GET['signup'] == 'success') echo "<p style='color: green; text-align: center;'>Signup successful! Please log in.</p>"; ?>
        <form method="POST" action="">
            <input type="text" name="username" placeholder="Username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
            <a href="signup.php">Don't have an account? Sign up</a>
        </form>
    </div>
</body>
</html>
