<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';

// Lấy thông tin vai trò và họ tên người dùng
$user_id = $_SESSION['user_id'];
$sql = "SELECT role, full_name FROM users WHERE id = ?";
$stmt = mysqli_prepare($connect, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($user = mysqli_fetch_assoc($result)) {
    $_SESSION['role'] = $user['role'] ?? 'user';
    $_SESSION['full_name'] = $user['full_name'] ?? 'Người dùng';
} else {
    echo "<div class='alert alert-danger'>Không tìm thấy thông tin người dùng. Vui lòng kiểm tra cơ sở dữ liệu.</div>";
    $_SESSION['role'] = 'user';
    $_SESSION['full_name'] = 'Người dùng';
}
mysqli_stmt_close($stmt);

// Lấy danh sách sản phẩm với bộ lọc tìm kiếm và danh mục
$search = isset($_GET['search']) ? mysqli_real_escape_string($connect, $_GET['search']) : '';
$loai = isset($_GET['loai']) ? mysqli_real_escape_string($connect, $_GET['loai']) : '';
$sql = "SELECT * FROM products WHERE (name LIKE ? OR description LIKE ?)";
$params = ["%$search%", "%$search%"];
$types = "ss";

if ($loai && in_array($loai, ['shirt', 'pants', 'dress'])) {
    $sql .= " AND loai_banh_keo = ?";
    $params[] = $loai;
    $types .= "s";
}

$stmt = mysqli_prepare($connect, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$products = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Lấy 3 sản phẩm nổi bật (mới nhất)
$sql = "SELECT id, name, price, image, loai_banh_keo FROM products ORDER BY id DESC LIMIT 3";
$result = mysqli_query($connect, $sql);
$featured_products = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Xử lý thêm sản phẩm vào giỏ hàng
$cart_msg = '';
$cart_id = null;
if (isset($connect) && $connect) {
    $sql = "SELECT id FROM carts WHERE user_id = ?";
    $stmt = mysqli_prepare($connect, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            $cart = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($cart) {
                $cart_id = $cart['id'];
            } else {
                $sql = "INSERT INTO carts (user_id) VALUES (?)";
                $stmt = mysqli_prepare($connect, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "i", $user_id);
                    if (mysqli_stmt_execute($stmt)) {
                        $cart_id = mysqli_insert_id($connect);
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_to_cart']) && isset($_POST['product_id']) && $cart_id) {
        $product_id = intval($_POST['product_id']);
        $sql = "SELECT id FROM cart_items WHERE cart_id = ? AND product_id = ?";
        $stmt = mysqli_prepare($connect, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $cart_id, $product_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if (mysqli_num_rows($result) > 0) {
                $sql = "UPDATE cart_items SET quantity = quantity + 1 WHERE cart_id = ? AND product_id = ?";
                $stmt = mysqli_prepare($connect, $sql);
                mysqli_stmt_bind_param($stmt, "ii", $cart_id, $product_id);
            } else {
                $sql = "INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, 1)";
                $stmt = mysqli_prepare($connect, $sql);
                mysqli_stmt_bind_param($stmt, "ii", $cart_id, $product_id);
            }
            if ($stmt && mysqli_stmt_execute($stmt)) {
                $cart_msg = "<div class='alert alert-success'>Thêm vào giỏ hàng thành công!</div>";
            } else {
                $cart_msg = "<div class='alert alert-danger'>Lỗi thêm sản phẩm: " . mysqli_error($connect) . "</div>";
            }
            mysqli_stmt_close($stmt);
        }
    }
}
if (isset($connect)) mysqli_close($connect);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sản Phẩm - BTEC Sweet Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4A90E2;
            --secondary-color: #4B5563;
            --accent-color: #FEF3C7;
            --background-color: #FFF7ED;
            --hover-color: #3B82F6;
        }
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background: url('https://images.unsplash.com/photo-1600585154340-be6161a56a0c') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
        }
        .wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: rgba(255, 247, 237, 0.95);
        }
        .header {
            background: linear-gradient(90deg, #ffffff, var(--accent-color));
            padding: 12px 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .logo img {
            height: 50px;
            margin-left: 20px;
            transition: transform 0.3s ease;
        }
        .logo img:hover {
            transform: scale(1.1);
        }
        .form-search {
            max-width: 450px;
            flex-grow: 1;
            margin: 0 20px;
        }
        .form-search input[type="text"] {
            border-radius: 20px 0 0 20px;
            padding: 10px 15px;
            font-size: 14px;
            border: 1px solid #d1d5db;
            transition: border-color 0.3s ease;
        }
        .form-search input[type="text"]:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        .form-search button {
            border-radius: 0 20px 20px 0;
            padding: 10px 15px;
            background-color: var(--primary-color);
            border: none;
            color: white;
            transition: background-color 0.3s ease;
        }
        .form-search button:hover {
            background-color: var(--hover-color);
        }
        .icon-cart img, .icon-user img {
            height: 30px;
            width: 30px;
            margin: 0 12px;
            transition: transform 0.3s ease;
        }
        .icon-cart img:hover, .icon-user img:hover {
            transform: scale(1.2);
        }
        .user-name {
            font-size: 14px;
            font-weight: 500;
            color: var(--secondary-color);
            margin-left: 8px;
        }
        .user-dropdown .dropdown-toggle::after {
            display: none;
        }
        .content {
            padding: 25px 0;
            display: flex;
            gap: 20px;
        }
        .main-content {
            flex: 1;
        }
        .product-grid {
            background-color: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .product-grid h3 {
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        .product-card {
            background-color: #fff;
            border: none;
            border-radius: 12px;
            text-align: center;
            padding: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .product-card:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }
        .product-card img {
            height: 200px;
            width: 100%;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 12px;
        }
        .product-card h5 {
            font-size: 16px;
            color: var(--secondary-color);
            margin: 10px 0 6px;
        }
        .product-card p {
            font-size: 14px;
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 12px;
        }
        .product-card .detail-link {
            display: block;
            margin-bottom: 10px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        .product-card .detail-link:hover {
            color: var(--hover-color);
            text-decoration: underline;
        }
        .product-card button {
            padding: 10px 20px;
            font-size: 14px;
            background-color: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 20px;
            transition: background-color 0.3s ease;
        }
        .product-card button:hover {
            background-color: var(--hover-color);
        }
        .category-filter .form-select {
            border-radius: 8px;
            max-width: 200px;
        }
        .sidebar {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        .sidebar .carousel-inner img {
            border-radius: 8px;
            object-fit: cover;
            height: 200px;
        }
        .sidebar .carousel-indicators {
            bottom: -40px;
        }
        .sidebar .carousel-indicators button {
            background-color: var(--secondary-color);
        }
        .sidebar .carousel-indicators .active {
            background-color: var(--primary-color);
        }
        .sidebar .carousel-control-prev, .sidebar .carousel-control-next {
            width: 10%;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
        }
        .sidebar .menu-links {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .sidebar .menu-links a {
            display: block;
            padding: 10px 15px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }
        .sidebar .menu-links a:hover {
            background-color: var(--hover-color);
        }
        .footer {
            background: linear-gradient(90deg, var(--primary-color), var(--hover-color));
            color: #fff;
            padding: 50px 0;
            margin-top: auto;
        }
        .footer a {
            color: #fff;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .footer a:hover {
            color: var(--accent-color);
        }
        .footer .social-icons a {
            font-size: 22px;
            margin: 0 12px;
        }
        .newsletter-form input[type="email"] {
            border-radius: 20px 0 0 20px;
            padding: 10px 15px;
            font-size: 14px;
            border: none;
        }
        .newsletter-form button {
            border-radius: 0 20px 20px 0;
            padding: 10px 15px;
            background-color: var(--hover-color);
            border: none;
            color: #fff;
            transition: background-color 0.3s ease;
        }
        .newsletter-form button:hover {
            background-color: #2563EB;
        }
        @media (max-width: 991px) {
            .content {
                flex-direction: column;
            }
            .sidebar {
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <header class="header">
            <div class="container-fluid d-flex align-items-center justify-content-between">
                <div class="logo">
                    <a href="banbanh.php"><img src="https://via.placeholder.com/100x50?text=Fashion+Shop" alt="Fashion Shop"></a>
                </div>
                <form class="form-search d-flex" action="product.php" method="GET" role="search">
                    <input type="text" name="search" placeholder="" class="form-control" value="<?php echo htmlspecialchars($search); ?>" aria-label="Tìm kiếm sản phẩm">
                    <button type="submit" class="btn" aria-label="Tìm kiếm"><i class="fas fa-search"></i></button>
                </form>
                <div class="icon-cart">
                    <a href="cart.php" aria-label="Giỏ hàng"><img src="https://cdn-icons-png.flaticon.com/512/3144/3144456.png" alt="Cart"></a>
                </div>
                <div class="icon-user dropdown d-flex align-items-center">
                    <a href="account.php" class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Tài khoản" aria-haspopup="true">
                        <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="User">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="account.php">Profile</a></li>
                        <li><a class="dropdown-item" href="account.php#orders">Order</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="index.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>
        <div class="content container-fluid">
            <div class="col-lg-3 col-md-12">
                <div class="sidebar">
                    <div id="adCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="3000">
                        <div class="carousel-indicators">
                            <button type="button" data-bs-target="#adCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                            <button type="button" data-bs-target="#adCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                            <button type="button" data-bs-target="#adCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
                        </div>
                        <div class="carousel-inner">
                            <div class="carousel-item active">
                                <a href="product.php?search=Shirt">
                                    <img src="https://th.bing.com/th/id/R.19ccea47ddd13dd49bb467a4eea6dfda?rik=q1eS8io5cAyzzw&pid=ImgRaw&r=0" class="d-block w-100" alt="Quảng cáo Áo">
                                </a>
                            </div>
                            <div class="carousel-item">
                                <a href="product.php?search=Pants">
                                    <img src="https://tse3.mm.bing.net/th/id/OIP.JVHRIeVqtQBopyfA7t10bgHaFw?cb=thfvnext&rs=1&pid=ImgDetMain&o=7&rm=3" class="d-block w-100" alt="Quảng cáo Quần">
                                </a>
                            </div>
                            <div class="carousel-item">
                                <a href="cart.php">
                                    <img src="https://tse1.mm.bing.net/th/id/OIP.w1OKGpd2UNBfcefs8tVP3wHaFj?cb=thfvnext&rs=1&pid=ImgDetMain&o=7&rm=3" class="d-block w-100" alt="Ưu đãi Giỏ hàng">
                                </a>
                            </div>
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#adCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#adCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                    <div class="menu-links">
                        <a href="product.php">List Product</a>
                        <a href="cart.php">Cart</a>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <a href="manage_products.php">Manage Products</a>
                            <a href="manage_users.php">Manage Users</a>
                            <a href="manage_cart.php">Manage Carts</a>
                        <?php endif; ?>
                        
                        <a href="logout.php">Logout</a>
                    </div>
                </div>
            </div>
            <div class="main-content col-lg-9 col-md-12">
                <div class="product-grid">
                    <h3>Sản Phẩm</h3>
                    <form class="category-filter" method="GET" action="product.php">
                        <div class="input-group mb-3">
                            <select name="loai" class="form-select" onchange="this.form.submit()">
                                <option value="" <?php echo $loai === '' ? 'selected' : ''; ?>>Tất cả</option>
                                <option value="shirt" <?php echo $loai === 'shirt' ? 'selected' : ''; ?>>Áo</option>
                                <option value="pants" <?php echo $loai === 'pants' ? 'selected' : ''; ?>>Quần</option>
                                <option value="dress" <?php echo $loai === 'dress' ? 'selected' : ''; ?>>Váy</option>
                            </select>
                            <?php if ($search): ?>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <?php endif; ?>
                        </div>
                    </form>
                    <?php if (!empty($cart_msg)) echo $cart_msg; ?>
                    <?php if (empty($products)): ?>
                        <div class="alert alert-info">Không tìm thấy sản phẩm nào.</div>
                    <?php endif; ?>
                    <div class="row">
                        <?php foreach ($products as $product): ?>
                            <div class="col-lg-4 col-md-6 col-sm-6 mb-4">
                                <div class="card product-card">
                                    <img src="<?php echo htmlspecialchars($product['image'] ?? 'images/placeholder.jpg'); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                        <p class="card-text"><?php echo number_format($product['price'], 0, ',', '.'); ?>đ</p>
                                        <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="detail-link">Xem chi tiết</a>
                                        <form action="" method="POST">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <button type="submit" name="add_to_cart" class="btn btn-primary w-100">Thêm vào giỏ</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <footer class="footer">
            <div class="container">
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <h5>Giới Thiệu</h5>
                        <p>Cập nhật những thông tin mới nhất về ưu đãi, thời trang và phong cách sống.</p>
                    </div>
                    <div class="col-md-4 mb-4">
                        <h5>Liên Hệ</h5>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-map-marker-alt me-2"></i>Xuân Phương</li>
                            <li><i class="fas fa-phone me-2"></i>099999999</li>
                            <li><i class="fas fa-envelope me-2"></i>abc@gmail.com</li>
                        </ul>
                    </div>
                    <div class="col-md-4 mb-4">
                        <h5>Đăng Ký Bản Tin</h5>
                        <form class="newsletter-form d-flex">
                            <input type="email" placeholder="Nhập email của bạn..." class="form-control" aria-label="Email đăng ký bản tin" required>
                            <button type="submit" class="btn">Đăng Ký</button>
                        </form>
                        <h5 class="mt-4">Theo Dõi Chúng Tôi</h5>
                        <div class="social-icons">
                            <a href="https://www.facebook.com/hoa082005" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                            <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                            <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <p>© 2025 BTEC Sweet Shop. All Rights Reserved.</p>
                </div>
            </div>
        </footer>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>