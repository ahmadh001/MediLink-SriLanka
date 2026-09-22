<?php


$pageTitle = 'Medical Specializations';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::check();
    $action = $_POST['action'] ?? '';

    
    if ($action === 'create_specialization') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            setFlash('danger', 'Specialization name is required.');
        } else {
            
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

    
    } elseif ($action === 'update_specialization') {
        $specId = (int)($_POST['specialization_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name) || $specId <= 0) {
            setFlash('danger', 'Invalid specialization details.');
        } else {
            $chk = $db->prepare("SELECT Specialization_ID FROM `SPECIALIZATION` WHERE Name = ? AND Specialization_ID <> ?");
            $chk->execute([$name, $specId]);
            if ($chk->fetch()) {
                setFlash('danger', 'Another specialization already uses this name.');
            } else {
                $upd = $db->prepare("UPDATE `SPECIALIZATION` SET Name = ?, Description = ? WHERE Specialization_ID = ?");
                $upd->execute([$name, $description, $specId]);
                setFlash('success', "Specialization #{$specId} updated successfully.");
            }
        }
        redirect('admin/specializations.php');

    
    } elseif ($action === 'delete_specialization') {
        $specId = (int)($_POST['specialization_id'] ?? 0);
        $del = $db->prepare("DELETE FROM `SPECIALIZATION` WHERE Specialization_ID = ?");
        $del->execute([$specId]);
        setFlash('success', 'Specialization deleted successfully.');
        redirect('admin/specializations.php');
    }
}


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

