<?php
session_start();
// 如果已登录，跳转到对应角色的主页
if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'Customer': header("Location: /customer/catalog.php"); break;
        case 'Staff':    header("Location: /staff/pos.php"); break;
        case 'Manager':  header("Location: /manager/dashboard.php"); break;
        case 'Admin':    header("Location: /admin/products.php"); break;
    }
    exit();
}
// 未登录显示欢迎页
require_once __DIR__ . '/../includes/header.php';
?>
<div class="text-center mt-5">
    <h1 class="display-4 text-warning">Retro Echo Records</h1>
    <p class="lead">Premium Vinyl Collection & Community</p>
    <hr class="my-4 bg-secondary">
    <p>Please login to access our services.</p>
    <a class="btn btn-warning btn-lg" href="/login.php" role="button">Login Now</a>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>