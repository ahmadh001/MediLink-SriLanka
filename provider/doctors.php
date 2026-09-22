<?php


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


$cStmt = $db->prepare("SELECT Centre_ID, Centre_Name FROM `HEALTHCARE_CENTRE` WHERE Provider_ID = ?");
$cStmt->execute([$providerId]);
$centre = $cStmt->fetch();
$centreId = (int)$centre['Centre_ID'];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::check();
    $action = $_POST['action'] ?? '';

    
    if ($action === 'link_doctor') {
        $doctorId = (int)($_POST['doctor_id'] ?? 0);
        if ($doctorId > 0) {
            
            $chk = $db->prepare("SELECT Status FROM `CENTRE_DOCTOR_LINK` WHERE Centre_ID = ? AND Doctor_ID = ?");
            $chk->execute([$centreId, $doctorId]);
            $existing = $chk->fetch();

            if ($existing) {
                if (in_array($existing['Status'], ['INACTIVE', 'REJECTED'])) {
                    $db->prepare("UPDATE `CENTRE_DOCTOR_LINK` SET Status = 'PENDING', Joined_Date = CURRENT_DATE WHERE Centre_ID = ? AND Doctor_ID = ?")->execute([$centreId, $doctorId]);
                    setFlash('success', 'Affiliation request sent to the doctor for approval.');
                } else {
                    setFlash('info', $existing['Status'] === 'PENDING' ? 'An affiliation request is already awaiting this doctor.' : 'This doctor is already actively affiliated with your healthcare centre.');
                }
            } else {
                $ins = $db->prepare("INSERT INTO `CENTRE_DOCTOR_LINK` (Centre_ID, Doctor_ID, Joined_Date, Status) VALUES (?, ?, CURRENT_DATE, 'PENDING')");
                $ins->execute([$centreId, $doctorId]);
                setFlash('success', 'Affiliation request sent to the doctor for approval.');
            }
        } else {
            setFlash('danger', 'Please select a valid doctor to affiliate.');
        }
        redirect('provider/doctors.php');

    
    } elseif ($action === 'toggle_status') {
        $doctorId = (int)($_POST['doctor_id'] ?? 0);
        $newStatus = ($_POST['status'] ?? 'ACTIVE') === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
        
        $db->prepare("UPDATE `CENTRE_DOCTOR_LINK` SET Status = ? WHERE Centre_ID = ? AND Doctor_ID = ?")
           ->execute([$newStatus, $centreId, $doctorId]);
        
        setFlash('info', "Doctor affiliation status updated to {$newStatus}.");
        redirect('provider/doctors.php');
    }
}


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


