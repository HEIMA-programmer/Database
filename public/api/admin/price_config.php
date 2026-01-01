<?php
/**
 * 采购价格配置API
 * 仅Admin可访问，按需返回特定专辑的价格配置
 */
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/db_procedures.php';
require_once __DIR__ . '/../../../includes/ApiResponse.php';
require_once __DIR__ . '/../../../includes/auth_guard.php';

requireRole('Admin');

// 验证请求方法
if (!ApiResponse::requireMethod('POST')) {
    exit;
}

$releaseId = (int)($_POST['release_id'] ?? 0);
$condition = $_POST['condition'] ?? '';

if (!ApiResponse::requireParams(['release_id' => $releaseId > 0 ? $releaseId : null])) {
    exit;
}

// 条件成本系数（保密）
$CONDITION_COST_MULTIPLIERS = [
    'New'  => 1.00,
    'Mint' => 0.95,
    'NM'   => 0.85,
    'VG+'  => 0.70,
    'VG'   => 0.55,
];

// 【重构】getSuggestedSalePrice 函数已移至 includes/functions.php，避免重复定义

ApiResponse::handle(function() use ($pdo, $releaseId, $condition, $CONDITION_COST_MULTIPLIERS) {
    // 获取专辑基础成本
    $releasesWithCost = DBProcedures::getReleaseListWithCost($pdo);
    $baseCost = null;

    foreach ($releasesWithCost as $row) {
        if ($row['ReleaseID'] == $releaseId) {
            $baseCost = (float)($row['BaseUnitCost'] ?? 25.00);
            break;
        }
    }

    if ($baseCost === null) {
        $baseCost = 25.00; // 默认成本
    }

    // 如果指定了condition，只返回该condition的价格
    if (!empty($condition) && isset($CONDITION_COST_MULTIPLIERS[$condition])) {
        $multiplier = $CONDITION_COST_MULTIPLIERS[$condition];
        $unitCost = round($baseCost * $multiplier, 2);
        $suggestedPrice = round(getSuggestedSalePrice($unitCost), 2);

        ApiResponse::success([
            'unit_cost' => $unitCost,
            'suggested_price' => $suggestedPrice
        ]);
    } else {
        // 返回所有condition的价格配置
        $conditions = [];
        foreach ($CONDITION_COST_MULTIPLIERS as $cond => $multiplier) {
            $unitCost = round($baseCost * $multiplier, 2);
            $conditions[$cond] = [
                'unit_cost' => $unitCost,
                'suggested_price' => round(getSuggestedSalePrice($unitCost), 2)
            ];
        }

        ApiResponse::success([
            'base_cost' => $baseCost,
            'conditions' => $conditions
        ]);
    }
}, 'api/admin/price_config');
