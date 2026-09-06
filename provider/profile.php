<?php
/**
 * Provider Profile Management
 */

$pageTitle = 'Manage Provider Profile';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_PROVIDER);
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];
$providerId = $currentUser['provider_id'];
$providerType = $currentUser['provider_type'];

$db = Database::getConnection();

$cities = getSriLankanCities();
$specializations = $db->query("SELECT * FROM `SPECIALIZATION` ORDER BY Name ASC")->fetchAll();

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::check();
    $action = $_POST['action'] ?? 'update_profile';

    if ($action === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $businessName = trim($_POST['business_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? 'Colombo');
        $latitude = floatval($_POST['latitude'] ?? 6.9271);
        $longitude = floatval($_POST['longitude'] ?? 79.8612);
        $description = trim($_POST['description'] ?? '');

        try {
            $db->beginTransaction();

            // Update USER
            $uStmt = $db->prepare("UPDATE `USER` SET First_Name = ?, Last_Name = ?, Phone = ? WHERE User_ID = ?");
            $uStmt->execute([$firstName, $lastName, $phone, $userId]);

            // Update PROVIDER
            $pStmt = $db->prepare("
                UPDATE `PROVIDER` 
                SET Business_Name = ?, Address = ?, City = ?, Latitude = ?, Longitude = ?, Contact_Number = ?, Description = ?
                WHERE Provider_ID = ?
            ");
            $pStmt->execute([$businessName, $address, $city, $latitude, $longitude, $phone, $description, $providerId]);

            if ($providerType === PROVIDER_DOCTOR) {
                $licenseNo = trim($_POST['medical_license_no'] ?? '');
                $experience = (int)($_POST['experience_years'] ?? 0);
                $duration = (int)($_POST['consultation_duration'] ?? 20);
                $bio = trim($_POST['bio'] ?? '');
                $selectedSpecs = $_POST['specializations'] ?? [];

                // Update DOCTOR
                $dStmt = $db->prepare("
                    UPDATE `DOCTOR` 
                    SET Medical_License_No = ?, Professional_Bio = ?, Experience_Years = ?, Consultation_Duration = ?
                    WHERE Provider_ID = ?
                ");
                $dStmt->execute([$licenseNo, $bio, $experience, $duration, $providerId]);

                // Fetch Doctor ID
                $docRow = $db->query("SELECT Doctor_ID FROM `DOCTOR` WHERE Provider_ID = " . (int)$providerId)->fetch();
                $doctorId = $docRow['Doctor_ID'];

                // Sync Specializations
                $db->prepare("DELETE FROM `DOCTOR_SPECIALIZATION` WHERE Doctor_ID = ?")->execute([$doctorId]);
                if (!empty($selectedSpecs)) {
                    $dsIns = $db->prepare("INSERT INTO `DOCTOR_SPECIALIZATION` (Doctor_ID, Specialization_ID) VALUES (?, ?)");
                    foreach ($selectedSpecs as $spId) {
                        $dsIns->execute([$doctorId, (int)$spId]);
                    }
                }
            } else { // HEALTHCARE_CENTRE
                $centreName = trim($_POST['centre_name'] ?? '');
                $regNo = trim($_POST['registration_no'] ?? '');

                $cStmt = $db->prepare("
                    UPDATE `HEALTHCARE_CENTRE`
                    SET Centre_Name = ?, Registration_No = ?, Description = ?
                    WHERE Provider_ID = ?
                ");
                $cStmt->execute([$centreName, $regNo, $description, $providerId]);
            }

            $db->commit();
            
            // Refresh session full_name
            $_SESSION['user']['first_name'] = $firstName;
            $_SESSION['user']['last_name'] = $lastName;
            $_SESSION['user']['full_name'] = $firstName . ' ' . $lastName;
            $_SESSION['user']['business_name'] = $businessName;

            setFlash('success', 'Profile credentials and coordinates updated successfully.');
            redirect('provider/profile.php');

        } catch (Exception $e) {
            $db->rollBack();
            setFlash('danger', 'Error updating profile: ' . $e->getMessage());
        }

    } elseif ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        $uStmt = $db->prepare("SELECT Password_Hash FROM `USER` WHERE User_ID = ?");
        $uStmt->execute([$userId]);
        $userRow = $uStmt->fetch();

        if (!password_verify($currentPass, $userRow['Password_Hash'])) {
            setFlash('danger', 'Current password entered is incorrect.');
        } elseif (strlen($newPass) < 6) {
            setFlash('danger', 'New password must be at least 6 characters long.');
        } elseif ($newPass !== $confirmPass) {
            setFlash('danger', 'New passwords do not match.');
        } else {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $db->prepare("UPDATE `USER` SET Password_Hash = ? WHERE User_ID = ?")->execute([$newHash, $userId]);
            setFlash('success', 'Password changed successfully.');
            redirect('provider/profile.php');
        }
    }
}

// Fetch current details
$uData = $db->prepare("SELECT * FROM `USER` WHERE User_ID = ?");
$uData->execute([$userId]);
$userData = $uData->fetch();

$pData = $db->prepare("SELECT * FROM `PROVIDER` WHERE Provider_ID = ?");
$pData->execute([$providerId]);
$provData = $pData->fetch();

$docData = null;
$centreData = null;
$currentDoctorSpecs = [];

if ($providerType === PROVIDER_DOCTOR) {
    $dStmt = $db->prepare("SELECT * FROM `DOCTOR` WHERE Provider_ID = ?");
    $dStmt->execute([$providerId]);
    $docData = $dStmt->fetch();

    if ($docData) {
        $dsStmt = $db->prepare("SELECT Specialization_ID FROM `DOCTOR_SPECIALIZATION` WHERE Doctor_ID = ?");
        $dsStmt->execute([$docData['Doctor_ID']]);
        $currentDoctorSpecs = $dsStmt->fetchAll(PDO::FETCH_COLUMN);
    }
} else {
    $cStmt = $db->prepare("SELECT * FROM `HEALTHCARE_CENTRE` WHERE Provider_ID = ?");
    $cStmt->execute([$providerId]);
    $centreData = $cStmt->fetch();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-building-gear text-teal me-2"></i> Provider Profile & Credentials</h2>
      <p class="text-muted small mb-0">Update your clinical information, SLMC registration, and GPS search coordinates.</p>
    </div>
    <a href="<?= url('provider/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Dashboard
    </a>
  </div>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card card-custom p-4">
        <form method="POST" action="<?= url('provider/profile.php') ?>">
          <?= CSRF::inputField() ?>
          <input type="hidden" name="action" value="update_profile">

          <h5 class="fw-bold text-teal mb-3"><i class="bi bi-person-badge-fill me-2"></i> Primary Contact Information</h5>
          
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">First Name</label>
              <input type="text" name="first_name" class="form-control" value="<?= e($userData['First_Name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Last Name</label>
              <input type="text" name="last_name" class="form-control" value="<?= e($userData['Last_Name']) ?>" required>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Email Address (Read-only)</label>
              <input type="email" class="form-control bg-light" value="<?= e($userData['Email']) ?>" readonly>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Contact Phone Number</label>
              <input type="tel" name="phone" class="form-control" value="<?= e($userData['Phone']) ?>" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Public Practice / Hospital / Business Name</label>
            <input type="text" name="business_name" class="form-control" value="<?= e($provData['Business_Name']) ?>" required>
          </div>

          <?php if ($providerType === PROVIDER_DOCTOR && $docData): ?>
            <hr class="my-4">
            <h5 class="fw-bold text-teal mb-3"><i class="bi bi-award-fill me-2"></i> Doctor SLMC Credentials & Specialities</h5>

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label small fw-semibold">SLMC Registration No</label>
                <input type="text" name="medical_license_no" class="form-control" value="<?= e($docData['Medical_License_No']) ?>" required>
              </div>
              <div class="col-md-3">
                <label class="form-label small fw-semibold">Experience (Years)</label>
                <input type="number" name="experience_years" class="form-control" min="0" max="60" value="<?= e($docData['Experience_Years']) ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label small fw-semibold">Slot Duration (Mins)</label>
                <input type="number" name="consultation_duration" class="form-control" min="10" max="120" step="5" value="<?= e($docData['Consultation_Duration']) ?>">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-semibold">Specializations (Select all that apply)</label>
              <div class="row g-2 p-2 border rounded-3 bg-light">
                <?php foreach ($specializations as $sp): ?>
                  <div class="col-md-6">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="specializations[]" value="<?= $sp['Specialization_ID'] ?>" id="edit_sp_<?= $sp['Specialization_ID'] ?>" <?= in_array($sp['Specialization_ID'], $currentDoctorSpecs) ? 'checked' : '' ?>>
                      <label class="form-check-label small" for="edit_sp_<?= $sp['Specialization_ID'] ?>">
                        <?= e($sp['Name']) ?>
                      </label>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-semibold">Professional Biography & Qualifications</label>
              <textarea name="bio" class="form-control" rows="3"><?= e($docData['Professional_Bio']) ?></textarea>
            </div>

          <?php elseif ($providerType === PROVIDER_CENTRE && $centreData): ?>
            <hr class="my-4">
            <h5 class="fw-bold text-teal mb-3"><i class="bi bi-hospital-fill me-2"></i> Healthcare Centre Credentials</h5>

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Centre Name</label>
                <input type="text" name="centre_name" class="form-control" value="<?= e($centreData['Centre_Name']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">PHSRC / MOH Registration No</label>
                <input type="text" name="registration_no" class="form-control" value="<?= e($centreData['Registration_No']) ?>" required>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-semibold">Facility Description & Services</label>
              <textarea name="description" class="form-control" rows="3"><?= e($centreData['Description']) ?></textarea>
            </div>
          <?php endif; ?>

          <!-- Physical Location & GPS Coordinates -->
          <hr class="my-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-teal mb-0"><i class="bi bi-geo-alt-fill me-2"></i> Location & GPS Coordinates</h5>
            <button type="button" id="btn-detect-location" class="btn btn-outline-teal btn-sm">
              <i class="bi bi-crosshair me-1"></i> Detect My Current Location
            </button>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Street / Clinic Address</label>
            <input type="text" name="address" class="form-control" value="<?= e($provData['Address']) ?>" required>
          </div>

          <div class="row g-3 mb-2">
            <div class="col-md-4">
              <label class="form-label small fw-semibold">City / District</label>
              <select name="city" id="city-select" class="form-select">
                <?php foreach ($cities as $cName => $cData): ?>
                  <option value="<?= $cName ?>" <?= $provData['City'] === $cName ? 'selected' : '' ?>><?= $cName ?> (<?= $cData['district'] ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Latitude</label>
              <input type="number" step="0.000001" name="latitude" id="latitude-input" class="form-control" value="<?= e($provData['Latitude']) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Longitude</label>
              <input type="number" step="0.000001" name="longitude" id="longitude-input" class="form-control" value="<?= e($provData['Longitude']) ?>" required>
            </div>
          </div>

          <div id="location-feedback" class="small mb-4">
            <span class="text-muted"><i class="bi bi-info-circle"></i> Coordinates power proximity search calculations for patients within subscription radius.</span>
          </div>

          <button type="submit" class="btn btn-teal px-4 py-2 fw-semibold">
            <i class="bi bi-check-circle me-1"></i> Save Changes
          </button>
        </form>
      </div>
    </div>

    <!-- Password & Verification Sidebar -->
    <div class="col-lg-4">
      <div class="card card-custom p-4 mb-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-shield-check text-teal me-2"></i> Verification Status</h5>
        <div class="d-flex align-items-center gap-2 mb-2">
          <span>Status:</span>
          <?= renderStatusBadge($provData['Verification_Status']) ?>
        </div>
        <p class="small text-muted mb-0">
          Verified providers receive priority ranking in distance searches and an official trust badge for Sri Lankan patients.
        </p>
      </div>

      <div class="card card-custom p-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-key-fill text-teal me-2"></i> Change Password</h5>
        <form method="POST" action="<?= url('provider/profile.php') ?>">
          <?= CSRF::inputField() ?>
          <input type="hidden" name="action" value="change_password">

          <div class="mb-3">
            <label class="form-label small fw-semibold">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">New Password</label>
            <input type="password" name="new_password" class="form-control" placeholder="Min 6 characters" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
          </div>

          <button type="submit" class="btn btn-outline-secondary w-100 py-2">
            Update Password
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
