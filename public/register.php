<?php
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();

// 如果已登录，直接跳转
if (isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birthday = $_POST['birthday'] ?? null;
    $password = $_POST['password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    // 1. 基础验证
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirmPass) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        try {
            // 2. 检查邮箱是否已存在
            $stmt = $pdo->prepare("SELECT CustomerID FROM Customer WHERE Email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'This email is already registered. Please login.';
            } else {
                // 3. 创建新用户
                // 获取默认等级 (Standard)
                $tierStmt = $pdo->query("SELECT TierID FROM MembershipTier ORDER BY MinPoints ASC LIMIT 1");
                $defaultTierId = $tierStmt->fetchColumn() ?: 1;

                $hash = password_hash($password, PASSWORD_DEFAULT);
                
                $sql = "INSERT INTO Customer (TierID, Name, Email, PasswordHash, Birthday, Points) 
                        VALUES (?, ?, ?, ?, ?, 0)";
                $insertStmt = $pdo->prepare($sql);
                $insertStmt->execute([$defaultTierId, $name, $email, $hash, $birthday ?: null]);
                
                $newUserId = $pdo->lastInsertId();

                // 4. 自动登录
                session_regenerate_id(true);
                $_SESSION['user_id']   = $newUserId;
                $_SESSION['username']  = $name;
                $_SESSION['role']      = 'Customer';
                $_SESSION['tier_id']   = $defaultTierId;
                if ($birthday) {
                    $_SESSION['birth_month'] = (int)date('m', strtotime($birthday));
                }

                flash("Welcome to Retro Echo, $name! Start your collection today.", 'success');
                header("Location: /customer/catalog.php");
                exit();
            }
        } catch (PDOException $e) {
            error_log("Registration Error: " . $e->getMessage());
            $error = 'System error during registration. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="col-md-6 col-lg-5">
        
        <div class="text-center mb-4">
            <h1 class="display-5 text-warning fw-bold mb-0">Join the Club</h1>
            <p class="text-secondary">Create your Retro Echo account</p>
        </div>

        <div class="card bg-secondary border-0 shadow-lg">
            <div class="card-body p-4 p-md-5">
                <?php if ($error): ?>
                    <div class="alert alert-danger bg-danger text-white border-0 py-2 small mb-4">
                        <i class="fa-solid fa-circle-exclamation me-2"></i><?= h($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label text-muted small text-uppercase">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" value="<?= h($_POST['name'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small text-uppercase">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" value="<?= h($_POST['email'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small text-uppercase">Birthday (Optional)</label>
                        <input type="date" class="form-control text-muted" name="birthday" value="<?= h($_POST['birthday'] ?? '') ?>">
                        <div class="form-text text-light-50">Enter your birthday to get exclusive discounts!</div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label text-muted small text-uppercase">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small text-uppercase">Confirm <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning w-100 py-2 fw-bold shadow-sm">
                        CREATE ACCOUNT
                    </button>
                </form>
            </div>
            <div class="card-footer bg-dark border-0 text-center py-3">
                <span class="text-secondary">Already a member?</span>
                <a href="login.php" class="text-warning fw-bold ms-1 text-decoration-none">Sign In</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>