<style>
.spec-shell{max-width:1180px}.spec-kpi,.spec-card{border:1px solid var(--bs-border-color);border-radius:1rem;background:#fff}.spec-kpi{padding:1rem 1.1rem;height:100%}.spec-icon{width:42px;height:42px;border-radius:.8rem;display:grid;place-items:center;background:rgba(8,127,120,.09);color:#087f78;font-size:1.1rem}.spec-card{padding:1.15rem;height:100%;transition:transform .18s ease,box-shadow .18s ease}.spec-card:hover{transform:translateY(-2px);box-shadow:0 .65rem 1.5rem rgba(31,41,55,.08)}.spec-desc{min-height:48px}.spec-search{max-width:420px}.spec-count{font-size:1.55rem;font-weight:750;line-height:1}.spec-empty{border:1px dashed var(--bs-border-color);border-radius:1rem;padding:3rem 1rem;text-align:center}.spec-id{font-size:.72rem;color:#6c757d}.spec-actions .btn{min-width:38px}@media(max-width:767.98px){.spec-search{max-width:none;width:100%}}
</style>

<div class="container py-4 spec-shell">
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
      <div class="text-uppercase small fw-semibold text-teal mb-1">Clinical catalogue</div>
      <h2 class="fw-bold mb-1">Medical Specializations</h2>
      <p class="text-muted mb-0">Maintain the disciplines used for doctor profiles and patient search filters.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
      <button class="btn btn-teal" data-bs-toggle="modal" data-bs-target="#createSpecModal"><i class="bi bi-plus-lg me-1"></i> Add specialization</button>
    </div>
  </div>

  <?php
    $totalSpecs = count($specializations);
    $linkedSpecs = count(array_filter($specializations, fn($sp) => (int)$sp['linked_doctors_count'] > 0));
    $doctorLinks = array_sum(array_map(fn($sp) => (int)$sp['linked_doctors_count'], $specializations));
  ?>
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-4"><div class="spec-kpi d-flex align-items-center gap-3"><div class="spec-icon"><i class="bi bi-heart-pulse"></i></div><div><div class="spec-count"><?= $totalSpecs ?></div><div class="small text-muted">Specializations</div></div></div></div>
    <div class="col-6 col-lg-4"><div class="spec-kpi d-flex align-items-center gap-3"><div class="spec-icon"><i class="bi bi-link-45deg"></i></div><div><div class="spec-count"><?= $linkedSpecs ?></div><div class="small text-muted">In use</div></div></div></div>
    <div class="col-12 col-lg-4"><div class="spec-kpi d-flex align-items-center gap-3"><div class="spec-icon"><i class="bi bi-person-badge"></i></div><div><div class="spec-count"><?= $doctorLinks ?></div><div class="small text-muted">Doctor links</div></div></div></div>
  </div>

  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
    <div class="input-group spec-search"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input id="specSearch" type="search" class="form-control" placeholder="Search specialization or description…" aria-label="Search specializations"></div>
    <div class="small text-muted"><span id="visibleSpecCount"><?= $totalSpecs ?></span> of <?= $totalSpecs ?> shown</div>
  </div>

  <div class="row g-3" id="specGrid">
    <?php foreach ($specializations as $sp): ?>
      <?php $linked=(int)$sp['linked_doctors_count']; ?>
      <div class="col-12 col-md-6 col-xl-4 spec-item" data-search="<?= e(strtolower($sp['Name'].' '.$sp['Description'])) ?>">
        <article class="spec-card d-flex flex-column">
          <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
            <div class="d-flex gap-3 align-items-center"><div class="spec-icon flex-shrink-0"><i class="bi bi-activity"></i></div><div><h5 class="mb-1 fw-bold"><?= e($sp['Name']) ?></h5><div class="spec-id">ID #<?= (int)$sp['Specialization_ID'] ?></div></div></div>
            <span class="badge rounded-pill <?= $linked ? 'bg-success-subtle text-success-emphasis' : 'bg-light text-secondary border' ?>"><?= $linked ? 'In use' : 'Unused' ?></span>
          </div>
          <p class="text-secondary small spec-desc mb-3"><?= $sp['Description'] ? e($sp['Description']) : 'No clinical description has been added yet.' ?></p>
          <div class="d-flex align-items-center justify-content-between mt-auto pt-3 border-top">
            <div class="small"><i class="bi bi-person-badge me-1 text-teal"></i><strong><?= $linked ?></strong> linked doctor<?= $linked===1?'':'s' ?></div>
            <div class="d-flex gap-1 spec-actions">
              <button type="button" class="btn btn-outline-teal btn-sm" data-bs-toggle="modal" data-bs-target="#editSpecModal_<?= (int)$sp['Specialization_ID'] ?>" title="Edit"><i class="bi bi-pencil"></i></button>
              <?php if ($linked === 0): ?>
                <form method="POST" action="<?= url('admin/specializations.php') ?>" class="d-inline" data-confirm="Delete specialization <?= e($sp['Name']) ?>?">
                  <?= CSRF::inputField() ?><input type="hidden" name="action" value="delete_specialization"><input type="hidden" name="specialization_id" value="<?= (int)$sp['Specialization_ID'] ?>">
                  <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete"><i class="bi bi-trash"></i></button>
                </form>
              <?php else: ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="Remove doctor links before deleting"><i class="bi bi-lock"></i></button>
              <?php endif; ?>
            </div>
          </div>
        </article>
      </div>

      <div class="modal fade" id="editSpecModal_<?= (int)$sp['Specialization_ID'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow"><form method="POST" action="<?= url('admin/specializations.php') ?>">
          <?= CSRF::inputField() ?><input type="hidden" name="action" value="update_specialization"><input type="hidden" name="specialization_id" value="<?= (int)$sp['Specialization_ID'] ?>">
          <div class="modal-header"><div><h5 class="modal-title fw-bold mb-0">Edit specialization</h5><small class="text-muted">Update the catalogue label and description.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body p-4"><div class="mb-3"><label class="form-label fw-semibold">Specialization name</label><input type="text" name="name" class="form-control" value="<?= e($sp['Name']) ?>" maxlength="120" required></div><div><label class="form-label fw-semibold">Clinical description</label><textarea name="description" class="form-control" rows="4" maxlength="500"><?= e($sp['Description']) ?></textarea><div class="form-text">Keep this short and useful for administrators and search categorization.</div></div></div>
          <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-teal"><i class="bi bi-check2 me-1"></i> Save changes</button></div>
        </form></div></div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="spec-empty d-none mt-3" id="specEmpty"><i class="bi bi-search fs-3 text-muted"></i><h5 class="mt-2 mb-1">No specializations found</h5><p class="text-muted small mb-0">Try a different search term.</p></div>
</div>

<script>
document.addEventListener('DOMContentLoaded',()=>{const input=document.getElementById('specSearch'),items=[...document.querySelectorAll('.spec-item')],count=document.getElementById('visibleSpecCount'),empty=document.getElementById('specEmpty');if(!input)return;input.addEventListener('input',()=>{const q=input.value.trim().toLowerCase();let visible=0;items.forEach(item=>{const show=!q||item.dataset.search.includes(q);item.classList.toggle('d-none',!show);if(show)visible++;});count.textContent=visible;empty.classList.toggle('d-none',visible!==0);});});
</script>

<!-- Create Specialization Modal -->
<div class="modal fade" id="createSpecModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= url('admin/specializations.php') ?>">
        <?= CSRF::inputField() ?>
        <input type="hidden" name="action" value="create_specialization">

        <div class="modal-header">
          <h5 class="modal-title fw-bold">Add Medical Specialization</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-teal btn-sm px-4 fw-semibold">Save Specialization</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
