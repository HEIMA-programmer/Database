<?php
/**
 * 【安全修复】回购建议价格计算API
 * 将定价逻辑从前端移到后端，避免商业策略暴露
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Staff');

header('Content-Type: application/json');

// 验证请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$releaseId = (int)($_POST['release_id'] ?? 0);
$condition = $_POST['condition'] ?? '';
$shopId = $_SESSION['shop_id'] ?? 0;

if ($releaseId <= 0 || empty($condition)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

// 【安全】定价逻辑仅在后端执行

// 条件系数（保密）
$conditionFactors = [
    'New'  => 1.00,
    'Mint' => 0.95,
    'NM'   => 0.85,
    'VG+'  => 0.70,
    'VG'   => 0.55
];

// 利润率计算（保密）
function getProfitMargin($cost) {
    if ($cost <= 20) return 1.50;
    if ($cost <= 50) return 1.60;
    if ($cost <= 100) return 1.70;
    return 1.80;
}

try {
    // 1. 首先检查是否有现有库存价格
    $priceMap = DBProcedures::getStockPriceMap($pdo, $shopId);
    $key = $releaseId . '_' . $condition;

    if (isset($priceMap[$key])) {
        // 有现有库存价格
        echo json_encode([
            'success' => true,
            'price' => (float)$priceMap[$key],
            'source' => 'existing',
            'message' => 'Using existing stock price'
        ]);
        exit;
    }

    // 2. 没有现有价格，计算建议价格
    $releases = DBProcedures::getReleaseListWithCost($pdo);
    $baseCost = null;

    foreach ($releases as $r) {
        if ($r['ReleaseID'] == $releaseId) {
            $baseCost = (float)$r['BaseUnitCost'];
            break;
        }
    }

    if ($baseCost === null || $baseCost <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Base cost not found for this release'
        ]);
        exit;
    }

    // 计算建议价格（逻辑在后端，前端看不到）
    $conditionFactor = $conditionFactors[$condition] ?? 0.55;
    $adjustedCost = $baseCost * $conditionFactor;
    $margin = getProfitMargin($adjustedCost);
    $suggestedPrice = round($adjustedCost * $margin, 2);

    echo json_encode([
        'success' => true,
        'price' => $suggestedPrice,
        'source' => 'calculated',
        'message' => 'Suggested price calculated'
    ]);

} catch (Exception $e) {
    error_log("api_calculate_price error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error'
    ]);
}
