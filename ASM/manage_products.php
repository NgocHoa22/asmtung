<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'config.php';

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Lấy danh sách sản phẩm
$sql = "SELECT id, name, price, image, loai_banh_keo FROM products ORDER BY id DESC";
$result = mysqli_query($connect, $sql);
$products = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Xử lý thêm sản phẩm
$add_msg = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_product'])) {
    $name = mysqli_real_escape_string($connect, $_POST['name']);
    $price = mysqli_real_escape_string($connect, $_POST['price']);
    $loai_banh_keo = mysqli_real_escape_string($connect, $_POST['loai_banh_keo']);
    $image = '';

    if (empty($name) || empty($price) || empty($loai_banh_keo)) {
        $add_msg = "<div class='alert alert-danger'>Vui lòng nhập đầy đủ thông tin!</div>";
    } elseif (!is_numeric($price) || $price < 0) {
        $add_msg = "<div class='alert alert-danger'>Giá phải là số dương!</div>";
    } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $add_msg = "<div class='alert alert-danger'>Vui lòng chọn một file ảnh!</div>";
    } else {
        $file_name = time() . '_' . basename($_FILES['image']['name']);
        $target_file = $upload_dir . $file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($imageFileType, $allowed_types)) {
            $add_msg = "<div class='alert alert-danger'>Chỉ chấp nhận file JPG, JPEG, PNG hoặc GIF!</div>";
        } elseif ($_FILES['image']['size'] > 5000000) { // 5MB limit
            $add_msg = "<div class='alert alert-danger'>File ảnh quá lớn! Tối đa 5MB.</div>";
        } elseif (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image = $target_file;
            $sql = "INSERT INTO products (name, price, image, loai_banh_keo) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($connect, $sql);
            mysqli_stmt_bind_param($stmt, "sdss", $name, $price, $image, $loai_banh_keo);
            if (mysqli_stmt_execute($stmt)) {
                $add_msg = "<div class='alert alert-success'>Thêm sản phẩm thành công!</div>";
                $products = mysqli_fetch_all(mysqli_query($connect, "SELECT id, name, price, image, loai_banh_keo FROM products ORDER BY id DESC"), MYSQLI_ASSOC);
            } else {
                $add_msg = "<div class='alert alert-danger'>Thêm sản phẩm thất bại: " . mysqli_error($connect) . "</div>";
            }
            mysqli_stmt_close($stmt);
        } else {
            $add_msg = "<div class='alert alert-danger'>Lỗi khi tải file ảnh!</div>";
        }
    }
}

