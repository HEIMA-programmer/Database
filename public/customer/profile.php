<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Customer');
require_once __DIR__ . '/../../includes/header.php';

$userId = $_SESSION['user_id'];
$message = '';

// 处理个人信息更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newName = trim($_POST['name']);
    $newPass = trim($_POST['password']);
    
    if ($newName) {
        if (!empty($newPass)) {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE Customer SET Name = ?, PasswordHash = ? WHERE CustomerID = ?");
            $stmt->execute([$newName, $hash, $userId]);
        } else {
            $stmt = $pdo->prepare("UPDATE Customer SET Name = ? WHERE CustomerID = ?");
            $stmt->execute([$newName, $userId]);
        }
        $_SESSION['username'] = $newName; // 更新 Session 显示
        flash("Profile updated successfully.", 'success');
        header("Location: profile.php");
        exit();
    }
}

// [Phase 2 Fix] 使用视图获取个人信息
// 视图: vw_customer_profile_info
$sql = "SELECT * FROM vw_customer_profile_info WHERE CustomerID = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$user = $stmt->fetch();

// 计算升级进度
// 注意：查找下一级规则需要查规则表，这不是直接获取用户信息，允许使用Base Table查询配置数据
$currentPoints = $user['Points'];
$nextTargetRow = $pdo->query("SELECT MinPoints, TierName FROM MembershipTier WHERE MinPoints > $currentPoints ORDER BY MinPoints ASC LIMIT 1")->fetch();

$nextTarget = $nextTargetRow['MinPoints'] ?? 0;
$nextTierName = $nextTargetRow['TierName'] ?? 'Max Level';
$progress = 0;

if ($nextTarget > 0) {
    $progress = min(100, ($currentPoints / $nextTarget) * 100);
} else {
    $progress = 100; // 已是最高等级
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <h2 class="text-warning mb-4"><i class="fa-solid fa-id-card me-2"></i>My Membership Profile</h2>

        <div class="card bg-gradient-dark border-warning mb-4 shadow-lg position-relative overflow-hidden">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="text-secondary text-uppercase mb-1">Retro Echo Member</h5>
                        <h1 class="display-5 fw-bold text-white mb-0"><?= h($user['TierName']) ?></h1>
                        <p class="text-warning mt-2"><i class="fa-solid fa-tags me-1"></i><?= $user['DiscountRate']*100 ?>% Off Everything</p>
                    </div>
                    <div class="text-end">
                        <div class="display-6 text-white"><?= number_format($currentPoints) ?></div>
                        <small class="text-muted">Current Points</small>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-light-50">Progress to <?= h($nextTierName) ?></span>
                        <span class="text-white"><?= $nextTarget ? ($nextTarget - $currentPoints) . ' pts to go' : 'Top Tier' ?></span>
                    </div>
                    <div class="progress bg-secondary" style="height: 10px;">
                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $progress ?>%"></div>
                    </div>
                </div>
            </div>
            <div class="position-absolute top-0 end-0 translate-middle p-5 rounded-circle bg-warning opacity-10" style="margin-right: -20px; margin-top: -20px;"></div>
        </div>

        <div class="row g-4">
            <div class="col-md-7">
                <div class="card bg-secondary text-light h-100">
                    <div class="card-header bg-dark border-secondary">Update Details</div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="name" class="form-control bg-dark text-white border-secondary" value="<?= h($user['Name']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control bg-dark text-white-50 border-secondary" value="<?= h($user['Email']) ?>" disabled>
                                <div class="form-text text-light-50">Email cannot be changed. Contact support for help.</div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">New Password (Optional)</label>
                                <input type="password" name="password" class="form-control bg-dark text-white border-secondary" placeholder="Leave blank to keep current">
                            </div>
                            <button type="submit" class="btn btn-info w-100">Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-header border-secondary">Quick Actions</div>
                    <div class="list-group list-group-flush bg-dark">
                        <a href="orders.php" class="list-group-item list-group-item-action bg-dark text-light border-secondary">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <span><i class="fa-solid fa-box-open me-2 text-warning"></i>Order History</span>
                                <i class="fa-solid fa-chevron-right small"></i>
                            </div>
                        </a>
                        <a href="cart.php" class="list-group-item list-group-item-action bg-dark text-light border-secondary">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <span><i class="fa-solid fa-cart-shopping me-2 text-info"></i>Shopping Cart</span>
                                <i class="fa-solid fa-chevron-right small"></i>
                            </div>
                        </a>
                        <a href="/logout.php" class="list-group-item list-group-item-action bg-dark text-danger border-secondary">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <span><i class="fa-solid fa-right-from-bracket me-2"></i>Sign Out</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>