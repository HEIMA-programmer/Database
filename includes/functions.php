<?php
// includes/functions.php

/**
 * 安全过滤输出，防止XSS攻击
 */
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * 格式化价格
 */
function formatPrice($amount) {
    return '¥' . number_format((float)$amount, 2);
}

/**
 * 格式化日期
 */
function formatDate($dateString) {
    return date('Y-m-d H:i', strtotime($dateString));
}

/**
 * 设置或获取 Flash 消息 (一次性提示)
 */
function flash($message = null, $type = 'success') {
    if ($message) {
        $_SESSION['flash'] = [
            'message' => $message,
            'type'    => $type // success, danger, warning, info
        ];
    } else {
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
    }
    return null;
}

/**
 * 显示 Flash 消息的 HTML
 */
function displayFlash() {
    $flash = flash();
    if ($flash) {
        echo "<div class='alert alert-{$flash['type']} alert-dismissible fade show' role='alert'>
                {$flash['message']}
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
    }
}

/**
 * 检查当前用户是否有特定权限
 */
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * 获取购物车商品数量（用于导航栏显示）
 */
function getCartCount() {
    if (isset($_SESSION['cart'])) {
        return count($_SESSION['cart']);
    }
    return 0;
}

/**
 * [架构重构] 动态获取特定类型的店铺ID
 */
function getShopIdByType($pdo, $type) {
    static $cache = []; 
    
    if (isset($cache[$type])) {
        return $cache[$type];
    }

    try {
        $stmt = $pdo->prepare("SELECT ShopID FROM Shop WHERE Type = ? LIMIT 1");
        $stmt->execute([$type]);
        $id = $stmt->fetchColumn();
        
        if ($id) {
            $cache[$type] = $id;
            return $id;
        }
        
        error_log("Critical Warning: No shop found for type '$type'. Operations may fail.");
        return false; // 返回 false 让调用者处理错误
    } catch (Exception $e) {
        error_log("DB Error in getShopIdByType: " . $e->getMessage());
        return false;
    }
}

/**
 * [Refactored] 核心业务逻辑：增加积分并检查会员升级
 * 使用存储过程 sp_update_customer_tier 来处理会员等级升级
 * 此函数可在 Online Checkout 和 POS Checkout 中复用
 */
function addPointsAndCheckUpgrade($pdo, $customerId, $amountSpent) {
    // 1. 计算积分 (假设 1元 = 1分)
    $pointsEarned = floor($amountSpent);
    if ($pointsEarned <= 0) return false;

    try {
        // 2. 获取升级前的会员等级信息
        $stmt = $pdo->prepare("SELECT TierID, TierName FROM vw_customer_profile_info WHERE CustomerID = ?");
        $stmt->execute([$customerId]);
        $beforeUpgrade = $stmt->fetch();
        $oldTierId = $beforeUpgrade['TierID'] ?? null;

        // 3. 更新用户积分 (这个简单的更新操作保留在应用层)
        $stmt = $pdo->prepare("UPDATE Customer SET Points = Points + ? WHERE CustomerID = ?");
        $stmt->execute([$pointsEarned, $customerId]);

        // 4. 使用存储过程检查并自动升级会员等级
        $stmt = $pdo->prepare("CALL sp_update_customer_tier(?)");
        $stmt->execute([$customerId]);

        // 5. 获取升级后的状态
        $stmt = $pdo->prepare("SELECT TierID, TierName FROM vw_customer_profile_info WHERE CustomerID = ?");
        $stmt->execute([$customerId]);
        $afterUpgrade = $stmt->fetch();

        $upgraded = false;
        $newTierName = '';

        if ($afterUpgrade && $oldTierId != $afterUpgrade['TierID']) {
            $upgraded = true;
            $newTierName = $afterUpgrade['TierName'];
        }

        return [
            'points_earned' => $pointsEarned,
            'upgraded'      => $upgraded,
            'new_tier_name' => $newTierName
        ];
    } catch (Exception $e) {
        error_log("Error in addPointsAndCheckUpgrade: " . $e->getMessage());
        return false;
    }
}
?>