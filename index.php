<?php
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
    // Select the database
    $conn->select_db($dbname);
    // Create tables
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
    if (isset($_POST['register'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        $sql = "INSERT INTO users (name, email, password, role) VALUES ('$name', '$email', '$password', '$role')";
        $conn->query($sql);
        header("Location: index.php?page=login");
    } elseif (isset($_POST['login'])) {
        $email = $_POST['email'];
        $password = $_POST['password'];
        $sql = "SELECT * FROM users WHERE email='$email'";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                session_start();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                header("Location: index.php?page=home");
            }
        }
    } elseif (isset($_POST['add_house'])) {
        session_start();
        $title = $_POST['title'];
        $description = $_POST['description'];
        $city = $_POST['city'];
        $price = $_POST['price'];
        $rooms = $_POST['rooms'];
        $image = $_POST['image'];
        $owner_id = $_SESSION['user_id'];
        $sql = "INSERT INTO houses (title, description, city, price, rooms, image, owner_id) VALUES ('$title', '$description', '$city', $price, $rooms, '$image', $owner_id)";
        $conn->query($sql);
        header("Location: index.php?page=home");
    } elseif (isset($_POST['book'])) {
        session_start();
        $house_id = $_POST['house_id'];
        $user_id = $_SESSION['user_id'];
        $sql = "INSERT INTO bookings (house_id, user_id) VALUES ($house_id, $user_id)";
        $conn->query($sql);
        header("Location: index.php?page=house&id=$house_id");
    } elseif (isset($_POST['approve'])) {
        $id = $_POST['id'];
        $sql = "UPDATE bookings SET status='approved' WHERE id=$id";
        $conn->query($sql);
        header("Location: index.php?page=admin");
    } elseif (isset($_POST['delete'])) {
        $id = $_POST['id'];
        $table = $_POST['table'];
        $sql = "DELETE FROM $table WHERE id=$id";
        $conn->query($sql);
        header("Location: index.php?page=admin");
    }
}

// Get page
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
$id = isset($_GET['id']) ? $_GET['id'] : null;

