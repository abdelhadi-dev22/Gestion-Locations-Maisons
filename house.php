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
        role ENUM('user', 'owner', 'admin') DEFAULT 'user',
        phone VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS houses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        city VARCHAR(255),
        district VARCHAR(255),
        price DECIMAL(10,2),
        rooms INT,
        area DECIMAL(8,2),
        property_type ENUM('apartment', 'villa', 'studio', 'house') DEFAULT 'apartment',
        image VARCHAR(255),
        latitude DECIMAL(10,8),
        longitude DECIMAL(11,8),
        owner_id INT,
        views INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (owner_id) REFERENCES users(id)
    );
    CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        house_id INT,
        user_id INT,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (house_id) REFERENCES houses(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    );
    CREATE TABLE IF NOT EXISTS favorites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        house_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (house_id) REFERENCES houses(id),
        UNIQUE KEY unique_favorite (user_id, house_id)
    );
    CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        house_id INT,
        user_id INT,
        rating INT CHECK (rating >= 1 AND rating <= 5),
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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

// Get house ID
$id = isset($_GET['id']) ? $_GET['id'] : null;
if (!$id) {
    header("Location: home.php");
    exit();
}

// Fetch house
$sql = "SELECT * FROM houses WHERE id=$id";
$house = $conn->query($sql)->fetch_assoc();
if (!$house) {
    header("Location: home.php");
    exit();
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    if (isset($_POST['house_id'])) {
        $house_id = $_POST['house_id'];
        $sql = "INSERT INTO bookings (house_id, user_id) VALUES ($house_id, $user_id)";
        $conn->query($sql);
        $success = "تم إرسال طلب الحجز بنجاح";
    } elseif (isset($_POST['favorite'])) {
        $house_id = $_POST['house_id'];
        $sql = "INSERT IGNORE INTO favorites (user_id, house_id) VALUES ($user_id, $house_id)";
        $conn->query($sql);
        $success = "تم إضافة المنزل للمفضلة";
    } elseif (isset($_POST['review'])) {
        $house_id = $_POST['house_id'];
        $rating = $_POST['rating'];
        $comment = $_POST['comment'];
        $sql = "INSERT INTO reviews (house_id, user_id, rating, comment) VALUES ($house_id, $user_id, $rating, '$comment')";
        $conn->query($sql);
        $success = "تم إرسال التقييم بنجاح";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>كراء منازل - <?php echo $house['title']; ?></title>
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
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            animation: slideUp 0.8s ease-out;
        }
        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .nav {
            text-align: center;
            margin-bottom: 30px;
            background: rgba(255, 255, 255, 0.95);
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }
        .nav a {
            margin: 0 15px;
            text-decoration: none;
            color: #3498db;
            font-weight: bold;
            padding: 8px 16px;
            border-radius: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .nav a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, #3498db, #2980b9);
            transition: left 0.3s ease;
            z-index: -1;
        }
        .nav a:hover::before {
            left: 0;
        }
        .nav a:hover {
            color: white;
            transform: translateY(-2px);
        }
        .house-image {
            width: 100%;
            max-width: 600px;
            height: 400px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
            background: linear-gradient(45deg, #3498db, #2980b9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .house-details {
            margin-bottom: 30px;
        }
        .house-details p {
            margin: 15px 0;
            font-size: 1.2em;
            color: #555;
        }
        .house-details .price {
            font-size: 1.5em;
            font-weight: bold;
            color: #27ae60;
        }
        form {
            text-align: center;
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
        .success {
            color: #27ae60;
            text-align: center;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .login-required {
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
            .house-image {
                height: 250px;
            }
            h1 {
                font-size: 2em;
            }
            .nav a {
                display: block;
                margin: 5px 0;
            }
        }
    </style>
    <script>
        function shareHouse() {
            if (navigator.share) {
                navigator.share({
                    title: '<?php echo $house['title']; ?>',
                    text: 'شاهد هذا المنزل المعروض للإيجار',
                    url: window.location.href
                });
            } else {
                // Fallback for browsers that don't support Web Share API
                const url = window.location.href;
                navigator.clipboard.writeText(url).then(() => {
                    alert('تم نسخ رابط المنزل إلى الحافظة');
                });
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="home.php">الرئيسية</a>
            <a href="login.php">الدخول</a>
            <a href="register.php">التسجيل</a>
        </div>

        <img src="<?php echo $house['image']; ?>" alt="<?php echo $house['title']; ?>" class="house-image">
        <h1><?php echo $house['title']; ?></h1>
        <div class="house-details">
            <p><?php echo $house['description']; ?></p>
            <p class="price">السعر: <?php echo $house['price']; ?> DH</p>
            <p>المدينة: <?php echo $house['city']; ?></p>
            <p>عدد الغرف: <?php echo $house['rooms']; ?></p>
        </div>

        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id'])): ?>
            <form method="POST">
                <input type="hidden" name="house_id" value="<?php echo $house['id']; ?>">
                <button type="submit">طلب الحجز</button>
            </form>
        <?php else: ?>
            <div class="login-required">يجب تسجيل الدخول لطلب الحجز</div>
            <a href="login.php" style="display: inline-block; margin-top: 10px;">
                <button type="button">الدخول</button>
            </a>
        <?php endif; ?>

        <div class="reviews-section" style="margin-top: 50px;">
            <h2 style="color: #2c3e50; text-align: center; margin-bottom: 30px;">التقييمات والمراجعات</h2>

            <?php
            $conn = new mysqli($servername, $username, $password, $dbname);
            $reviews_sql = "SELECT r.*, u.name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.house_id = $id ORDER BY r.created_at DESC";
            $reviews = $conn->query($reviews_sql);
            $conn->close();
            ?>

            <?php if ($reviews->num_rows > 0): ?>
                <?php while ($review = $reviews->fetch_assoc()): ?>
                    <div class="review" style="border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 10px; background: #f9f9f9;">
                        <div class="review-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <strong><?php echo $review['name']; ?></strong>
                            <div class="rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span style="color: <?php echo $i <= $review['rating'] ? '#f39c12' : '#ddd'; ?>;">★</span>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <p><?php echo $review['comment']; ?></p>
                        <small style="color: #7f8c8d;"><?php echo date('Y-m-d H:i', strtotime($review['created_at'])); ?></small>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; color: #7f8c8d;">لا توجد تقييمات بعد</p>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="add-review" style="margin-top: 40px; padding: 20px; background: rgba(255, 255, 255, 0.9); border-radius: 10px;">
                    <h3 style="color: #2c3e50; text-align: center; margin-bottom: 20px;">أضف تقييمك</h3>
                    <form method="POST">
                        <input type="hidden" name="house_id" value="<?php echo $house['id']; ?>">
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px;">التقييم:</label>
                            <select name="rating" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                <option value="">اختر التقييم</option>
                                <option value="5">5 نجوم</option>
                                <option value="4">4 نجوم</option>
                                <option value="3">3 نجوم</option>
                                <option value="2">2 نجوم</option>
                                <option value="1">1 نجمة</option>
                            </select>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px;">التعليق:</label>
                            <textarea name="comment" rows="4" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"></textarea>
                        </div>
                        <button type="submit" name="review" style="background: linear-gradient(45deg, #3498db, #2980b9); color: white; border: none; padding: 12px 25px; border-radius: 5px; cursor: pointer;">إرسال التقييم</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
