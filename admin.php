<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$db = "seo01_sdlc";
$connect = mysqli_connect($servername, $username, $password, $db);

if (!$connect) {
    die("Kết nối CSDL thất bại: " . mysqli_connect_error());
}

$success_msg = '';
$error_msg = '';

// Handle user deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    if ($user_id == $_SESSION['user_id']) {
        $error_msg = "<div class='alert alert-danger'>Không thể xóa tài khoản của chính bạn!</div>";
    } else {
        $sql = "DELETE FROM users WHERE id = ? AND role != 'admin'"; // Prevent deleting admin accounts
        $stmt = mysqli_prepare($connect, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "<div class='alert alert-success'>Xóa tài khoản thành công!</div>";
        } else {
            $error_msg = "<div class='alert alert-danger'>Xóa tài khoản thất bại: " . mysqli_error($connect) . "</div>";
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle role update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_role'])) {
    $user_id = $_POST['user_id'];
    $new_role = mysqli_real_escape_string($connect, $_POST['role']);
    if ($user_id == $_SESSION['user_id']) {
        $error_msg = "<div class='alert alert-danger'>Không thể thay đổi vai trò của chính bạn!</div>";
    } elseif (!in_array($new_role, ['user', 'admin'])) {
        $error_msg = "<div class='alert alert-danger'>Vai trò không hợp lệ!</div>";
    } else {
        $sql = "UPDATE users SET role = ? WHERE id = ?";
        $stmt = mysqli_prepare($connect, $sql);
        mysqli_stmt_bind_param($stmt, "si", $new_role, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "<div class='alert alert-success'>Cập nhật vai trò thành công!</div>";
        } else {
            $error_msg = "<div class='alert alert-danger'>Cập nhật vai trò thất bại: " . mysqli_error($connect) . "</div>";
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle product addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_product'])) {
    $name = mysqli_real_escape_string($connect, $_POST['name']);
    $description = mysqli_real_escape_string($connect, $_POST['description']);
    $price = floatval($_POST['price']);
    $image = mysqli_real_escape_string($connect, $_POST['image']);
    $loai_banh_keo = mysqli_real_escape_string($connect, $_POST['loai_banh_keo']);

    $sql = "INSERT INTO products (name, description, price, image, loai_banh_keo) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($connect, $sql);
    mysqli_stmt_bind_param($stmt, "ssdss", $name, $description, $price, $image, $loai_banh_keo);
    if (mysqli_stmt_execute($stmt)) {
        $success_msg = "<div class='alert alert-success'>Thêm sản phẩm thành công!</div>";
    } else {
        $error_msg = "<div class='alert alert-danger'>Thêm sản phẩm thất bại: " . mysqli_error($connect) . "</div>";
    }
    mysqli_stmt_close($stmt);
}

// Handle product deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_product'])) {
    $product_id = $_POST['product_id'];
    $sql = "DELETE FROM products WHERE id = ?";
    $stmt = mysqli_prepare($connect, $sql);
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    if (mysqli_stmt_execute($stmt)) {
        $success_msg = "<div class='alert alert-success'>Xóa sản phẩm thành công!</div>";
    } else {
        $error_msg = "<div class='alert alert-danger'>Xóa sản phẩm thất bại: " . mysqli_error($connect) . "</div>";
    }
    mysqli_stmt_close($stmt);
}

// Handle order status update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_order'])) {
    $order_id = $_POST['order_id'];
    $status = mysqli_real_escape_string($connect, $_POST['status']);
    $sql = "UPDATE orders SET status = ? WHERE id = ?";
    $stmt = mysqli_prepare($connect, $sql);
    mysqli_stmt_bind_param($stmt, "si", $status, $order_id);
    if (mysqli_stmt_execute($stmt)) {
        $success_msg = "<div class='alert alert-success'>Cập nhật trạng thái đơn hàng thành công!</div>";
    } else {
        $error_msg = "<div class='alert alert-danger'>Cập nhật trạng thái đơn hàng thất bại: " . mysqli_error($connect) . "</div>";
    }
    mysqli_stmt_close($stmt);
}

// Fetch all users
$sql = "SELECT id, full_name, email, phone, role, created_at FROM users";
$result = mysqli_query($connect, $sql);
$users = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Fetch all products
$sql = "SELECT * FROM products";
$result = mysqli_query($connect, $sql);
$products = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Fetch all orders with user details
$sql = "SELECT o.id, o.user_id, u.full_name, o.total_amount, o.status, o.shipping_address, o.created_at 
        FROM orders o JOIN users u ON o.user_id = u.id";
$result = mysqli_query($connect, $sql);
$orders = mysqli_fetch_all($result, MYSQLI_ASSOC);

mysqli_close($connect);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Trị - BTEC Sweet Shop</title>
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
            background: url('https://images.unsplash.com/photo-1600585154340-be6161a56a0c') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
        }
        .wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: rgba(255, 247, 237, 0.95);
            padding: 20px;
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
        .cart-container {
            display: -webkit-flex;
            display: flex;
            -webkit-justify-content: space-between;
            justify-content: space-between;
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
        .navbar {
            background-color: var(--primary-color);
            padding: 8px 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            z-index: 999;
        }
        .navbar-nav .nav-link {
            color: #fff !important;
            font-size: 15px;
            font-weight: 600;
            padding: 10px 18px;
            transition: background-color 0.3s ease, color 0.3s ease;
            border-radius: 6px;
            margin: 0 5px;
        }
        .navbar-nav .nav-link:hover, .navbar-nav .nav-link.active {
            background-color: var(--hover-color);
        }
        .dropdown-menu {
            z-index: 1001;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            background-color: #fff;
        }
        .dropdown-menu .dropdown-item {
            font-size: 14px;
            padding: 8px 15px;
            transition: background-color 0.3s ease;
        }
        .dropdown-menu .dropdown-item:hover {
            background-color: var(--accent-color);
            color: var(--primary-color);
        }
        .content {
            padding: 20px;
        }
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            border-radius: 12px 12px 0 0;
        }
        .table {
            background-color: white;
            border-radius: 8px;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .table th, .table td {
            font-size: 0.9rem;
            word-break: break-word;
            max-width: 200px;
        }
        .table td.description {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            border-radius: 20px;
        }
        .btn-primary:hover {
            background-color: var(--hover-color);
        }
        .btn-danger {
            border-radius: 20px;
        }
        .form-control, .form-select {
            border-radius: 8px;
        }
        .modal-content {
            border-radius: 12px;
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
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Quản trị viên'); ?></span>
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
        <nav class="navbar navbar-expand-md sticky-top">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav mx-auto">
                        <li class="nav-item"><a class="nav-link" href="product.php">Tất Cả Sản Phẩm</a></li>
                        <li class="nav-item"><a class="nav-link" href="account.php">Tài Khoản</a></li>
                        <li class="nav-item"><a class="nav-link" href="cart.php">Giỏ Hàng</a></li>
                        <li class="nav-item"><a class="nav-link" href="contact.php">Liên Hệ</a></li>
                        <li class="nav-item"><a class="nav-link active" href="admin.php">Quản Trị</a></li>
                    </ul>
                </div>
            </div>
        </nav>
        <div class="content container">
            <?php echo $success_msg; ?>
            <?php echo $error_msg; ?>
            <!-- Manage Users -->
            <div class="card">
                <div class="card-header">Quản Lý Tài Khoản</div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Họ Tên</th>
                                <th>Email</th>
                                <th>Số Điện Thoại</th>
                                <th>Vai Trò</th>
                                <th>Ngày Tạo</th>
                                <th>Hành Động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                                    <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn thay đổi vai trò của tài khoản này?');">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <select name="role" class="form-select form-select-sm d-inline-block w-auto">
                                                <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>Người dùng</option>
                                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Quản trị viên</option>
                                            </select>
                                            <button type="submit" name="update_role" class="btn btn-primary btn-sm"><i class="fas fa-save"></i></button>
                                        </form>
                                        <?php if ($user['role'] !== 'admin'): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa tài khoản này?');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Manage Products -->
            <div class="card">
                <div class="card-header">Quản Lý Sản Phẩm</div>
                <div class="card-body">
                    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addProductModal">Thêm Sản Phẩm</button>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên</th>
                                    <th>Mô Tả</th>
                                    <th>Giá</th>
                                    <th>Hình Ảnh</th>
                                    <th>Loại Bánh Kẹo</th>
                                    <th>Ngày Tạo</th>
                                    <th>Hành Động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['id']); ?></td>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td class="description"><?php echo htmlspecialchars($product['description'] ?? 'N/A'); ?></td>
                                        <td><?php echo number_format($product['price'], 2); ?> VNĐ</td>
                                        <td><?php echo htmlspecialchars($product['image'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($product['loai_banh_keo'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($product['created_at']); ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa sản phẩm này?');">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <button type="submit" name="delete_product" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- Manage Orders -->
            <div class="card">
                <div class="card-header">Quản Lý Đơn Hàng</div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Khách Hàng</th>
                                <th>Tổng Tiền</th>
                                <th>Trạng Thái</th>
                                <th>Địa Chỉ Giao Hàng</th>
                                <th>Ngày Tạo</th>
                                <th>Hành Động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['id']); ?></td>
                                    <td><?php echo htmlspecialchars($order['full_name']); ?></td>
                                    <td><?php echo number_format($order['total_amount'], 2); ?> VNĐ</td>
                                    <td><?php echo htmlspecialchars($order['status']); ?></td>
                                    <td><?php echo htmlspecialchars($order['shipping_address']); ?></td>
                                    <td><?php echo htmlspecialchars($order['created_at']); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <select name="status" class="form-select form-select-sm d-inline-block w-auto">
                                                <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Đang xử lý</option>
                                                <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>Hoàn thành</option>
                                                <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Hủy</option>
                                            </select>
                                            <button type="submit" name="update_order" class="btn btn-primary btn-sm"><i class="fas fa-save"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- Add Product Modal -->
        <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addProductModalLabel">Thêm Sản Phẩm Mới</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="name" class="form-label">Tên Sản Phẩm</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Mô Tả</label>
                                <textarea class="form-control" id="description" name="description"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="price" class="form-label">Giá (VNĐ)</label>
                                <input type="number" step="0.01" class="form-control" id="price" name="price" required>
                            </div>
                            <div class="mb-3">
                                <label for="image" class="form-label">URL Hình Ảnh</label>
                                <input type="text" class="form-control" id="image" name="image">
                            </div>
                            <div class="mb-3">
                                <label for="loai_banh_keo" class="form-label">Loại Bánh Kẹo</label>
                                <select class="form-select" id="loai_banh_keo" name="loai_banh_keo" required>
                                    <option value="bánh">Bánh</option>
                                    <option value="kẹo">Kẹo</option>
                                </select>
                            </div>
                            <button type="submit" name="add_product" class="btn btn-primary">Thêm Sản Phẩm</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>