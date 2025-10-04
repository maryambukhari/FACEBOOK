<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    error_log("Index access failed: No user_id or username in session, redirecting to login");
    header("Location: login.php");
    echo "<script>window.location.href = 'login.php';</script>";
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
error_log("Index access by user_id: $user_id, username: $username");

$upload_dir = 'Uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
    error_log("Created Uploads directory");
}
if (!is_writable($upload_dir)) {
    chmod($upload_dir, 0777);
    error_log("Set Uploads directory permissions to 777");
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['content'])) {
    $content = trim($_POST['content']);
    $image = null;

    if (empty($content) && empty($_FILES['image']['name'])) {
        $error = "Please enter some content or upload an image.";
        error_log("Post failed: No content or image provided by user_id: $user_id");
    } else {
        try {
            if (!empty($_FILES['image']['name'])) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 2 * 1024 * 1024; // 2MB
                if (!in_array($_FILES['image']['type'], $allowed_types)) {
                    $error = "Invalid image format. Use JPEG, PNG, or GIF.";
                    error_log("Post failed: Invalid image format for user_id: $user_id");
                } elseif ($_FILES['image']['size'] > $max_size) {
                    $error = "Image must be under 2MB.";
                    error_log("Post failed: Image too large for user_id: $user_id");
                } elseif ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                    $error = "Image upload error: " . $_FILES['image']['error'];
                    error_log("Post failed: Image upload error for user_id: $user_id, code: {$_FILES['image']['error']}");
                } else {
                    $image = $upload_dir . uniqid() . '_' . basename($_FILES['image']['name']);
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $image)) {
                        $error = "Failed to upload image. Check server permissions.";
                        error_log("Post failed: move_uploaded_file failed for user_id: $user_id");
                    }
                }
            }

            if (!$error) {
                $stmt = $pdo->prepare("INSERT INTO posts (user_id, content, image, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$user_id, $content, $image]);
                $post_id = $pdo->lastInsertId();
                error_log("Post successful by user_id: $user_id, post_id: $post_id, content: " . substr($content, 0, 50));
                $success = "Post created successfully!";
                header("Location: index.php");
                echo "<script>window.location.href = 'index.php';</script>";
                exit;
            }
        } catch (PDOException $e) {
            $error = "Failed to save post. Please try again.";
            error_log("Post database error for user_id: $user_id: " . $e->getMessage());
        }
    }
}

