<?php
session_start();
// 路由逻辑
if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'Customer': header("Location: /customer/catalog.php"); break;
        case 'Staff':    header("Location: /staff/pos.php"); break;
        case 'Manager':  header("Location: /manager/dashboard.php"); break;
        case 'Admin':    header("Location: /admin/products.php"); break;
        default:         header("Location: /logout.php"); break;
    }
    exit();
}
require_once __DIR__ . '/../includes/header.php';
?>

<div class="text-center d-flex flex-column justify-content-center align-items-center" style="min-height: 70vh;">
    
    <div class="mb-4">
        <i class="fa-solid fa-record-vinyl fa-6x text-warning fa-spin" style="animation-duration: 10s;"></i>
    </div>

    <h1 class="display-2 fw-bold text-white mb-2" style="font-family: 'Playfair Display', serif;">
        Retro Echo Records
    </h1>
    
    <p class="lead text-secondary mb-5 fs-3 fst-italic">
        "More than music, it's a collection of time."
    </p>

    <div class="d-flex gap-3">
        <a href="/login.php" class="btn btn-warning btn-lg px-5 py-3 rounded-pill shadow-lg">
            Start Your Collection
        </a>
    </div>

    <div class="row mt-5 pt-5 text-secondary border-top border-secondary w-75">
        <div class="col-md-4">
            <h3 class="h5 text-white mb-3">Premium Vinyl</h3>
            <p class="small">Curated collection of new releases and rare vintage finds.</p>
        </div>
        <div class="col-md-4">
            <h3 class="h5 text-white mb-3">Community</h3>
            <p class="small">Join our loyalty program to earn points and exclusive access.</p>
        </div>
        <div class="col-md-4">
            <h3 class="h5 text-white mb-3">Locations</h3>
            <p class="small">Visit our flagship stores in Changsha and Shanghai.</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>