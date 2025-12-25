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
 * 【新增】获取客户当前的会员等级信息
 * 在调用 completeOrder 之前调用此函数保存原始等级
 */
function getCustomerTierInfo($pdo, $customerId) {
    if (!$customerId) return null;

    try {
        $stmt = $pdo->prepare("SELECT TierID, TierName, Points FROM vw_customer_profile_info WHERE CustomerID = ?");
        $stmt->execute([$customerId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error in getCustomerTierInfo: " . $e->getMessage());
        return null;
    }
}

/**
 * 【修复】检查会员升级状态
 * 在 completeOrder 之后调用，传入升级前的 TierID 来对比
 *
 * @param PDO $pdo
 * @param int $customerId
 * @param float $amountSpent 消费金额（用于计算积分）
 * @param int|null $oldTierId 升级前的等级ID（从 getCustomerTierInfo 获取）
 * @return array|false
 */
function checkMembershipUpgrade($pdo, $customerId, $amountSpent, $oldTierId = null) {
    if (!$customerId) return false;

    $pointsEarned = floor($amountSpent);
    if ($pointsEarned <= 0) return false;

    try {
        // 查询当前会员状态（触发器已自动更新积分和等级）
        $stmt = $pdo->prepare("SELECT TierID, TierName FROM vw_customer_profile_info WHERE CustomerID = ?");
        $stmt->execute([$customerId]);
        $currentStatus = $stmt->fetch();

        if (!$currentStatus) {
            return false;
        }

        // 对比升级前后的等级
        $upgraded = ($oldTierId !== null && $currentStatus['TierID'] > $oldTierId);
        $newTierName = $currentStatus['TierName'] ?? 'Unknown';

        return [
            'points_earned' => $pointsEarned,
            'upgraded'      => $upgraded,
            'new_tier_name' => $newTierName
        ];
    } catch (Exception $e) {
        error_log("Error in checkMembershipUpgrade: " . $e->getMessage());
        return false;
    }
}

/**
 * 【已废弃】旧函数保留用于向后兼容
 * 建议使用 getCustomerTierInfo + checkMembershipUpgrade 组合
 * @deprecated 使用 checkMembershipUpgrade 替代
 */
function addPointsAndCheckUpgrade($pdo, $customerId, $amountSpent) {
    // 积分更新现在由触发器 trg_after_order_complete 自动处理
    // 此函数仅用于向后兼容，返回当前状态但无法检测升级
    return checkMembershipUpgrade($pdo, $customerId, $amountSpent, null);
}
?>