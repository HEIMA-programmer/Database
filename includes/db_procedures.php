<?php
/**
 * Database Stored Procedures Helper
 * 数据库存储过程辅助类 - 封装所有业务流程调用
 *
 * 使用示例:
 * require_once __DIR__ . '/db_procedures.php';
 * $result = DBProcedures::completeOrder($pdo, $orderId, $pointsEarned);
 */

class DBProcedures {

    // =============================================
    // 供应商订单流程
    // =============================================

    /**
     * 创建供应商订单
     * @param PDO $pdo
     * @param int $supplierId
     * @param int $employeeId
     * @param int $shopId
     * @return int|false 订单ID或false
     */
    public static function createSupplierOrder($pdo, $supplierId, $employeeId, $shopId) {
        try {
            $stmt = $pdo->prepare("CALL sp_create_supplier_order(?, ?, ?, @order_id)");
            $stmt->execute([$supplierId, $employeeId, $shopId]);

            $result = $pdo->query("SELECT @order_id AS order_id")->fetch();
            return $result['order_id'] > 0 ? $result['order_id'] : false;
        } catch (PDOException $e) {
            error_log("创建供应商订单失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 添加供应商订单行
     * @param PDO $pdo
     * @param int $orderId
     * @param int $releaseId
     * @param int $quantity
     * @param float $unitCost
     * @return bool
     */
    public static function addSupplierOrderLine($pdo, $orderId, $releaseId, $quantity, $unitCost) {
        try {
            $stmt = $pdo->prepare("CALL sp_add_supplier_order_line(?, ?, ?, ?)");
            return $stmt->execute([$orderId, $releaseId, $quantity, $unitCost]);
        } catch (PDOException $e) {
            error_log("添加订单行失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 接收供应商订单并生成库存
     * @param PDO $pdo
     * @param int $orderId
     * @param string $batchNo 批次号
     * @param string $conditionGrade 品相
     * @param float $markupRate 加价率 (如0.50表示成本价的150%)
     * @return bool
     */
    public static function receiveSupplierOrder($pdo, $orderId, $batchNo, $conditionGrade = 'New', $markupRate = 0.50) {
        try {
            $stmt = $pdo->prepare("CALL sp_receive_supplier_order(?, ?, ?, ?)");
            return $stmt->execute([$orderId, $batchNo, $conditionGrade, $markupRate]);
        } catch (PDOException $e) {
            error_log("接收供应商订单失败: " . $e->getMessage());
            return false;
        }
    }

    // =============================================
    // 回购流程
    // =============================================

    /**
     * 处理客户回购
     * @param PDO $pdo
     * @param int $customerId
     * @param int $employeeId
     * @param int $shopId
     * @param int $releaseId
     * @param int $quantity
     * @param float $unitPrice 回购单价（支付给客户）
     * @param string $conditionGrade
     * @param float $resalePrice 转售价格
     * @return int|false 回购订单ID或false
     */
    public static function processBuyback($pdo, $customerId, $employeeId, $shopId, $releaseId,
                                          $quantity, $unitPrice, $conditionGrade, $resalePrice) {
        try {
            $stmt = $pdo->prepare("CALL sp_process_buyback(?, ?, ?, ?, ?, ?, ?, ?, @buyback_id)");
            $stmt->execute([$customerId, $employeeId, $shopId, $releaseId,
                           $quantity, $unitPrice, $conditionGrade, $resalePrice]);

            $result = $pdo->query("SELECT @buyback_id AS buyback_id")->fetch();
            return $result['buyback_id'] > 0 ? $result['buyback_id'] : false;
        } catch (PDOException $e) {
            error_log("处理回购失败: " . $e->getMessage());
            return false;
        }
    }

    // =============================================
    // 库存调拨流程
    // =============================================

    /**
     * 发起库存调拨
     * @param PDO $pdo
     * @param int $stockItemId
     * @param int $fromShopId
     * @param int $toShopId
     * @param int $employeeId
     * @return int|false 调拨ID或false
     */
    public static function initiateTransfer($pdo, $stockItemId, $fromShopId, $toShopId, $employeeId) {
        try {
            $stmt = $pdo->prepare("CALL sp_initiate_transfer(?, ?, ?, ?, @transfer_id)");
            $stmt->execute([$stockItemId, $fromShopId, $toShopId, $employeeId]);

            $result = $pdo->query("SELECT @transfer_id AS transfer_id")->fetch();
            return $result['transfer_id'] > 0 ? $result['transfer_id'] : false;
        } catch (PDOException $e) {
            error_log("发起调拨失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 完成库存调拨
     * @param PDO $pdo
     * @param int $transferId
     * @param int $receivedByEmployeeId
     * @return bool
     */
    public static function completeTransfer($pdo, $transferId, $receivedByEmployeeId) {
        try {
            $stmt = $pdo->prepare("CALL sp_complete_transfer(?, ?)");
            return $stmt->execute([$transferId, $receivedByEmployeeId]);
        } catch (PDOException $e) {
            error_log("完成调拨失败: " . $e->getMessage());
            return false;
        }
    }

    // =============================================
    // 销售流程
    // =============================================

    /**
     * 创建客户订单
     * @param PDO $pdo
     * @param int|null $customerId
     * @param int $shopId
     * @param int|null $employeeId
     * @param string $orderType 'InStore' or 'Online'
     * @return int|false 订单ID或false
     */
    public static function createCustomerOrder($pdo, $customerId, $shopId, $employeeId, $orderType = 'InStore') {
        try {
            $stmt = $pdo->prepare("CALL sp_create_customer_order(?, ?, ?, ?, @order_id)");
            $stmt->execute([$customerId, $shopId, $employeeId, $orderType]);

            $result = $pdo->query("SELECT @order_id AS order_id")->fetch();
            return $result['order_id'] > 0 ? $result['order_id'] : false;
        } catch (PDOException $e) {
            error_log("创建客户订单失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 添加订单商品
     * @param PDO $pdo
     * @param int $orderId
     * @param int $stockItemId
     * @param float $priceAtSale
     * @return bool
     */
    public static function addOrderItem($pdo, $orderId, $stockItemId, $priceAtSale) {
        try {
            $stmt = $pdo->prepare("CALL sp_add_order_item(?, ?, ?)");
            return $stmt->execute([$orderId, $stockItemId, $priceAtSale]);
        } catch (PDOException $e) {
            error_log("添加订单商品失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 完成订单（支付成功）
     * @param PDO $pdo
     * @param int $orderId
     * @param int $pointsEarned 获得的积分
     * @return bool
     */
    public static function completeOrder($pdo, $orderId, $pointsEarned) {
        try {
            $stmt = $pdo->prepare("CALL sp_complete_order(?, ?)");
            return $stmt->execute([$orderId, $pointsEarned]);
        } catch (PDOException $e) {
            error_log("完成订单失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 取消订单
     * @param PDO $pdo
     * @param int $orderId
     * @return bool
     */
    public static function cancelOrder($pdo, $orderId) {
        try {
            $stmt = $pdo->prepare("CALL sp_cancel_order(?)");
            return $stmt->execute([$orderId]);
        } catch (PDOException $e) {
            error_log("取消订单失败: " . $e->getMessage());
            return false;
        }
    }

    // =============================================
    // 辅助函数
    // =============================================

    /**
     * 检查库存数量
     * @param PDO $pdo
     * @param int $releaseId
     * @param int $shopId
     * @param string $conditionGrade
     * @return int 可用数量
     */
    public static function getAvailableStock($pdo, $releaseId, $shopId, $conditionGrade) {
        try {
            $stmt = $pdo->prepare("SELECT fn_get_available_stock(?, ?, ?) AS qty");
            $stmt->execute([$releaseId, $shopId, $conditionGrade]);
            $result = $stmt->fetch();
            return $result['qty'] ?? 0;
        } catch (PDOException $e) {
            error_log("检查库存失败: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * 更新客户会员等级
     * @param PDO $pdo
     * @param int $customerId
     * @return bool
     */
    public static function updateCustomerTier($pdo, $customerId) {
        try {
            $stmt = $pdo->prepare("CALL sp_update_customer_tier(?)");
            return $stmt->execute([$customerId]);
        } catch (PDOException $e) {
            error_log("更新会员等级失败: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * 示例使用
 *
 * // 1. 创建供应商订单
 * $orderId = DBProcedures::createSupplierOrder($pdo, 1, 5, 1);
 * if ($orderId) {
 *     DBProcedures::addSupplierOrderLine($pdo, $orderId, 101, 10, 50.00);
 *     DBProcedures::receiveSupplierOrder($pdo, $orderId, 'BATCH-001', 'New', 0.50);
 * }
 *
 * // 2. 处理回购
 * $buybackId = DBProcedures::processBuyback($pdo, 123, 5, 1, 101, 3, 30.00, 'VG+', 50.00);
 *
 * // 3. 库存调拨
 * $transferId = DBProcedures::initiateTransfer($pdo, 1001, 1, 2, 5);
 * if ($transferId) {
 *     // 稍后完成
 *     DBProcedures::completeTransfer($pdo, $transferId, 8);
 * }
 *
 * // 4. 销售流程
 * $orderId = DBProcedures::createCustomerOrder($pdo, 123, 1, 5, 'InStore');
 * if ($orderId) {
 *     DBProcedures::addOrderItem($pdo, $orderId, 1001, 59.99);
 *     DBProcedures::completeOrder($pdo, $orderId, 60);
 * }
 */
?>
