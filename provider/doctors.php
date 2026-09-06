<?php
/**
 * Healthcare Centre - Affiliated Doctors Management (CENTRE_DOCTOR_LINK)
 */

$pageTitle = 'Manage Affiliated Doctors';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_PROVIDER);
$currentUser = getCurrentUser();
$providerId = $currentUser['provider_id'];
$providerType = $currentUser['provider_type'];

if ($providerType !== PROVIDER_CENTRE) {
    setFlash('warning', 'Only registered Healthcare Centres can manage affiliated doctors.');
    redirect('provider/dashboard.php');
}

$db = Database::getConnection();

// Fetch Centre ID
$cStmt = $db->prepare("SELECT Centre_ID, Centre_Name FROM `HEALTHCARE_CENTRE` WHERE Provider_ID = ?");
$cStmt->execute([$providerId]);
$centre = $cStmt->fetch();
$centreId = (int)$centre['Centre_ID'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::check();
    $action = $_POST['action'] ?? '';

    // Action: Link New Doctor
    if ($action === 'link_doctor') {
        $doctorId = (int)($_POST['doctor_id'] ?? 0);
        if ($doctorId > 0) {
            // Check if already linked
            $chk = $db->prepare("SELECT Status FROM `CENTRE_DOCTOR_LINK` WHERE Centre_ID = ? AND Doctor_ID = ?");
            $chk->execute([$centreId, $doctorId]);
            $existing = $chk->fetch();

            if ($existing) {
                if ($existing['Status'] === 'INACTIVE') {
                    $db->prepare("UPDATE `CENTRE_DOCTOR_LINK` SET Status = 'ACTIVE', Joined_Date = CURRENT_DATE WHERE Centre_ID = ? AND Doctor_ID = ?")->execute([$centreId, $doctorId]);
                    setFlash('success', 'Doctor affiliation re-activated successfully.');
                } else {
                    setFlash('info', 'This doctor is already actively affiliated with your healthcare centre.');
                }
            } else {
                $ins = $db->prepare("INSERT INTO `CENTRE_DOCTOR_LINK` (Centre_ID, Doctor_ID, Joined_Date, Status) VALUES (?, ?, CURRENT_DATE, 'ACTIVE')");
                $ins->execute([$centreId, $doctorId]);
                setFlash('success', 'Doctor successfully affiliated with your healthcare centre.');
            }
        } else {
            setFlash('danger', 'Please select a valid doctor to affiliate.');
        }
        redirect('provider/doctors.php');

    // Action: Toggle Status / Remove
    } elseif ($action === 'toggle_status') {
        $doctorId = (int)($_POST['doctor_id'] ?? 0);
        $newStatus = ($_POST['status'] ?? 'ACTIVE') === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
        
        $db->prepare("UPDATE `CENTRE_DOCTOR_LINK` SET Status = ? WHERE Centre_ID = ? AND Doctor_ID = ?")
           ->execute([$newStatus, $centreId, $doctorId]);
        
        setFlash('info', "Doctor affiliation status updated to {$newStatus}.");
        redirect('provider/doctors.php');
    }
}

