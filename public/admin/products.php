<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Admin');
require_once __DIR__ . '/../../includes/header.php';

// --- Action: Add Release ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_release'])) {
    try {
        $sql = "INSERT INTO ReleaseAlbum (Title, ArtistName, LabelName, ReleaseYear, Genre, Format, Description) 
                VALUES (:title, :artist, :label, :year, :genre, 'Vinyl', :desc)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':title' => trim($_POST['title']),
            ':artist' => trim($_POST['artist']),
            ':label' => trim($_POST['label']),
            ':year' => trim($_POST['year']),
            ':genre' => trim($_POST['genre']),
            ':desc' => trim($_POST['desc'])
        ]);
        flash("New release added to catalog.", 'success');
        header("Location: products.php");
        exit();
    } catch (PDOException $e) {
        flash("Error adding release: " . $e->getMessage(), 'danger');
    }
}

// --- Action: Edit Release (New Feature) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_release'])) {
    try {
        $sql = "UPDATE ReleaseAlbum 
                SET Title = :title, ArtistName = :artist, LabelName = :label, 
                    ReleaseYear = :year, Genre = :genre, Description = :desc 
                WHERE ReleaseID = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':title' => trim($_POST['title']),
            ':artist' => trim($_POST['artist']),
            ':label' => trim($_POST['label']),
            ':year' => trim($_POST['year']),
            ':genre' => trim($_POST['genre']),
            ':desc' => trim($_POST['desc']),
            ':id' => $_POST['release_id']
        ]);
        flash("Release updated successfully.", 'success');
        header("Location: products.php");
        exit();
    } catch (PDOException $e) {
        flash("Error updating release: " . $e->getMessage(), 'danger');
    }
}

// [Phase 2 Fix] 使用视图查询
$releases = $pdo->query("SELECT * FROM vw_admin_release_list ORDER BY ReleaseID DESC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-warning"><i class="fa-solid fa-compact-disc me-2"></i>Global Product Catalog</h2>
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
                        <button class="btn btn-sm btn-outline-info edit-btn" 
                                data-bs-toggle="modal" 
                                data-bs-target="#editModal"
                                data-id="<?= $r['ReleaseID'] ?>"
                                data-title="<?= h($r['Title']) ?>"
                                data-artist="<?= h($r['ArtistName']) ?>"
                                data-label="<?= h($r['LabelName']) ?>"
                                data-year="<?= h($r['ReleaseYear']) ?>"
                                data-genre="<?= h($r['Genre']) ?>"
                                data-desc="<?= h($r['Description']) ?>">
                            <i class="fa-solid fa-pen-to-square"></i> Edit
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-secondary text-light">
            <div class="modal-header border-dark">
                <h5 class="modal-title">Add New Release</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="add_release" value="1">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label>Title</label>
                            <input type="text" name="title" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-6">
                            <label>Artist</label>
                            <input type="text" name="artist" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label>Label</label>
                            <input type="text" name="label" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-4">
                            <label>Year</label>
                            <input type="number" name="year" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-4">
                            <label>Genre</label>
                            <input type="text" name="genre" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <textarea name="desc" class="form-control bg-dark text-white border-secondary" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-dark">
                    <button type="submit" class="btn btn-warning fw-bold">Save Release</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-secondary text-light">
            <div class="modal-header border-dark">
                <h5 class="modal-title">Edit Release</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="edit_release" value="1">
                    <input type="hidden" name="release_id" id="edit_id">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label>Title</label>
                            <input type="text" name="title" id="edit_title" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-6">
                            <label>Artist</label>
                            <input type="text" name="artist" id="edit_artist" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label>Label</label>
                            <input type="text" name="label" id="edit_label" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-4">
                            <label>Year</label>
                            <input type="number" name="year" id="edit_year" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-4">
                            <label>Genre</label>
                            <input type="text" name="genre" id="edit_genre" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <textarea name="desc" id="edit_desc" class="form-control bg-dark text-white border-secondary" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-dark">
                    <button type="submit" class="btn btn-info text-dark fw-bold">Update Release</button>
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
            document.getElementById('edit_title').value = this.dataset.title;
            document.getElementById('edit_artist').value = this.dataset.artist;
            document.getElementById('edit_label').value = this.dataset.label;
            document.getElementById('edit_year').value = this.dataset.year;
            document.getElementById('edit_genre').value = this.dataset.genre;
            document.getElementById('edit_desc').value = this.dataset.desc;
        });
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>