// Fetch data
if ($page == 'home') {
    $sql = "SELECT * FROM houses";
    $houses = $conn->query($sql);
} elseif ($page == 'house' && $id) {
    $sql = "SELECT * FROM houses WHERE id=$id";
    $house = $conn->query($sql)->fetch_assoc();
} elseif ($page == 'admin') {
    $users = $conn->query("SELECT * FROM users");
    $houses = $conn->query("SELECT * FROM houses");
    $bookings = $conn->query("SELECT * FROM bookings");
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>كراء منازل</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-dark: #3a56d4;
            --secondary-color: #2563eb;
            --accent-color: #f72585;
            --success-color: #4cc9f0;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --light-gray: #e9ecef;
            --card-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Cairo', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            min-height: 100vh;
            color: var(--dark-color);
            line-height: 1.6;
            direction: rtl;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 20px 0;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo i {
            font-size: 2.5rem;
            color: var(--accent-color);
        }

        .logo h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(to right, #fff, #f8f9fa);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .nav {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .nav a {
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .nav a:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .nav a.active {
            background: var(--accent-color);
            color: white;
        }

        .main-content {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }

        h1 {
            color: var(--primary-color);
            margin-bottom: 25px;
            font-size: 2.2rem;
            position: relative;
            padding-bottom: 15px;
        }

        h1::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 100px;
            height: 4px;
            background: linear-gradient(to left, var(--primary-color), var(--accent-color));
            border-radius: 2px;
        }

        h2 {
            color: var(--secondary-color);
            margin: 30px 0 20px 0;
            font-size: 1.8rem;
            position: relative;
            padding-right: 15px;
        }

        h2::before {
            content: '';
            position: absolute;
            right: 0;
            top: 5px;
            height: 70%;
            width: 5px;
            background: var(--accent-color);
            border-radius: 5px;
        }

        .filter-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-color);
        }

        .form-control {
            padding: 12px 15px;
            border: 2px solid var(--light-gray);
            border-radius: 10px;
            font-size: 1rem;
            transition: var(--transition);
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(to right, var(--primary-color), var(--primary-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 15px rgba(67, 97, 238, 0.3);
        }

        .btn-success {
            background: linear-gradient(to right, #20c997, #1ba87e);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(to right, #f72585, #e01e75);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(to right, #ff9e00, #ff9100);
            color: white;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 15px rgba(0, 0, 0, 0.1);
        }

        .houses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .house-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            position: relative;
            border: 1px solid var(--light-gray);
        }

        .house-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .house-img {
            width: 100%;
            height: 220px;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .house-card:hover .house-img {
            transform: scale(1.05);
        }

        .house-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--accent-color);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .house-content {
            padding: 20px;
        }

        .house-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--dark-color);
        }

        .house-details {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }

        .house-detail {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--gray-color);
        }

        .house-detail i {
            color: var(--primary-color);
        }

        .house-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 10px 0;
        }

        .house-description {
            color: var(--gray-color);
            margin-bottom: 20px;
            line-height: 1.7;
        }

        .form-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 600px;
            margin: 0 auto;
            box-shadow: var(--card-shadow);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-full {
            margin-bottom: 20px;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .data-table th {
            background: linear-gradient(to right, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 15px;
            text-align: right;
            font-weight: 600;
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        .data-table tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ff9800;
        }

        .status-approved {
            background: rgba(76, 201, 240, 0.2);
            color: #20c997;
        }

        .status-rejected {
            background: rgba(247, 37, 133, 0.2);
            color: #f72585;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .footer {
            text-align: center;
            padding: 20px;
            margin-top: 40px;
            color: var(--gray-color);
            border-top: 1px solid var(--light-gray);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(76, 201, 240, 0.1);
            border-right: 4px solid var(--success-color);
            color: #0a7a8c;
        }

        .alert-warning {
            background: rgba(255, 193, 7, 0.1);
            border-right: 4px solid #ffc107;
            color: #b28704;
        }

        .alert-danger {
            background: rgba(247, 37, 133, 0.1);
            border-right: 4px solid var(--accent-color);
            color: #c2185b;
        }

        @media (max-width: 992px) {
            .header-content {
                flex-direction: column;
                gap: 20px;
            }
            
            .nav {
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .main-content {
                padding: 20px;
            }
            
            .houses-grid {
                grid-template-columns: 1fr;
            }
            
            .nav a {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
            
            h1 {
                font-size: 1.8rem;
            }
        }

        .link {
            text-align: center;
            margin-top: 20px;
        }

        .link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }

        .link a:hover {
            color: var(--accent-color);
            text-decoration: underline;
        }

        .house-detail-page {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        @media (max-width: 768px) {
            .house-detail-page {
                grid-template-columns: 1fr;
            }
        }

        .house-detail-img {
            width: 100%;
            border-radius: 15px;
            height: 400px;
            object-fit: cover;
        }

        .detail-info {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--light-gray);
        }

        .info-label {
            font-weight: 600;
            color: var(--dark-color);
        }

        .info-value {
            color: var(--primary-color);
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-home"></i>
                <h1>كراء منازل</h1>
            </div>
            <div class="nav">
                <a href="index.php?page=home" class="<?php echo $page == 'home' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> الرئيسية
                </a>
                <a href="index.php?page=login" class="<?php echo $page == 'login' ? 'active' : ''; ?>">
                    <i class="fas fa-sign-in-alt"></i> الدخول
                </a>
                <a href="index.php?page=register" class="<?php echo $page == 'register' ? 'active' : ''; ?>">
                    <i class="fas fa-user-plus"></i> التسجيل
                </a>
                <a href="index.php?page=add_house" class="<?php echo $page == 'add_house' ? 'active' : ''; ?>">
                    <i class="fas fa-plus-circle"></i> إضافة منزل
                </a>
                <a href="index.php?page=admin" class="<?php echo $page == 'admin' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i> الإدارة
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($page == 'home'): ?>
            <div class="main-content">
                <h1><i class="fas fa-search"></i> البحث عن منزل</h1>
                
                <div class="filter-section">
                    <form method="GET" class="filter-form">
                        <input type="hidden" name="page" value="home">
                        <div class="form-group">
                            <label for="city"><i class="fas fa-city"></i> المدينة</label>
                            <input type="text" id="city" name="city" class="form-control" placeholder="المدينة" value="<?php echo isset($_GET['city']) ? $_GET['city'] : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="price"><i class="fas fa-money-bill-wave"></i> السعر الأقصى</label>
                            <input type="number" id="price" name="price" class="form-control" placeholder="السعر الأقصى" value="<?php echo isset($_GET['price']) ? $_GET['price'] : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="rooms"><i class="fas fa-door-closed"></i> عدد الغرف</label>
                            <input type="number" id="rooms" name="rooms" class="form-control" placeholder="عدد الغرف" value="<?php echo isset($_GET['rooms']) ? $_GET['rooms'] : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> بحث
                            </button>
                        </div>
                    </form>
                </div>

                <h2>المنازل المتاحة</h2>
                
                <div class="houses-grid">
                    <?php while ($row = $houses->fetch_assoc()): ?>
                        <div class="house-card">
                            <div class="house-badge">للإيجار</div>
                            <img src="<?php echo $row['image']; ?>" alt="<?php echo $row['title']; ?>" class="house-img">
                            <div class="house-content">
                                <h3 class="house-title"><?php echo $row['title']; ?></h3>
                                
                                <div class="house-details">
                                    <div class="house-detail">
                                        <i class="fas fa-city"></i>
                                        <span><?php echo $row['city']; ?></span>
                                    </div>
                                    <div class="house-detail">
                                        <i class="fas fa-door-closed"></i>
                                        <span><?php echo $row['rooms']; ?> غرف</span>
                                    </div>
                                </div>
                                
                                <div class="house-price"><?php echo $row['price']; ?> درهم</div>
                                
                                <p class="house-description"><?php echo substr($row['description'], 0, 100); ?>...</p>
                                
                                <a href="index.php?page=house&id=<?php echo $row['id']; ?>" class="btn btn-primary" style="width:100%; text-align:center;">
                                    <i class="fas fa-eye"></i> عرض التفاصيل
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

        <?php elseif ($page == 'login'): ?>
            <div class="main-content">
                <div class="form-card">
                    <h1><i class="fas fa-sign-in-alt"></i> الدخول</h1>
                    
                    <form method="POST">
                        <div class="form-full">
                            <label for="email" class="form-label">البريد الإلكتروني</label>
                            <input type="email" id="email" name="email" class="form-control" placeholder="example@email.com" required>
                        </div>
                        
                        <div class="form-full">
                            <label for="password" class="form-label">كلمة المرور</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="********" required>
                        </div>
                        
                        <button type="submit" name="login" class="btn btn-primary" style="width:100%; margin-top:20px;">
                            <i class="fas fa-sign-in-alt"></i> دخول
                        </button>
                    </form>
                    
                    <div class="link">
                        <a href="index.php?page=register">
                            <i class="fas fa-user-plus"></i> ليس لديك حساب؟ سجل الآن
                        </a>
                    </div>
                </div>
            </div>

        <?php elseif ($page == 'register'): ?>
            <div class="main-content">
                <div class="form-card">
                    <h1><i class="fas fa-user-plus"></i> التسجيل</h1>
                    
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name" class="form-label">الاسم الكامل</label>
                                <input type="text" id="name" name="name" class="form-control" placeholder="الاسم الكامل" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="form-label">البريد الإلكتروني</label>
                                <input type="email" id="email" name="email" class="form-control" placeholder="example@email.com" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="password" class="form-label">كلمة المرور</label>
                                <input type="password" id="password" name="password" class="form-control" placeholder="********" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="role" class="form-label">الدور</label>
                                <select id="role" name="role" class="form-control" required>
                                    <option value="user">مستأجر</option>
                                    <option value="owner">مالك منزل</option>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" name="register" class="btn btn-primary" style="width:100%; margin-top:20px;">
                            <i class="fas fa-user-plus"></i> تسجيل
                        </button>
                    </form>
                    
                    <div class="link">
                        <a href="index.php?page=login">
                            <i class="fas fa-sign-in-alt"></i> لديك حساب؟ الدخول الآن
                        </a>
                    </div>
                </div>
            </div>

        <?php elseif ($page == 'add_house'): ?>
            <div class="main-content">
                <div class="form-card">
                    <h1><i class="fas fa-plus-circle"></i> إضافة منزل جديد</h1>
                    
                    <form method="POST">
                        <div class="form-full">
                            <label for="title" class="form-label">عنوان المنزل</label>
                            <input type="text" id="title" name="title" class="form-control" placeholder="عنوان المنزل" required>
                        </div>
                        
                        <div class="form-full">
                            <label for="description" class="form-label">وصف المنزل</label>
                            <textarea id="description" name="description" class="form-control" rows="4" placeholder="وصف تفصيلي للمنزل" required></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="city" class="form-label">المدينة</label>
                                <input type="text" id="city" name="city" class="form-control" placeholder="المدينة" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="price" class="form-label">السعر (درهم)</label>
                                <input type="number" id="price" name="price" class="form-control" placeholder="السعر" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="rooms" class="form-label">عدد الغرف</label>
                                <input type="number" id="rooms" name="rooms" class="form-control" placeholder="عدد الغرف" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="image" class="form-label">رابط الصورة</label>
                                <input type="text" id="image" name="image" class="form-control" placeholder="https://example.com/image.jpg" required>
                            </div>
                        </div>
                        
                        <button type="submit" name="add_house" class="btn btn-primary" style="width:100%; margin-top:20px;">
                            <i class="fas fa-plus-circle"></i> إضافة المنزل
                        </button>
                    </form>
                </div>
            </div>

        <?php elseif ($page == 'house' && $house): ?>
            <div class="main-content">
                <div class="house-detail-page">
                    <div>
                        <img src="<?php echo $house['image']; ?>" alt="<?php echo $house['title']; ?>" class="house-detail-img">
                    </div>
                    
                    <div class="detail-info">
                        <h1><?php echo $house['title']; ?></h1>
                        
                        <div class="info-item">
                            <span class="info-label">المدينة:</span>
                            <span class="info-value"><?php echo $house['city']; ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">السعر:</span>
                            <span class="info-value"><?php echo $house['price']; ?> درهم/شهر</span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">عدد الغرف:</span>
                            <span class="info-value"><?php echo $house['rooms']; ?> غرف</span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">الوصف:</span>
                            <span class="info-value"><?php echo $house['description']; ?></span>
                        </div>
                        
                        <form method="POST" style="margin-top: 30px;">
                            <input type="hidden" name="house_id" value="<?php echo $house['id']; ?>">
                            <button type="submit" name="book" class="btn btn-primary" style="width:100%; padding:15px;">
                                <i class="fas fa-calendar-check"></i> طلب الحجز
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        <?php elseif ($page == 'admin'): ?>
            <div class="main-content">
                <h1><i class="fas fa-cog"></i> لوحة الإدارة</h1>
                
                <h2><i class="fas fa-users"></i> المستخدمون</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>الاسم</th>
                                <th>البريد</th>
                                <th>الدور</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo $row['name']; ?></td>
                                    <td><?php echo $row['email']; ?></td>
                                    <td>
                                        <?php if ($row['role'] == 'admin'): ?>
                                            <span class="status-badge status-approved">مدير</span>
                                        <?php elseif ($row['role'] == 'owner'): ?>
                                            <span class="status-badge status-pending">مالك</span>
                                        <?php else: ?>
                                            <span class="status-badge">مستخدم</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="table" value="users">
                                                <button type="submit" name="delete" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا المستخدم؟')">
                                                    <i class="fas fa-trash"></i> حذف
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <h2><i class="fas fa-home"></i> المنازل</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>العنوان</th>
                                <th>المدينة</th>
                                <th>السعر</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $houses->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo $row['title']; ?></td>
                                    <td><?php echo $row['city']; ?></td>
                                    <td><?php echo $row['price']; ?> درهم</td>
                                    <td>
                                        <div class="action-buttons">
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="table" value="houses">
                                                <button type="submit" name="delete" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا المنزل؟')">
                                                    <i class="fas fa-trash"></i> حذف
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <h2><i class="fas fa-calendar-alt"></i> الحجوزات</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>رقم المنزل</th>
                                <th>رقم المستخدم</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $bookings->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo $row['house_id']; ?></td>
                                    <td><?php echo $row['user_id']; ?></td>
                                    <td>
                                        <?php if ($row['status'] == 'approved'): ?>
                                            <span class="status-badge status-approved">مقبول</span>
                                        <?php elseif ($row['status'] == 'rejected'): ?>
                                            <span class="status-badge status-rejected">مرفوض</span>
                                        <?php else: ?>
                                            <span class="status-badge status-pending">قيد الانتظار</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($row['status'] == 'pending'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                    <button type="submit" name="approve" class="btn btn-success">
                                                        <i class="fas fa-check"></i> قبول
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="table" value="bookings">
                                                <button type="submit" name="delete" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذه الحجز؟')">
                                                    <i class="fas fa-trash"></i> حذف
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>© 2023 نظام كراء المنازل. جميع الحقوق محفوظة.</p>
        </div>
    </div>

    <script>
        // JavaScript for interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Add active class to current page link
            const currentPage = '<?php echo $page; ?>';
            const navLinks = document.querySelectorAll('.nav a');
            
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href.includes(`page=${currentPage}`)) {
                    link.classList.add('active');
                }
            });
            
            // Filter form submission with validation
            const filterForm = document.querySelector('.filter-form');
            if (filterForm) {
                filterForm.addEventListener('submit', function(e) {
                    // Simple validation
                    const city = document.getElementById('city').value;
                    const price = document.getElementById('price').value;
                    
                    if (price && price < 0) {
                        e.preventDefault();
                        alert('السعر يجب أن يكون رقم موجب');
                    }
                });
            }
            
            // House card hover effects
            const houseCards = document.querySelectorAll('.house-card');
            houseCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-10px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Confirm before delete actions
            const deleteButtons = document.querySelectorAll('button[name="delete"]');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('هل أنت متأكد من الحذف؟ لا يمكن التراجع عن هذا الإجراء.')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>