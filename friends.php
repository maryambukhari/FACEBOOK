<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db.php';

if (!isset($_SESSION['user_id'])) {
    error_log("Friends access failed: No user_id in session, redirecting to login");
    header("Location: login.php");
    echo "<script>window.location.href = 'login.php';</script>";
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Unknown';
error_log("Friends access by user_id: $user_id, username: $username");

// Search for users
$error = '';
$users = [];
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search = trim($_GET['search']);
    try {
        $stmt = $pdo->prepare("SELECT id, username, name, profile_picture, bio FROM users WHERE (LOWER(username) LIKE LOWER(?) OR LOWER(name) LIKE LOWER(?)) AND id != ?");
        $stmt->execute(["%$search%", "%$search%", $user_id]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Search executed for term: $search, found " . count($users) . " users");
    } catch (PDOException $e) {
        $error = "Search error: " . $e->getMessage();
        error_log("Search error for user_id: $user_id: " . $e->getMessage());
    }
}

// Fetch pending friend requests
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.name, u.profile_picture, u.bio 
    FROM users u 
    JOIN friends f ON u.id = f.user_id 
    WHERE f.friend_id = ? AND f.status = 'pending'
");
$stmt->execute([$user_id]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
error_log("Fetched " . count($requests) . " pending friend requests for user_id: $user_id");

// Fetch accepted friends
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.name, u.profile_picture, u.bio, (
        SELECT COUNT(*) FROM friends f2 
        WHERE (f2.user_id IN (SELECT friend_id FROM friends WHERE user_id = ? AND status = 'accepted') 
        OR f2.friend_id IN (SELECT friend_id FROM friends WHERE user_id = ? AND status = 'accepted'))
        AND (f2.user_id = u.id OR f2.friend_id = u.id) AND f2.status = 'accepted'
    ) as mutual_friends
    FROM users u 
    WHERE u.id IN (
        SELECT friend_id FROM friends WHERE user_id = ? AND status = 'accepted'
        UNION
        SELECT user_id FROM friends WHERE friend_id = ? AND status = 'accepted'
    )
");
$stmt->execute([$user_id, $user_id, $user_id, $user_id]);
$friends = $stmt->fetchAll(PDO::FETCH_ASSOC);
error_log("Fetched " . count($friends) . " friends for user_id: $user_id");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Friends - SocialSphere</title>
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
        body.dark-mode .container, body.dark-mode .friend-box, body.dark-mode .search-box {
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
        .search-box {
            background: var(--card-bg);
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .search-box form {
            display: flex;
            align-items: center;
        }
        .search-box input {
            width: calc(100% - 120px);
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color var(--animation-duration);
        }
        .search-box input:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        .search-box button {
            padding: 12px 20px;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 10px;
            transition: transform var(--animation-duration);
        }
        .search-box button:hover {
            transform: scale(1.05);
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
        }
        .friend-box {
            background: var(--card-bg);
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            transition: transform 0.2s;
            position: relative;
        }
        .friend-box:hover {
            transform: translateY(-3px);
        }
        .friend-box img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 15px;
            transition: transform var(--animation-duration);
        }
        .friend-box img:hover {
            transform: scale(1.1);
        }
        .friend-box .bio-preview {
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
        .friend-box:hover .bio-preview {
            display: block;
        }
        .friend-box button {
            color: #fff;
            background: var(--primary-color);
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            margin: 0 5px;
            transition: background var(--animation-duration);
        }
        .friend-box button:hover {
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
        }
        .friend-box button.reject {
            background: #dc3545;
        }
        .friend-box button.reject:hover {
            background: #c82333;
        }
        .error {
            color: red;
            text-align: center;
            margin: 10px 0;
            animation: shake 0.3s;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        @media (max-width: 600px) {
            .container { padding: 10px; }
            .search-box input { width: calc(100% - 100px); }
            .friend-box { flex-direction: column; align-items: flex-start; }
            .friend-box img { margin-bottom: 10px; }
            .bio-preview { position: static; display: block; margin-top: 5px; }
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
        <div class="search-box">
            <form method="GET">
                <input type="text" name="search" placeholder="Search for friends by name or username..." value="<?php echo isset($search) ? htmlspecialchars($search) : ''; ?>">
                <button type="submit">Search</button>
            </form>
        </div>
        <?php if (isset($error)) echo "<p class='error'>" . htmlspecialchars($error) . "</p>"; ?>
        <h2>Friend Requests</h2>
        <?php if (empty($requests)): ?>
            <p>No pending friend requests.</p>
        <?php else: ?>
            <?php foreach ($requests as $request): ?>
                <div class="friend-box">
                    <img src="<?php echo htmlspecialchars($request['profile_picture'] ?: 'default-profile.jpg'); ?>" alt="Profile">
                    <div>
                        <p><?php echo htmlspecialchars($request['name']); ?> (@<?php echo htmlspecialchars($request['username']); ?>)</p>
                        <div class="bio-preview"><?php echo htmlspecialchars($request['bio'] ?: 'No bio available'); ?></div>
                        <form action="add_friend.php" method="POST" style="display: inline;">
                            <input type="hidden" name="friend_id" value="<?php echo $request['id']; ?>">
                            <button type="submit" name="action" value="accept">Accept</button>
                            <button type="submit" name="action" value="reject" class="reject">Reject</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <h2>Friends</h2>
        <?php if (empty($friends)): ?>
            <p>No friends yet. Send some friend requests!</p>
        <?php else: ?>
            <?php foreach ($friends as $friend): ?>
                <div class="friend-box">
                    <img src="<?php echo htmlspecialchars($friend['profile_picture'] ?: 'default-profile.jpg'); ?>" alt="Profile">
                    <div>
                        <p><?php echo htmlspecialchars($friend['name']); ?> (@<?php echo htmlspecialchars($friend['username']); ?>)</p>
                        <p><?php echo $friend['mutual_friends']; ?> mutual friends</p>
                        <div class="bio-preview"><?php echo htmlspecialchars($friend['bio'] ?: 'No bio available'); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if (isset($users) && !empty($users)): ?>
            <h2>Search Results</h2>
            <?php foreach ($users as $user): ?>
                <div class="friend-box">
                    <img src="<?php echo htmlspecialchars($user['profile_picture'] ?: 'default-profile.jpg'); ?>" alt="Profile">
                    <div>
                        <p><?php echo htmlspecialchars($user['name']); ?> (@<?php echo htmlspecialchars($user['username']); ?>)</p>
                        <div class="bio-preview"><?php echo htmlspecialchars($user['bio'] ?: 'No bio available'); ?></div>
                        <form action="add_friend.php" method="POST" style="display: inline;">
                            <input type="hidden" name="friend_id" value="<?php echo $user['id']; ?>">
                            <?php
                            // Check if a request is already sent or user is a friend
                            $stmt = $pdo->prepare("SELECT status FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
                            $stmt->execute([$user_id, $user['id'], $user['id'], $user_id]);
                            $status = $stmt->fetchColumn();
                            if ($status === 'accepted') {
                                echo "<p>Already friends</p>";
                            } elseif ($status === 'pending') {
                                echo "<p>Request pending</p>";
                            } else {
                                echo '<button type="submit" name="action" value="send">Send Friend Request</button>';
                            }
                            ?>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php elseif (isset($users)): ?>
            <h2>Search Results</h2>
            <p>No users found.</p>
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
