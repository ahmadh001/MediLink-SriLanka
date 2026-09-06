<?php
/**
 * Landing Page - MediLink Sri Lanka
 */

$pageTitle = 'Home - Medical Mediator Platform';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

// Fetch specializations for search bar
$specs = $db->query("SELECT * FROM `SPECIALIZATION` ORDER BY Name ASC")->fetchAll();

// Fetch featured verified doctors
$docQuery = "
    SELECT d.Doctor_ID, p.Provider_ID, p.Business_Name, p.City, p.Address, p.Verification_Status,
           u.First_Name, u.Last_Name, d.Medical_License_No, d.Experience_Years, d.Professional_Bio,
           GROUP_CONCAT(s.Name SEPARATOR ', ') AS Specializations
    FROM `DOCTOR` d
    JOIN `PROVIDER` p ON d.Provider_ID = p.Provider_ID
    JOIN `USER` u ON p.User_ID = u.User_ID
    LEFT JOIN `DOCTOR_SPECIALIZATION` ds ON d.Doctor_ID = ds.Doctor_ID
    LEFT JOIN `SPECIALIZATION` s ON ds.Specialization_ID = s.Specialization_ID
    WHERE p.Verification_Status = 'VERIFIED' AND u.Account_Status = 'ACTIVE'
    GROUP BY d.Doctor_ID
    LIMIT 3
";
$featuredDoctors = $db->query($docQuery)->fetchAll();

// Fetch featured verified healthcare centres
$centreQuery = "
    SELECT hc.Centre_ID, p.Provider_ID, hc.Centre_Name, hc.Registration_No, hc.Description, p.City, p.Address
    FROM `HEALTHCARE_CENTRE` hc
    JOIN `PROVIDER` p ON hc.Provider_ID = p.Provider_ID
    JOIN `USER` u ON p.User_ID = u.User_ID
    WHERE p.Verification_Status = 'VERIFIED' AND u.Account_Status = 'ACTIVE'
    LIMIT 2
";
$featuredCentres = $db->query($centreQuery)->fetchAll();

// Fetch public subscription plans
$plans = $db->query("SELECT * FROM `SUBSCRIPTION_PLAN` WHERE Status = 'ACTIVE' AND Target_Role = 'CLIENT' ORDER BY Price ASC")->fetchAll();

$cities = getSriLankanCities();
?>

