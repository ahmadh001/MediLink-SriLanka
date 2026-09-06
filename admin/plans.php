<?php
/**
 * Admin Subscription Plans Management (CRUD & Limits Adjuster)
 */

$pageTitle = 'Subscription Plans Management';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::check();
    $action = $_POST['action'] ?? '';

    // Action: Create Plan
    if ($action === 'create_plan') {
        $planName = trim($_POST['plan_name'] ?? '');
        $targetRole = $_POST['target_role'] ?? 'CLIENT';
        $price = floatval($_POST['price'] ?? 0.00);
        $duration = max(1, (int)($_POST['duration_days'] ?? 30));
        $maxBookings = max(1, (int)($_POST['max_book_per_month'] ?? 5));
        $searchRadius = max(1, (int)($_POST['search_radius_km'] ?? 10));
        $description = trim($_POST['description'] ?? '');

        if (empty($planName)) {
            setFlash('danger', 'Plan name is required.');
        } else {
            $ins = $db->prepare("
                INSERT INTO `SUBSCRIPTION_PLAN` (Plan_Name, Target_Role, Price, Duration_Days, Max_Book_per_Month, Search_Radius_KM, Description, Status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE')
            ");
            $ins->execute([$planName, $targetRole, $price, $duration, $maxBookings, $searchRadius, $description]);
            setFlash('success', 'New subscription plan created successfully.');
            redirect('admin/plans.php');
        }

    // Action: Update Plan
    } elseif ($action === 'update_plan') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $planName = trim($_POST['plan_name'] ?? '');
        $targetRole = $_POST['target_role'] ?? 'CLIENT';
        $price = floatval($_POST['price'] ?? 0.00);
        $duration = max(1, (int)($_POST['duration_days'] ?? 30));
        $maxBookings = max(1, (int)($_POST['max_book_per_month'] ?? 5));
        $searchRadius = max(1, (int)($_POST['search_radius_km'] ?? 10));
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'ACTIVE';

        if (empty($planName) || $planId <= 0) {
            setFlash('danger', 'Invalid plan details.');
        } else {
            $upd = $db->prepare("
                UPDATE `SUBSCRIPTION_PLAN` 
                SET Plan_Name = ?, Target_Role = ?, Price = ?, Duration_Days = ?, Max_Book_per_Month = ?, Search_Radius_KM = ?, Description = ?, Status = ?
                WHERE Plan_ID = ?
            ");
            $upd->execute([$planName, $targetRole, $price, $duration, $maxBookings, $searchRadius, $description, $status, $planId]);
            setFlash('success', "Plan #{$planId} updated successfully.");
            redirect('admin/plans.php');
        }

    // Action: Toggle Status
    } elseif ($action === 'toggle_status') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $newStatus = ($_POST['status'] ?? 'ACTIVE') === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
        $db->prepare("UPDATE `SUBSCRIPTION_PLAN` SET Status = ? WHERE Plan_ID = ?")->execute([$newStatus, $planId]);
        setFlash('info', "Plan #{$planId} status changed to {$newStatus}.");
        redirect('admin/plans.php');
    }
}

