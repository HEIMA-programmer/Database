<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Admin');
require_once __DIR__ . '/../../includes/header.php';

// 处理添加新专辑 (INSERT 操作不需要视图，直接写入 Base Table)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_release'])) {
    try {
        $sql = "INSERT INTO ReleaseAlbum (Title, ArtistName, LabelName, ReleaseYear, Genre, Format, Description) 
                VALUES (:title, :artist, :label, :year, :genre, 'Vinyl', :desc)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':title' => $_POST['title'],
            ':artist' => $_POST['artist'],
            ':label' => $_POST['label'],
            ':year' => $_POST['year'],
            ':genre' => $_POST['genre'],
            ':desc' => $_POST['desc']
        ]);
        flash("New release added to catalog.", 'success');
        header("Location: products.php");
        exit();
    } catch (PDOException $e) {
        flash("Error adding release: " . $e->getMessage(), 'danger');
    }
}

// [Phase 2 Fix] 使用视图查询
// 视图: vw_admin_release_list
$releases = $pdo->query("SELECT * FROM vw_admin_release_list ORDER BY ReleaseID DESC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-warning">Global Product Catalog</h2>
    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="fa-solid fa-plus me-2"></i>Add New Release
    </button>
</div>

<div class="card bg-dark border-secondary">
    <div class="table-responsive">
        <table class="table table-dark table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Artist</th>
                    <th>Label / Year</th>
                    <th>Genre</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($releases as $r): ?>
                <tr>
                    <td><?= $r['ReleaseID'] ?></td>
                    <td class="fw-bold"><?= h($r['Title']) ?></td>
                    <td><?= h($r['ArtistName']) ?></td>
                    <td><?= h($r['LabelName']) ?> <small class="text-muted">(<?= $r['ReleaseYear'] ?>)</small></td>
                    <td><span class="badge bg-secondary"><?= h($r['Genre']) ?></span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-info">Edit</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-secondary text-light">
            <div class="modal-header border-dark">
                <h5 class="modal-title">Add New Release</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="add_release" value="1">
                    <div class="mb-2"><label>Title</label><input type="text" name="title" class="form-control bg-dark text-white" required></div>
                    <div class="mb-2"><label>Artist</label><input type="text" name="artist" class="form-control bg-dark text-white" required></div>
                    <div class="row">
                        <div class="col"><label>Label</label><input type="text" name="label" class="form-control bg-dark text-white" required></div>
                        <div class="col"><label>Year</label><input type="number" name="year" class="form-control bg-dark text-white" required></div>
                    </div>
                    <div class="mb-2"><label>Genre</label><input type="text" name="genre" class="form-control bg-dark text-white" required></div>
                    <div class="mb-2"><label>Description</label><textarea name="desc" class="form-control bg-dark text-white"></textarea></div>
                </div>
                <div class="modal-footer border-dark">
                    <button type="submit" class="btn btn-warning">Save Release</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>