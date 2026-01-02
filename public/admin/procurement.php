<?php
/**
 * Procurement Management Page - Admin Version
 * Admin creates POs, warehouse staff confirms receipt
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Admin');

// Condition cost multipliers (relative to BaseUnitCost)
$CONDITION_COST_MULTIPLIERS = [
    'New'  => 1.00,
    'Mint' => 0.95,
    'NM'   => 0.85,
    'VG+'  => 0.70,
    'VG'   => 0.55,
];

/**
 * Calculate actual procurement cost by condition
 */
function getUnitCostByCondition($baseCost, $condition) {
    global $CONDITION_COST_MULTIPLIERS;
    $multiplier = $CONDITION_COST_MULTIPLIERS[$condition] ?? 1.00;
    return round($baseCost * $multiplier, 2);
}

// ========== Data Preparation ==========
$pageData = prepareProcurementPageData($pdo);
$warehouseId = $pageData['warehouse_id'];
$suppliers = $pageData['suppliers'];
$releases = $pageData['releases'];
$pendingPOs = $pageData['pending_orders'];
$receivedPOs = $pageData['received_orders'] ?? [];

// Get release base unit costs for backend validation
$releaseBaseUnitCosts = [];
$releasesWithCost = DBProcedures::getReleaseListWithCost($pdo);
foreach ($releasesWithCost as $row) {
    $releaseBaseUnitCosts[$row['ReleaseID']] = (float)($row['BaseUnitCost'] ?? 25.00);
}

$conditionOptions = ['New', 'Mint', 'NM', 'VG+', 'VG'];

// Current tab
$currentTab = $_GET['tab'] ?? 'pending';

// ========== POST Request Handling ==========
// Action: Create New Supplier Order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_po'])) {
    $releaseId = (int)$_POST['release_id'];
    $salePrice = (float)$_POST['sale_price'];
    $condition = $_POST['condition'] ?? 'New';

    $baseCost = $releaseBaseUnitCosts[$releaseId] ?? 25.00;
    $unitCost = getUnitCostByCondition($baseCost, $condition);

    $data = [
        'supplier_id'  => (int)$_POST['supplier_id'],
        'employee_id'  => $_SESSION['user_id'],
        'release_id'   => $releaseId,
        'quantity'     => (int)$_POST['quantity'],
        'unit_cost'    => $unitCost,
        'condition'    => $condition,
        'sale_price'   => $salePrice
    ];

    if (!in_array($data['condition'], $conditionOptions)) {
        flash("Invalid condition grade.", 'danger');
        header("Location: procurement.php");
        exit();
    }

    $result = handleProcurementCreatePO($pdo, $data, $warehouseId);
    flash($result['message'], $result['success'] ? 'success' : 'danger');

    header("Location: procurement.php");
    exit();
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Header -->
<?php if (!$warehouseId): ?>
    <div class='alert alert-danger'>Critical Configuration Error: 'Warehouse' shop type not found in database. Procurement functions disabled.</div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="text-warning mb-1"><i class="fa-solid fa-boxes-packing me-2"></i>Procurement Management</h2>
            <p class="text-secondary mb-0">Create purchase orders - warehouse staff confirms receipt</p>
        </div>
        <div class="d-flex gap-2">
            <a href="warehouse_dispatch.php" class="btn btn-info" <?= !$warehouseId ? 'disabled' : '' ?>>
                <i class="fa-solid fa-truck me-2"></i>Warehouse Dispatch
            </a>
            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#newPOModal" <?= !$warehouseId ? 'disabled' : '' ?>>
                <i class="fa-solid fa-plus me-2"></i>New Purchase Order
            </button>
        </div>
    </div>
</div>

<!-- Procurement Cost Policy Info -->
<div class="alert alert-info mb-4">
    <i class="fa-solid fa-info-circle me-2"></i>
    <strong>Procurement Cost Policy:</strong> Each album has a fixed unit cost. Sale price is suggested based on cost level.
    After creating an order, warehouse staff will confirm receipt and add items to inventory.
</div>

<!-- Tab Navigation -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $currentTab == 'pending' ? 'active bg-dark text-warning' : 'text-light' ?>" href="?tab=pending">
            <i class="fa-solid fa-clock me-1"></i>Pending Orders
            <?php if (count($pendingPOs) > 0): ?>
                <span class="badge bg-warning text-dark"><?= count($pendingPOs) ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $currentTab == 'history' ? 'active bg-dark text-success' : 'text-light' ?>" href="?tab=history">
            <i class="fa-solid fa-history me-1"></i>Order History
        </a>
    </li>
</ul>

