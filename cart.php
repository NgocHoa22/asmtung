<?php
// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id'])) {
    echo "<div class='alert alert-warning'>Vui lòng đăng nhập trước!</div>";
    header("Location: index.php");
    exit();
}

include 'config.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION['user_id'];
$cart_msg = '';
$cart_id = null;

// Lấy danh sách sản phẩm
$products = [];
if (isset($connect) && $connect) {
    $sql = "SELECT id, name FROM products";
    $result = mysqli_query($connect, $sql);
    if ($result) {
        $products = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_free_result($result);
    } else {
        $cart_msg = "<div class='alert alert-danger'>Lỗi lấy danh sách sản phẩm: " . mysqli_error($connect) . "</div>";
    }
}

// Lấy hoặc tạo giỏ hàng
if (isset($connect) && $connect) {
    $sql = "SELECT id FROM carts WHERE user_id = ?";
    $stmt = mysqli_prepare($connect, $sql);
    if ($stmt === false) {
        $cart_msg = "<div class='alert alert-danger'>Lỗi chuẩn bị truy vấn carts: " . mysqli_error($connect) . "</div>";
    } else {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (!mysqli_stmt_execute($stmt)) {
            $cart_msg = "<div class='alert alert-danger'>Lỗi thực thi truy vấn carts: " . mysqli_error($connect) . "</div>";
        } else {
            $result = mysqli_stmt_get_result($stmt);
            $cart = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($cart) {
                $cart_id = $cart['id'];
                echo "<div class='alert alert-info'>Giỏ hàng đã được khởi tạo.</div>";
            } else {
                $sql = "INSERT INTO carts (user_id) VALUES (?)";
                $stmt = mysqli_prepare($connect, $sql);
                if ($stmt === false) {
                    $cart_msg = "<div class='alert alert-danger'>Lỗi chuẩn bị tạo carts: " . mysqli_error($connect) . "</div>";
                } else {
                    mysqli_stmt_bind_param($stmt, "i", $user_id);
                    if (!mysqli_stmt_execute($stmt)) {
                        $cart_msg = "<div class='alert alert-danger'>Lỗi tạo giỏ hàng: " . mysqli_error($connect) . "</div>";
                    } else {
                        $cart_id = mysqli_insert_id($connect);
                        echo "<div class='alert alert-success'>Giỏ hàng mới ID $cart_id đã được tạo.</div>";
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}

function addToCart($connect, $cart_id, $csrf_token) {
    global $cart_msg;
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_to_cart']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $csrf_token && $connect && $cart_id) {
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
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return true;
    }
    return false;
}

// Gọi hàm add_to_cart nếu có yêu cầu
if (isset($connect) && $connect && $cart_id) {
    addToCart($connect, $cart_id, $_SESSION['csrf_token']);
}

// Update cart
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_cart']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token'] && isset($connect) && $connect && $cart_id) {
    $quantities = $_POST['quantity'] ?? [];
    foreach ($quantities as $item_id => $quantity) {
        $quantity = intval($quantity);
        $item_id = intval($item_id);
        $sql = $quantity <= 0 ? "DELETE FROM cart_items WHERE id = ? AND cart_id = ?" : "UPDATE cart_items SET quantity = ? WHERE id = ? AND cart_id = ?";
        $stmt = mysqli_prepare($connect, $sql);
        if ($stmt) {
            if ($quantity <= 0) {
                mysqli_stmt_bind_param($stmt, "ii", $item_id, $cart_id);
            } else {
                mysqli_stmt_bind_param($stmt, "iii", $quantity, $item_id, $cart_id);
            }
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    $cart_msg = "<div class='alert alert-success'>Cập nhật giỏ hàng thành công!</div>";
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Remove item
if (isset($_GET['remove_item']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token'] && isset($connect) && $connect && $cart_id) {
    $item_id = intval($_GET['remove_item']);
    $sql = "DELETE FROM cart_items WHERE id = ? AND cart_id = ?";
    $stmt = mysqli_prepare($connect, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $item_id, $cart_id);
        if (mysqli_stmt_execute($stmt)) {
            $cart_msg = "<div class='alert alert-success'>Xóa sản phẩm thành công!</div>";
        } else {
            $cart_msg = "<div class='alert alert-danger'>Lỗi xóa sản phẩm: " . mysqli_error($connect) . "</div>";
        }
        mysqli_stmt_close($stmt);
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Place order with transaction support
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['place_order']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token'] && isset($connect) && $connect && $cart_id) {
    $shipping_address = mysqli_real_escape_string($connect, $_POST['shipping_address']);
    $payment_method = isset($_POST['payment_method']) ? mysqli_real_escape_string($connect, $_POST['payment_method']) : 'COD';

    if (empty($shipping_address)) {
        $cart_msg = "<div class='alert alert-danger'>Vui lòng nhập địa chỉ giao hàng!</div>";
    } else {
        mysqli_begin_transaction($connect);
        try {
            $sql = "SELECT ci.id, ci.quantity, p.id as product_id, p.price, p.name 
                    FROM cart_items ci 
                    JOIN products p ON ci.product_id = p.id 
                    WHERE ci.cart_id = ?";
            $stmt = mysqli_prepare($connect, $sql);
            if ($stmt === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn cart items: " . mysqli_error($connect));
            }
            mysqli_stmt_bind_param($stmt, "i", $cart_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $items = mysqli_fetch_all($result, MYSQLI_ASSOC);
            mysqli_stmt_close($stmt);

            if (empty($items)) {
                throw new Exception("Giỏ hàng trống!");
            }

            $total_amount = 0;
            foreach ($items as $item) {
                $total_amount += $item['quantity'] * $item['price'];
            }

            $sql = "INSERT INTO orders (user_id, total_amount, status, shipping_address) VALUES (?, ?, 'pending', ?)";
            $stmt = mysqli_prepare($connect, $sql);
            if ($stmt === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn orders: " . mysqli_error($connect));
            }
            mysqli_stmt_bind_param($stmt, "ids", $user_id, $total_amount, $shipping_address);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Lỗi tạo đơn hàng: " . mysqli_error($connect));
            }
            $order_id = mysqli_insert_id($connect);
            mysqli_stmt_close($stmt);

            foreach ($items as $item) {
                $sql = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
                $stmt = mysqli_prepare($connect, $sql);
                if ($stmt === false) {
                    throw new Exception("Lỗi chuẩn bị truy vấn order items: " . mysqli_error($connect));
                }
                mysqli_stmt_bind_param($stmt, "iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Lỗi thêm mục đơn hàng: " . mysqli_error($connect));
                }
                mysqli_stmt_close($stmt);
            }

            $sql = "DELETE FROM cart_items WHERE cart_id = ?";
            $stmt = mysqli_prepare($connect, $sql);
            if ($stmt === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn xóa cart items: " . mysqli_error($connect));
            }
            mysqli_stmt_bind_param($stmt, "i", $cart_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Lỗi xóa giỏ hàng: " . mysqli_error($connect));
            }
            mysqli_stmt_close($stmt);

            mysqli_commit($connect);
            $cart_msg = "<div class='alert alert-success'>Đặt hàng thành công! Mã đơn: #$order_id</div>";
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            mysqli_rollback($connect);
            $cart_msg = "<div class='alert alert-danger'>{$e->getMessage()}</div>";
            error_log("Order placement failed: " . $e->getMessage());
        }
    }
}

// Get cart items
$cart_items = [];
if (isset($connect) && $connect && $cart_id) {
    $sql = "SELECT ci.id, ci.quantity, p.id as product_id, p.name, p.price, p.image 
            FROM cart_items ci 
            JOIN products p ON ci.product_id = p.id 
            WHERE ci.cart_id = ?";
    $stmt = mysqli_prepare($connect, $sql);
    if ($stmt === false) {
        $cart_msg = "<div class='alert alert-danger'>Lỗi chuẩn bị truy vấn cart_items: " . mysqli_error($connect) . "</div>";
    } else {
        mysqli_stmt_bind_param($stmt, "i", $cart_id);
        if (!mysqli_stmt_execute($stmt)) {
            $cart_msg = "<div class='alert alert-danger'>Lỗi thực thi truy vấn cart_items: " . mysqli_error($connect) . "</div>";
        } else {
            $result = mysqli_stmt_get_result($stmt);
            $cart_items = mysqli_fetch_all($result, MYSQLI_ASSOC);
        }
        mysqli_stmt_close($stmt);
    }
}

$total_amount = 0;
foreach ($cart_items as $item) {
    $total_amount += $item['quantity'] * $item['price'];
}

$sql = "SELECT full_name FROM users WHERE id = ?";
$stmt = mysqli_prepare($connect, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
$_SESSION['full_name'] = $user['full_name'] ?? 'Người dùng';
mysqli_stmt_close($stmt);

if (isset($connect)) mysqli_close($connect);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ Hàng - BTEC Sweet Shop</title>
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
            flex: 1;
            padding: 25px 0;
            display: flex;
            gap: 20px;
        }
        .main-content {
            flex: 1;
        }
        .cart-section {
            background-color: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .cart-section h2 {
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        .cart-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            background-color: #fff;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .cart-item img {
            max-width: 100px;
            height: auto;
            margin-right: 15px;
            border-radius: 4px;
        }
        .cart-item-details {
            flex-grow: 1;
        }
        .cart-item-details h5 {
            margin: 0 0 5px;
            color: var(--secondary-color);
        }
        .cart-item-details p {
            margin: 0;
        }
        .cart-item-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        .cart-item-actions input {
            width: 60px;
            margin-bottom: 5px;
            border-radius: 8px;
        }
        .cart-item-actions .btn {
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 20px;
        }
        .btn-primary, .btn-success, .btn-danger {
            border-radius: 20px;
        }
        .form-control, .form-select, .form-check-input {
            border-radius: 8px;
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
        .menu-links {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .menu-links a {
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
        .menu-links a:hover {
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
                    <a href="banbanh.php"><img src="https://cdn.haitrieu.com/wp-content/uploads/2023/02/Logo-Truong-cao-dang-Quoc-te-BTEC-FPT.png" alt="BTEC Sweet Shop"></a>
                </div>
                <form class="form-search d-flex" action="product.php" method="GET" role="search">
                    <input type="text" name="search" placeholder="Tìm kiếm bánh kẹo..." class="form-control" aria-label="Tìm kiếm sản phẩm">
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
                        <li><a class="dropdown-item" href="account.php">Hồ Sơ</a></li>
                        <li><a class="dropdown-item" href="account.php#orders">Đơn Hàng</a></li>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <li><a class="dropdown-item" href="manage_products.php">Quản Lý Sản Phẩm</a></li>
                            <li><a class="dropdown-item" href="manage_users.php">Quản Lý Người Dùng</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">Đăng Xuất</a></li>
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
                                <a href="product.php?search=bánh">
                                    <img src="https://www.dacsanbanhpia.com/wp-content/uploads/2022/12/banh-keo-nhat-ban-15.jpg" class="d-block w-100" alt="Quảng cáo Bánh">
                                </a>
                            </div>
                            <div class="carousel-item">
                                <a href="product.php?search=kẹo">
                                    <img src="https://img.lazcdn.com/g/p/487c3ceb8c9e78f0722080643b2722a8.png_720x720q80.png_.webp" class="d-block w-100" alt="Quảng cáo Kẹo">
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
                <div class="cart-section">
                    <h2>Giỏ Hàng của <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Người dùng'); ?></h2>
                    <?php echo $cart_msg; ?>
                    <?php if (empty($cart_items)): ?>
                        <p class="text-center">Giỏ hàng của bạn đang trống.</p>
                    <?php else: ?>
                        <form action="" method="POST">
                            <input type="hidden" name="update_cart" value="1">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="row">
                                <?php foreach ($cart_items as $item): ?>
                                    <div class="col-lg-4 col-md-6 mb-4">
                                        <div class="cart-item">
                                            <img src="<?php echo htmlspecialchars($item['image'] ?? 'images/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                            <div class="cart-item-details">
                                                <h5><?php echo htmlspecialchars($item['name']); ?></h5>
                                                <p>Giá: <?php echo number_format($item['price'], 0, ',', '.'); ?>đ</p>
                                                <p>Tổng: <?php echo number_format($item['quantity'] * $item['price'], 0, ',', '.'); ?>đ</p>
                                            </div>
                                            <div class="cart-item-actions">
                                                <input type="number" name="quantity[<?php echo $item['id']; ?>]" value="<?php echo $item['quantity']; ?>" min="1" class="form-control mb-2">
                                                <a href="cart.php?remove_item=<?php echo $item['id']; ?>&csrf_token=<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bạn có chắc chắn muốn xóa?');">Xóa</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-end mt-3">
                                <button type="submit" class="btn btn-primary">Cập Nhật Giỏ Hàng</button>
                            </div>
                        </form>
                        <div class="mt-4">
                            <h4>Tổng Cộng: <?php echo number_format($total_amount, 0, ',', '.'); ?>đ</h4>
                            <form action="" method="POST">
                                <input type="hidden" name="place_order" value="1">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <div class="mb-3">
                                    <label for="shipping_address" class="form-label">Địa Chỉ Giao Hàng</label>
                                    <textarea class="form-control" id="shipping_address" name="shipping_address" rows="3" required></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Phương Thức Thanh Toán</label><br>
                                    <div class="form-check">
                                        <input type="radio" name="payment_method" value="COD" class="form-check-input" id="payment_cod" checked>
                                        <label class="form-check-label" for="payment_cod">Thanh toán khi nhận hàng (COD)</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="payment_method" value="Wallet" class="form-check-input" id="payment_wallet">
                                        <label class="form-check-label" for="payment_wallet">Thanh toán bằng ví</label>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success">Đặt Hàng</button>
                            </form>
                        </div>
                    <?php endif; ?>
                    <form method="POST" class="mt-4">
                        <input type="hidden" name="add_to_cart" value="1">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="input-group mb-3" style="max-width: 300px;">
                            <select name="product_id" class="form-select" required>
                                <option value="">Chọn sản phẩm</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo htmlspecialchars($product['id']); ?>">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary">Thêm Sản Phẩm</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <footer class="footer">
            <div class="container">
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <h5>Giới Thiệu</h5>
                        <p>BTEC Sweet Shop mang đến những loại bánh kẹo ngon, chất lượng cao, lan tỏa niềm vui ngọt ngào cho mọi nhà.</p>
                    </div>
                    <div class="col-md-4 mb-4">
                        <h5>Liên Hệ</h5>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-map-marker-alt me-2"></i>406 Xuân Phương</li>
                            <li><i class="fas fa-phone me-2"></i>0899133869</li>
                            <li><i class="fas fa-envelope me-2"></i>hoa2282005hhh@gmail.com</li>
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