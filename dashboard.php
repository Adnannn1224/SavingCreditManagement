<?php
session_start();

// Debug: Log session data
error_log("Dashboard - Session data: " . print_r($_SESSION, true));

// Redirect to login if not logged in
if (!isset($_SESSION['member_id'])) {
    error_log("Dashboard - Member ID not set in session. Redirecting to login.php");
    header("Location: login.php");
    exit();
}

try {
    $conn = new PDO('mysql:host=localhost;dbname=employee_saving_credit_db', 'root', '');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch member details
    $member_id = $_SESSION['member_id'];
    $sql_member = "SELECT first_name, last_name, email, account_balance, profile_image FROM members WHERE member_id = :member_id";
    $stmt_member = $conn->prepare($sql_member);
    $stmt_member->execute([':member_id' => $member_id]);
    $member = $stmt_member->fetch(PDO::FETCH_ASSOC);

    // Fetch recent transactions
    $sql_transactions = "SELECT transaction_type, amount, transaction_date FROM transactions WHERE member_id = :member_id ORDER BY transaction_date DESC LIMIT 5";
    $stmt_transactions = $conn->prepare($sql_transactions);
    $stmt_transactions->execute([':member_id' => $member_id]);
    $transactions = $stmt_transactions->fetchAll(PDO::FETCH_ASSOC);

    // Fetch unread notifications count and recent notifications
    $sql_unread = "SELECT COUNT(*) as unread FROM notifications WHERE member_id = :member_id AND is_read = 'No'";
    $stmt_unread = $conn->prepare($sql_unread);
    $stmt_unread->execute([':member_id' => $member_id]);
    $unread_count = $stmt_unread->fetch(PDO::FETCH_ASSOC)['unread'];

    $sql_notifications = "SELECT message, notification_date FROM notifications WHERE member_id = :member_id ORDER BY notification_date DESC LIMIT 3";
    $stmt_notifications = $conn->prepare($sql_notifications);
    $stmt_notifications->execute([':member_id' => $member_id]);
    $notifications = $stmt_notifications->fetchAll(PDO::FETCH_ASSOC);

    // Mark notifications as read if any unread
    if ($unread_count > 0) {
        $sql_mark_read = "UPDATE notifications SET is_read = 'Yes' WHERE member_id = :member_id";
        $stmt_mark_read = $conn->prepare($sql_mark_read);
        $stmt_mark_read->execute([':member_id' => $member_id]);
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Savings & Credit</title>
    <link rel="icon" type="image/jpeg" href="images/background4.jpg">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Montserrat:wght@700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Reset and basic styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f7fa;
            color: #333;
            overflow-x: hidden;
        }
        /* New Header Styling */
        .top-header {
            background: linear-gradient(135deg, #2c3e50, #34495e); /* Gradient background matching sidebar */
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }
        .top-header .logo {
            display: flex;
            align-items: center;
            background: #fff; /* White background for the logo */
            padding: 5px;
            border-radius: 5px;
        }
        .top-header .logo img {
            height: 60px;
            width: auto;
            transition: transform 0.3s ease;
        }
        .top-header .logo img:hover {
            transform: scale(1.1); /* Slight scale-up on hover */
        }
        .top-header .organization-title {
            color: #fff;
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            animation: fadeIn 2s ease-in-out; /* Fade-in animation */
        }
        .top-header .organization-title::after {
            content: '';
            display: block;
            width: 60px;
            height: 3px;
            background: #fff;
            margin: 5px auto 0;
            border-radius: 2px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            animation: slideIn 2s ease-in-out; /* Slide-in animation */
        }
        .top-header .toggle-btn {
            background: #fff;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        .top-header .toggle-btn i {
            color: #2c3e50; /* Match the gradient color */
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }
        .top-header .toggle-btn:hover {
            background: #e0e0e0;
            transform: rotate(90deg);
        }
        .top-header .toggle-btn:hover i {
            transform: rotate(-90deg);
        }
        /* Sidebar styling */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #2c3e50, #1a252f);
            height: calc(100vh - 90px); /* Adjust height to account for top header */
            position: fixed;
            top: 90px; /* Start below the top header */
            left: 0;
            padding: 30px 20px;
            transition: all 0.3s ease;
            overflow-y: auto;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }
        .sidebar .profile {
            text-align: center;
            margin-bottom: 40px;
        }
        .sidebar .profile img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ecf0f1;
            margin-bottom: 10px;
            transition: transform 0.3s ease;
        }
        .sidebar .profile img:hover {
            transform: scale(1.05);
        }
        .sidebar .profile h3 {
            font-size: 1.4rem;
            color: #ecf0f1;
            margin-bottom: 5px;
        }
        .sidebar .profile p {
            font-size: 0.9rem;
            color: #bdc3c7;
        }
        .sidebar a {
            display: block;
            color: #ecf0f1;
            text-decoration: none;
            padding: 12px 20px;
            margin-bottom: 10px;
            border-radius: 5px;
            transition: all 0.3s ease;
            font-size: 1rem;
            position: relative;
            overflow: hidden;
        }
        .sidebar a:hover {
            background: #34495e;
            transform: translateX(5px);
        }
        .sidebar a:hover::before {
            width: 100%;
        }
        .sidebar a::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transition: width 0.3s ease;
            z-index: 0;
        }
        .sidebar a * {
            position: relative;
            z-index: 1;
        }
        .sidebar a.active {
            background: #34495e;
            font-weight: bold;
        }
        /* Main content styling */
        .main-content {
            margin-left: 260px;
            padding: 40px;
            margin-top: 90px; /* Account for the fixed top header */
            transition: all 0.3s ease;
        }
        .header {
            display: flex;
            justify-content: flex-start; /* Adjusted since toggle button is now in top-header */
            align-items: center;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 2.2rem;
            color: #2c3e50;
        }
        /* Dashboard grid styling */
        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }
        .card {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }
        .card h3 {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        .card p {
            font-size: 1.1rem;
            color: #7f8c8d;
            margin-bottom: 15px;
        }
        .card.balance .amount {
            font-size: 2.5rem;
            font-weight: bold;
            color: #27ae60; /* Green color for balance */
            margin-bottom: 10px;
        }
        .card .list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .card .list li {
            padding: 10px 0;
            border-bottom: 1px solid #ecf0f1;
            font-size: 0.95rem;
            color: #555;
            animation: fadeInUp 0.5s ease-in-out;
        }
        .card .list li:last-child {
            border-bottom: none;
        }
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .top-header {
                height: 70px;
                padding: 0 15px;
            }
            .top-header .logo img {
                height: 45px;
            }
            .top-header .organization-title {
                font-size: 1.3rem;
            }
            .top-header .organization-title::after {
                width: 40px;
                height: 2px;
            }
            .top-header .toggle-btn {
                width: 35px;
                height: 35px;
            }
            .top-header .toggle-btn i {
                font-size: 1rem;
            }
            .sidebar {
                top: 70px;
                height: calc(100vh - 70px);
                transform: translateX(-260px);
            }
            .sidebar.visible {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
                margin-top: 70px;
            }
            .header h1 {
                font-size: 1.8rem;
            }
        }
        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes slideIn {
            from {
                width: 0;
            }
            to {
                width: 60px;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <!-- Top Header -->
    <div class="top-header">
        <div class="logo">
            <img src="images/background4.jpg" alt="Werabe Hospital Logo">
        </div>
        <div class="organization-title">
            Werabe Comprehensive and Specialized Hospital
        </div>
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="profile">
            <img src="images/<?php echo htmlspecialchars($member['profile_image'] ?: 'default-profile.jpg'); ?>" alt="Profile">
            <h3><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h3>
            <p><?php echo htmlspecialchars($member['email']); ?></p>
        </div>
        <?php $active_page = basename($_SERVER['PHP_SELF'], '.php'); ?>
        <a href="dashboard.php" class="<?php echo $active_page === 'dashboard' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="profile.php" class="<?php echo $active_page === 'profile' ? 'active' : ''; ?>"><i class="fas fa-user"></i> Profile</a>
        <a href="view_balance.php" class="<?php echo $active_page === 'view_balance' ? 'active' : ''; ?>"><i class="fas fa-wallet"></i> View Balance</a>
        <a href="deposit.php" class="<?php echo $active_page === 'deposit' ? 'active' : ''; ?>"><i class="fas fa-money-bill"></i> Deposit</a>
        <a href="loan_request.php" class="<?php echo $active_page === 'loan_request' ? 'active' : ''; ?>"><i class="fas fa-hand-holding-usd"></i> Request Loan</a>
        <a href="customer_repay_loan.php" class="<?php echo $active_page === 'customer_repay_loan' ? 'active' : ''; ?>"><i class="fas fa-money-bill-wave"></i> View Loan Status</a>
        <a href="cancel_request.php" class="<?php echo $active_page === 'cancel_request' ? 'active' : ''; ?>"><i class="fas fa-times-circle"></i> Cancel Request</a>
        <a href="view_responses.php" class="<?php echo $active_page === 'view_responses' ? 'active' : ''; ?>"><i class="fas fa-comment-alt"></i> View Loan Responses</a>
        <a href="withdraw_request.php" class="<?php echo $active_page === 'withdraw_request' ? 'active' : ''; ?>"><i class="fas fa-money-check-alt"></i> Withdraw</a>
        <a href="cust_transfer_money.php" class="<?php echo $active_page === 'transfer_money' ? 'active' : ''; ?>"><i class="fas fa-exchange-alt"></i> Transfer Money</a>
        <a href="notifications.php" class="<?php echo $active_page === 'notifications' ? 'active' : ''; ?>"><i class="fas fa-bell"></i> Notifications</a>
        <a href="surety_requests.php" class="<?php echo $active_page === 'surety_requests' ? 'active' : ''; ?>"><i class="fas fa-user-shield"></i> Surety Requests</a>
        <a href="messages.php" class="<?php echo $active_page === 'messages' ? 'active' : ''; ?>"><i class="fas fa-comment-dots"></i> Messages</a>
        <a href="logout.php" class="<?php echo $active_page === 'logout' ? 'active' : ''; ?>"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <div class="header">
            <h1>Welcome, <?php echo htmlspecialchars($member['first_name']); ?>!</h1>
        </div>
        <div class="dashboard">
            <!-- Account Balance Card -->
            <div class="card balance">
                <h3>Account Balance</h3>
                <p class="amount"><?php echo number_format($member['account_balance'], 2); ?> ETB</p>
                <p>Manage your savings effortlessly.</p>
            </div>
            <!-- Recent Transactions Card -->
            <div class="card">
                <h3>Recent Transactions</h3>
                <?php if (empty($transactions)): ?>
                    <p>No recent transactions.</p>
                <?php else: ?>
                    <ul class="list">
                        <?php foreach ($transactions as $transaction): ?>
                            <li>
                                <?php echo htmlspecialchars($transaction['transaction_type']); ?> - <?php echo number_format($transaction['amount'], 2); ?> ETB<br>
                                <small><?php echo date('Y-m-d H:i', strtotime($transaction['transaction_date'])); ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <!-- Recent Notifications Card -->
            <div class="card">
                <h3>Recent Notifications <span>(<?php echo $unread_count; ?> unread)</span></h3>
                <?php if (empty($notifications)): ?>
                    <p>No notifications.</p>
                <?php else: ?>
                    <ul class="list">
                        <?php foreach ($notifications as $notification): ?>
                            <li>
                                <?php echo htmlspecialchars($notification['message']); ?><br>
                                <small><?php echo date('Y-m-d H:i', strtotime($notification['notification_date'])); ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            sidebar.classList.toggle('visible');
            if (sidebar.classList.contains('visible')) {
                sidebar.style.transform = 'translateX(0)';
                mainContent.style.marginLeft = '260px';
            } else {
                sidebar.style.transform = 'translateX(-260px)';
                mainContent.style.marginLeft = '0';
            }
        }
    </script>
</body>
</html>