<?php if ($currentTab == 'pending'): ?>
<!-- Pending Orders Section -->
<div class="card bg-dark border-secondary">
    <div class="card-header border-secondary d-flex justify-content-between align-items-center">
        <h5 class="card-title text-white mb-0"><i class="fa-solid fa-clock me-2"></i>Pending Shipments</h5>
        <small class="text-muted">Waiting for warehouse staff to confirm receipt</small>
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>PO #</th>
                    <th>Supplier</th>
                    <th>Order Date</th>
                    <th>Items</th>
                    <th>Est. Cost</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingPOs as $po): ?>
                <tr>
                    <td><span class="badge bg-info">#<?= $po['SupplierOrderID'] ?></span></td>
                    <td><?= h($po['SupplierName']) ?></td>
                    <td><?= date('Y-m-d', strtotime($po['OrderDate'])) ?></td>
                    <td><?= $po['TotalItems'] ?? 0 ?> units</td>
                    <td class="text-success fw-bold"><?= formatPrice($po['TotalCost'] ?? 0) ?></td>
                    <td>
                        <span class="badge bg-warning text-dark">
                            <i class="fa-solid fa-truck me-1"></i>Awaiting Warehouse
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($pendingPOs)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No pending orders. All shipments have been received.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<!-- Order History Section -->
<div class="card bg-dark border-secondary">
    <div class="card-header border-secondary">
        <h5 class="card-title text-white mb-0"><i class="fa-solid fa-history me-2"></i>Received Orders History</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>PO #</th>
                    <th>Supplier</th>
                    <th>Order Date</th>
                    <th>Received Date</th>
                    <th>Items</th>
                    <th>Total Cost</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($receivedPOs as $po): ?>
                <tr>
                    <td><span class="badge bg-secondary">#<?= $po['SupplierOrderID'] ?></span></td>
                    <td><?= h($po['SupplierName']) ?></td>
                    <td><?= date('Y-m-d', strtotime($po['OrderDate'])) ?></td>
                    <td><?= $po['ReceivedDate'] ? date('Y-m-d', strtotime($po['ReceivedDate'])) : '-' ?></td>
                    <td><?= $po['TotalItems'] ?? 0 ?> units</td>
                    <td class="text-success fw-bold"><?= formatPrice($po['TotalCost'] ?? 0) ?></td>
                    <td><span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Received</span></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($receivedPOs)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No order history available.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- New PO Modal -->
<div class="modal fade" id="newPOModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fa-solid fa-file-invoice me-2 text-warning"></i>Create Purchase Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="poForm">
                <div class="modal-body">
                    <input type="hidden" name="create_po" value="1">

                    <div class="alert alert-secondary small mb-3">
                        <i class="fa-solid fa-info-circle me-1"></i>
                        After creating this order, warehouse staff will need to confirm receipt before items are added to inventory.
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" class="form-select bg-dark text-white border-secondary" required>
                            <option value="">Select Supplier...</option>
                            <?php foreach($suppliers as $s): ?>
                                <option value="<?= $s['SupplierID'] ?>"><?= h($s['Name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <hr class="border-secondary">
                    <h6 class="text-warning mb-3"><i class="fa-solid fa-compact-disc me-2"></i>Order Items</h6>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Release Album</label>
                            <select name="release_id" class="form-select bg-dark text-white border-secondary" required>
                                <option value="">Select Album...</option>
                                <?php foreach($releases as $r): ?>
                                    <option value="<?= $r['ReleaseID'] ?>"><?= h($r['Title']) ?> - <?= h($r['ArtistName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Condition Grade</label>
                            <select name="condition" class="form-select bg-dark text-white border-secondary" required>
                                <?php foreach($conditionOptions as $cond): ?>
                                    <option value="<?= $cond ?>"><?= $cond ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" id="poQuantity" class="form-control bg-dark text-white border-secondary"
                                   min="1" value="10" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Unit Cost (Fixed)</label>
                            <input type="text" id="unitCostDisplay"
                                   class="form-control bg-secondary text-warning border-secondary fw-bold"
                                   value="¥25.00" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Suggested Price</label>
                            <input type="text" id="suggestedPriceDisplay"
                                   class="form-control bg-secondary text-info border-secondary"
                                   value="¥40.00" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sale Price (¥)</label>
                            <input type="number" name="sale_price" id="salePrice"
                                   class="form-control bg-dark text-white border-secondary"
                                   step="0.01" min="1" value="40.00" required>
                            <small class="text-muted">You decide final price</small>
                        </div>
                    </div>

                    <div class="alert alert-secondary">
                        <div class="row">
                            <div class="col-6">
                                <div class="d-flex justify-content-between">
                                    <span>Total Procurement Cost:</span>
                                    <span id="totalCostDisplay" class="fw-bold text-danger">¥250.00</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex justify-content-between">
                                    <span>Expected Revenue:</span>
                                    <span id="expectedRevenueDisplay" class="fw-bold text-success">¥400.00</span>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-2 pt-2 border-top border-secondary">
                            <span>Expected Profit:</span>
                            <span id="expectedProfitDisplay" class="fw-bold text-warning">¥150.00 (37.5%)</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold">
                        <i class="fa-solid fa-paper-plane me-1"></i>Issue PO
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../assets/js/pages/admin-procurement.js"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
