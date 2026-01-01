<?php
/**
 * 注册页面
 * 【架构重构】遵循理想化分层架构
 * - 通过 functions.php 的注册函数处理注册逻辑
 * - 通过 DBProcedures 的存储过程处理数据库操作
 */
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

// 【Session管理修复】使用条件检查，避免重复调用session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 已登录用户直接跳转
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

$error = '';

// =============================================
// 【业务逻辑层调用】通过 functions.php 处理注册
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birthday = $_POST['birthday'] ?? null;
    $password = $_POST['password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    // 基础验证
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirmPass) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // 调用注册函数（通过存储过程）
        $result = registerNewCustomer($pdo, $name, $email, $password, $birthday ?: null);

        if ($result['success']) {
            flash("Welcome to Retro Echo, $name! Start your collection today.", 'success');
            header("Location: " . BASE_URL . "/customer/catalog.php");
            exit();
        } else {
            $error = $result['message'];
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- =============================================
     【表现层】仅负责 HTML 渲染
     ============================================= -->

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
