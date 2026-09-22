<?php


$pageTitle = 'Subscription Plans Management';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();
$currentAdmin = getCurrentUser();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::check();
    $action = $_POST['action'] ?? '';

    
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

    
    } elseif ($action === 'toggle_status') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $newStatus = ($_POST['status'] ?? 'ACTIVE') === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
        $db->prepare("UPDATE `SUBSCRIPTION_PLAN` SET Status = ? WHERE Plan_ID = ?")->execute([$newStatus, $planId]);
        setFlash('info', "Plan #{$planId} status changed to {$newStatus}.");
        redirect('admin/plans.php');
    }
}


$plans = $db->query("SELECT * FROM `SUBSCRIPTION_PLAN` ORDER BY Target_Role ASC, Price ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.plan-admin-hero{background:linear-gradient(135deg,rgba(8,127,120,.09),rgba(255,255,255,.96));border:1px solid rgba(8,127,120,.14);border-radius:22px;padding:1.4rem}
.plan-summary{border:1px solid #e8ecef;border-radius:18px;background:#fff;padding:1rem;height:100%}.plan-summary i{width:38px;height:38px;border-radius:12px;display:inline-grid;place-items:center;background:rgba(8,127,120,.1);color:#087f78}
.admin-plan-card{border:1px solid #e8ecef;border-radius:20px;background:#fff;padding:1.25rem;height:100%;transition:.18s ease}.admin-plan-card:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(22,42,54,.08)}
.plan-price{font-size:1.55rem;font-weight:800}.plan-meta{display:grid;grid-template-columns:repeat(3,1fr);gap:.65rem}.plan-meta>div{background:#f8fafb;border-radius:13px;padding:.7rem}.plan-meta small{display:block;color:#6c757d;margin-bottom:.15rem}.plan-meta strong{font-size:.9rem}.role-pill{display:inline-flex;align-items:center;gap:.35rem;border-radius:999px;padding:.32rem .65rem;font-size:.76rem;font-weight:700;background:#eef8f7;color:#087f78}.role-pill.provider{background:#eef4ff;color:#315ca8}.role-pill.all{background:#f2f2f2;color:#555}
.plan-desc{min-height:44px}.modal-content{border-radius:20px!important;overflow:hidden}.modal-header{background:#fff!important;color:#212529!important;border-bottom:1px solid #eef1f3}.modal-header .btn-close{filter:none!important}.form-hint{font-size:.76rem;color:#7a858d}
@media(max-width:575.98px){.plan-meta{grid-template-columns:1fr}.plan-admin-hero{padding:1rem}}
</style>
<?php
$activeCount = count(array_filter($plans, fn($p) => $p['Status'] === 'ACTIVE'));
$clientCount = count(array_filter($plans, fn($p) => $p['Target_Role'] === 'CLIENT'));
$providerCount = count(array_filter($plans, fn($p) => $p['Target_Role'] === 'PROVIDER'));
?>
<div class="container py-4 py-lg-5">
  <section class="plan-admin-hero mb-4">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
      <div>
        <div class="text-teal small fw-semibold mb-2"><i class="bi bi-sliders me-1"></i> PLAN CONFIGURATION</div>
        <h2 class="fw-bold mb-1">Subscription Plans</h2>
        <p class="text-muted mb-0">Create and maintain pricing, duration and access limits for MediLink subscriptions.</p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
        <button class="btn btn-teal" data-bs-toggle="modal" data-bs-target="#createPlanModal"><i class="bi bi-plus-lg me-1"></i> New Plan</button>
      </div>
    </div>
  </section>

  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="plan-summary"><i class="bi bi-tags"></i><div class="mt-2 text-muted small">Total Plans</div><div class="fs-4 fw-bold"><?= count($plans) ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="plan-summary"><i class="bi bi-check-circle"></i><div class="mt-2 text-muted small">Active</div><div class="fs-4 fw-bold"><?= $activeCount ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="plan-summary"><i class="bi bi-person"></i><div class="mt-2 text-muted small">Client Plans</div><div class="fs-4 fw-bold"><?= $clientCount ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="plan-summary"><i class="bi bi-building"></i><div class="mt-2 text-muted small">Provider Plans</div><div class="fs-4 fw-bold"><?= $providerCount ?></div></div></div>
  </div>

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div><h5 class="fw-bold mb-0">Plan catalogue</h5><small class="text-muted">Only active plans are available for new subscriptions.</small></div>
  </div>
  <div class="row g-3">
    <?php foreach ($plans as $p): ?>
      <?php $roleClass = $p['Target_Role']==='PROVIDER'?'provider':($p['Target_Role']==='ALL'?'all':''); ?>
      <div class="col-12 col-lg-6">
        <article class="admin-plan-card">
          <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
            <div>
              <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <span class="role-pill <?= $roleClass ?>"><i class="bi <?= $p['Target_Role']==='PROVIDER'?'bi-building':'bi-person' ?>"></i><?= e(ucfirst(strtolower($p['Target_Role']))) ?></span>
                <?= renderStatusBadge($p['Status']) ?>
                <span class="text-muted small">#<?= (int)$p['Plan_ID'] ?></span>
              </div>
              <h5 class="fw-bold mb-1"><?= e($p['Plan_Name']) ?></h5>
              <p class="text-muted small mb-0 plan-desc"><?= e($p['Description'] ?: 'No description added.') ?></p>
            </div>
            <div class="text-end flex-shrink-0"><div class="plan-price"><?= formatLKR($p['Price']) ?></div><small class="text-muted"><?= (int)$p['Duration_Days'] ?> days</small></div>
          </div>
          <div class="plan-meta mb-3">
            <div><small><i class="bi bi-calendar-check me-1"></i>Monthly bookings</small><strong><?= (int)$p['Max_Book_per_Month'] ?></strong></div>
            <div><small><i class="bi bi-geo-alt me-1"></i>Search radius</small><strong><?= (int)$p['Search_Radius_KM'] ?> km</strong></div>
            <div><small><i class="bi bi-clock me-1"></i>Duration</small><strong><?= (int)$p['Duration_Days'] ?> days</strong></div>
          </div>
          <div class="d-flex justify-content-end gap-2 pt-2 border-top">
            <button class="btn btn-outline-teal btn-sm" data-bs-toggle="modal" data-bs-target="#editPlanModal_<?= (int)$p['Plan_ID'] ?>"><i class="bi bi-pencil me-1"></i>Edit</button>
            <form method="POST" action="<?= url('admin/plans.php') ?>" class="d-inline">
              <?= CSRF::inputField() ?><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="plan_id" value="<?= (int)$p['Plan_ID'] ?>"><input type="hidden" name="status" value="<?= e($p['Status']) ?>">
              <button class="btn btn-sm <?= $p['Status']==='ACTIVE'?'btn-outline-danger':'btn-outline-success' ?>" type="submit"><i class="bi <?= $p['Status']==='ACTIVE'?'bi-pause-circle':'bi-play-circle' ?> me-1"></i><?= $p['Status']==='ACTIVE'?'Deactivate':'Activate' ?></button>
            </form>
          </div>
        </article>
      </div>

      <div class="modal fade" id="editPlanModal_<?= (int)$p['Plan_ID'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><form method="POST" action="<?= url('admin/plans.php') ?>">
          <?= CSRF::inputField() ?><input type="hidden" name="action" value="update_plan"><input type="hidden" name="plan_id" value="<?= (int)$p['Plan_ID'] ?>">
          <div class="modal-header"><div><h5 class="modal-title fw-bold">Edit <?= e($p['Plan_Name']) ?></h5><small class="text-muted">Plan #<?= (int)$p['Plan_ID'] ?></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body p-4">
            <div class="row g-3">
              <div class="col-md-8"><label class="form-label fw-semibold">Plan name</label><input name="plan_name" class="form-control" value="<?= e($p['Plan_Name']) ?>" required></div>
              <div class="col-md-4"><label class="form-label fw-semibold">Target role</label><select name="target_role" class="form-select"><option value="CLIENT" <?= $p['Target_Role']==='CLIENT'?'selected':'' ?>>Client</option><option value="PROVIDER" <?= $p['Target_Role']==='PROVIDER'?'selected':'' ?>>Provider</option><option value="ALL" <?= $p['Target_Role']==='ALL'?'selected':'' ?>>All</option></select></div>
              <div class="col-md-3"><label class="form-label fw-semibold">Price (LKR)</label><input type="number" min="0" step="0.01" name="price" class="form-control" value="<?= e($p['Price']) ?>" required></div>
              <div class="col-md-3"><label class="form-label fw-semibold">Duration</label><input type="number" min="1" name="duration_days" class="form-control" value="<?= (int)$p['Duration_Days'] ?>" required><div class="form-hint">Days</div></div>
              <div class="col-md-3"><label class="form-label fw-semibold">Booking quota</label><input type="number" min="1" name="max_book_per_month" class="form-control" value="<?= (int)$p['Max_Book_per_Month'] ?>" required><div class="form-hint">Per month</div></div>
              <div class="col-md-3"><label class="form-label fw-semibold">Search radius</label><input type="number" min="1" name="search_radius_km" class="form-control" value="<?= (int)$p['Search_Radius_KM'] ?>" required><div class="form-hint">Kilometres</div></div>
              <div class="col-12"><label class="form-label fw-semibold">Description</label><textarea name="description" class="form-control" rows="3"><?= e($p['Description']) ?></textarea></div>
              <div class="col-md-4"><label class="form-label fw-semibold">Status</label><select name="status" class="form-select"><option value="ACTIVE" <?= $p['Status']==='ACTIVE'?'selected':'' ?>>Active</option><option value="INACTIVE" <?= $p['Status']==='INACTIVE'?'selected':'' ?>>Inactive</option></select></div>
            </div>
          </div>
          <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-teal" type="submit"><i class="bi bi-check-lg me-1"></i>Save Changes</button></div>
        </form></div></div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (!$plans): ?><div class="text-center border rounded-4 p-5 bg-white"><i class="bi bi-tags fs-1 text-muted"></i><h5 class="mt-3">No plans yet</h5><p class="text-muted">Create the first subscription plan to get started.</p><button class="btn btn-teal" data-bs-toggle="modal" data-bs-target="#createPlanModal">Create Plan</button></div><?php endif; ?>
</div>

<div class="modal fade" id="createPlanModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><form method="POST" action="<?= url('admin/plans.php') ?>">
  <?= CSRF::inputField() ?><input type="hidden" name="action" value="create_plan">
  <div class="modal-header"><div><h5 class="modal-title fw-bold">Create Subscription Plan</h5><small class="text-muted">Define the plan's pricing and access limits.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body p-4"><div class="row g-3">
    <div class="col-md-8"><label class="form-label fw-semibold">Plan name</label><input name="plan_name" class="form-control" placeholder="e.g. Client Plus" required></div>
    <div class="col-md-4"><label class="form-label fw-semibold">Target role</label><select name="target_role" class="form-select"><option value="CLIENT">Client</option><option value="PROVIDER">Provider</option></select></div>
    <div class="col-md-3"><label class="form-label fw-semibold">Price (LKR)</label><input type="number" min="0" step="0.01" name="price" class="form-control" placeholder="0.00" required></div>
    <div class="col-md-3"><label class="form-label fw-semibold">Duration</label><input type="number" min="1" name="duration_days" class="form-control" value="30" required><div class="form-hint">Days</div></div>
    <div class="col-md-3"><label class="form-label fw-semibold">Booking quota</label><input type="number" min="1" name="max_book_per_month" class="form-control" value="15" required><div class="form-hint">Per month</div></div>
    <div class="col-md-3"><label class="form-label fw-semibold">Search radius</label><input type="number" min="1" name="search_radius_km" class="form-control" value="25" required><div class="form-hint">Kilometres</div></div>
    <div class="col-12"><label class="form-label fw-semibold">Description</label><textarea name="description" class="form-control" rows="3" placeholder="Briefly explain what this plan provides."></textarea></div>
  </div></div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-teal" type="submit"><i class="bi bi-plus-lg me-1"></i>Create Plan</button></div>
</form></div></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