// Fetch Currently Affiliated Doctors
$affilStmt = $db->prepare("
    SELECT cdl.Joined_Date, cdl.Status AS Link_Status,
           d.Doctor_ID, d.Medical_License_No, d.Experience_Years, d.Consultation_Duration,
           u.First_Name, u.Last_Name, u.Email, u.Phone, p.City,
           GROUP_CONCAT(s.Name SEPARATOR ', ') AS Specializations
    FROM `CENTRE_DOCTOR_LINK` cdl
    JOIN `DOCTOR` d ON cdl.Doctor_ID = d.Doctor_ID
    JOIN `PROVIDER` p ON d.Provider_ID = p.Provider_ID
    JOIN `USER` u ON p.User_ID = u.User_ID
    LEFT JOIN `DOCTOR_SPECIALIZATION` ds ON d.Doctor_ID = ds.Doctor_ID
    LEFT JOIN `SPECIALIZATION` s ON ds.Specialization_ID = s.Specialization_ID
    WHERE cdl.Centre_ID = ?
    GROUP BY d.Doctor_ID
    ORDER BY cdl.Joined_Date DESC
");
$affilStmt->execute([$centreId]);
$affiliatedList = $affilStmt->fetchAll();

// Fetch Doctors available for affiliation (all verified doctors not yet active in this centre)
$allDocsStmt = $db->prepare("
    SELECT d.Doctor_ID, d.Medical_License_No, u.First_Name, u.Last_Name, p.City,
           GROUP_CONCAT(s.Name SEPARATOR ', ') AS Specializations
    FROM `DOCTOR` d
    JOIN `PROVIDER` p ON d.Provider_ID = p.Provider_ID
    JOIN `USER` u ON p.User_ID = u.User_ID
    LEFT JOIN `DOCTOR_SPECIALIZATION` ds ON d.Doctor_ID = ds.Doctor_ID
    LEFT JOIN `SPECIALIZATION` s ON ds.Specialization_ID = s.Specialization_ID
    WHERE p.Verification_Status = 'VERIFIED' AND u.Account_Status = 'ACTIVE'
      AND d.Doctor_ID NOT IN (SELECT Doctor_ID FROM `CENTRE_DOCTOR_LINK` WHERE Centre_ID = ? AND Status = 'ACTIVE')
    GROUP BY d.Doctor_ID
    ORDER BY u.First_Name ASC
");
$allDocsStmt->execute([$centreId]);
$availableDoctors = $allDocsStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-people-fill text-teal me-2"></i> Affiliated Medical Specialists</h2>
      <p class="text-muted small mb-0">Manage doctor consultant affiliations for <strong><?= e($centre['Centre_Name']) ?></strong>.</p>
    </div>
    <a href="<?= url('provider/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Dashboard
    </a>
  </div>

  <div class="row g-4">
    <!-- Add Doctor to Centre -->
    <div class="col-lg-4">
      <div class="card card-custom p-4 h-100">
        <h5 class="fw-bold text-teal mb-3"><i class="bi bi-person-plus-fill me-2"></i> Affiliate a Specialist Doctor</h5>
        <p class="small text-muted mb-3">
          Select an SLMC registered doctor from the verified national registry to associate them with your healthcare centre's consulting schedule.
        </p>

        <?php if (empty($availableDoctors)): ?>
          <div class="alert alert-light border small text-muted">
            All registered doctors are currently affiliated.
          </div>
        <?php else: ?>
          <form method="POST" action="<?= url('provider/doctors.php') ?>">
            <?= CSRF::inputField() ?>
            <input type="hidden" name="action" value="link_doctor">

            <div class="mb-3">
              <label class="form-label small fw-semibold">Select Doctor</label>
              <select name="doctor_id" class="form-select" required>
                <option value="">-- Choose Specialist --</option>
                <?php foreach ($availableDoctors as $doc): ?>
                  <option value="<?= $doc['Doctor_ID'] ?>">
                    Dr. <?= e($doc['First_Name'] . ' ' . $doc['Last_Name']) ?> (<?= e($doc['Medical_License_No']) ?>) - <?= e($doc['Specializations'] ?: 'General') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <button type="submit" class="btn btn-teal w-100 py-2">
              <i class="bi bi-link-45deg me-1"></i> Add Doctor Affiliation
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Affiliated Doctors Table -->
    <div class="col-lg-8">
      <div class="card card-custom p-4 h-100">
        <h5 class="fw-bold mb-3"><i class="bi bi-journal-medical text-teal me-2"></i> Current Centre Affiliations</h5>

        <?php if (empty($affiliatedList)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-person-slash fs-1 d-block mb-2 text-secondary"></i>
            <p class="mb-0">No doctors currently affiliated with this centre.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-custom table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Doctor</th>
                  <th>SLMC License</th>
                  <th>Specialization</th>
                  <th>Joined Date</th>
                  <th>Status</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($affiliatedList as $doc): ?>
                  <tr>
                    <td>
                      <div class="fw-bold">Dr. <?= e($doc['First_Name'] . ' ' . $doc['Last_Name']) ?></div>
                      <small class="text-muted"><?= e($doc['Phone']) ?></small>
                    </td>
                    <td>
                      <span class="badge bg-light text-dark border"><?= e($doc['Medical_License_No']) ?></span>
                    </td>
                    <td><small class="text-teal fw-semibold"><?= e($doc['Specializations'] ?: 'General') ?></small></td>
                    <td><small><?= formatDate($doc['Joined_Date']) ?></small></td>
                    <td><?= renderStatusBadge($doc['Link_Status']) ?></td>
                    <td class="text-end">
                      <form method="POST" action="<?= url('provider/doctors.php') ?>" class="d-inline">
                        <?= CSRF::inputField() ?>
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="doctor_id" value="<?= $doc['Doctor_ID'] ?>">
                        <input type="hidden" name="status" value="<?= $doc['Link_Status'] ?>">
                        <button type="submit" class="btn btn-sm <?= $doc['Link_Status'] === 'ACTIVE' ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                          <?= $doc['Link_Status'] === 'ACTIVE' ? 'Deactivate' : 'Activate' ?>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
