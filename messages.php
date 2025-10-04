<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    error_log("Messages access failed: No user_id or username in session, redirecting to login");
    header("Location: login.php");
    echo "<script>window.location.href = 'login.php';</script>";
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
error_log("Messages access by user_id: $user_id, username: $username");

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['receiver_id']) && isset($_POST['content'])) {
    $receiver_id = $_POST['receiver_id'];
    $content = trim($_POST['content']);
    
    if (empty($content)) {
        $error = "Please enter a message.";
        error_log("Message send failed: Empty content for user_id: $user_id, receiver_id: $receiver_id");
    } else {
        try {
            // Verify receiver is a friend
            $stmt = $pdo->prepare("
                SELECT status FROM friends 
                WHERE (user_id = ? AND friend_id = ? AND status = 'accepted') 
                OR (user_id = ? AND friend_id = ? AND status = 'accepted')
            ");
            $stmt->execute([$user_id, $receiver_id, $receiver_id, $user_id]);
            if (!$stmt->fetch()) {
                $error = "You can only message friends.";
                error_log("Message send failed: Not friends, user_id: $user_id, receiver_id: $receiver_id");
            } else {
                $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$user_id, $receiver_id, $content]);
                $message_id = $pdo->lastInsertId();
                error_log("Message sent successfully by user_id: $user_id to receiver_id: $receiver_id, message_id: $message_id");
                $success = "Message sent!";
                header("Location: messages.php?chat_with=$receiver_id");
                echo "<script>window.location.href = 'messages.php?chat_with=$receiver_id';</script>";
                exit;
            }
        } catch (PDOException $e) {
            $error = "Failed to send message. Please try again.";
            error_log("Message database error for user_id: $user_id, receiver_id: $receiver_id: " . $e->getMessage());
        }
    }
}

// Fetch friends
$stmt = $pdo->prepare("
    SELECT id, username, name, profile_picture, bio 
    FROM users 
    WHERE id IN (
        SELECT friend_id FROM friends WHERE user_id = ? AND status = 'accepted'
        UNION
        SELECT user_id FROM friends WHERE friend_id = ? AND status = 'accepted'
    )
");
$stmt->execute([$user_id, $user_id]);
$friends = $stmt->fetchAll(PDO::FETCH_ASSOC);
error_log("Fetched " . count($friends) . " friends for user_id: $user_id");

// Fetch messages for selected friend
$chat_with = isset($_GET['chat_with']) ? (int)$_GET['chat_with'] : null;
$messages = [];
$chat_friend = null;
if ($chat_with) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, u.name, u.username, u.profile_picture 
            FROM messages m 
            JOIN users u ON u.id = m.sender_id 
            WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?) 
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$user_id, $chat_with, $chat_with, $user_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Fetched " . count($messages) . " messages for user_id: $user_id, chat_with: $chat_with");

        $stmt = $pdo->prepare("SELECT id, name, username, profile_picture, bio FROM users WHERE id = ?");
        $stmt->execute([$chat_with]);
        $chat_friend = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$chat_friend) {
            $error = "Selected user not found.";
            error_log("Chat friend not found for user_id: $user_id, chat_with: $chat_with");
        }
    } catch (PDOException $e) {
        $error = "Failed to load messages: " . $e->getMessage();
        error_log("Messages fetch error for user_id: $user_id, chat_with: $chat_with: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - SocialSphere</title>
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
        body.dark-mode .container, body.dark-mode .friends-list, body.dark-mode .chat-box {
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
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.2);
            display: flex;
            gap: 20px;
            animation: slideIn var(--animation-duration) ease-out;
        }
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .friends-list {
            width: 30%;
            background: var(--card-bg);
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .friends-list h2 {
            margin: 0 0 10px;
            color: var(--primary-color);
        }
        .friend {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            transition: background 0.2s;
            position: relative;
        }
        .friend:hover {
            background: #f5f5f5;
        }
        .friend img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .friend .bio-preview {
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
        .friend:hover .bio-preview {
            display: block;
        }
        .chat-box {
            width: 70%;
            background: var(--card-bg);
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
        }
        .chat-header {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }
        .chat-header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .chat-messages {
            flex-grow: 1;
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
        }
        .message {
            margin-bottom: 10px;
            padding: 8px 12px;
            border-radius: 5px;
            max-width: 70%;
            animation: slideInMessage 0.3s ease-out;
        }
        .message.sent {
            background: var(--primary-color);
            color: #fff;
            margin-left: auto;
            text-align: right;
        }
        .message.received {
            background: #e9ecef;
            margin-right: auto;
        }
        @keyframes slideInMessage {
            from { transform: translateY(10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .chat-input {
            display: flex;
            gap: 10px;
            padding: 10px 0;
        }
        .chat-input textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            resize: none;
        }
        .chat-input button {
            padding: 10px 20px;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: transform var(--animation-duration);
        }
        .chat-input button:hover {
            transform: scale(1.05);
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
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
        @media (max-width: 600px) {
            .container { flex-direction: column; padding: 10px; }
            .friends-list, .chat-box { width: 100%; }
            .chat-messages { max-height: 300px; }
            .navbar a { margin: 0 8px; font-size: 14px; }
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
        <div class="friends-list">
            <h2>Friends</h2>
            <?php if (empty($friends)): ?>
                <p>No friends yet. Add some friends to start messaging!</p>
            <?php else: ?>
                <?php foreach ($friends as $friend): ?>
                    <a href="messages.php?chat_with=<?php echo $friend['id']; ?>" style="text-decoration: none; color: inherit;">
                        <div class="friend">
                            <img src="<?php echo htmlspecialchars($friend['profile_picture'] ?: 'default-profile.jpg'); ?>" alt="Profile">
                            <div>
                                <strong><?php echo htmlspecialchars($friend['name']); ?></strong> (@<?php echo htmlspecialchars($friend['username']); ?>)
                                <div class="bio-preview"><?php echo htmlspecialchars($friend['bio'] ?: 'No bio available'); ?></div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="chat-box">
            <?php if ($error) echo "<p class='error'>" . htmlspecialchars($error) . "</p>"; ?>
            <?php if ($success) echo "<p class='success'>" . htmlspecialchars($success) . "</p>"; ?>
            <?php if ($chat_with && $chat_friend): ?>
                <div class="chat-header">
                    <img src="<?php echo htmlspecialchars($chat_friend['profile_picture'] ?: 'default-profile.jpg'); ?>" alt="Profile">
                    <strong><?php echo htmlspecialchars($chat_friend['name']); ?> (@<?php echo htmlspecialchars($chat_friend['username']); ?>)</strong>
                </div>
                <div class="chat-messages" id="chat-messages">
                    <?php if (empty($messages)): ?>
                        <p>No messages yet. Start the conversation!</p>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="message <?php echo $message['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                                <small><?php echo htmlspecialchars($message['name']); ?> - <?php echo $message['created_at']; ?></small>
                                <p><?php echo htmlspecialchars($message['content']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <form method="POST" class="chat-input">
                    <input type="hidden" name="receiver_id" value="<?php echo $chat_with; ?>">
                    <textarea name="content" placeholder="Type a message..." rows="2" required></textarea>
                    <button type="submit">Send</button>
                </form>
            <?php else: ?>
                <p>Select a friend to start messaging.</p>
            <?php endif; ?>
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
        // Auto-scroll to latest message
        const chatMessages = document.getElementById('chat-messages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    </script>
</body>
</html>