// Xử lý sửa sản phẩm
$edit_product = null;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_product'])) {
    $id = intval($_POST['id']);
    $name = mysqli_real_escape_string($connect, $_POST['name']);
    $price = mysqli_real_escape_string($connect, $_POST['price']);
    $loai_banh_keo = mysqli_real_escape_string($connect, $_POST['loai_banh_keo']);
    $image = $_POST['existing_image'];

    if (empty($name) || empty($price) || empty($loai_banh_keo)) {
        $add_msg = "<div class='alert alert-danger'>Vui lòng nhập đầy đủ thông tin!</div>";
    } elseif (!is_numeric($price) || $price < 0) {
        $add_msg = "<div class='alert alert-danger'>Giá phải là số dương!</div>";
    } else {
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file_name = time() . '_' . basename($_FILES['image']['name']);
            $target_file = $upload_dir . $file_name;
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($imageFileType, $allowed_types)) {
                $add_msg = "<div class='alert alert-danger'>Chỉ chấp nhận file JPG, JPEG, PNG hoặc GIF!</div>";
            } elseif ($_FILES['image']['size'] > 5000000) {
                $add_msg = "<div class='alert alert-danger'>File ảnh quá lớn! Tối đa 5MB.</div>";
            } elseif (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image = $target_file;
            } else {
                $add_msg = "<div class='alert alert-danger'>Lỗi khi tải file ảnh!</div>";
            }
        }

        if (empty($add_msg)) {
            $sql = "UPDATE products SET name = ?, price = ?, image = ?, loai_banh_keo = ? WHERE id = ?";
            $stmt = mysqli_prepare($connect, $sql);
            mysqli_stmt_bind_param($stmt, "sdssi", $name, $price, $image, $loai_banh_keo, $id);
            if (mysqli_stmt_execute($stmt)) {
                $add_msg = "<div class='alert alert-success'>Sửa sản phẩm thành công!</div>";
                $products = mysqli_fetch_all(mysqli_query($connect, "SELECT id, name, price, image, loai_banh_keo FROM products ORDER BY id DESC"), MYSQLI_ASSOC);
            } else {
                $add_msg = "<div class='alert alert-danger'>Sửa sản phẩm thất bại: " . mysqli_error($connect) . "</div>";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Xử lý xóa sản phẩm
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $sql = "SELECT image FROM products WHERE id = ?";
    $stmt = mysqli_prepare($connect, $sql);
    mysqli_stmt_bind_param($stmt, "i", $delete_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);
    if ($product && file_exists($product['image'])) {
        unlink($product['image']);
    }
    mysqli_stmt_close($stmt);

    $sql = "DELETE FROM products WHERE id = ?";
    $stmt = mysqli_prepare($connect, $sql);
    mysqli_stmt_bind_param($stmt, "i", $delete_id);
    if (mysqli_stmt_execute($stmt)) {
        $add_msg = "<div class='alert alert-success'>Xóa sản phẩm thành công!</div>";
        $products = mysqli_fetch_all(mysqli_query($connect, "SELECT id, name, price, image, loai_banh_keo FROM products ORDER BY id DESC"), MYSQLI_ASSOC);
    } else {
        $add_msg = "<div class='alert alert-danger'>Xóa sản phẩm thất bại: " . mysqli_error($connect) . "</div>";
    }
    mysqli_stmt_close($stmt);
}

// Lấy thông tin sản phẩm để sửa
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $sql = "SELECT id, name, price, image, loai_banh_keo FROM products WHERE id = ?";
    $stmt = mysqli_prepare($connect, $sql);
    mysqli_stmt_bind_param($stmt, "i", $edit_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_product = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

mysqli_close($connect);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Sản Phẩm - BTEC Sweet Shop</title>
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
        .product-table img {
            max-width: 50px;
            height: auto;
            border-radius: 4px;
        }
        .btn-primary, .btn-warning, .btn-danger, .btn-secondary {
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
                    <h3><?php echo $edit_product ? 'Sửa Sản Phẩm' : 'Thêm Sản Phẩm'; ?></h3>
                    <?php echo $add_msg; ?>
                    <form action="" method="POST" enctype="multipart/form-data" class="row g-3 mb-4">
                        <input type="hidden" name="<?php echo $edit_product ? 'edit_product' : 'add_product'; ?>" value="1">
                        <?php if ($edit_product): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_product['id']; ?>">
                            <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($edit_product['image']); ?>">
                        <?php endif; ?>
                        <div class="col-md-4">
                            <label for="name" class="form-label">Tên Sản Phẩm</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo $edit_product ? htmlspecialchars($edit_product['name']) : ''; ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label for="price" class="form-label">Giá</label>
                            <input type="number" class="form-control" id="price" name="price" step="0.01" value="<?php echo $edit_product ? htmlspecialchars($edit_product['price']) : ''; ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label for="image" class="form-label">Hình Ảnh</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*" <?php echo $edit_product ? '' : 'required'; ?>>
                            <?php if ($edit_product): ?>
                                <small class="form-text text-muted">Hiện tại: <img src="<?php echo htmlspecialchars($edit_product['image']); ?>" alt="Current Image" style="max-width: 50px; height: auto;"></small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label for="loai_banh_keo" class="form-label">Loại Sản Phẩm</label>
                            <select class="form-select" id="loai_banh_keo" name="loai_banh_keo" required>
                                <option value="shirt" <?php echo ($edit_product && $edit_product['loai_banh_keo'] == 'shirt') ? 'selected' : ''; ?>>Áo</option>
                                <option value="pants" <?php echo ($edit_product && $edit_product['loai_banh_keo'] == 'pants') ? 'selected' : ''; ?>>Quần</option>
                                <option value="dress" <?php echo ($edit_product && $edit_product['loai_banh_keo'] == 'dress') ? 'selected' : ''; ?>>Váy</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><?php echo $edit_product ? 'Cập Nhật Sản Phẩm' : 'Thêm Sản Phẩm'; ?></button>
                            <?php if ($edit_product): ?>
                                <a href="manage_products.php" class="btn btn-secondary">Hủy</a>
                            <?php endif; ?>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-bordered product-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên Sản Phẩm</th>
                                    <th>Giá</th>
                                    <th>Hình Ảnh</th>
                                    <th>Loại</th>
                                    <th>Hành Động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['id']); ?></td>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo number_format($product['price'], 0, ',', '.'); ?>đ</td>
                                        <td><img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>"></td>
                                        <td><?php echo htmlspecialchars($product['loai_banh_keo']); ?></td>
                                        <td>
                                            <a href="?edit_id=<?php echo $product['id']; ?>" class="btn btn-warning btn-sm">Sửa</a>
                                            <a href="?delete_id=<?php echo $product['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bạn có chắc muốn xóa sản phẩm này?');">Xóa</a>
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