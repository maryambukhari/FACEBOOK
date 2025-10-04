<?php
// Start session explicitly
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db.php';

if (!isset($_SESSION['user_id'])) {
    error_log("Profile access failed: No user_id in session, redirecting to login");
    header("Location: login.php");
    echo "<script>window.location.href = 'login.php';</script>";
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Unknown';
error_log("Profile access by user_id: $user_id, username: $username");

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        error_log("Profile error: User not found for user_id: $user_id");
        header("Location: logout.php");
        exit;
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    error_log("Profile database error for user_id: $user_id: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $bio = trim($_POST['bio']);
    $profile_picture = $user['profile_picture'];
    $cover_picture = $user['cover_picture'];

    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    if (!is_writable($upload_dir)) {
        chmod($upload_dir, 0777);
    }

    if (!empty($_FILES['profile_picture']['name'])) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        if (!in_array($_FILES['profile_picture']['type'], $allowed_types)) {
            $error = "Profile picture must be JPEG, PNG, or GIF.";
            error_log("Profile picture upload failed for user_id: $user_id: Invalid file type");
        } elseif ($_FILES['profile_picture']['size'] > $max_size) {
            $error = "Profile picture must be under 2MB.";
            error_log("Profile picture upload failed for user_id: $user_id: File too large");
        } elseif ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            $error = "Profile picture upload error: " . $_FILES['profile_picture']['error'];
            error_log("Profile picture upload failed for user_id: $user_id: Upload error code {$_FILES['profile_picture']['error']}");
        } else {
            $profile_picture = $upload_dir . uniqid() . '_' . basename($_FILES['profile_picture']['name']);
            if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $profile_picture)) {
                $error = "Failed to upload profile picture. Check server permissions.";
                error_log("Profile picture upload failed for user_id: $user_id: move_uploaded_file failed");
            }
        }
    }

    if (!empty($_FILES['cover_picture']['name']) && !isset($error)) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        if (!in_array($_FILES['cover_picture']['type'], $allowed_types)) {
            $error = "Cover picture must be JPEG, PNG, or GIF.";
            error_log("Cover picture upload failed for user_id: $user_id: Invalid file type");
        } elseif ($_FILES['cover_picture']['size'] > $max_size) {
            $error = "Cover picture must be under 2MB.";
            error_log("Cover picture upload failed for user_id: $user_id: File too large");
        } elseif ($_FILES['cover_picture']['error'] !== UPLOAD_ERR_OK) {
            $error = "Cover picture upload error: " . $_FILES['cover_picture']['error'];
            error_log("Cover picture upload failed for user_id: $user_id: Upload error code {$_FILES['cover_picture']['error']}");
        } else {
            $cover_picture = $upload_dir . uniqid() . '_' . basename($_FILES['cover_picture']['name']);
            if (!move_uploaded_file($_FILES['cover_picture']['tmp_name'], $cover_picture)) {
                $error = "Failed to upload cover picture. Check server permissions.";
                error_log("Cover picture upload failed for user_id: $user_id: move_uploaded_file failed");
            }
        }
    }

    if (!isset($error)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, bio = ?, profile_picture = ?, cover_picture = ? WHERE id = ?");
            $stmt->execute([$name, $bio, $profile_picture, $cover_picture, $user_id]);
            error_log("Profile updated successfully for user_id: $user_id");
            header("Location: profile.php");
            echo "<script>window.location.href = 'profile.php';</script>";
            exit;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            error_log("Profile update database error for user_id: $user_id: " . $e->getMessage());
        }
    }
}