$stmt = $pdo->prepare("
    SELECT p.*, u.name, u.username, u.profile_picture, u.bio,
           (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
           (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
    FROM posts p
    JOIN users u ON u.id = p.user_id
    WHERE p.user_id = ? OR p.user_id IN (
        SELECT friend_id FROM friends WHERE user_id = ? AND status = 'accepted'
        UNION
        SELECT user_id FROM friends WHERE friend_id = ? AND status = 'accepted'
    )
    ORDER BY p.created_at DESC LIMIT 50
");
$stmt->execute([$user_id, $user_id, $user_id]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
error_log("Fetched " . count($posts) . " posts for user_id: $user_id");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - SocialSphere</title>
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
        body.dark-mode .container, body.dark-mode .post-box, body.dark-mode .post {
            background: var(--dark-card-bg);
        }
        .navbar {
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
            padding: 15px 20px;
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .navbar a {
            color: #fff;
            text-decoration: none;
            margin: 0 15px;
            font-weight: 500;
            transition: transform var(--animation-duration);
        }
        .navbar a:hover {
            transform: scale(1.1);
        }
        .dark-mode-toggle {
            cursor: pointer;
            padding: 8px 12px;
            background: #fff;
            color: var(--primary-color);
            border-radius: 5px;
            font-size: 14px;
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
            box-shadow: 0 0 15px rgba(0,0,0,0.2);
            animation: slideIn var(--animation-duration) ease-out;
        }
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .post-box {
            background: var(--card-bg);
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .post-box textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color var(--animation-duration);
        }
        .post-box textarea:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        .post-box input[type="file"] {
            margin: 10px 0;
        }
        .post-box button {
            padding: 12px 20px;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: transform var(--animation-duration);
        }
        .post-box button:hover {
            transform: scale(1.05);
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
        }
        .post {
            background: var(--card-bg);
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            animation: slideInPost var(--animation-duration) ease-out;
            position: relative;
        }
        @keyframes slideInPost {
            from { transform: translateX(-50px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .post img.profile-pic {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            vertical-align: middle;
            transition: transform var(--animation-duration);
        }
        .post img.profile-pic:hover {
            transform: scale(1.1);
        }
        .post .bio-preview {
            display: none;
            position: absolute;
            background: var(--card-bg);
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            max-width: 200px;
            z-index: 10;
            top: 100%;
            left: 0;
            margin-top: 5px;
        }
        .post:hover .bio-preview {
            display: block;
        }
        .post img.content-img {
            max-width: 100%;
            border-radius: 5px;
            margin-top: 10px;
        }
        .post-actions a {
            color: var(--primary-color);
            text-decoration: none;
            margin-right: 15px;
            font-weight: 500;
            transition: transform var(--animation-duration);
        }
        .post-actions a:hover {
            transform: scale(1.1);
            animation: pulse 0.5s;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        .error {
            color: red;
            text-align: center;
            margin: 10px 0;
            animation: shake 0.3s;
        }
        .success {
            color: green;
            text-align: center;
            margin: 10px 0;
            animation: fadeIn 0.3s;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .comment-box {
            margin-top: 10px;
            display: none;
        }
        .comment-box textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .comment-box button {
            padding: 8px 12px;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        @media (max-width: 600px) {
            .container { padding: 10px; }
            .post-box textarea, .comment-box textarea { width: calc(100% - 20px); }
            .navbar a { margin: 0 8px; font-size: 14px; }
            .bio-preview { position: static; display: block; margin-top: 5px; }
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
        <div class="post-box">
            <form method="POST" enctype="multipart/form-data">
                <textarea name="content" placeholder="What's on your mind, <?php echo htmlspecialchars($username); ?>?" rows="4"></textarea>
                <input type="file" name="image" accept="image/jpeg,image/png,image/gif">
                <button type="submit">Post</button>
            </form>
            <?php if ($error) echo "<p class='error'>" . htmlspecialchars($error) . "</p>"; ?>
            <?php if ($success) echo "<p class='success'>" . htmlspecialchars($success) . "</p>"; ?>
        </div>
        <?php if (empty($posts)): ?>
            <p>No posts yet. Share something with your friends!</p>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <div class="post">
                    <img src="<?php echo htmlspecialchars($post['profile_picture'] ?: 'default-profile.jpg'); ?>" class="profile-pic" alt="Profile">
                    <strong><?php echo htmlspecialchars($post['name']); ?> (@<?php echo htmlspecialchars($post['username']); ?>)</strong>
                    <div class="bio-preview"><?php echo htmlspecialchars($post['bio'] ?: 'No bio available'); ?></div>
                    <p><?php echo htmlspecialchars($post['content']); ?></p>
                    <?php if ($post['image']): ?>
                        <img src="<?php echo htmlspecialchars($post['image']); ?>" class="content-img" alt="Post Image">
                    <?php endif; ?>
                    <small><?php echo $post['created_at']; ?></small>
                    <div class="post-actions">
                        <a href="post.php?action=like&post_id=<?php echo $post['id']; ?>">Like (<?php echo $post['like_count']; ?>)</a>
                        <a href="javascript:void(0)" onclick="document.getElementById('comment-<?php echo $post['id']; ?>').style.display='block'">Comment (<?php echo $post['comment_count']; ?>)</a>
                        <a href="post.php?action=share&post_id=<?php echo $post['id']; ?>">Share</a>
                    </div>
                    <div class="comment-box" id="comment-<?php echo $post['id']; ?>">
                        <form method="POST" action="post.php">
                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                            <textarea name="comment" placeholder="Write a comment..." required></textarea>
                            <button type="submit">Comment</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
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
