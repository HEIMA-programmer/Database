<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Admin');
require_once __DIR__ . '/../../includes/header.php';

// --- 处理表单提交 ---

// 1. 添加供应商
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_supplier'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO Supplier (Name, Email) VALUES (?, ?)");
        $stmt->execute([trim($_POST['name']), trim($_POST['email'])]);
        flash("Supplier added successfully.", 'success');
    } catch (PDOException $e) {
        flash("Error adding supplier: " . $e->getMessage(), 'danger');
    }
    header("Location: suppliers.php");
    exit();
}

// 2. 编辑供应商
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_supplier'])) {
    try {
        $stmt = $pdo->prepare("UPDATE Supplier SET Name = ?, Email = ? WHERE SupplierID = ?");
        $stmt->execute([trim($_POST['name']), trim($_POST['email']), $_POST['supplier_id']]);
        flash("Supplier details updated.", 'success');
    } catch (PDOException $e) {
        flash("Error updating supplier: " . $e->getMessage(), 'danger');
    }
    header("Location: suppliers.php");
    exit();
}

// 3. 删除供应商 (工业级：先检查依赖)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_supplier'])) {
    $id = $_POST['supplier_id'];

    // 检查是否有关联的供应商订单
    $check = $pdo->prepare("SELECT COUNT(*) FROM SupplierOrder WHERE SupplierID = ?");
    $check->execute([$id]);

    if ($check->fetchColumn() > 0) {
        flash("Cannot delete supplier: There are existing Supplier Orders linked to this supplier.", 'danger');
    } else {
        try {
            $pdo->prepare("DELETE FROM Supplier WHERE SupplierID = ?")->execute([$id]);
            flash("Supplier deleted successfully.", 'warning');
        } catch (PDOException $e) {
            flash("Delete failed: " . $e->getMessage(), 'danger');
        }
    }
    header("Location: suppliers.php");
    exit();
}

// --- 数据查询 ---
$suppliers = $pdo->query("SELECT * FROM Supplier ORDER BY Name ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-warning"><i class="fa-solid fa-truck-field me-2"></i>Supplier Management</h2>
    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="fa-solid fa-plus me-2"></i>New Supplier
    </button>
</div>

<div class="card bg-dark border-secondary">
    <div class="table-responsive">
        <table class="table table-dark table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Company Name</th>
                    <th>Contact Email</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suppliers as $s): ?>
                <tr>
                    <td>#<?= $s['SupplierID'] ?></td>
                    <td class="fw-bold text-white"><?= h($s['Name']) ?></td>
                    <td class="text-info"><?= h($s['Email']) ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-info me-2 edit-btn" 
                                data-bs-toggle="modal" 
                                data-bs-target="#editModal"
                                data-id="<?= $s['SupplierID'] ?>"
                                data-name="<?= h($s['Name']) ?>"
                                data-email="<?= h($s['Email']) ?>">
                            <i class="fa-solid fa-pen-to-square"></i> Edit
                        </button>
                        
                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure? This action cannot be undone.');">
                            <input type="hidden" name="delete_supplier" value="1">
                            <input type="hidden" name="supplier_id" value="<?= $s['SupplierID'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($suppliers)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No suppliers found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-secondary text-light">
            <div class="modal-header border-dark">
                <h5 class="modal-title">Add Supplier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="add_supplier" value="1">
                    <div class="mb-3">
                        <label>Company Name</label>
                        <input type="text" name="name" class="form-control bg-dark text-white border-secondary" required>
                    </div>
                    <div class="mb-3">
                        <label>Contact Email</label>
                        <input type="email" name="email" class="form-control bg-dark text-white border-secondary" required>
                    </div>
                </div>
                <div class="modal-footer border-dark">
                    <button type="submit" class="btn btn-warning text-dark fw-bold">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-secondary text-light">
            <div class="modal-header border-dark">
                <h5 class="modal-title">Edit Supplier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="edit_supplier" value="1">
                    <input type="hidden" name="supplier_id" id="edit_id">
                    <div class="mb-3">
                        <label>Company Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control bg-dark text-white border-secondary" required>
                    </div>
                    <div class="mb-3">
                        <label>Contact Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control bg-dark text-white border-secondary" required>
                    </div>
                </div>
                <div class="modal-footer border-dark">
                    <button type="submit" class="btn btn-info text-dark fw-bold">Update Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // JS Logic to populate Edit Modal
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.dataset.id;
            document.getElementById('edit_name').value = this.dataset.name;
            document.getElementById('edit_email').value = this.dataset.email;
        });
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>