// Fetch stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
$stmt->execute([$user_id]);
$post_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM friends WHERE (user_id = ? OR friend_id = ?) AND status = 'accepted'");
$stmt->execute([$user_id, $user_id]);
$friend_count = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - SocialSphere</title>
    <style>
        :root {
            --primary-color: #3b5998;
            --accent-color: #ff9900;
            --bg-color: #f0f2f5;
            --text-color: #333;
            --card-bg: #fff;
            --animation-duration: 0.5s;
            --dark-bg: #18191a;
            --dark-card-bg: #242526;
            --dark-text: #e4e6eb;
        }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--bg-color);
            color: var(--text-color);
            transition: background var(--animation-duration);
        }
        body.dark-mode {
            background: var(--dark-bg);
            color: var(--dark-text);
        }
        body.dark-mode .container, body.dark-mode .profile-box {
            background: var(--dark-card-bg);
        }
        .navbar {
            background: var(--primary-color);
            padding: 10px 20px;
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .navbar a {
            color: #fff;
            text-decoration: none;
            margin: 0 10px;
            transition: transform var(--animation-duration);
        }
        .navbar a:hover {
            transform: scale(1.1);
        }
        .dark-mode-toggle {
            cursor: pointer;
            padding: 5px 10px;
            background: #fff;
            color: var(--primary-color);
            border-radius: 5px;
        }
        .dark-mode-toggle:hover {
            background: var(--accent-color);
            color: #fff;
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
            animation: slideIn var(--animation-duration) ease-out;
        }
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .cover {
            background: url('<?php echo htmlspecialchars($user['cover_picture'] ?: 'default-cover.jpg'); ?>') no-repeat center;
            height: 250px;
            border-radius: 8px;
            background-size: cover;
            position: relative;
        }
        .profile-picture {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin-top: -60px;
            border: 4px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            transition: transform var(--animation-duration);
        }
        .profile-picture:hover {
            transform: scale(1.1);
        }
        .profile-box {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .profile-box input, .profile-box textarea {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
        .profile-box button {
            padding: 10px 20px;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: transform var(--animation-duration);
        }
        .profile-box button:hover {
            transform: scale(1.05);
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
        }
        .error {
            color: red;
            text-align: center;
            margin: 10px 0;
        }
        .stats {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            background: #f9f9f9;
            padding: 10px;
            border-radius: 8px;
        }
        @media (max-width: 600px) {
            .container { padding: 10px; }
            .cover { height: 150px; }
            .profile-picture { width: 80px; height: 80px; margin-top: -40px; }
            .stats { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <span>Welcome, <?php echo htmlspecialchars($username); ?>!</span>
        <div>
            <a href="index.php">Home</a>
            <a href="profile.php">Profile</a>
            <a href="friends.php">Friends</a>
            <a href="messages.php">Messages</a>
            <a href="logout.php">Logout</a>
            <span class="dark-mode-toggle">Toggle Dark Mode</span>
        </div>
    </div>
    <div class="container">
        <div class="cover"></div>
        <img src="<?php echo htmlspecialchars($user['profile_picture'] ?: 'default-profile.jpg'); ?>" class="profile-picture" alt="Profile">
        <div class="profile-box">
            <h2><?php echo htmlspecialchars($user['name']); ?> (@<?php echo htmlspecialchars($user['username']); ?>)</h2>
            <p><?php echo htmlspecialchars($user['bio'] ?: 'No bio yet'); ?></p>
            <div class="stats">
                <div><strong><?php echo $post_count; ?></strong> Posts</div>
                <div><strong><?php echo $friend_count; ?></strong> Friends</div>
            </div>
            <?php if (isset($error)) echo "<p class='error'>" . htmlspecialchars($error) . "</p>"; ?>
            <form method="POST" enctype="multipart/form-data">
                <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                <textarea name="bio" placeholder="Bio"><?php echo htmlspecialchars($user['bio']); ?></textarea>
                <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/gif">
                <input type="file" name="cover_picture" accept="image/jpeg,image/png,image/gif">
                <button type="submit">Update Profile</button>
            </form>
        </div>
    </div>
    <script>
        document.querySelector('.dark-mode-toggle').addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
        });
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark-mode');
        }
    </script>
</body>
</html>
