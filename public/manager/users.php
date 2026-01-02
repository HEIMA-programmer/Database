<?php
/**
 * Manager Users Management Page
 * Only shows self and shop staff, can only add/edit/delete staff in own shop
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('Manager');

// Get manager's shop ID
$managerShopId = $_SESSION['user']['ShopID'] ?? $_SESSION['shop_id'] ?? null;
$managerId = $_SESSION['user_id'];

if (!$managerShopId) {
    flash('Unable to determine your shop. Please log in again.', 'danger');
    header("Location: " . BASE_URL . "/logout.php");
    exit();
}

// ========== POST Request Handling ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['add_employee']) ? 'add' :
             (isset($_POST['edit_employee']) ? 'edit' :
             (isset($_POST['delete_employee']) ? 'delete' : ''));

    $data = [
        'employee_id'     => $_POST['employee_id'] ?? null,
        'name'            => trim($_POST['name'] ?? ''),
        'username'        => trim($_POST['username'] ?? ''),
        'password'        => $_POST['password'] ?? '',
        'role'            => 'Staff', // Manager can only add Staff
        'current_user_id' => $managerId
    ];

    // Validation
    $validationError = null;
    if ($action === 'add') {
        if (empty($data['name'])) {
            $validationError = 'Staff name is required.';
        } elseif (empty($data['username'])) {
            $validationError = 'Username is required.';
        } elseif (empty($data['password'])) {
            $validationError = 'Password is required for new staff.';
        }
    } elseif ($action === 'edit') {
        if (empty($data['employee_id'])) {
            $validationError = 'Invalid employee ID.';
        } elseif (empty($data['name'])) {
            $validationError = 'Name is required.';
        }
    } elseif ($action === 'delete') {
        if (empty($data['employee_id'])) {
            $validationError = 'Invalid employee ID.';
        }
    }

    if ($validationError) {
        flash($validationError, 'danger');
    } else {
        $result = handleManagerEmployeeAction($pdo, $action, $data, $managerShopId);
        flash($result['message'], $result['success'] ? 'success' : 'danger');
    }

    header("Location: users.php");
    exit();
}

// ========== Data Preparation ==========
$pageData = prepareManagerUsersPageData($pdo, $managerShopId, $managerId);
$employees = $pageData['employees'];
$shopName = $_SESSION['shop_name'] ?? 'Your Shop';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-warning"><i class="fa-solid fa-users me-2"></i>Staff Management</h2>
    <span class="badge bg-secondary fs-6"><i class="fa-solid fa-store me-1"></i><?= h($shopName) ?></span>
</div>

<div class="alert alert-info mb-4">
    <i class="fa-solid fa-circle-info me-2"></i>
    As a manager, you can add, edit, and dismiss staff members for your shop.
</div>

<div class="card bg-dark border-secondary">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-user-tie me-2"></i>Employees (<?= count($employees) ?>)</span>
        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#addStaffModal">
            <i class="fa-solid fa-user-plus me-2"></i>Add Staff
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Name / Username</th>
                    <th>Role</th>
                    <th>Hire Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                <tr>
                    <td colspan="4" class="text-center text-muted py-4">No employees found.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($employees as $e): ?>
                <tr>
                    <td>
                        <div class="fw-bold">
                            <?= h($e['Name']) ?>
                            <?php if ($e['EmployeeID'] == $managerId): ?>
                                <span class="badge bg-warning text-dark ms-2">You</span>
                            <?php endif; ?>
                        </div>
                        <div class="small text-muted">@<?= h($e['Username']) ?></div>
                    </td>
                    <td>
                        <?php
                        $badge = match($e['Role']) {
                            'Admin' => 'bg-danger',
                            'Manager' => 'bg-warning text-dark',
                            default => 'bg-info text-dark'
                        };
                        ?>
                        <span class="badge <?= $badge ?>"><?= h($e['Role']) ?></span>
                    </td>
                    <td><?= $e['HireDate'] ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-info me-1 edit-btn"
                                data-bs-toggle="modal"
                                data-bs-target="#editStaffModal"
                                data-id="<?= $e['EmployeeID'] ?>"
                                data-name="<?= h($e['Name']) ?>"
                                data-is-self="<?= $e['EmployeeID'] == $managerId ? '1' : '0' ?>">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>

                        <?php if ($e['EmployeeID'] != $managerId && $e['Role'] === 'Staff'): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to dismiss this staff member?');">
                            <input type="hidden" name="delete_employee" value="1">
                            <input type="hidden" name="employee_id" value="<?= $e['EmployeeID'] ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Dismiss">
                                <i class="fa-solid fa-user-minus"></i>
                            </button>
                        </form>
                        <?php else: ?>
                        <button class="btn btn-sm btn-outline-secondary" disabled title="Cannot dismiss">
                            <i class="fa-solid fa-user-minus"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-secondary text-light">
            <div class="modal-header border-dark">
                <h5 class="modal-title"><i class="fa-solid fa-user-plus me-2"></i>Add New Staff</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="add_employee" value="1">

                    <div class="alert alert-info small mb-3">
                        <i class="fa-solid fa-info-circle me-1"></i>
                        New staff will be assigned to <strong><?= h($shopName) ?></strong>
                    </div>

                    <div class="mb-3">
                        <label>Full Name</label>
                        <input type="text" name="name" class="form-control bg-dark text-white border-secondary" required>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label>Username</label>
                            <input type="text" name="username" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-dark">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold">Add Staff</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Staff Modal -->
<div class="modal fade" id="editStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-secondary text-light">
            <div class="modal-header border-dark">
                <h5 class="modal-title"><i class="fa-solid fa-user-edit me-2"></i>Edit Employee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="edit_employee" value="1">
                    <input type="hidden" name="employee_id" id="edit_emp_id">

                    <div id="edit_self_notice" class="alert alert-warning small mb-3" style="display: none;">
                        <i class="fa-solid fa-exclamation-triangle me-1"></i>
                        You can only edit your name and password.
                    </div>

                    <div class="mb-3">
                        <label>Full Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control bg-dark text-white border-secondary" required>
                    </div>

                    <div class="mb-3">
                        <label>Reset Password (Optional)</label>
                        <input type="password" name="password" class="form-control bg-dark text-white border-secondary" placeholder="Leave blank to keep current">
                    </div>
                </div>
                <div class="modal-footer border-dark">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-dark fw-bold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_emp_id').value = this.dataset.id;
            document.getElementById('edit_name').value = this.dataset.name;

            // Show notice if editing self
            const notice = document.getElementById('edit_self_notice');
            if (this.dataset.isSelf === '1') {
                notice.style.display = 'block';
            } else {
                notice.style.display = 'none';
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
