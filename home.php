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

// Fetch houses
$sql = "SELECT * FROM houses";
$houses = $conn->query($sql);

// Fetch markers for map
$markers = [];
$houses_sql = "SELECT * FROM houses WHERE latitude IS NOT NULL AND longitude IS NOT NULL";
$houses_result = $conn->query($houses_sql);
while ($house_marker = $houses_result->fetch_assoc()) {
    $markers[] = $house_marker;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>كراء منازل - الرئيسية</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%, #f093fb 50%, #f5576c 100%);
            min-height: 100vh;
            color: #333;
            animation: fadeIn 1.5s ease-in-out;
            overflow-x: hidden;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px);
            animation: slideUp 1s ease-out;
            position: relative;
        }
        .container::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, #667eea, #764ba2, #f093fb, #f5576c);
            border-radius: 22px;
            z-index: -1;
            opacity: 0.1;
        }
        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
            font-size: 3em;
            text-shadow: 3px 3px 6px rgba(0,0,0,0.2);
            background: linear-gradient(45deg, #3498db, #2980b9, #e74c3c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: textGlow 2s ease-in-out infinite alternate;
        }
        @keyframes textGlow {
            from { filter: brightness(1); }
            to { filter: brightness(1.2); }
        }
        .houses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        .house-card {
            border: none;
            padding: 25px;
            border-radius: 15px;
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1), 0 0 0 1px rgba(255, 255, 255, 0.5);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            overflow: hidden;
            position: relative;
            animation: cardFadeIn 0.6s ease-out forwards;
            opacity: 0;
        }
        .house-card:nth-child(1) { animation-delay: 0.1s; }
        .house-card:nth-child(2) { animation-delay: 0.2s; }
        .house-card:nth-child(3) { animation-delay: 0.3s; }
        .house-card:nth-child(4) { animation-delay: 0.4s; }
        .house-card:nth-child(5) { animation-delay: 0.5s; }
        .house-card:nth-child(6) { animation-delay: 0.6s; }
        @keyframes cardFadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .house-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(45deg, #27ae60, #229954, #f39c12);
            transition: height 0.3s ease;
        }
        .house-card:hover::before {
            height: 6px;
        }
        .house-card:hover {
            transform: translateY(-15px) scale(1.03);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.7);
        }
        .house-card img {
            width: 100%;
            height: 220px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 20px;
            transition: transform 0.4s ease, filter 0.3s ease;
            filter: brightness(1);
        }
        .house-card:hover img {
            transform: scale(1.08) rotate(1deg);
            filter: brightness(1.1) saturate(1.2);
        }
        .house-card h3 {
            color: #2c3e50;
            margin: 0 0 15px 0;
            font-size: 1.4em;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        .house-card:hover h3 {
            color: #3498db;
        }
        .house-card p {
            margin: 8px 0;
            color: #7f8c8d;
            font-size: 1.1em;
            transition: color 0.3s ease;
        }
        .house-card:hover p {
            color: #34495e;
        }
        .house-card a {
            display: inline-block;
            background: linear-gradient(45deg, #3498db, #2980b9, #e74c3c);
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 30px;
            transition: all 0.3s ease;
            margin-top: 15px;
            font-weight: 500;
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
            position: relative;
            overflow: hidden;
        }
        .house-card a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        .house-card a:hover::before {
            left: 100%;
        }
        .house-card a:hover {
            background: linear-gradient(45deg, #2980b9, #21618c, #c0392b);
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(52, 152, 219, 0.5);
        }
        .filter {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 40px;
            background: rgba(255, 255, 255, 0.9);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1), inset 0 1px 0 rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(15px);
            animation: filterSlideIn 0.8s ease-out;
        }
        @keyframes filterSlideIn {
            from { opacity: 0; transform: translateX(-30px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .filter input, .filter select {
            padding: 15px;
            border: 2px solid #bdc3c7;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            flex: 1;
            min-width: 180px;
            background: rgba(255, 255, 255, 0.8);
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .filter input:focus, .filter select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 15px rgba(52, 152, 219, 0.5), inset 0 2px 4px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 1);
        }
        .filter button {
            background: linear-gradient(45deg, #e74c3c, #c0392b, #f39c12);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
            font-weight: 500;
            box-shadow: 0 4px 8px rgba(231, 76, 60, 0.3);
            position: relative;
            overflow: hidden;
        }
        .filter button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s ease;
        }
        .filter button:hover::before {
            left: 100%;
        }
        .filter button:hover {
            background: linear-gradient(45deg, #c0392b, #a93226, #e67e22);
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 8px 16px rgba(231, 76, 60, 0.5);
        }
        .nav {
            text-align: center;
            margin-bottom: 40px;
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1), 0 0 0 1px rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(15px);
            animation: navFadeIn 0.8s ease-out;
        }
        @keyframes navFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .nav a {
            margin: 0 20px;
            text-decoration: none;
            color: #3498db;
            font-weight: bold;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        .nav a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, #3498db, #2980b9, #e74c3c);
            transition: left 0.4s ease;
            z-index: -1;
        }
        .nav a:hover::before {
            left: 0;
        }
        .nav a:hover {
            color: white;
            transform: translateY(-3px) scale(1.05);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        #map {
            height: 400px;
            margin: 30px 0;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1), inset 0 0 0 1px rgba(255, 255, 255, 0.5);
            animation: mapZoomIn 1s ease-out;
        }
        @keyframes mapZoomIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 20px;
            }
            .houses-grid {
                grid-template-columns: 1fr;
            }
            .filter {
                flex-direction: column;
            }
            .nav a {
                display: block;
                margin: 5px 0;
            }
            h1 {
                font-size: 2.2em;
            }
            .house-card {
                padding: 20px;
            }
            #map {
                height: 300px;
            }
        }
        @media (max-width: 480px) {
            h1 {
                font-size: 1.8em;
            }
            .container {
                padding: 15px;
            }
            .house-card {
                padding: 15px;
            }
            .filter input, .filter select {
                min-width: 100%;
            }
        }
    </style>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize map
            var map = L.map('map').setView([31.7917, -7.0926], 6); // Center on Morocco

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            // Add markers for houses with coordinates
            <?php
            foreach ($markers as $house_marker) {
                echo "L.marker([" . $house_marker['latitude'] . ", " . $house_marker['longitude'] . "]).addTo(map).bindPopup('<b>" . addslashes($house_marker['title']) . "</b><br>" . addslashes($house_marker['city']) . "<br><a href=\"house.php?id=" . $house_marker['id'] . "\">عرض التفاصيل</a>');";
            }
            ?>

            // City autocomplete
            const cities = [
                'الرباط', 'الدار البيضاء', 'مراكش', 'فاس', 'طنجة', 'أكادير', 'مكناس', 'وجدة',
                'الجديدة', 'تطوان', 'الصويرة', 'القنيطرة', 'خنيفرة', 'بني ملال', 'المحمدية',
                'تمارة', 'سلا', 'العيون', 'الداخلة', 'كلميم', 'ورزازات', 'الراشيدية', 'تازة',
                'شفشاون', 'الحسيمة', 'الناظور', 'بركان', 'تاوريرت', 'جرسيف', 'الفنيدق',
                'الصخيرات', 'سيدي قاسم', 'سيدي سليمان', 'القصر الكبير', 'سيدي بنور', 'اليوسفية',
                'أزيلال', 'ميدلت', 'تنغير', 'زاكورة', 'طاطا', 'كرسيف', 'بوجدور', 'لعيون'
            ];

            const cityInput = document.getElementById('city-input');
            let currentFocus = -1;

            cityInput.addEventListener('input', function() {
                const val = this.value;
                closeAllLists();
                if (!val) return false;
                currentFocus = -1;

                const listContainer = document.createElement('div');
                listContainer.setAttribute('id', this.id + '-autocomplete-list');
                listContainer.setAttribute('class', 'autocomplete-items');
                this.parentNode.appendChild(listContainer);

                for (let i = 0; i < cities.length; i++) {
                    if (cities[i].substr(0, val.length).toUpperCase() == val.toUpperCase()) {
                        const item = document.createElement('div');
                        item.innerHTML = '<strong>' + cities[i].substr(0, val.length) + '</strong>' + cities[i].substr(val.length);
                        item.innerHTML += '<input type="hidden" value="' + cities[i] + '">';
                        item.addEventListener('click', function() {
                            cityInput.value = this.getElementsByTagName('input')[0].value;
                            closeAllLists();
                        });
                        listContainer.appendChild(item);
                    }
                }
            });

            cityInput.addEventListener('keydown', function(e) {
                let list = document.getElementById(this.id + '-autocomplete-list');
                if (list) list = list.getElementsByTagName('div');
                if (e.keyCode == 40) { // Down arrow
                    currentFocus++;
                    addActive(list);
                } else if (e.keyCode == 38) { // Up arrow
                    currentFocus--;
                    addActive(list);
                } else if (e.keyCode == 13) { // Enter
                    e.preventDefault();
                    if (currentFocus > -1 && list) list[currentFocus].click();
                }
            });

            function addActive(list) {
                if (!list) return false;
                removeActive(list);
                if (currentFocus >= list.length) currentFocus = 0;
                if (currentFocus < 0) currentFocus = list.length - 1;
                list[currentFocus].classList.add('autocomplete-active');
            }

            function removeActive(list) {
                for (let i = 0; i < list.length; i++) {
                    list[i].classList.remove('autocomplete-active');
                }
            }

            function closeAllLists(elmnt) {
                const items = document.getElementsByClassName('autocomplete-items');
                for (let i = 0; i < items.length; i++) {
                    if (elmnt != items[i] && elmnt != cityInput) {
                        items[i].parentNode.removeChild(items[i]);
                    }
                }
            }

            document.addEventListener('click', function(e) {
                closeAllLists(e.target);
            });
        });
    </script>
    <style>
        .autocomplete-items {
            position: absolute;
            border: 1px solid #d4d4d4;
            border-bottom: none;
            border-top: none;
            z-index: 99;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            max-height: 200px;
            overflow-y: auto;
        }
        .autocomplete-items div {
            padding: 10px;
            cursor: pointer;
            background-color: #fff;
            border-bottom: 1px solid #d4d4d4;
        }
        .autocomplete-items div:hover {
            background-color: #e9e9e9;
        }
        .autocomplete-active {
            background-color: #3498db !important;
            color: #ffffff !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="home.php">الرئيسية</a>
            <a href="login.php">الدخول</a>
            <a href="register.php">التسجيل</a>
            <a href="add_house.php">إضافة منزل</a>
            <a href="admin.php">الإدارة</a>
        </div>

        <h1>البحث عن منزل</h1>
        <form method="GET" class="filter">
            <input type="text" name="city" placeholder="المدينة" value="<?php echo isset($_GET['city']) ? $_GET['city'] : ''; ?>" id="city-input">
            <input type="text" name="district" placeholder="الحي" value="<?php echo isset($_GET['district']) ? $_GET['district'] : ''; ?>">
            <input type="number" name="price_min" placeholder="السعر الأدنى" value="<?php echo isset($_GET['price_min']) ? $_GET['price_min'] : ''; ?>">
            <input type="number" name="price_max" placeholder="السعر الأقصى" value="<?php echo isset($_GET['price_max']) ? $_GET['price_max'] : ''; ?>">
            <input type="number" name="rooms" placeholder="عدد الغرف" value="<?php echo isset($_GET['rooms']) ? $_GET['rooms'] : ''; ?>">
            <input type="number" name="area" placeholder="المساحة (م²)" value="<?php echo isset($_GET['area']) ? $_GET['area'] : ''; ?>">
            <select name="property_type">
                <option value="">نوع العقار</option>
                <option value="apartment" <?php echo (isset($_GET['property_type']) && $_GET['property_type'] == 'apartment') ? 'selected' : ''; ?>>شقة</option>
                <option value="villa" <?php echo (isset($_GET['property_type']) && $_GET['property_type'] == 'villa') ? 'selected' : ''; ?>>فيلا</option>
                <option value="studio" <?php echo (isset($_GET['property_type']) && $_GET['property_type'] == 'studio') ? 'selected' : ''; ?>>ستوديو</option>
                <option value="house" <?php echo (isset($_GET['property_type']) && $_GET['property_type'] == 'house') ? 'selected' : ''; ?>>منزل</option>
            </select>
            <button type="submit">بحث</button>
        </form>

        <div id="map" style="height: 400px; margin: 30px 0; border-radius: 15px; box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);"></div>
        <div class="houses-grid">
            <?php while ($row = $houses->fetch_assoc()): ?>
                <div class="house-card">
                    <img src="<?php echo $row['image']; ?>" alt="<?php echo $row['title']; ?>">
                    <h3><?php echo $row['title']; ?></h3>
                    <p><?php echo $row['city']; ?></p>
                    <p><?php echo $row['price']; ?> DH</p>
                    <p><?php echo $row['rooms']; ?> غرف</p>
                    <a href="house.php?id=<?php echo $row['id']; ?>">عرض التفاصيل</a>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>
