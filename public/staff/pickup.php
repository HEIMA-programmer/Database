<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole(['Staff', 'Manager']);
require_once __DIR__ . '/../../includes/header.php';

$shopId = $_SESSION['shop_id'];

// 处理取货操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    require_once __DIR__ . '/../../includes/db_procedures.php';

    $orderId = $_POST['order_id'];

    try {
        $pdo->beginTransaction();

        // 获取订单信息并验证
        $stmt = $pdo->prepare("
            SELECT TotalAmount, OrderStatus FROM CustomerOrder
            WHERE OrderID = ? AND FulfilledByShopID = ?
        ");
        $stmt->execute([$orderId, $shopId]);
        $order = $stmt->fetch();

        if (!$order) {
            throw new Exception("订单不存在或不属于本店");
        }

        if ($order['OrderStatus'] !== 'Paid') {
            throw new Exception("订单状态不正确，当前状态: {$order['OrderStatus']}");
        }

        // 使用存储过程完成订单（自动更新库存、增加积分、升级会员）
        $pointsEarned = floor($order['TotalAmount']);
        $success = DBProcedures::completeOrder($pdo, $orderId, $pointsEarned);

        if (!$success) {
            throw new Exception("订单完成失败");
        }

        $pdo->commit();
        flash("Order #$orderId marked as collected.", 'success');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash("Error: " . $e->getMessage(), 'danger');
    }

    header("Location: pickup.php");
    exit();
}

// 查询待取货订单 (Paid but not Completed)
// 引用 Phase 1 定义的视图 vw_staff_bopis_pending，并在 PHP 层过滤 ShopID
$sql = "SELECT * FROM vw_staff_bopis_pending WHERE ShopID = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$shopId]);
$orders = $stmt->fetchAll();
?>

<h2 class="text-warning mb-4"><i class="fa-solid fa-box-open me-2"></i>In-Store Pickup Queue</h2>

<?php if (empty($orders)): ?>
    <div class="alert alert-success text-center py-5">
        <h4>No pending pickups.</h4>
        <p>All online orders for this location have been fulfilled.</p>
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($orders as $o): ?>
        <div class="col-md-6 mb-3">
            <div class="card bg-secondary text-light border-start border-4 border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <h5 class="card-title">Order #<?= $o['OrderID'] ?></h5>
                        <span class="badge bg-info text-dark">Ready for Pickup</span>
                    </div>
                    <p class="card-text mb-1">Customer: <strong><?= h($o['CustomerName']) ?></strong></p>
                    <p class="card-text text-light-50 small">Date: <?= formatDate($o['OrderDate']) ?></p>
                    
                    <form method="POST" class="mt-3">
                        <input type="hidden" name="order_id" value="<?= $o['OrderID'] ?>">
                        <button type="submit" class="btn btn-warning w-100 fw-bold">
                            Mark as Collected
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>