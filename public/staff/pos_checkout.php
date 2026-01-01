<?php
/**
 * POS 结账处理
 * 【架构重构】遵循理想化分层架构
 * - 通过 DBProcedures 进行所有数据库操作
 * - 通过 functions.php 进行业务逻辑处理
 */
// 【修复】防止重复启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';

// =============================================
// 【权限验证】
// =============================================
requireRole(['Staff', 'Manager']);

// 【安全修复】从数据库验证员工店铺归属
$employeeId = $_SESSION['user_id'] ?? null;
if (!$employeeId) {
    flash('Session expired. Please re-login.', 'warning');
    header('Location: /login.php');
    exit;
}

$employee = DBProcedures::getEmployeeShopInfo($pdo, $employeeId);
if (!$employee) {
    flash('Employee information not found. Please contact administrator.', 'danger');
    header('Location: /login.php');
    exit;
}

// 【安全修复】使用数据库验证后的店铺ID
$shopId = $employee['ShopID'];
$_SESSION['shop_id'] = $shopId; // 同步session

// =============================================
// 【购物车验证】
// =============================================
$posCart = getPOSCart();
if (empty($posCart)) {
    flash("Cart is empty.", 'warning');
    header("Location: pos.php");
    exit();
}
$customerEmail = trim($_POST['customer_email'] ?? '');
$customerId = null;

// =============================================
// 【事务处理】使用原子存储过程完成所有操作
// =============================================
try {
    // 1. 查找关联会员（通过视图）
    if (!empty($customerEmail)) {
        $customer = DBProcedures::getCustomerByEmail($pdo, $customerEmail);
        if ($customer) {
            $customerId = $customer['CustomerID'];
        } else {
            flash("Customer email not found. Proceeding as Guest.", 'warning');
        }
    }

    // 2. 保存原始会员等级（用于检测升级）
    $oldTierInfo = null;
    if ($customerId) {
        $oldTierInfo = getCustomerTierInfo($pdo, $customerId);
    }

    // 3. 【修复】使用原子存储过程创建并完成POS订单
    // sp_create_pos_order 内部会自动调用 sp_complete_order
    $result = DBProcedures::createPosOrder($pdo, $customerId, $employeeId, $shopId, $posCart);

    if (!$result || !isset($result['order_id']) || $result['order_id'] <= 0) {
        if (isset($result['order_id']) && $result['order_id'] == -2) {
            throw new Exception("Some items are no longer available. Please clear cart and rescan.");
        }
        throw new Exception("Failed to create order.");
    }

    $orderId = $result['order_id'];
    $total = $result['total_amount'];

    // 4. 检测会员升级
    $upgradeMsg = "";
    if ($customerId) {
        $oldTierId = $oldTierInfo ? $oldTierInfo['TierID'] : null;
        $res = checkMembershipUpgrade($pdo, $customerId, $total, $oldTierId);
        if ($res && $res['upgraded']) {
            $upgradeMsg = " Customer upgraded to {$res['new_tier_name']}!";
        }
    }

    // 清空购物车
    clearPOSCart();

    flash("Transaction #$orderId completed. Amount: " . formatPrice($total) . "." . $upgradeMsg, 'success');
    header("Location: pos.php");

} catch (Exception $e) {
    error_log("POS Checkout Error: " . $e->getMessage());
    flash("Error processing transaction: " . $e->getMessage(), 'danger');
    header("Location: pos.php");
}
?>
