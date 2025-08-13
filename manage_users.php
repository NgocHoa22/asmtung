<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'config.php';

// Lấy danh sách người dùng
$sql = "SELECT id, full_name, email, phone, role FROM users ORDER BY id DESC";
$result = mysqli_query($connect, $sql);
$users = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Xử lý sửa vai trò
$edit_msg = '';
$edit_user = null;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_user'])) {
    $id = intval($_POST['id']);
    $role = mysqli_real_escape_string($connect, $_POST['role']);

    if ($id == $_SESSION['user_id']) {
        $edit_msg = "<div class='alert alert-danger'>Không thể thay đổi vai trò của tài khoản đang đăng nhập!</div>";
    } elseif (!in_array($role, ['admin', 'user'])) {
        $edit_msg = "<div class='alert alert-danger'>Vai trò không hợp lệ!</div>";
    } else {
        $sql = "UPDATE users SET role = ? WHERE id = ?";
        $stmt = mysqli_prepare($connect, $sql);
        mysqli_stmt_bind_param($stmt, "si", $role, $id);
        if (mysqli_stmt_execute($stmt)) {
            $edit_msg = "<div class='alert alert-success'>Cập nhật vai trò thành công!</div>";
            $users = mysqli_fetch_all(mysqli_query($connect, "SELECT id, full_name, email, phone, role FROM users ORDER BY id DESC"), MYSQLI_ASSOC);
        } else {
            $edit_msg = "<div class='alert alert-danger'>Cập nhật vai trò thất bại: " . mysqli_error($connect) . "</div>";
        }
        mysqli_stmt_close($stmt);
    }
}

// Xử lý xóa người dùng
$delete_msg = '';
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    if ($delete_id == $_SESSION['user_id']) {
        $delete_msg = "<div class='alert alert-danger'>Không thể xóa chính tài khoản đang đăng nhập!</div>";
    } else {
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = mysqli_prepare($connect, $sql);
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        if (mysqli_stmt_execute($stmt)) {
            $delete_msg = "<div class='alert alert-success'>Xóa người dùng thành công!</div>";
            $users = mysqli_fetch_all(mysqli_query($connect, "SELECT id, full_name, email, phone, role FROM users ORDER BY id DESC"), MYSQLI_ASSOC);
        } else {
            $delete_msg = "<div class='alert alert-danger'>Xóa người dùng thất bại: " . mysqli_error($connect) . "</div>";
        }
        mysqli_stmt_close($stmt);
    }
}

// Lấy thông tin người dùng để sửa
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $sql = "SELECT id, full_name, email, phone, role FROM users WHERE id = ?";
    $stmt = mysqli_prepare($connect, $sql);
    mysqli_stmt_bind_param($stmt, "i", $edit_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

mysqli_close($connect);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Người Dùng - BTEC Sweet Shop</title>
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
        .account-section {
            background-color: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .account-section h3 {
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        .user-table img {
            max-width: 50px;
            height: auto;
            border-radius: 4px;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            border-radius: 20px;
        }
        .btn-primary:hover {
            background-color: var(--hover-color);
        }
        .btn-warning, .btn-danger, .btn-secondary {
            border-radius: 20px;
        }
        .form-control, .form-select {
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
                    <input type="text" name="search" placeholder="Tìm kiếm bánh kẹo..." class="form-control" aria-label="Tìm kiếm sản phẩm">
                    <button type="submit" class="btn" aria-label="Tìm kiếm"><i class="fas fa-search"></i></button>
                </form>
                <div class="icon-cart">
                    <a href="cart.php" aria-label="Giỏ hàng"><img src="https://cdn-icons-png.flaticon.com/512/3144/3144456.png" alt="Cart"></a>
                </div>
                <div class="icon-user dropdown d-flex align-items-center">
                    <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Tài khoản" aria-haspopup="true">
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
                <div class="account-section">
                    <h3><?php echo $edit_user ? 'Sửa Vai Trò Người Dùng' : 'Quản Lý Người Dùng'; ?></h3>
                    <?php echo $edit_msg ?: $delete_msg; ?>
                    <?php if ($edit_user): ?>
                        <form action="" method="POST" class="row g-3 mb-4">
                            <input type="hidden" name="edit_user" value="1">
                            <input type="hidden" name="id" value="<?php echo $edit_user['id']; ?>">
                            <div class="col-md-6">
                                <label for="full_name" class="form-label">Họ và Tên</label>
                                <input type="text" class="form-control" id="full_name" value="<?php echo htmlspecialchars($edit_user['full_name']); ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" valuearchitecture="<?php echo htmlspecialchars($edit_user['email']); ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Số Điện Thoại</label>
                                <input type="text" class="form-control" id="phone" value="<?php echo htmlspecialchars($edit_user['phone'] ?: 'Chưa cập nhật'); ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label for="role" class="form-label">Vai Trò</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="admin" <?php echo $edit_user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="user" <?php echo $edit_user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Cập Nhật Vai Trò</button>
                                <a href="manage_users.php" class="btn btn-secondary">Hủy</a>
                            </div>
                        </form>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-bordered user-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Họ và Tên</th>
                                    <th>Email</th>
                                    <th>Số Điện Thoại</th>
                                    <th>Vai Trò</th>
                                    <th>Hành Động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone'] ?: 'Chưa cập nhật'); ?></td>
                                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                                        <td>
                                            <a href="?edit_id=<?php echo $user['id']; ?>" class="btn btn-warning btn-sm">Sửa</a>
                                            <a href="?delete_id=<?php echo $user['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bạn có chắc muốn xóa người dùng này?');">Xóa</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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