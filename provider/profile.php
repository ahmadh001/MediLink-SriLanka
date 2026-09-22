<?php


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


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::check();
    $action = $_POST['action'] ?? 'update_profile';

    if ($action === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $nicNo = trim($_POST['nic_no'] ?? '');
        $businessName = trim($_POST['business_name'] ?? '');
        $consultationFee = max(0, floatval($_POST['consultation_fee'] ?? 0.00));
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? 'Colombo');
        $latitude = floatval($_POST['latitude'] ?? 6.9271);
        $longitude = floatval($_POST['longitude'] ?? 79.8612);
        $description = trim($_POST['description'] ?? '');

        try {
            $db->beginTransaction();

            
            $uStmt = $db->prepare("UPDATE `USER` SET First_Name = ?, Last_Name = ?, Phone = ?, NIC_No = ? WHERE User_ID = ?");
            $uStmt->execute([$firstName, $lastName, $phone, $nicNo ?: null, $userId]);

            
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

                
                $dStmt = $db->prepare("
                    UPDATE `DOCTOR` 
                    SET Medical_License_No = ?, Professional_Bio = ?, Experience_Years = ?, Consultation_Duration = ?
                    WHERE Provider_ID = ?
                ");
                $dStmt->execute([$licenseNo, $bio, $experience, $duration, $providerId]);

                
                $docRow = $db->query("SELECT Doctor_ID FROM `DOCTOR` WHERE Provider_ID = " . (int)$providerId)->fetch();
                $doctorId = $docRow['Doctor_ID'];

                
                $db->prepare("DELETE FROM `DOCTOR_SPECIALIZATION` WHERE Doctor_ID = ?")->execute([$doctorId]);
                if (!empty($selectedSpecs)) {
                    $dsIns = $db->prepare("INSERT INTO `DOCTOR_SPECIALIZATION` (Doctor_ID, Specialization_ID) VALUES (?, ?)");
                    foreach ($selectedSpecs as $spId) {
                        $dsIns->execute([$doctorId, (int)$spId]);
                    }
                }
            } else { 
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
<style>
.provider-profile-shell{max-width:1180px}.profile-hero{background:#fff;border:1px solid var(--bs-border-color);border-radius:20px;padding:1.25rem 1.4rem}.profile-avatar{width:54px;height:54px;border-radius:16px;background:rgba(8,127,120,.1);color:#087f78;display:grid;place-items:center;font-size:1.35rem;font-weight:800}.profile-panel{background:#fff;border:1px solid var(--bs-border-color);border-radius:18px;padding:1.35rem}.profile-section+.profile-section{border-top:1px solid var(--bs-border-color);margin-top:1.5rem;padding-top:1.5rem}.profile-section-title{display:flex;align-items:center;gap:.65rem;margin-bottom:1rem}.profile-section-icon{width:34px;height:34px;border-radius:10px;background:rgba(8,127,120,.09);color:#087f78;display:grid;place-items:center}.profile-section-title h5{margin:0;font-weight:750}.profile-help{font-size:.82rem;color:var(--bs-secondary-color)}.profile-side{position:sticky;top:92px}.verification-box{background:rgba(8,127,120,.055);border:1px solid rgba(8,127,120,.16);border-radius:14px;padding:1rem}.specialization-grid{max-height:210px;overflow:auto;border:1px solid var(--bs-border-color);border-radius:12px;padding:.75rem}.map-frame{height:260px;width:100%;border-radius:14px;border:1px solid var(--bs-border-color);overflow:hidden}.password-wrap{position:relative}.password-wrap .form-control{padding-right:2.8rem}.password-toggle{position:absolute;right:.5rem;top:50%;transform:translateY(-50%);border:0;background:transparent;color:var(--bs-secondary-color);padding:.4rem}.save-bar{display:flex;justify-content:flex-end;align-items:center;gap:.75rem;margin-top:1.4rem;padding-top:1.2rem;border-top:1px solid var(--bs-border-color)}
@media(max-width:991.98px){.profile-side{position:static}.profile-hero{padding:1rem}.profile-panel{padding:1rem}}
</style>

<div class="container py-4 provider-profile-shell">
  <div class="profile-hero mb-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
    <div class="d-flex align-items-center gap-3">
      <div class="profile-avatar"><?= e(strtoupper(substr($userData['First_Name'] ?? 'P',0,1))) ?></div>
      <div>
        <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
          <h2 class="h4 fw-bold mb-0"><?= e($provData['Business_Name'] ?: ($userData['First_Name'].' '.$userData['Last_Name'])) ?></h2>
          <?= renderStatusBadge($provData['Verification_Status']) ?>
        </div>
        <div class="text-muted small"><i class="bi bi-geo-alt me-1"></i><?= e($provData['City']) ?> <span class="mx-2">•</span> <?= $providerType === PROVIDER_DOCTOR ? 'Doctor provider' : 'Healthcare centre' ?></div>
      </div>
    </div>
    <a href="<?= url('provider/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
  </div>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="profile-panel">
        <form method="POST" action="<?= url('provider/profile.php') ?>">
          <?= CSRF::inputField() ?><input type="hidden" name="action" value="update_profile"><input type="hidden" name="consultation_fee" value="0">

          <section class="profile-section">
            <div class="profile-section-title"><span class="profile-section-icon"><i class="bi bi-person"></i></span><div><h5>Contact information</h5><div class="profile-help">Account and public contact details.</div></div></div>
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label small fw-semibold">First name</label><input type="text" name="first_name" class="form-control" value="<?= e($userData['First_Name']) ?>" required></div>
              <div class="col-md-6"><label class="form-label small fw-semibold">Last name</label><input type="text" name="last_name" class="form-control" value="<?= e($userData['Last_Name']) ?>" required></div>
              <div class="col-md-6"><label class="form-label small fw-semibold">Email</label><div class="input-group"><span class="input-group-text"><i class="bi bi-envelope"></i></span><input type="email" class="form-control bg-body-tertiary" value="<?= e($userData['Email']) ?>" readonly></div><div class="profile-help mt-1">Email cannot be changed here.</div></div>
              <div class="col-md-6"><label class="form-label small fw-semibold">Contact number</label><div class="input-group"><span class="input-group-text"><i class="bi bi-telephone"></i></span><input type="tel" name="phone" class="form-control" value="<?= e($userData['Phone']) ?>" required></div></div>
              <div class="col-md-6"><label class="form-label small fw-semibold">NIC number</label><input type="text" name="nic_no" class="form-control" value="<?= e($userData['NIC_No'] ?? '') ?>"></div>
              <div class="col-md-6"><label class="form-label small fw-semibold">Public practice / business name</label><input type="text" name="business_name" class="form-control" value="<?= e($provData['Business_Name']) ?>" required></div>
            </div>
          </section>

          <?php if ($providerType === PROVIDER_DOCTOR && $docData): ?>
          <section class="profile-section">
            <div class="profile-section-title"><span class="profile-section-icon"><i class="bi bi-patch-check"></i></span><div><h5>Professional details</h5><div class="profile-help">Credentials and information shown to patients.</div></div></div>
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label small fw-semibold">SLMC registration no.</label><input type="text" name="medical_license_no" class="form-control" value="<?= e($docData['Medical_License_No']) ?>" required></div>
              <div class="col-md-3"><label class="form-label small fw-semibold">Experience</label><div class="input-group"><input type="number" name="experience_years" class="form-control" min="0" max="60" value="<?= e($docData['Experience_Years']) ?>"><span class="input-group-text">yrs</span></div></div>
              <div class="col-md-3"><label class="form-label small fw-semibold">Slot duration</label><div class="input-group"><input type="number" name="consultation_duration" class="form-control" min="10" max="120" step="5" value="<?= e($docData['Consultation_Duration']) ?>"><span class="input-group-text">min</span></div></div>
              <div class="col-12"><label class="form-label small fw-semibold">Specializations</label><div class="specialization-grid"><div class="row g-2"><?php foreach ($specializations as $sp): ?><div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="specializations[]" value="<?= $sp['Specialization_ID'] ?>" id="edit_sp_<?= $sp['Specialization_ID'] ?>" <?= in_array($sp['Specialization_ID'], $currentDoctorSpecs) ? 'checked' : '' ?>><label class="form-check-label small" for="edit_sp_<?= $sp['Specialization_ID'] ?>"><?= e($sp['Name']) ?></label></div></div><?php endforeach; ?></div></div></div>
              <div class="col-12"><label class="form-label small fw-semibold">Professional biography & qualifications</label><textarea name="bio" class="form-control" rows="4" placeholder="Briefly describe your clinical background and qualifications."><?= e($docData['Professional_Bio']) ?></textarea></div>
            </div>
          </section>
          <?php elseif ($providerType === PROVIDER_CENTRE && $centreData): ?>
          <section class="profile-section">
            <div class="profile-section-title"><span class="profile-section-icon"><i class="bi bi-hospital"></i></span><div><h5>Centre details</h5><div class="profile-help">Registration and public facility information.</div></div></div>
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label small fw-semibold">Centre name</label><input type="text" name="centre_name" class="form-control" value="<?= e($centreData['Centre_Name']) ?>" required></div>
              <div class="col-md-6"><label class="form-label small fw-semibold">PHSRC / MOH registration no.</label><input type="text" name="registration_no" class="form-control" value="<?= e($centreData['Registration_No']) ?>" required></div>
              <div class="col-12"><label class="form-label small fw-semibold">Facility description & services</label><textarea name="description" class="form-control" rows="4"><?= e($centreData['Description']) ?></textarea></div>
            </div>
          </section>
          <?php endif; ?>

          <section class="profile-section">
            <div class="profile-section-title d-flex justify-content-between flex-wrap"><div class="d-flex align-items-center gap-2"><span class="profile-section-icon"><i class="bi bi-geo-alt"></i></span><div><h5>Practice location</h5><div class="profile-help">Used for location-based patient search.</div></div></div><button type="button" id="btn-detect-location" class="btn btn-outline-teal btn-sm"><i class="bi bi-crosshair me-1"></i>Use current location</button></div>
            <div class="row g-3 mb-3">
              <div class="col-12"><label class="form-label small fw-semibold">Street / clinic address</label><input type="text" name="address" id="address-input" class="form-control" value="<?= e($provData['Address']) ?>" required></div>
              <div class="col-md-7"><label class="form-label small fw-semibold">City / district</label><select name="city" id="city-select" class="form-select"><?php foreach ($cities as $cName => $cData): ?><option value="<?= $cName ?>" data-lat="<?= $cData['lat'] ?>" data-lng="<?= $cData['lng'] ?>" <?= $provData['City'] === $cName ? 'selected' : '' ?>><?= $cName ?> (<?= $cData['district'] ?> District)</option><?php endforeach; ?></select></div>
              <div class="col-md-5 d-flex align-items-end"><div class="w-100 p-2 rounded-3 bg-body-tertiary small font-monospace text-muted" id="pin-coords-badge">Lat: <?= number_format((float)$provData['Latitude'],4) ?>, Lng: <?= number_format((float)$provData['Longitude'],4) ?></div></div>
            </div>
            <input type="hidden" name="latitude" id="latitude-input" value="<?= e($provData['Latitude']) ?>" required><input type="hidden" name="longitude" id="longitude-input" value="<?= e($provData['Longitude']) ?>" required>
            <div id="map-picker" class="map-frame shadow-sm"></div><div class="profile-help mt-2"><i class="bi bi-hand-index-thumb me-1"></i>Click the map or drag the marker to set the exact consulting location.</div>
            <div id="location-feedback" class="small mt-2 text-success"><i class="bi bi-check-circle me-1"></i>Saved location: <?= e($provData['City']) ?>.</div>
          </section>

          <div class="save-bar"><span class="profile-help d-none d-sm-inline">Review your public details before saving.</span><button type="submit" class="btn btn-teal px-4"><i class="bi bi-check2 me-1"></i>Save profile</button></div>
        </form>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="profile-side">
        <div class="profile-panel mb-4">
          <div class="profile-section-title"><span class="profile-section-icon"><i class="bi bi-shield-check"></i></span><div><h5>Verification</h5><div class="profile-help">Provider account status</div></div></div>
          <div class="verification-box"><div class="d-flex justify-content-between align-items-center gap-2"><span class="small fw-semibold">Current status</span><?= renderStatusBadge($provData['Verification_Status']) ?></div></div>
          <p class="profile-help mt-3 mb-0">Verification status is managed by MediLink administrators based on the provider information stored in the system.</p>
        </div>
        <div class="profile-panel">
          <div class="profile-section-title"><span class="profile-section-icon"><i class="bi bi-lock"></i></span><div><h5>Password & security</h5><div class="profile-help">Update your sign-in password.</div></div></div>
          <form method="POST" action="<?= url('provider/profile.php') ?>">
            <?= CSRF::inputField() ?><input type="hidden" name="action" value="change_password">
            <?php foreach ([['current_password','Current password'],['new_password','New password'],['confirm_password','Confirm new password']] as $i=>$f): ?>
            <div class="mb-3"><label class="form-label small fw-semibold"><?= $f[1] ?></label><div class="password-wrap"><input type="password" name="<?= $f[0] ?>" class="form-control profile-password" <?= $i===1?'minlength="6" placeholder="Minimum 6 characters"':'' ?> required><button class="password-toggle" type="button" aria-label="Show password"><i class="bi bi-eye"></i></button></div></div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-outline-secondary w-100"><i class="bi bi-key me-1"></i>Update password</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
document.querySelectorAll('.password-toggle').forEach(btn=>btn.addEventListener('click',()=>{const input=btn.parentElement.querySelector('input');const show=input.type==='password';input.type=show?'text':'password';btn.querySelector('i').className=show?'bi bi-eye-slash':'bi bi-eye';btn.setAttribute('aria-label',show?'Hide password':'Show password')}));
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
