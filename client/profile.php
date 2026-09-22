<?php


$pageTitle = 'My Profile & GPS Location';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_CLIENT);
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];
$clientId = $currentUser['client_id'];

$db = Database::getConnection();
$cities = getSriLankanCities();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::check();
    $action = $_POST['action'] ?? 'update_profile';

    if ($action === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $nicNo = trim($_POST['nic_no'] ?? '');
        $dob = $_POST['dob'] ?: null;
        $gender = $_POST['gender'] ?? 'OTHER';
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? 'Colombo');
        $latitude = floatval($_POST['latitude'] ?? 6.9271);
        $longitude = floatval($_POST['longitude'] ?? 79.8612);

        try {
            $db->beginTransaction();

            $uStmt = $db->prepare("UPDATE `USER` SET First_Name = ?, Last_Name = ?, Phone = ?, NIC_No = ? WHERE User_ID = ?");
            $uStmt->execute([$firstName, $lastName, $phone, $nicNo ?: null, $userId]);

            $cStmt = $db->prepare("UPDATE `CLIENT` SET Date_of_Birth = ?, Gender = ?, Address = ?, City = ?, Latitude = ?, Longitude = ? WHERE Client_ID = ?");
            $cStmt->execute([$dob, $gender, $address, $city, $latitude, $longitude, $clientId]);

            $db->commit();

            $_SESSION['user']['first_name'] = $firstName;
            $_SESSION['user']['last_name'] = $lastName;
            $_SESSION['user']['full_name'] = $firstName . ' ' . $lastName;
            $_SESSION['user']['phone'] = $phone;

            setFlash('success', 'Profile and coordinates updated successfully.');
            redirect('client/profile.php');

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
            setFlash('success', 'Password updated successfully.');
            redirect('client/profile.php');
        }
    }
}


