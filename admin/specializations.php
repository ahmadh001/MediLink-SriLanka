<?php
/**
 * Admin Medical Specialization CRUD Management
 */

$pageTitle = 'Medical Specializations';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();

// Handle Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::check();
    $action = $_POST['action'] ?? '';

    // Action: Create Specialization
    if ($action === 'create_specialization') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            setFlash('danger', 'Specialization name is required.');
        } else {
            // Check duplicate
            $chk = $db->prepare("SELECT Specialization_ID FROM `SPECIALIZATION` WHERE Name = ?");
            $chk->execute([$name]);
            if ($chk->fetch()) {
                setFlash('danger', 'A specialization with this name already exists.');
            } else {
                $ins = $db->prepare("INSERT INTO `SPECIALIZATION` (Name, Description) VALUES (?, ?)");
                $ins->execute([$name, $description]);
                setFlash('success', "Specialization '{$name}' added successfully.");
            }
        }
        redirect('admin/specializations.php');

    // Action: Update Specialization
    } elseif ($action === 'update_specialization') {
        $specId = (int)($_POST['specialization_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name) || $specId <= 0) {
            setFlash('danger', 'Invalid specialization details.');
        } else {
            $upd = $db->prepare("UPDATE `SPECIALIZATION` SET Name = ?, Description = ? WHERE Specialization_ID = ?");
            $upd->execute([$name, $description, $specId]);
            setFlash('success', "Specialization #{$specId} updated successfully.");
        }
        redirect('admin/specializations.php');

    // Action: Delete Specialization
    } elseif ($action === 'delete_specialization') {
        $specId = (int)($_POST['specialization_id'] ?? 0);
        $del = $db->prepare("DELETE FROM `SPECIALIZATION` WHERE Specialization_ID = ?");
        $del->execute([$specId]);
        setFlash('success', 'Specialization deleted successfully.');
        redirect('admin/specializations.php');
    }
}

// Fetch all specializations with doctor counts
$specializations = $db->query("
    SELECT s.*, 
           COUNT(ds.Doctor_ID) AS linked_doctors_count
    FROM `SPECIALIZATION` s
    LEFT JOIN `DOCTOR_SPECIALIZATION` ds ON s.Specialization_ID = ds.Specialization_ID
    GROUP BY s.Specialization_ID
    ORDER BY s.Name ASC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-heart-pulse-fill text-teal me-2"></i> Medical Specializations</h2>
      <p class="text-muted small mb-0">Manage clinical disciplines, departments, and search categorization filters.</p>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-teal btn-sm" data-bs-toggle="modal" data-bs-target="#createSpecModal">
        <i class="bi bi-plus-lg me-1"></i> Add Specialization
      </button>
      <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Admin Dashboard
      </a>
    </div>
  </div>

  <div class="card card-custom p-4">
    <div class="table-responsive">
      <table class="table table-custom table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>ID</th>
            <th>Specialization Name</th>
            <th>Description</th>
            <th>Affiliated Doctors</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($specializations as $sp): ?>
            <tr>
              <td><code>#<?= $sp['Specialization_ID'] ?></code></td>
              <td><strong class="text-teal"><?= e($sp['Name']) ?></strong></td>
              <td><small class="text-secondary"><?= e($sp['Description']) ?></small></td>
              <td>
                <span class="badge bg-light text-dark border">
                  <i class="bi bi-person-badge me-1"></i> <?= $sp['linked_doctors_count'] ?> Doctor(s)
                </span>
              </td>
              <td class="text-end">
                <div class="d-inline-flex gap-1">
                  <!-- Edit Modal Trigger -->
                  <button type="button" class="btn btn-outline-teal btn-sm" data-bs-toggle="modal" data-bs-target="#editSpecModal_<?= $sp['Specialization_ID'] ?>">
                    <i class="bi bi-pencil"></i>
                  </button>

                  <!-- Delete Form -->
                  <form method="POST" action="<?= url('admin/specializations.php') ?>" class="d-inline" onsubmit="return confirm('Delete specialization <?= e($sp['Name']) ?>?');">
                    <?= CSRF::inputField() ?>
                    <input type="hidden" name="action" value="delete_specialization">
                    <input type="hidden" name="specialization_id" value="<?= $sp['Specialization_ID'] ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>

            <!-- Edit Specialization Modal -->
            <div class="modal fade" id="editSpecModal_<?= $sp['Specialization_ID'] ?>" tabindex="-1">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                  <form method="POST" action="<?= url('admin/specializations.php') ?>">
                    <?= CSRF::inputField() ?>
                    <input type="hidden" name="action" value="update_specialization">
                    <input type="hidden" name="specialization_id" value="<?= $sp['Specialization_ID'] ?>">

                    <div class="modal-header bg-teal text-white">
                      <h5 class="modal-title fw-bold">Edit Specialization</h5>
                      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                      <div class="mb-3">
                        <label class="form-label small fw-semibold">Specialization Name</label>
                        <input type="text" name="name" class="form-control" value="<?= e($sp['Name']) ?>" required>
                      </div>
                      <div class="mb-3">
                        <label class="form-label small fw-semibold">Clinical Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= e($sp['Description']) ?></textarea>
                      </div>
                    </div>
                    <div class="modal-footer bg-light border-0">
                      <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-teal btn-sm px-3">Save Changes</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Create Specialization Modal -->
<div class="modal fade" id="createSpecModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= url('admin/specializations.php') ?>">
        <?= CSRF::inputField() ?>
        <input type="hidden" name="action" value="create_specialization">

        <div class="modal-header bg-teal text-white">
          <h5 class="modal-title fw-bold">Add Medical Specialization</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Specialization Name</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Endocrinology & Diabetes" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Description</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Scope of practice, common treatments, diagnosis..."></textarea>
          </div>
        </div>
        <div class="modal-footer bg-light border-0">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-teal btn-sm px-4 fw-semibold">Save Specialization</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