$allDocsStmt = $db->prepare("
    SELECT d.Doctor_ID, d.Medical_License_No, u.First_Name, u.Last_Name, p.City,
           GROUP_CONCAT(s.Name SEPARATOR ', ') AS Specializations
    FROM `DOCTOR` d
    JOIN `PROVIDER` p ON d.Provider_ID = p.Provider_ID
    JOIN `USER` u ON p.User_ID = u.User_ID
    LEFT JOIN `DOCTOR_SPECIALIZATION` ds ON d.Doctor_ID = ds.Doctor_ID
    LEFT JOIN `SPECIALIZATION` s ON ds.Specialization_ID = s.Specialization_ID
    WHERE p.Verification_Status = 'VERIFIED' AND u.Account_Status = 'ACTIVE'
      AND d.Doctor_ID NOT IN (SELECT Doctor_ID FROM `CENTRE_DOCTOR_LINK` WHERE Centre_ID = ? AND Status IN ('ACTIVE','PENDING'))
    GROUP BY d.Doctor_ID
    ORDER BY u.First_Name ASC
");
$allDocsStmt->execute([$centreId]);
$availableDoctors = $allDocsStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 doctors-v2">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <div class="page-kicker mb-2">Centre workspace</div>
      <h2 class="fw-bold mb-1">Doctor affiliations</h2>
      <p class="text-muted mb-0">Manage specialists connected to <strong><?= e($centre['Centre_Name']) ?></strong>.</p>
    </div>
    <a href="<?= url('provider/dashboard.php') ?>" class="btn btn-outline-secondary">
      <i class="bi bi-grid me-2"></i>Dashboard
    </a>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
      <div class="doctor-summary p-3 d-flex align-items-center gap-3 h-100">
        <div class="doctor-summary-icon"><i class="bi bi-people"></i></div>
        <div><div class="text-muted small">Total affiliations</div><div class="fs-4 fw-bold"><?= count($affiliatedList) ?></div></div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="doctor-summary p-3 d-flex align-items-center gap-3 h-100">
        <div class="doctor-summary-icon"><i class="bi bi-person-check"></i></div>
        <div><div class="text-muted small">Active doctors</div><div class="fs-4 fw-bold"><?= count(array_filter($affiliatedList, fn($d) => $d['Link_Status'] === 'ACTIVE')) ?></div></div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="doctor-summary p-3 d-flex align-items-center gap-3 h-100">
        <div class="doctor-summary-icon"><i class="bi bi-person-plus"></i></div>
        <div><div class="text-muted small">Available to add</div><div class="fs-4 fw-bold"><?= count($availableDoctors) ?></div></div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card card-custom p-4 affiliation-panel">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="doctor-summary-icon"><i class="bi bi-person-plus"></i></div>
          <div><h5 class="fw-bold mb-0">Request a doctor</h5><small class="text-muted">Verified doctors only</small></div>
        </div>
        <p class="small text-muted">Choose a verified doctor and send an affiliation request. The doctor must accept before the link becomes active.</p>
        <?php if (empty($availableDoctors)): ?>
          <div class="alert alert-light border mb-0"><i class="bi bi-check-circle me-2 text-success"></i>No additional verified doctors are available to add.</div>
        <?php else: ?>
          <form method="POST" action="<?= url('provider/doctors.php') ?>">
            <?= CSRF::inputField() ?><input type="hidden" name="action" value="link_doctor">
            <label class="form-label small fw-semibold" for="doctor_id">Doctor</label>
            <select id="doctor_id" name="doctor_id" class="form-select mb-3" required>
              <option value="">Select a doctor</option>
              <?php foreach ($availableDoctors as $doc): ?>
                <option value="<?= $doc['Doctor_ID'] ?>">Dr. <?= e($doc['First_Name'].' '.$doc['Last_Name']) ?> · <?= e($doc['Specializations'] ?: 'General') ?> · <?= e($doc['City']) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-teal w-100" type="submit"><i class="bi bi-link-45deg me-2"></i>Send request</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="d-flex justify-content-between align-items-end mb-3">
        <div><h5 class="fw-bold mb-1">Affiliated doctors</h5><p class="small text-muted mb-0">Review contact, speciality and affiliation status.</p></div>
      </div>
      <?php if (empty($affiliatedList)): ?>
        <div class="card card-custom text-center p-5">
          <div class="doctor-summary-icon mx-auto mb-3"><i class="bi bi-person-plus"></i></div>
          <h5 class="fw-bold">No doctor affiliations yet</h5>
          <p class="text-muted mb-0">Use the form to connect your first verified doctor.</p>
        </div>
      <?php else: ?>
        <div class="d-grid gap-3">
          <?php foreach ($affiliatedList as $doc): ?>
            <article class="doctor-card">
              <div class="d-flex flex-column flex-md-row gap-3 justify-content-between">
                <div class="d-flex gap-3 min-w-0">
                  <div class="doctor-avatar"><?= e(strtoupper(substr($doc['First_Name'],0,1).substr($doc['Last_Name'],0,1))) ?></div>
                  <div class="min-w-0">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                      <h6 class="fw-bold mb-0">Dr. <?= e($doc['First_Name'].' '.$doc['Last_Name']) ?></h6>
                      <span class="badge rounded-pill <?= $doc['Link_Status']==='ACTIVE'?'text-bg-success':($doc['Link_Status']==='PENDING'?'text-bg-warning':($doc['Link_Status']==='REJECTED'?'text-bg-danger':'text-bg-secondary')) ?>"><?= e(ucfirst(strtolower($doc['Link_Status']))) ?></span>
                    </div>
                    <div class="text-teal fw-semibold small mb-2"><?= e($doc['Specializations'] ?: 'General') ?></div>
                    <div class="doctor-meta">
                      <span><i class="bi bi-patch-check"></i><?= e($doc['Medical_License_No']) ?></span>
                      <span><i class="bi bi-briefcase"></i><?= (int)$doc['Experience_Years'] ?> yrs</span>
                      <span><i class="bi bi-geo-alt"></i><?= e($doc['City']) ?></span>
                      <span><i class="bi bi-telephone"></i><?= e($doc['Phone'] ?: 'No phone') ?></span>
                      <span><i class="bi bi-calendar-check"></i>Joined <?= formatDate($doc['Joined_Date']) ?></span>
                    </div>
                  </div>
                </div>
                <div class="align-self-md-center">
                  <form method="POST" action="<?= url('provider/doctors.php') ?>">
                    <?= CSRF::inputField() ?><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="doctor_id" value="<?= $doc['Doctor_ID'] ?>"><input type="hidden" name="status" value="<?= $doc['Link_Status'] ?>">
                    <button type="submit" class="btn btn-sm <?= $doc['Link_Status']==='ACTIVE'?'btn-outline-danger':'btn-outline-teal' ?>" <?= $doc['Link_Status']==='PENDING'?'disabled':'' ?>><i class="bi <?= $doc['Link_Status']==='ACTIVE'?'bi-pause-circle':'bi-send' ?> me-1"></i><?= $doc['Link_Status']==='ACTIVE'?'Deactivate':($doc['Link_Status']==='PENDING'?'Awaiting doctor':'Request again') ?></button>
                  </form>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
