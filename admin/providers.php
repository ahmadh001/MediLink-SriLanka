<?php
/**
 * Admin Provider Verification & Governance
 */

$pageTitle = 'Provider Verification & Management';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();

// Handle Verification Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_verification') {
    CSRF::check();

    $providerId = (int)($_POST['provider_id'] ?? 0);
    $newStatus = strtoupper(trim($_POST['verification_status'] ?? ''));

    if (in_array($newStatus, ['PENDING', 'VERIFIED', 'REJECTED'])) {
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE `PROVIDER` SET Verification_Status = ? WHERE Provider_ID = ?")->execute([$newStatus, $providerId]);
            
            // Also update underlying DOCTOR or HEALTHCARE_CENTRE record
            $db->prepare("UPDATE `DOCTOR` SET Verification_Status = ? WHERE Provider_ID = ?")->execute([$newStatus, $providerId]);
            $db->prepare("UPDATE `HEALTHCARE_CENTRE` SET Verification_Status = ? WHERE Provider_ID = ?")->execute([$newStatus, $providerId]);

            $db->commit();
            setFlash('success', "Provider #{$providerId} verification updated to {$newStatus}.");
        } catch (Exception $e) {
            $db->rollBack();
            setFlash('danger', 'Error updating verification: ' . $e->getMessage());
        }
    } else {
        setFlash('danger', 'Invalid verification status.');
    }
    redirect('admin/providers.php');
}

// Filter Status
$filterStatus = $_GET['status'] ?? 'ALL';
$filterType = $_GET['type'] ?? 'ALL';

$query = "
    SELECT p.*, u.First_Name, u.Last_Name, u.Email, u.Account_Status,
           d.Doctor_ID, d.Medical_License_No, d.Experience_Years, d.Consultation_Duration,
           hc.Centre_ID, hc.Centre_Name, hc.Registration_No,
           GROUP_CONCAT(DISTINCT s.Name SEPARATOR ', ') AS Specializations,
           (SELECT COUNT(*) FROM `SCHEDULED_SLOT` ss WHERE ss.Provider_ID = p.Provider_ID) AS total_slots
    FROM `PROVIDER` p
    JOIN `USER` u ON p.User_ID = u.User_ID
    LEFT JOIN `DOCTOR` d ON p.Provider_ID = d.Provider_ID
    LEFT JOIN `HEALTHCARE_CENTRE` hc ON p.Provider_ID = hc.Provider_ID
    LEFT JOIN `DOCTOR_SPECIALIZATION` ds ON d.Doctor_ID = ds.Doctor_ID
    LEFT JOIN `SPECIALIZATION` s ON ds.Specialization_ID = s.Specialization_ID
    WHERE 1=1
";
$params = [];

if ($filterStatus !== 'ALL') {
    $query .= " AND p.Verification_Status = ?";
    $params[] = $filterStatus;
}
if ($filterType !== 'ALL') {
    $query .= " AND p.Provider_Type = ?";
    $params[] = $filterType;
}

$query .= " GROUP BY p.Provider_ID ORDER BY p.Created_At DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$providers = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-patch-check-fill text-teal me-2"></i> Provider Verifications</h2>
      <p class="text-muted small mb-0">Verify SLMC medical licenses and PHSRC registrations before providers go live.</p>
    </div>
    <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Admin Dashboard
    </a>
  </div>

  <!-- Filters -->
  <div class="card card-custom p-4 mb-4">
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <span class="small fw-semibold me-2 text-muted">Filter by Status:</span>
      <a href="<?= url('admin/providers.php?status=ALL&type=' . $filterType) ?>" class="btn btn-sm <?= $filterStatus === 'ALL' ? 'btn-teal' : 'btn-outline-secondary' ?>">All</a>
      <a href="<?= url('admin/providers.php?status=PENDING&type=' . $filterType) ?>" class="btn btn-sm <?= $filterStatus === 'PENDING' ? 'btn-warning text-dark' : 'btn-outline-secondary' ?>">Pending Review</a>
      <a href="<?= url('admin/providers.php?status=VERIFIED&type=' . $filterType) ?>" class="btn btn-sm <?= $filterStatus === 'VERIFIED' ? 'btn-success' : 'btn-outline-secondary' ?>">Verified</a>
      <a href="<?= url('admin/providers.php?status=REJECTED&type=' . $filterType) ?>" class="btn btn-sm <?= $filterStatus === 'REJECTED' ? 'btn-danger' : 'btn-outline-secondary' ?>">Rejected</a>
    </div>
  </div>

  <!-- Providers Table -->
  <div class="card card-custom p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="fw-bold mb-0">Providers (<?= count($providers) ?>)</h5>
    </div>

    <?php if (empty($providers)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-hospital fs-1 d-block mb-2 text-secondary"></i>
        <p class="mb-0">No providers found matching this filter.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-custom table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Provider Info</th>
              <th>Type</th>
              <th>Medical Reg / SLMC</th>
              <th>Specialties / Facilities</th>
              <th>Location & GPS</th>
              <th>Verification</th>
              <th class="text-end">Approve / Reject</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($providers as $p): ?>
              <tr>
                <td>
                  <div class="fw-bold"><?= e($p['Business_Name']) ?></div>
                  <small class="text-muted"><?= e($p['First_Name'] . ' ' . $p['Last_Name']) ?> • <?= e($p['Email']) ?></small>
                </td>
                <td>
                  <span class="badge <?= $p['Provider_Type'] === 'DOCTOR' ? 'bg-teal' : 'bg-primary' ?>">
                    <?= e($p['Provider_Type']) ?>
                  </span>
                </td>
                <td>
                  <code class="fw-bold text-dark">
                    <?= e($p['Medical_License_No'] ?: ($p['Registration_No'] ?: 'N/A')) ?>
                  </code>
                </td>
                <td>
                  <small class="text-secondary"><?= e($p['Specializations'] ?: 'General Consultations') ?></small>
                </td>
                <td>
                  <small><?= e($p['Address']) ?>, <?= e($p['City']) ?><br>
                  <span class="text-muted"><?= round($p['Latitude'], 4) ?>, <?= round($p['Longitude'], 4) ?></span></small>
                </td>
                <td><?= renderStatusBadge($p['Verification_Status']) ?></td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <?php if ($p['Verification_Status'] !== 'VERIFIED'): ?>
                      <form method="POST" action="<?= url('admin/providers.php') ?>" class="d-inline">
                        <?= CSRF::inputField() ?>
                        <input type="hidden" name="action" value="update_verification">
                        <input type="hidden" name="provider_id" value="<?= $p['Provider_ID'] ?>">
                        <input type="hidden" name="verification_status" value="VERIFIED">
                        <button type="submit" class="btn btn-outline-success btn-sm">
                          <i class="bi bi-check2"></i> Verify
                        </button>
                      </form>
                    <?php endif; ?>

                    <?php if ($p['Verification_Status'] !== 'REJECTED'): ?>
                      <form method="POST" action="<?= url('admin/providers.php') ?>" class="d-inline" onsubmit="return confirm('Reject this provider application?');">
                        <?= CSRF::inputField() ?>
                        <input type="hidden" name="action" value="update_verification">
                        <input type="hidden" name="provider_id" value="<?= $p['Provider_ID'] ?>">
                        <input type="hidden" name="verification_status" value="REJECTED">
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                          <i class="bi bi-x"></i> Reject
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
