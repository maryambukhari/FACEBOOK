<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db.php';

if (!isset($_SESSION['user_id'])) {
    error_log("Add friend failed: No user_id in session, redirecting to login");
    header("Location: login.php");
    echo "<script>window.location.href = 'login.php';</script>";
    exit;
}

$user_id = $_SESSION['user_id'];
$friend_id = $_POST['friend_id'] ?? null;
$action = $_POST['action'] ?? null;

if (!$friend_id || !$action) {
    error_log("Add friend failed: Missing friend_id or action for user_id: $user_id");
    header("Location: friends.php");
    echo "<script>window.location.href = 'friends.php';</script>";
    exit;
}

try {
    if ($action === 'send') {
        // Check for existing request in either direction
        $stmt = $pdo->prepare("SELECT status FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
        $stmt->execute([$user_id, $friend_id, $friend_id, $user_id]);
        $status = $stmt->fetchColumn();
        if (!$status) {
            $stmt = $pdo->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')");
            $stmt->execute([$user_id, $friend_id]);
            error_log("Friend request sent by user_id: $user_id to friend_id: $friend_id");
        } else {
            error_log("Friend request skipped: Already exists for user_id: $user_id, friend_id: $friend_id (status: $status)");
        }
    } elseif ($action === 'accept') {
        // Update pending request to accepted
        $stmt = $pdo->prepare("UPDATE friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ? AND status = 'pending'");
        $stmt->execute([$friend_id, $user_id]);
        if ($stmt->rowCount() > 0) {
            // Insert reciprocal friendship
            $stmt = $pdo->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'accepted') ON DUPLICATE KEY UPDATE status = 'accepted'");
            $stmt->execute([$user_id, $friend_id]);
            error_log("Friend request accepted by user_id: $user_id for friend_id: $friend_id");
        } else {
            error_log("Accept failed: No pending request from friend_id: $friend_id to user_id: $user_id");
        }
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("DELETE FROM friends WHERE user_id = ? AND friend_id = ? AND status = 'pending'");
        $stmt->execute([$friend_id, $user_id]);
        error_log("Friend request rejected by user_id: $user_id for friend_id: $friend_id");
    }
} catch (PDOException $e) {
    error_log("Friend action error for user_id: $user_id, action: $action: " . $e->getMessage());
}

header("Location: friends.php");
echo "<script>window.location.href = 'friends.php';</script>";
exit;
?>
