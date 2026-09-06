<?php
/**
 * Multi-Role Registration Page (Client, Doctor, Healthcare Centre)
 */

$pageTitle = 'Create an Account';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    redirect('public/index.php');
}

$db = Database::getConnection();

// Fetch specializations for doctor registration
$specializations = $db->query("SELECT * FROM `SPECIALIZATION` ORDER BY Name ASC")->fetchAll();
$cities = getSriLankanCities();

$errors = [];
$roleType = $_GET['role'] ?? $_POST['role_type'] ?? 'CLIENT';
$selectedPlanId = (int)($_GET['plan_id'] ?? $_POST['selected_plan_id'] ?? 0);

// Form Preserved Values
$form = [
    'first_name' => '',
    'last_name'  => '',
    'email'      => '',
    'phone'      => '',
    'role_type'  => $roleType,
    'provider_type' => $_POST['provider_type'] ?? 'DOCTOR',
    // Client fields
    'dob'        => '',
    'gender'     => 'MALE',
    'address'    => '',
    'city'       => 'Colombo',
    'latitude'   => '6.927100',
    'longitude'  => '79.861200',
    // Doctor fields
    'business_name' => '',
    'medical_license_no' => '',
    'experience_years' => 5,
    'consultation_duration' => 20,
    'bio'        => '',
    'specializations' => [],
    // Centre fields
    'centre_name' => '',
    'registration_no' => '',
    'centre_description' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::check();

    // Collect and sanitize base user info
    $form['first_name'] = trim($_POST['first_name'] ?? '');
    $form['last_name']  = trim($_POST['last_name'] ?? '');
    $form['email']      = trim($_POST['email'] ?? '');
    $form['phone']      = trim($_POST['phone'] ?? '');
    $password           = $_POST['password'] ?? '';
    $password_confirm   = $_POST['password_confirm'] ?? '';
    $form['role_type']  = $_POST['role_type'] ?? 'CLIENT';
    $form['provider_type'] = $_POST['provider_type'] ?? 'DOCTOR';
    $form['address']    = trim($_POST['address'] ?? '');
    $form['city']       = trim($_POST['city'] ?? 'Colombo');
    $form['latitude']   = floatval($_POST['latitude'] ?? 6.9271);
    $form['longitude']  = floatval($_POST['longitude'] ?? 79.8612);

    // Basic Validations
    if (empty($form['first_name']) || empty($form['last_name'])) {
        $errors[] = 'First Name and Last Name are required.';
    }
    if (empty($form['email']) || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if (empty($form['phone'])) {
        $errors[] = 'Phone number is required.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }
    if ($password !== $password_confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // Check duplicate email
    $chkStmt = $db->prepare("SELECT User_ID FROM `USER` WHERE Email = ?");
    $chkStmt->execute([$form['email']]);
    if ($chkStmt->fetch()) {
        $errors[] = 'This email address is already registered. Please sign in or use another email.';
    }

    // Role-specific validations
    if ($form['role_type'] === 'CLIENT') {
        $form['dob'] = $_POST['dob'] ?? null;
        $form['gender'] = $_POST['gender'] ?? 'OTHER';
    } elseif ($form['role_type'] === 'PROVIDER') {
        if ($form['provider_type'] === 'DOCTOR') {
            $form['business_name'] = trim($_POST['business_name'] ?? 'Dr. ' . $form['first_name'] . ' ' . $form['last_name'] . ' Clinic');
            $form['medical_license_no'] = trim($_POST['medical_license_no'] ?? '');
            $form['experience_years'] = (int)($_POST['experience_years'] ?? 0);
            $form['consultation_duration'] = (int)($_POST['consultation_duration'] ?? 20);
            $form['bio'] = trim($_POST['bio'] ?? '');
            $form['specializations'] = $_POST['specializations'] ?? [];

            if (empty($form['medical_license_no'])) {
                $errors[] = 'SLMC Medical License Number is required for doctor registration.';
            } else {
                $licStmt = $db->prepare("SELECT Doctor_ID FROM `DOCTOR` WHERE Medical_License_No = ?");
                $licStmt->execute([$form['medical_license_no']]);
                if ($licStmt->fetch()) {
                    $errors[] = 'This SLMC Medical License Number is already registered.';
                }
            }
        } else { // HEALTHCARE_CENTRE
            $form['centre_name'] = trim($_POST['centre_name'] ?? '');
            $form['registration_no'] = trim($_POST['registration_no'] ?? '');
            $form['centre_description'] = trim($_POST['centre_description'] ?? '');

            if (empty($form['centre_name'])) {
                $errors[] = 'Healthcare Centre Name is required.';
            }
            if (empty($form['registration_no'])) {
                $errors[] = 'Centre Registration/PHSRC Number is required.';
            } else {
                $regStmt = $db->prepare("SELECT Centre_ID FROM `HEALTHCARE_CENTRE` WHERE Registration_No = ?");
                $regStmt->execute([$form['registration_no']]);
                if ($regStmt->fetch()) {
                    $errors[] = 'This Healthcare Centre Registration Number is already registered.';
                }
            }
        }
    }

    // Process Registration if no validation errors
    if (empty($errors)) {
        try {
            $db->beginTransaction();

            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            
            // Insert USER
            $userStmt = $db->prepare("
                INSERT INTO `USER` (Email, Password_Hash, First_Name, Last_Name, Phone, Role_Type, Account_Status)
                VALUES (?, ?, ?, ?, ?, ?, 'ACTIVE')
            ");
            $userStmt->execute([
                $form['email'],
                $passwordHash,
                $form['first_name'],
                $form['last_name'],
                $form['phone'],
                $form['role_type']
            ]);
            $userId = (int)$db->lastInsertId();

            if ($form['role_type'] === 'CLIENT') {
                // Insert CLIENT
                $clientStmt = $db->prepare("
                    INSERT INTO `CLIENT` (User_ID, Date_of_Birth, Gender, Address, City, Latitude, Longitude)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $clientStmt->execute([
                    $userId,
                    $form['dob'] ?: null,
                    $form['gender'],
                    $form['address'],
                    $form['city'],
                    $form['latitude'],
                    $form['longitude']
                ]);
            } else { // PROVIDER
                $provType = $form['provider_type'];
                $bName = ($provType === 'DOCTOR') ? $form['business_name'] : $form['centre_name'];
                
                // Insert PROVIDER
                $provStmt = $db->prepare("
                    INSERT INTO `PROVIDER` (User_ID, Provider_Type, Business_Name, Address, City, Latitude, Longitude, Contact_Number, Description, Verification_Status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'VERIFIED')
                ");
                $provStmt->execute([
                    $userId,
                    $provType,
                    $bName,
                    $form['address'],
                    $form['city'],
                    $form['latitude'],
                    $form['longitude'],
                    $form['phone'],
                    ($provType === 'DOCTOR' ? $form['bio'] : $form['centre_description'])
                ]);
                $providerId = (int)$db->lastInsertId();

                if ($provType === 'DOCTOR') {
                    // Insert DOCTOR
                    $docStmt = $db->prepare("
                        INSERT INTO `DOCTOR` (Provider_ID, Medical_License_No, Professional_Bio, Experience_Years, Consultation_Duration, Verification_Status)
                        VALUES (?, ?, ?, ?, ?, 'VERIFIED')
                    ");
                    $docStmt->execute([
                        $providerId,
                        $form['medical_license_no'],
                        $form['bio'],
                        $form['experience_years'],
                        $form['consultation_duration']
                    ]);
                    $doctorId = (int)$db->lastInsertId();

                    // Insert DOCTOR_SPECIALIZATION links
                    if (!empty($form['specializations'])) {
                        $dsStmt = $db->prepare("INSERT INTO `DOCTOR_SPECIALIZATION` (Doctor_ID, Specialization_ID) VALUES (?, ?)");
                        foreach ($form['specializations'] as $spId) {
                            $dsStmt->execute([$doctorId, (int)$spId]);
                        }
                    }
                } else {
                    // Insert HEALTHCARE_CENTRE
                    $hcStmt = $db->prepare("
                        INSERT INTO `HEALTHCARE_CENTRE` (Provider_ID, Centre_Name, Registration_No, Description, Verification_Status)
                        VALUES (?, ?, ?, ?, 'VERIFIED')
                    ");
                    $hcStmt->execute([
                        $providerId,
                        $form['centre_name'],
                        $form['registration_no'],
                        $form['centre_description']
                    ]);
                }
            }

            $db->commit();

            // Fetch complete user record to establish session
            $uStmt = $db->prepare("SELECT * FROM `USER` WHERE User_ID = ?");
            $uStmt->execute([$userId]);
            $createdUser = $uStmt->fetch();
            loginUser($createdUser);

            setFlash('success', 'Registration successful! Welcome to MediLink Sri Lanka.');

            if ($selectedPlanId > 0) {
                redirect(($form['role_type'] === 'CLIENT' ? 'client' : 'provider') . '/subscription.php?plan_id=' . $selectedPlanId);
            } else {
                redirect(($form['role_type'] === 'CLIENT' ? 'client/dashboard.php' : 'provider/dashboard.php'));
            }

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Registration failed: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="card card-custom p-4 p-md-5 shadow">
        <div class="text-center mb-4">
          <h2 class="fw-bold">Create Your MediLink Account</h2>
          <p class="text-muted small">Join Sri Lanka's healthcare mediator platform</p>
        </div>

        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i> Please correct the following:</h6>
            <ul class="mb-0 small ps-3">
              <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
              <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <form method="POST" action="<?= url('public/register.php') ?>" id="registration-form">
          <?= CSRF::inputField() ?>
          <input type="hidden" name="selected_plan_id" value="<?= $selectedPlanId ?>">

          <!-- Role Selection Buttons -->
          <div class="mb-4">
            <label class="form-label fw-bold small text-uppercase letter-spacing-1 text-muted">Select Account Type</label>
            <div class="row g-2">
              <div class="col-md-4">
                <input type="radio" class="btn-check" name="role_type" id="role_client" value="CLIENT" <?= $form['role_type'] === 'CLIENT' ? 'checked' : '' ?> onchange="toggleRoleFields()">
                <label class="btn btn-outline-teal w-100 py-2 d-flex flex-column align-items-center gap-1" for="role_client">
                  <i class="bi bi-person fs-4"></i>
                  <span class="fw-semibold">Patient / Client</span>
                </label>
              </div>
              <div class="col-md-4">
                <input type="radio" class="btn-check" name="role_type" id="role_doctor" value="PROVIDER" data-provider-type="DOCTOR" <?= ($form['role_type'] === 'PROVIDER' && $form['provider_type'] === 'DOCTOR') ? 'checked' : '' ?> onchange="toggleRoleFields()">
                <label class="btn btn-outline-teal w-100 py-2 d-flex flex-column align-items-center gap-1" for="role_doctor">
                  <i class="bi bi-person-badge fs-4"></i>
                  <span class="fw-semibold">Specialist Doctor</span>
                </label>
              </div>
              <div class="col-md-4">
                <input type="radio" class="btn-check" name="role_type" id="role_centre" value="PROVIDER" data-provider-type="HEALTHCARE_CENTRE" <?= ($form['role_type'] === 'PROVIDER' && $form['provider_type'] === 'HEALTHCARE_CENTRE') ? 'checked' : '' ?> onchange="toggleRoleFields()">
                <label class="btn btn-outline-teal w-100 py-2 d-flex flex-column align-items-center gap-1" for="role_centre">
                  <i class="bi bi-hospital fs-4"></i>
                  <span class="fw-semibold">Healthcare Centre</span>
                </label>
              </div>
            </div>
            <input type="hidden" name="provider_type" id="provider_type_input" value="<?= e($form['provider_type']) ?>">
          </div>

          <!-- Basic User Fields -->
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">First Name *</label>
              <input type="text" name="first_name" class="form-control" value="<?= e($form['first_name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Last Name *</label>
              <input type="text" name="last_name" class="form-control" value="<?= e($form['last_name']) ?>" required>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Email Address *</label>
              <input type="email" name="email" class="form-control" value="<?= e($form['email']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Phone Number (Sri Lanka) *</label>
              <input type="tel" name="phone" class="form-control" placeholder="0771234567" value="<?= e($form['phone']) ?>" required>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Password *</label>
              <input type="password" name="password" class="form-control" placeholder="Min 6 characters" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Confirm Password *</label>
              <input type="password" name="password_confirm" class="form-control" required>
            </div>
          </div>

          <!-- Section: Client Specific Fields -->
          <div id="section-client" class="<?= $form['role_type'] === 'CLIENT' ? '' : 'd-none' ?>">
            <hr class="my-4">
            <h6 class="text-teal fw-bold mb-3"><i class="bi bi-person-lines-fill me-2"></i> Patient Information</h6>
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Date of Birth</label>
                <input type="date" name="dob" class="form-control" value="<?= e($form['dob']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Gender</label>
                <select name="gender" class="form-select">
                  <option value="MALE" <?= $form['gender'] === 'MALE' ? 'selected' : '' ?>>Male</option>
                  <option value="FEMALE" <?= $form['gender'] === 'FEMALE' ? 'selected' : '' ?>>Female</option>
                  <option value="OTHER" <?= $form['gender'] === 'OTHER' ? 'selected' : '' ?>>Other</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Section: Doctor Specific Fields -->
          <div id="section-doctor" class="<?= ($form['role_type'] === 'PROVIDER' && $form['provider_type'] === 'DOCTOR') ? '' : 'd-none' ?>">
            <hr class="my-4">
            <h6 class="text-teal fw-bold mb-3"><i class="bi bi-award-fill me-2"></i> Doctor SLMC & Medical Practice Credentials</h6>
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label small fw-semibold">SLMC License Number *</label>
                <input type="text" name="medical_license_no" class="form-control" placeholder="e.g. SLMC-48291" value="<?= e($form['medical_license_no']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Practice / Clinic Name</label>
                <input type="text" name="business_name" class="form-control" placeholder="e.g. Dr. Silva Family Health Clinic" value="<?= e($form['business_name']) ?>">
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Years of Experience</label>
                <input type="number" name="experience_years" class="form-control" min="0" max="60" value="<?= e($form['experience_years']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Consultation Duration (Minutes)</label>
                <input type="number" name="consultation_duration" class="form-control" min="10" max="120" step="5" value="<?= e($form['consultation_duration']) ?>">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-semibold">Specializations (Select all that apply)</label>
              <div class="row g-2">
                <?php foreach ($specializations as $sp): ?>
                  <div class="col-md-6">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="specializations[]" value="<?= $sp['Specialization_ID'] ?>" id="sp_<?= $sp['Specialization_ID'] ?>">
                      <label class="form-check-label small" for="sp_<?= $sp['Specialization_ID'] ?>">
                        <?= e($sp['Name']) ?>
                      </label>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-semibold">Professional Bio & Qualifications</label>
              <textarea name="bio" class="form-control" rows="2" placeholder="e.g. MBBS (Colombo), MD (Cardiology), Consultant at NHSL..."><?= e($form['bio']) ?></textarea>
            </div>
          </div>

          <!-- Section: Healthcare Centre Specific Fields -->
          <div id="section-centre" class="<?= ($form['role_type'] === 'PROVIDER' && $form['provider_type'] === 'HEALTHCARE_CENTRE') ? '' : 'd-none' ?>">
            <hr class="my-4">
            <h6 class="text-teal fw-bold mb-3"><i class="bi bi-hospital-fill me-2"></i> Healthcare Facility Details</h6>
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Centre / Hospital Name *</label>
                <input type="text" name="centre_name" class="form-control" placeholder="e.g. Asiri MediCare Complex" value="<?= e($form['centre_name']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">PHSRC / Ministry Registration No *</label>
                <input type="text" name="registration_no" class="form-control" placeholder="e.g. PHSRC/HC/2024/092" value="<?= e($form['registration_no']) ?>">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Facility Overview & Facilities</label>
              <textarea name="centre_description" class="form-control" rows="2" placeholder="Description of outpatient suites, laboratory, radiology services..."><?= e($form['centre_description']) ?></textarea>
            </div>
          </div>

          <!-- Location & Hybrid Geolocation Picker -->
          <hr class="my-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="text-teal fw-bold mb-0"><i class="bi bi-geo-alt-fill me-2"></i> Sri Lankan Location & GPS Coordinates</h6>
            <button type="button" id="btn-detect-location" class="btn btn-outline-teal btn-sm">
              <i class="bi bi-crosshair me-1"></i> Detect My Current Location
            </button>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Street Address / Clinic Address</label>
            <input type="text" name="address" class="form-control" placeholder="e.g. 75 Ward Place, Colombo 07" value="<?= e($form['address']) ?>">
          </div>

          <div class="row g-3 mb-2">
            <div class="col-md-4">
              <label class="form-label small fw-semibold">City / District *</label>
              <select name="city" id="city-select" class="form-select">
                <?php foreach ($cities as $cName => $cData): ?>
                  <option value="<?= $cName ?>" <?= $form['city'] === $cName ? 'selected' : '' ?>><?= $cName ?> (<?= $cData['district'] ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Latitude</label>
              <input type="number" step="0.000001" name="latitude" id="latitude-input" class="form-control" value="<?= e($form['latitude']) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Longitude</label>
              <input type="number" step="0.000001" name="longitude" id="longitude-input" class="form-control" value="<?= e($form['longitude']) ?>" required>
            </div>
          </div>

          <div id="location-feedback" class="small mb-4">
            <span class="text-muted"><i class="bi bi-info-circle"></i> Coordinates are used for accurate Haversine distance search within subscription radius.</span>
          </div>

          <button type="submit" class="btn btn-teal w-100 py-3 fw-bold fs-6">
            <i class="bi bi-check-circle-fill me-2"></i> Create Account & Get Started
          </button>
        </form>

        <div class="text-center small text-muted mt-4">
          Already have an account? <a href="<?= url('public/login.php') ?>" class="text-teal fw-semibold">Sign In Here</a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function toggleRoleFields() {
  const roleClient = document.getElementById('role_client');
  const roleDoctor = document.getElementById('role_doctor');
  const roleCentre = document.getElementById('role_centre');

  const secClient = document.getElementById('section-client');
  const secDoctor = document.getElementById('section-doctor');
  const secCentre = document.getElementById('section-centre');
  const provInput = document.getElementById('provider_type_input');

  if (roleClient.checked) {
    secClient.classList.remove('d-none');
    secDoctor.classList.add('d-none');
    secCentre.classList.add('d-none');
  } else if (roleDoctor.checked) {
    secClient.classList.add('d-none');
    secDoctor.classList.remove('d-none');
    secCentre.classList.add('d-none');
    provInput.value = 'DOCTOR';
  } else if (roleCentre.checked) {
    secClient.classList.add('d-none');
    secDoctor.classList.add('d-none');
    secCentre.classList.remove('d-none');
    provInput.value = 'HEALTHCARE_CENTRE';
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