$dataStmt = $db->prepare("SELECT u.*, c.* FROM `USER` u JOIN `CLIENT` c ON u.User_ID = c.User_ID WHERE u.User_ID = ?");
$dataStmt->execute([$userId]);
$profile = $dataStmt->fetch();

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.profile-shell{max-width:1180px}.profile-hero{background:linear-gradient(135deg,rgba(8,127,120,.08),rgba(8,127,120,.02));border:1px solid rgba(8,127,120,.14);border-radius:20px;padding:1.35rem 1.5rem}.profile-avatar{width:58px;height:58px;border-radius:16px;display:grid;place-items:center;background:#087f78;color:#fff;font-size:1.45rem;font-weight:800}.profile-card{border:1px solid #e8ecef;border-radius:18px;background:#fff;box-shadow:0 10px 28px rgba(18,38,63,.05)}.profile-section-title{display:flex;align-items:center;gap:.7rem;margin-bottom:1rem}.profile-section-icon{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;background:rgba(8,127,120,.09);color:#087f78}.profile-card .form-control,.profile-card .form-select{min-height:46px;border-radius:11px}.profile-card .form-control:focus,.profile-card .form-select:focus{border-color:#087f78;box-shadow:0 0 0 .2rem rgba(8,127,120,.11)}.profile-readonly{background:#f7f9fa!important}.map-frame{height:270px;width:100%;border-radius:14px;border:1px solid #dfe5e8;overflow:hidden}.security-card{position:sticky;top:92px}.password-wrap{position:relative}.password-wrap .form-control{padding-right:44px}.password-toggle{position:absolute;right:7px;top:50%;transform:translateY(-50%);border:0;background:transparent;color:#6c757d;width:34px;height:34px;border-radius:8px}.password-toggle:hover{background:#f1f4f5;color:#087f78}.profile-help{background:#f8fafb;border:1px solid #edf0f2;border-radius:12px;padding:.85rem}.coords-badge{font-size:.72rem;white-space:normal}.save-bar{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding-top:.5rem}@media(max-width:991.98px){.security-card{position:static}.profile-hero{padding:1.1rem}.save-bar{align-items:flex-start;flex-direction:column}.save-bar .btn{width:100%}}@media(max-width:575.98px){.profile-avatar{width:48px;height:48px;border-radius:14px}.map-frame{height:230px}}
</style>

<div class="container profile-shell py-4 py-lg-5">
  <section class="profile-hero mb-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
      <div class="d-flex align-items-center gap-3">
        <div class="profile-avatar" aria-hidden="true"><?= e(strtoupper(substr($profile['First_Name'] ?? 'U', 0, 1))) ?></div>
        <div>
          <div class="small text-teal fw-semibold mb-1"><i class="bi bi-person-check me-1"></i>Client account</div>
          <h2 class="fw-bold mb-1"><?= e(trim(($profile['First_Name'] ?? '') . ' ' . ($profile['Last_Name'] ?? ''))) ?></h2>
          <p class="text-muted mb-0">Keep your personal details and search location up to date.</p>
        </div>
      </div>
      <a href="<?= url('client/dashboard.php') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-grid me-1"></i> Dashboard
      </a>
    </div>
  </section>

  <div class="row g-4 align-items-start">
    <div class="col-lg-8">
      <form method="POST" action="<?= url('client/profile.php') ?>" class="profile-card p-3 p-md-4">
        <?= CSRF::inputField() ?>
        <input type="hidden" name="action" value="update_profile">

        <section>
          <div class="profile-section-title">
            <span class="profile-section-icon"><i class="bi bi-person"></i></span>
            <div><h5 class="fw-bold mb-0">Personal information</h5><div class="small text-muted">Basic details attached to your MediLink account.</div></div>
          </div>

          <div class="row g-3">
            <div class="col-md-6"><label class="form-label small fw-semibold">First name</label><input type="text" name="first_name" class="form-control" value="<?= e($profile['First_Name']) ?>" autocomplete="given-name" required></div>
            <div class="col-md-6"><label class="form-label small fw-semibold">Last name</label><input type="text" name="last_name" class="form-control" value="<?= e($profile['Last_Name']) ?>" autocomplete="family-name" required></div>
            <div class="col-md-6"><label class="form-label small fw-semibold">Email address</label><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-envelope"></i></span><input type="email" class="form-control profile-readonly" value="<?= e($profile['Email']) ?>" readonly aria-describedby="emailHelp"></div><div id="emailHelp" class="form-text">Email is fixed for this account.</div></div>
            <div class="col-md-6"><label class="form-label small fw-semibold">Phone number</label><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-telephone"></i></span><input type="tel" name="phone" class="form-control" value="<?= e($profile['Phone']) ?>" autocomplete="tel" required></div></div>
            <div class="col-md-6"><label class="form-label small fw-semibold">NIC number <span class="text-muted fw-normal">(optional)</span></label><input type="text" name="nic_no" class="form-control" placeholder="e.g. 199012345678" value="<?= e($profile['NIC_No'] ?? '') ?>"></div>
            <div class="col-md-3"><label class="form-label small fw-semibold">Date of birth</label><input type="date" name="dob" class="form-control" value="<?= e($profile['Date_of_Birth']) ?>"></div>
            <div class="col-md-3"><label class="form-label small fw-semibold">Gender</label><select name="gender" class="form-select"><option value="MALE" <?= $profile['Gender'] === 'MALE' ? 'selected' : '' ?>>Male</option><option value="FEMALE" <?= $profile['Gender'] === 'FEMALE' ? 'selected' : '' ?>>Female</option><option value="OTHER" <?= $profile['Gender'] === 'OTHER' ? 'selected' : '' ?>>Other</option></select></div>
          </div>
        </section>

        <hr class="my-4">

        <section>
          <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
            <div class="profile-section-title mb-0">
              <span class="profile-section-icon"><i class="bi bi-geo-alt"></i></span>
              <div><h5 class="fw-bold mb-0">Search location</h5><div class="small text-muted">Used to calculate nearby healthcare results.</div></div>
            </div>
            <button type="button" id="btn-detect-location" class="btn btn-outline-teal btn-sm"><i class="bi bi-crosshair me-1"></i> Use my location</button>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-7"><label class="form-label small fw-semibold">Street / residential address</label><input type="text" name="address" id="address-input" class="form-control" value="<?= e($profile['Address']) ?>" autocomplete="street-address"></div>
            <div class="col-md-5"><label class="form-label small fw-semibold">City / district</label><select name="city" id="city-select" class="form-select"><?php foreach ($cities as $cName => $cData): ?><option value="<?= e($cName) ?>" data-lat="<?= e($cData['lat']) ?>" data-lng="<?= e($cData['lng']) ?>" <?= $profile['City'] === $cName ? 'selected' : '' ?>><?= e($cName) ?> · <?= e($cData['district']) ?></option><?php endforeach; ?></select></div>
          </div>

          <input type="hidden" name="latitude" id="latitude-input" value="<?= e($profile['Latitude']) ?>" required>
          <input type="hidden" name="longitude" id="longitude-input" value="<?= e($profile['Longitude']) ?>" required>

          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <label class="form-label small fw-semibold mb-0"><i class="bi bi-pin-map text-teal me-1"></i>Adjust map pin</label>
            <span class="badge bg-light text-secondary border font-monospace coords-badge" id="pin-coords-badge">Lat: <?= number_format((float)$profile['Latitude'], 4) ?>, Lng: <?= number_format((float)$profile['Longitude'], 4) ?></span>
          </div>
          <div id="map-picker" class="map-frame"></div>
          <div class="form-text mt-2"><i class="bi bi-hand-index me-1"></i>Click the map or drag the marker to fine-tune your location.</div>
          <div id="location-feedback" class="profile-help small mt-3"><i class="bi bi-check-circle text-success me-1"></i>Saved search city: <strong><?= e($profile['City']) ?></strong>.</div>
        </section>

        <div class="save-bar mt-4">
          <div class="small text-muted"><i class="bi bi-info-circle me-1"></i>Your location helps MediLink order nearby search results.</div>
          <button type="submit" class="btn btn-teal px-4"><i class="bi bi-check2 me-1"></i> Save changes</button>
        </div>
      </form>
    </div>

    <div class="col-lg-4">
      <aside class="profile-card p-3 p-md-4 security-card">
        <div class="profile-section-title">
          <span class="profile-section-icon"><i class="bi bi-shield-lock"></i></span>
          <div><h5 class="fw-bold mb-0">Password & security</h5><div class="small text-muted">Change your account password.</div></div>
        </div>

        <form method="POST" action="<?= url('client/profile.php') ?>" id="passwordForm">
          <?= CSRF::inputField() ?>
          <input type="hidden" name="action" value="change_password">
          <div class="mb-3"><label class="form-label small fw-semibold">Current password</label><div class="password-wrap"><input type="password" name="current_password" class="form-control" autocomplete="current-password" required><button type="button" class="password-toggle" aria-label="Show password"><i class="bi bi-eye"></i></button></div></div>
          <div class="mb-3"><label class="form-label small fw-semibold">New password</label><div class="password-wrap"><input type="password" name="new_password" class="form-control" minlength="6" autocomplete="new-password" placeholder="At least 6 characters" required><button type="button" class="password-toggle" aria-label="Show password"><i class="bi bi-eye"></i></button></div></div>
          <div class="mb-3"><label class="form-label small fw-semibold">Confirm new password</label><div class="password-wrap"><input type="password" name="confirm_password" class="form-control" minlength="6" autocomplete="new-password" required><button type="button" class="password-toggle" aria-label="Show password"><i class="bi bi-eye"></i></button></div></div>
          <button type="submit" class="btn btn-outline-teal w-100"><i class="bi bi-key me-1"></i> Update password</button>
        </form>

        <div class="profile-help small text-muted mt-3"><i class="bi bi-shield-check text-teal me-1"></i>Your current password is required before a new password can be saved.</div>
      </aside>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.password-toggle').forEach(function(btn){
  btn.addEventListener('click', function(){
    var input = btn.parentElement.querySelector('input');
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.innerHTML = '<i class="bi ' + (show ? 'bi-eye-slash' : 'bi-eye') + '"></i>';
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
