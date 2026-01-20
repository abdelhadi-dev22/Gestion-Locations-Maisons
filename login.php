<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rent_houses";

$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    $conn->select_db($dbname);
    $tables = "
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('user', 'owner', 'admin') DEFAULT 'user'
    );
    CREATE TABLE IF NOT EXISTS houses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        city VARCHAR(255),
        price DECIMAL(10,2),
        rooms INT,
        image VARCHAR(255),
        owner_id INT,
        FOREIGN KEY (owner_id) REFERENCES users(id)
    );
    CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        house_id INT,
        user_id INT,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        FOREIGN KEY (house_id) REFERENCES houses(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    );
    ";
    if ($conn->multi_query($tables)) {
        do {
            // Consume results
        } while ($conn->next_result());
    }
} else {
    die("Error creating database: " . $conn->error);
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            header("Location: home.php");
            exit();
        }
    }
    $error = "بيانات الدخول غير صحيحة";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>كراء منازل - الدخول</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
            animation: fadeIn 1s ease-in-out;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .container {
            max-width: 550px;
            width: 100%;
            margin: 20px;
            background: rgba(255, 255, 255, 0.95);
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            animation: slideUp 0.8s ease-out;
        }
        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
            background: linear-gradient(45deg, #3498db, #2980b9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        input {
            padding: 15px;
            border: 2px solid #bdc3c7;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }
        input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 10px rgba(52, 152, 219, 0.5);
            transform: translateY(-2px);
        }
        button {
            background: linear-gradient(45deg, #27ae60, #229954);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
            font-weight: 500;
            box-shadow: 0 4px 8px rgba(39, 174, 96, 0.3);
        }
        button:hover {
            background: linear-gradient(45deg, #229954, #1e8449);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(39, 174, 96, 0.4);
        }
        .link {
            text-align: center;
            margin-top: 20px;
        }
        .link a {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .link a:hover {
            color: #2980b9;
            text-decoration: underline;
        }
        .error {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 20px;
            font-weight: 500;
        }
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 20px;
            }
            h1 {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>الدخول</h1>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="email" name="email" placeholder="البريد الإلكتروني" required>
            <input type="password" name="password" placeholder="كلمة المرور" required>
            <button type="submit">دخول</button>
        </form>
        <div class="link">
            <a href="register.php">ليس لديك حساب؟ سجل</a>
        </div>
    </div>
</body>
</html>