// Fetch all plans
$plans = $db->query("SELECT * FROM `SUBSCRIPTION_PLAN` ORDER BY Target_Role ASC, Price ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-tags-fill text-teal me-2"></i> Subscription Plans & Pricing</h2>
      <p class="text-muted small mb-0">Configure client monthly appointment quotas, LKR tier pricing, and Haversine search radii.</p>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-teal btn-sm" data-bs-toggle="modal" data-bs-target="#createPlanModal">
        <i class="bi bi-plus-lg me-1"></i> Add New Plan
      </button>
      <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Admin Dashboard
      </a>
    </div>
  </div>

  <!-- Plans Table Card -->
  <div class="card card-custom p-4 mb-4">
    <div class="table-responsive">
      <table class="table table-custom table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>ID</th>
            <th>Plan Name</th>
            <th>Target Role</th>
            <th>Price (LKR)</th>
            <th>Duration</th>
            <th>Monthly Quota</th>
            <th>Radius</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($plans as $p): ?>
            <tr>
              <td><code>#<?= $p['Plan_ID'] ?></code></td>
              <td>
                <div class="fw-bold"><?= e($p['Plan_Name']) ?></div>
                <small class="text-muted"><?= e($p['Description']) ?></small>
              </td>
              <td>
                <span class="badge <?= $p['Target_Role'] === 'CLIENT' ? 'bg-teal' : ($p['Target_Role'] === 'PROVIDER' ? 'bg-primary' : 'bg-secondary') ?>">
                  <?= e($p['Target_Role']) ?>
                </span>
              </td>
              <td><strong class="text-dark"><?= formatLKR($p['Price']) ?></strong></td>
              <td><?= $p['Duration_Days'] ?> Days</td>
              <td>
                <span class="badge bg-light text-dark border"><?= $p['Max_Book_per_Month'] ?> Appts/mo</span>
              </td>
              <td>
                <span class="badge bg-light text-teal border"><?= $p['Search_Radius_KM'] ?> km</span>
              </td>
              <td><?= renderStatusBadge($p['Status']) ?></td>
              <td class="text-end">
                <div class="d-inline-flex gap-1">
                  <!-- Edit Modal Button -->
                  <button type="button" class="btn btn-outline-teal btn-sm" data-bs-toggle="modal" data-bs-target="#editPlanModal_<?= $p['Plan_ID'] ?>">
                    <i class="bi bi-pencil-square"></i>
                  </button>
                  <!-- Toggle Status Button -->
                  <form method="POST" action="<?= url('admin/plans.php') ?>" class="d-inline">
                    <?= CSRF::inputField() ?>
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="plan_id" value="<?= $p['Plan_ID'] ?>">
                    <input type="hidden" name="status" value="<?= $p['Status'] ?>">
                    <button type="submit" class="btn btn-sm <?= $p['Status'] === 'ACTIVE' ? 'btn-outline-danger' : 'btn-outline-success' ?>" title="Toggle Active">
                      <?= $p['Status'] === 'ACTIVE' ? 'Deactivate' : 'Activate' ?>
                    </button>
                  </form>
                </div>
              </td>
            </tr>

            <!-- Edit Plan Modal -->
            <div class="modal fade" id="editPlanModal_<?= $p['Plan_ID'] ?>" tabindex="-1">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                  <form method="POST" action="<?= url('admin/plans.php') ?>">
                    <?= CSRF::inputField() ?>
                    <input type="hidden" name="action" value="update_plan">
                    <input type="hidden" name="plan_id" value="<?= $p['Plan_ID'] ?>">

                    <div class="modal-header bg-teal text-white">
                      <h5 class="modal-title fw-bold">Edit Plan #<?= $p['Plan_ID'] ?></h5>
                      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                      <div class="mb-3">
                        <label class="form-label small fw-semibold">Plan Name</label>
                        <input type="text" name="plan_name" class="form-control" value="<?= e($p['Plan_Name']) ?>" required>
                      </div>
                      <div class="row g-3 mb-3">
                        <div class="col-6">
                          <label class="form-label small fw-semibold">Target Role</label>
                          <select name="target_role" class="form-select">
                            <option value="CLIENT" <?= $p['Target_Role'] === 'CLIENT' ? 'selected' : '' ?>>CLIENT</option>
                            <option value="PROVIDER" <?= $p['Target_Role'] === 'PROVIDER' ? 'selected' : '' ?>>PROVIDER</option>
                            <option value="ALL" <?= $p['Target_Role'] === 'ALL' ? 'selected' : '' ?>>ALL</option>
                          </select>
                        </div>
                        <div class="col-6">
                          <label class="form-label small fw-semibold">Price (LKR)</label>
                          <input type="number" step="0.01" name="price" class="form-control" value="<?= $p['Price'] ?>" required>
                        </div>
                      </div>
                      <div class="row g-3 mb-3">
                        <div class="col-4">
                          <label class="form-label small fw-semibold">Days</label>
                          <input type="number" name="duration_days" class="form-control" value="<?= $p['Duration_Days'] ?>" required>
                        </div>
                        <div class="col-4">
                          <label class="form-label small fw-semibold">Max Appts</label>
                          <input type="number" name="max_book_per_month" class="form-control" value="<?= $p['Max_Book_per_Month'] ?>" required>
                        </div>
                        <div class="col-4">
                          <label class="form-label small fw-semibold">Radius (km)</label>
                          <input type="number" name="search_radius_km" class="form-control" value="<?= $p['Search_Radius_KM'] ?>" required>
                        </div>
                      </div>
                      <div class="mb-3">
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2"><?= e($p['Description']) ?></textarea>
                      </div>
                      <div class="mb-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select">
                          <option value="ACTIVE" <?= $p['Status'] === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE</option>
                          <option value="INACTIVE" <?= $p['Status'] === 'INACTIVE' ? 'selected' : '' ?>>INACTIVE</option>
                        </select>
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

<!-- Create Plan Modal -->
<div class="modal fade" id="createPlanModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= url('admin/plans.php') ?>">
        <?= CSRF::inputField() ?>
        <input type="hidden" name="action" value="create_plan">

        <div class="modal-header bg-teal text-white">
          <h5 class="modal-title fw-bold">Create Subscription Plan</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Plan Name</label>
            <input type="text" name="plan_name" class="form-control" placeholder="e.g. Executive Platinum Plan" required>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label small fw-semibold">Target User Role</label>
              <select name="target_role" class="form-select">
                <option value="CLIENT">CLIENT</option>
                <option value="PROVIDER">PROVIDER</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Price (LKR)</label>
              <input type="number" step="0.01" name="price" class="form-control" placeholder="3500.00" required>
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-4">
              <label class="form-label small fw-semibold">Duration (Days)</label>
              <input type="number" name="duration_days" class="form-control" value="30" required>
            </div>
            <div class="col-4">
              <label class="form-label small fw-semibold">Max Appts/Mo</label>
              <input type="number" name="max_book_per_month" class="form-control" value="15" required>
            </div>
            <div class="col-4">
              <label class="form-label small fw-semibold">Search Radius (km)</label>
              <input type="number" name="search_radius_km" class="form-control" value="25" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Plan Description</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Key benefits and target demographic..."></textarea>
          </div>
        </div>
        <div class="modal-footer bg-light border-0">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-teal btn-sm px-4 fw-semibold">Create Plan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
