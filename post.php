<?php
require 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    error_log("Post failed: No user_id in session, redirecting to login");
    header("Location: login.php");
    echo "<script>window.location.href = 'login.php';</script>";
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Unknown';
error_log("Post attempt by user_id: $user_id, username: $username");

$upload_dir = 'uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}
if (!is_writable($upload_dir)) {
    chmod($upload_dir, 0777);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['content'])) {
        $content = trim($_POST['content']);
        $image = null;

        if (empty($content) && empty($_FILES['image']['name'])) {
            $error = "Please enter some content or upload an image.";
            error_log("Post failed: No content or image provided by user_id: $user_id");
        } else {
            try {
                if (!empty($_FILES['image']['name'])) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    if (!in_array($_FILES['image']['type'], $allowed_types)) {
                        $error = "Invalid image format. Use JPEG, PNG, or GIF.";
                        error_log("Post failed: Invalid image format for user_id: $user_id, type: {$_FILES['image']['type']}");
                    } elseif ($_FILES['image']['size'] > 2 * 1024 * 1024) {
                        $error = "Image must be under 2MB.";
                        error_log("Post failed: Image too large for user_id: $user_id, size: {$_FILES['image']['size']}");
                    } elseif ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                        $error = "Image upload error: " . $_FILES['image']['error'];
                        error_log("Post failed: Image upload error for user_id: $user_id, code: {$_FILES['image']['error']}");
                    } else {
                        $image = $upload_dir . uniqid() . '_' . basename($_FILES['image']['name']);
                        if (!move_uploaded_file($_FILES['image']['tmp_name'], $image)) {
                            $error = "Failed to upload image. Check server permissions.";
                            error_log("Post failed: move_uploaded_file failed for user_id: $user_id, file: {$_FILES['image']['name']}");
                        }
                    }
                }

                if (!isset($error)) {
                    $stmt = $pdo->prepare("INSERT INTO posts (user_id, content, image) VALUES (?, ?, ?)");
                    $stmt->execute([$user_id, $content, $image]);
                    error_log("Post successful by user_id: $user_id, content: " . substr($content, 0, 50));
                    header("Location: index.php");
                    echo "<script>window.location.href = 'index.php';</script>";
                    exit;
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
                error_log("Post database error for user_id: $user_id: " . $e->getMessage());
            }
        }
    } elseif (isset($_POST['comment'])) {
        $post_id = $_POST['post_id'];
        $content = trim($_POST['comment']);
        if (empty($content)) {
            $error = "Please enter a comment.";
            error_log("Comment failed: No content provided by user_id: $user_id");
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO comments (user_id, post_id, content) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $post_id, $content]);
                error_log("Comment successful by user_id: $user_id on post_id: $post_id");
                header("Location: index.php");
                echo "<script>window.location.href = 'index.php';</script>";
                exit;
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
                error_log("Comment database error for user_id: $user_id: " . $e->getMessage());
            }
        }
    }
} elseif (isset($_GET['action'])) {
    $post_id = $_GET['post_id'];
    try {
        if ($_GET['action'] == 'like') {
            $stmt = $pdo->prepare("SELECT 1 FROM likes WHERE user_id = ? AND post_id = ?");
            $stmt->execute([$user_id, $post_id]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?");
                error_log("Unlike successful by user_id: $user_id on post_id: $post_id");
            } else {
                $stmt = $pdo->prepare("INSERT INTO likes (user_id, post_id) VALUES (?, ?)");
                error_log("Like successful by user_id: $user_id on post_id: $post_id");
            }
            $stmt->execute([$user_id, $post_id]);
            header("Location: index.php");
            echo "<script>window.location.href = 'index.php';</script>";
            exit;
        } elseif ($_GET['action'] == 'share') {
            $stmt = $pdo->prepare("INSERT INTO posts (user_id, content, image) SELECT ?, content, image FROM posts WHERE id = ?");
            $stmt->execute([$user_id, $post_id]);
            error_log("Share successful by user_id: $user_id on post_id: $post_id");
            header("Location: index.php");
            echo "<script>window.location.href = 'index.php';</script>";
            exit;
        } elseif ($_GET['action'] == 'delete' && isset($post_id)) {
            $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
            $stmt->execute([$post_id]);
            if ($stmt->fetchColumn() == $user_id) {
                $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
                $stmt->execute([$post_id]);
                error_log("Delete successful by user_id: $user_id on post_id: $post_id");
            }
            header("Location: index.php");
            echo "<script>window.location.href = 'index.php';</script>";
            exit;
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        error_log("Action database error for user_id: $user_id: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post - SocialSphere</title>
    <style>
        :root {
            --primary-color: #3b5998;
            --accent-color: #ff9900;
            --bg-color: #f0f2f5;
            --text-color: #333;
            --card-bg: #fff;
            --animation-duration: 0.5s;
        }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--bg-color);
            color: var(--text-color);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
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
            .container { padding: 10px; width: 90%; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Post Error</h2>
        <?php if (isset($error)) echo "<p class='error'>" . htmlspecialchars($error) . "</p>"; ?>
        <a href="index.php">Return to Home</a>
    </div>
</body>
</html>
