<?php
/**
 * 【架构重构】支付页面
 * 表现层 - 仅负责数据展示和用户交互
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('Customer');

$customerId = $_SESSION['user_id'];
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$orderId) {
    flash("Invalid order ID.", 'danger');
    header("Location: orders.php");
    exit();
}

// ========== 数据准备 ==========
$pageData = preparePayPageData($pdo, $orderId, $customerId);

if (!$pageData['found']) {
    flash("Order not found or already paid.", 'warning');
    header("Location: orders.php");
    exit();
}

$order = $pageData['order'];

// ========== POST 请求处理 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = $_POST['payment_method'] ?? '';

    $result = handlePaymentCompletion($pdo, $orderId, $customerId, $paymentMethod);

    if ($result['success']) {
        flash("Payment successful! Your order is now being processed.", 'success');
        header("Location: order_detail.php?id=$orderId");
        exit();
    } else {
        flash("Payment failed: " . $result['message'], 'danger');
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ========== 表现层 ========== -->
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card bg-dark border-warning">
            <div class="card-header border-warning bg-transparent text-center">
                <h4 class="text-warning mb-0">
                    <i class="fa-solid fa-credit-card me-2"></i>Complete Payment
                </h4>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <p class="text-muted mb-1">Order #<?= $order['OrderID'] ?></p>
                    <h2 class="text-warning"><?= formatPrice($order['TotalAmount']) ?></h2>
                </div>

                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label text-light">Select Payment Method</label>

                        <div class="d-grid gap-2">
                            <div class="form-check bg-secondary rounded p-3">
                                <input class="form-check-input" type="radio" name="payment_method" id="alipay" value="alipay" checked>
                                <label class="form-check-label text-light ms-2" for="alipay">
                                    <i class="fa-brands fa-alipay me-2 text-info"></i>Alipay
                                </label>
                            </div>

                            <div class="form-check bg-secondary rounded p-3">
                                <input class="form-check-input" type="radio" name="payment_method" id="wechat" value="wechat">
                                <label class="form-check-label text-light ms-2" for="wechat">
                                    <i class="fa-brands fa-weixin me-2 text-success"></i>WeChat Pay
                                </label>
                            </div>

                            <div class="form-check bg-secondary rounded p-3">
                                <input class="form-check-input" type="radio" name="payment_method" id="card" value="card">
                                <label class="form-check-label text-light ms-2" for="card">
                                    <i class="fa-solid fa-credit-card me-2 text-primary"></i>Credit/Debit Card
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-warning btn-lg">
                            <i class="fa-solid fa-lock me-2"></i>Pay Now
                        </button>
                        <a href="order_detail.php?id=<?= $order['OrderID'] ?>" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                    </div>
                </form>

                <div class="text-center mt-4">
                    <small class="text-muted">
                        <i class="fa-solid fa-shield-halved me-1"></i>
                        Secure payment powered by Demo Gateway
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