<!-- Hero Section -->
<section class="hero-section text-center text-md-start">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <span class="badge bg-light text-dark px-3 py-2 rounded-pill fw-semibold mb-3">
          <i class="bi bi-shield-check text-success me-1"></i> Sri Lanka's Verified Healthcare Network
        </span>
        <h1 class="display-4 fw-bold mb-3">Book Doctor Appointments Across Sri Lanka</h1>
        <p class="lead text-light mb-4 opacity-90">
          Subscribe to MediLink for seamless search by GPS location, specialized medical discovery, and direct calendar appointment booking with verified practitioners.
        </p>

        <!-- Quick Search Bar Card -->
        <div class="card card-custom p-3 shadow-lg border-0">
          <form action="<?= url('client/search.php') ?>" method="GET" class="row g-2 align-items-end">
            <div class="col-md-5 text-start">
              <label class="form-label text-muted small fw-semibold mb-1"><i class="bi bi-heart-pulse text-teal"></i> Specialization</label>
              <select name="specialization_id" class="form-select">
                <option value="">All Specializations</option>
                <?php foreach ($specs as $sp): ?>
                  <option value="<?= $sp['Specialization_ID'] ?>"><?= e($sp['Name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4 text-start">
              <label class="form-label text-muted small fw-semibold mb-1"><i class="bi bi-geo-alt text-teal"></i> Location / City</label>
              <select name="city" class="form-select">
                <option value="">All Sri Lankan Cities</option>
                <?php foreach (array_keys($cities) as $cName): ?>
                  <option value="<?= $cName ?>"><?= $cName ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <button type="submit" class="btn btn-teal w-100 py-2 fw-semibold">
                <i class="bi bi-search me-1"></i> Search
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="col-lg-5 d-none d-lg-block">
        <div class="card card-custom p-4 bg-white text-dark shadow-lg">
          <h5 class="fw-bold mb-3 text-teal"><i class="bi bi-lightning-charge-fill text-warning me-1"></i> How Subscription Works</h5>
          <div class="d-flex align-items-start gap-3 mb-3">
            <div class="rounded-circle bg-light p-2 text-teal fw-bold">1</div>
            <div>
              <h6 class="fw-semibold mb-1">Choose a Client Plan</h6>
              <p class="small text-muted mb-0">Select from Basic, Standard, or Premium based on your monthly booking & search radius needs in LKR.</p>
            </div>
          </div>
          <div class="d-flex align-items-start gap-3 mb-3">
            <div class="rounded-circle bg-light p-2 text-teal fw-bold">2</div>
            <div>
              <h6 class="fw-semibold mb-1">Search Verified Specialists</h6>
              <p class="small text-muted mb-0">Filter by SLMC doctors, healthcare centres, and calculate exact distance via GPS.</p>
            </div>
          </div>
          <div class="d-flex align-items-start gap-3">
            <div class="rounded-circle bg-light p-2 text-teal fw-bold">3</div>
            <div>
              <h6 class="fw-semibold mb-1">Book & Attend</h6>
              <p class="small text-muted mb-0">Instantly lock available schedule slots. Settle doctor consultation fees directly at the clinic.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Stats Bar -->
<section class="py-4 bg-white border-bottom shadow-sm">
  <div class="container">
    <div class="row g-3 text-center">
      <div class="col-6 col-md-3">
        <div class="fw-bold fs-3 text-teal">SLMC</div>
        <div class="small text-muted">Verified Medical Council Registry</div>
      </div>
      <div class="col-6 col-md-3">
        <div class="fw-bold fs-3 text-teal">100% Locked</div>
        <div class="small text-muted">Race-Condition Safe Booking</div>
      </div>
      <div class="col-6 col-md-3">
        <div class="fw-bold fs-3 text-teal">Haversine GPS</div>
        <div class="small text-muted">Radius-Based Islandwide Search</div>
      </div>
      <div class="col-6 col-md-3">
        <div class="fw-bold fs-3 text-teal">LKR Pricing</div>
        <div class="small text-muted">Transparent Monthly Subscriptions</div>
      </div>
    </div>
  </div>
</section>

<!-- Featured Specialists -->
<section class="py-5">
  <div class="container">
    <div class="d-flex justify-content-between align-items-end mb-4">
      <div>
        <h6 class="text-teal text-uppercase fw-bold letter-spacing-1 mb-1">Specialists</h6>
        <h2 class="fw-bold mb-0">Featured Doctors & Consultants</h2>
      </div>
      <a href="<?= url('client/search.php') ?>" class="btn btn-outline-teal btn-sm">View All Specialists <i class="bi bi-arrow-right"></i></a>
    </div>

    <div class="row g-4">
      <?php foreach ($featuredDoctors as $doc): ?>
        <div class="col-md-4">
          <div class="card card-custom h-100 p-3">
            <div class="d-flex align-items-center gap-3 mb-3">
              <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-teal" style="width: 54px; height: 54px; font-size: 1.5rem;">
                <i class="bi bi-person-badge"></i>
              </div>
              <div>
                <h5 class="fw-bold mb-0">Dr. <?= e($doc['First_Name'] . ' ' . $doc['Last_Name']) ?></h5>
                <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-patch-check-fill"></i> <?= e($doc['Medical_License_No']) ?></span>
              </div>
            </div>
            <p class="text-teal fw-semibold small mb-1"><i class="bi bi-award me-1"></i> <?= e($doc['Specializations'] ?: 'General Consultant') ?></p>
            <p class="text-muted small mb-3 text-truncate-2"><?= e($doc['Professional_Bio']) ?></p>
            <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
              <span class="small text-muted"><i class="bi bi-geo-alt"></i> <?= e($doc['City']) ?> (<?= $doc['Experience_Years'] ?> yrs exp)</span>
              <a href="<?= url('client/provider_view.php?id=' . $doc['Provider_ID']) ?>" class="btn btn-teal btn-sm px-3">View Profile & Slots</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Healthcare Centres -->
<section class="py-5 bg-light">
  <div class="container">
    <div class="d-flex justify-content-between align-items-end mb-4">
      <div>
        <h6 class="text-teal text-uppercase fw-bold letter-spacing-1 mb-1">Institutions</h6>
        <h2 class="fw-bold mb-0">Partner Healthcare Centres</h2>
      </div>
      <a href="<?= url('client/search.php?type=HEALTHCARE_CENTRE') ?>" class="btn btn-outline-teal btn-sm">View All Centres <i class="bi bi-arrow-right"></i></a>
    </div>

    <div class="row g-4">
      <?php foreach ($featuredCentres as $hc): ?>
        <div class="col-md-6">
          <div class="card card-custom h-100 p-4">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <h4 class="fw-bold text-teal mb-0"><?= e($hc['Centre_Name']) ?></h4>
              <span class="badge bg-info text-dark"><?= e($hc['Registration_No']) ?></span>
            </div>
            <p class="text-muted small mb-2"><i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= e($hc['Address']) ?>, <?= e($hc['City']) ?></p>
            <p class="text-secondary small mb-4"><?= e($hc['Description']) ?></p>
            <div class="mt-auto">
              <a href="<?= url('client/provider_view.php?id=' . $hc['Provider_ID']) ?>" class="btn btn-teal btn-sm px-3">View Centre & Doctors</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Subscription Pricing Preview -->
<section class="py-5">
  <div class="container">
    <div class="text-center max-w-700 mx-auto mb-5">
      <h6 class="text-teal text-uppercase fw-bold letter-spacing-1 mb-1">Flexible Membership</h6>
      <h2 class="fw-bold mb-2">Client Subscription Plans</h2>
      <p class="text-muted">Choose the membership tier that fits your healthcare needs with guaranteed monthly appointment quotas and GPS radius filters.</p>
    </div>

    <div class="row g-4 justify-content-center">
      <?php foreach ($plans as $plan): ?>
        <div class="col-md-4">
          <div class="card plan-card h-100 p-4 bg-white <?= $plan['Plan_ID'] == 2 ? 'featured' : '' ?>">
            <?php if ($plan['Plan_ID'] == 2): ?>
              <span class="badge bg-teal text-white plan-badge"><i class="bi bi-star-fill me-1"></i> Most Popular</span>
            <?php endif; ?>
            <h4 class="fw-bold mb-1"><?= e($plan['Plan_Name']) ?></h4>
            <p class="text-muted small mb-3"><?= e($plan['Description']) ?></p>
            
            <div class="mb-4">
              <span class="stat-value text-teal"><?= formatLKR($plan['Price']) ?></span>
              <span class="text-muted small"> / year (<?= $plan['Duration_Days'] ?> days)</span>
            </div>

            <ul class="list-unstyled d-flex flex-column gap-2 small text-secondary mb-4">
              <li><i class="bi bi-check2-circle text-success me-2"></i> <strong><?= $plan['Max_Book_per_Month'] ?> Appointments</strong> per month</li>
              <li><i class="bi bi-check2-circle text-success me-2"></i> <strong><?= $plan['Search_Radius_KM'] ?> km</strong> search distance radius</li>
              <li><i class="bi bi-check2-circle text-success me-2"></i> Direct calendar slot booking</li>
              <li><i class="bi bi-check2-circle text-success me-2"></i> Real-time cancellation & rescheduling</li>
            </ul>

            <div class="mt-auto">
              <a href="<?= url('public/plans.php') ?>" class="btn <?= $plan['Plan_ID'] == 2 ? 'btn-teal' : 'btn-outline-teal' ?> w-100 py-2">
                Subscribe to <?= e($plan['Plan_Name']) ?>
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
