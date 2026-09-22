<?php


$pageTitle = 'Home - Medical Mediator Platform';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();


$specs = $db->query("SELECT * FROM `SPECIALIZATION` ORDER BY Name ASC")->fetchAll();


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


$centreQuery = "
    SELECT hc.Centre_ID, p.Provider_ID, hc.Centre_Name, hc.Registration_No, hc.Description, p.City, p.Address,
           (SELECT COUNT(*) FROM `CENTRE_DOCTOR_LINK` cdl
            WHERE cdl.Centre_ID = hc.Centre_ID AND cdl.Status = 'ACTIVE') AS Active_Doctors,
           (SELECT COUNT(DISTINCT ds.Specialization_ID)
            FROM `CENTRE_DOCTOR_LINK` cdl
            JOIN `DOCTOR_SPECIALIZATION` ds ON ds.Doctor_ID = cdl.Doctor_ID
            WHERE cdl.Centre_ID = hc.Centre_ID AND cdl.Status = 'ACTIVE') AS Specialty_Count
    FROM `HEALTHCARE_CENTRE` hc
    JOIN `PROVIDER` p ON hc.Provider_ID = p.Provider_ID
    JOIN `USER` u ON p.User_ID = u.User_ID
    WHERE p.Verification_Status = 'VERIFIED' AND u.Account_Status = 'ACTIVE'
    LIMIT 2
";
$featuredCentres = $db->query($centreQuery)->fetchAll();


$plans = $db->query("SELECT * FROM `SUBSCRIPTION_PLAN` WHERE Status = 'ACTIVE' AND Target_Role = 'CLIENT' ORDER BY Price ASC")->fetchAll();

$cities = getSriLankanCities();
$publicStats = $db->query("SELECT (SELECT COUNT(*) FROM `PROVIDER` WHERE Verification_Status='VERIFIED') verified_providers, (SELECT COUNT(DISTINCT City) FROM `PROVIDER` WHERE Verification_Status='VERIFIED') cities_covered, (SELECT COUNT(*) FROM `DOCTOR`) doctors")->fetch();
?>

<!-- Home Hero V2 -->
<section class="home-hero-v2 text-center">
  <div class="container position-relative">
    <span class="hero-eyebrow"><i class="bi bi-patch-check-fill"></i> Verified healthcare discovery in Sri Lanka</span>
    <h1 class="hero-title">Find the right doctor.<br class="d-none d-md-block"> Book with confidence.</h1>
    <p class="hero-copy">Search verified doctors by specialty and city, compare provider details, and move from discovery to appointment booking in one place.</p>

    <div class="hero-search-v2 text-start">
      <form action="<?= url('client/search.php') ?>" method="GET" class="row g-0 align-items-center">
        <div class="col-md-5 search-field">
          <label class="search-label" for="heroSpecialization"><i class="bi bi-heart-pulse"></i> Specialty</label>
          <select id="heroSpecialization" name="specialization_id" class="form-select" aria-label="Choose a medical specialty">
            <option value="">All specializations</option>
            <?php foreach ($specs as $sp): ?>
              <option value="<?= $sp['Specialization_ID'] ?>"><?= e($sp['Name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 search-field">
          <label class="search-label" for="heroCity"><i class="bi bi-geo-alt"></i> City</label>
          <select id="heroCity" name="city" class="form-select" aria-label="Choose a Sri Lankan city">
            <option value="">Anywhere in Sri Lanka</option>
            <?php foreach (array_keys($cities) as $cName): ?>
              <option value="<?= e($cName) ?>"><?= e($cName) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 ps-md-2">
          <button type="submit" class="btn btn-teal hero-search-btn w-100">
            <i class="bi bi-search me-2"></i>Find Doctors
          </button>
        </div>
      </form>
    </div>

    <div class="hero-search-note">
      <span><i class="bi bi-shield-check"></i> Verified providers</span>
      <span><i class="bi bi-calendar2-check"></i> Clear appointment slots</span>
      <span><i class="bi bi-geo-alt"></i> Sri Lanka-wide search</span>
    </div>
  </div>
</section>

<!-- Trust Stats V2 -->
<section class="ml-trust-strip-v2" aria-label="MediLink network statistics">
  <div class="container">
    <div class="ml-trust-panel-v2">
      <div class="row g-0">
        <div class="col-md-4">
          <div class="ml-trust-item-v2">
            <span class="ml-trust-icon-v2"><i class="bi bi-patch-check"></i></span>
            <div><div class="ml-trust-number-v2"><?= number_format((int)$publicStats['verified_providers']) ?></div><div class="ml-trust-label-v2">Verified healthcare providers</div></div>
          </div>
        </div>
        <div class="col-md-4 ml-trust-divider-v2">
          <div class="ml-trust-item-v2">
            <span class="ml-trust-icon-v2"><i class="bi bi-person-badge"></i></span>
            <div><div class="ml-trust-number-v2"><?= number_format((int)$publicStats['doctors']) ?></div><div class="ml-trust-label-v2">Doctors & consultants listed</div></div>
          </div>
        </div>
        <div class="col-md-4 ml-trust-divider-v2">
          <div class="ml-trust-item-v2">
            <span class="ml-trust-icon-v2"><i class="bi bi-geo-alt"></i></span>
            <div><div class="ml-trust-number-v2"><?= number_format((int)$publicStats['cities_covered']) ?></div><div class="ml-trust-label-v2">Cities with verified providers</div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Featured Specialists V2 -->
<section class="ml-specialists-section py-5">
  <div class="container">
    <div class="ml-section-heading mb-4">
      <div>
        <span class="ml-section-kicker"><i class="bi bi-person-heart"></i> Specialists</span>
        <h2>Featured Doctors & Consultants</h2>
        <p>Explore verified professionals and review their specialty, experience and available appointment slots.</p>
      </div>
      <a href="<?= url('client/search.php') ?>" class="btn btn-outline-teal ml-section-link">
        View all <i class="bi bi-arrow-right"></i>
      </a>
    </div>

    <?php if ($featuredDoctors): ?>
      <div class="row g-4">
        <?php foreach ($featuredDoctors as $doc): ?>
          <div class="col-lg-4 col-md-6">
            <article class="ml-doctor-card h-100">
              <div class="ml-doctor-card-top">
                <div class="ml-doctor-avatar" aria-hidden="true">
                  <i class="bi bi-person"></i>
                </div>
                <div class="ml-doctor-identity">
                  <div class="d-flex align-items-start justify-content-between gap-2">
                    <div>
                      <h3>Dr. <?= e($doc['First_Name'] . ' ' . $doc['Last_Name']) ?></h3>
                      <div class="ml-doctor-specialty"><?= e($doc['Specializations'] ?: 'General Consultant') ?></div>
                    </div>
                    <span class="ml-verified-mark" title="Verified provider" aria-label="Verified provider"><i class="bi bi-patch-check-fill"></i></span>
                  </div>
                </div>
              </div>

              <div class="ml-doctor-meta">
                <span><i class="bi bi-briefcase"></i><strong><?= (int)$doc['Experience_Years'] ?></strong> years experience</span>
                <span><i class="bi bi-geo-alt"></i><?= e($doc['City']) ?></span>
              </div>

              <?php if (!empty($doc['Professional_Bio'])): ?>
                <p class="ml-doctor-bio text-truncate-2"><?= e($doc['Professional_Bio']) ?></p>
              <?php endif; ?>

              <div class="ml-doctor-license">
                <i class="bi bi-shield-check"></i>
                <span><small>Medical licence</small><?= e($doc['Medical_License_No']) ?></span>
              </div>

              <div class="ml-doctor-actions mt-auto">
                <a href="<?= url('client/provider_view.php?id=' . $doc['Provider_ID']) ?>" class="btn btn-teal w-100">
                  View profile & slots <i class="bi bi-arrow-up-right"></i>
                </a>
              </div>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="ml-specialists-empty text-center">
        <i class="bi bi-person-search"></i>
        <h3>No featured specialists yet</h3>
        <p class="mb-3">Verified specialists will appear here when they are available.</p>
        <a href="<?= url('client/search.php') ?>" class="btn btn-outline-teal">Search providers</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Partner Healthcare Centres V2 -->
<section class="ml-centres-section py-5">
  <div class="container">
    <div class="ml-section-heading mb-4">
      <div>
        <span class="ml-section-kicker"><i class="bi bi-hospital"></i> Healthcare network</span>
        <h2>Partner Healthcare Centres</h2>
        <p>Explore verified centres and the medical specialists currently affiliated with them.</p>
      </div>
      <a href="<?= url('client/search.php?type=HEALTHCARE_CENTRE') ?>" class="btn btn-outline-teal btn-sm ml-section-link">
        View all centres <i class="bi bi-arrow-right"></i>
      </a>
    </div>

    <?php if (!empty($featuredCentres)): ?>
      <div class="row g-4">
        <?php foreach ($featuredCentres as $hc): ?>
          <div class="col-lg-6">
            <article class="ml-centre-card h-100">
              <div class="ml-centre-card-head">
                <div class="ml-centre-icon"><i class="bi bi-hospital"></i></div>
                <div class="ml-centre-title">
                  <div class="ml-centre-verified"><i class="bi bi-patch-check-fill"></i> Verified partner</div>
                  <h3><?= e($hc['Centre_Name']) ?></h3>
                  <span><i class="bi bi-geo-alt"></i> <?= e($hc['City']) ?></span>
                </div>
              </div>

              <p class="ml-centre-description"><?= e($hc['Description'] ?: 'Verified healthcare centre available through MediLink Sri Lanka.') ?></p>

              <div class="ml-centre-stats">
                <div><i class="bi bi-people"></i><span><strong><?= (int)$hc['Active_Doctors'] ?></strong> Affiliated doctors</span></div>
                <div><i class="bi bi-heart-pulse"></i><span><strong><?= (int)$hc['Specialty_Count'] ?></strong> Specialties</span></div>
              </div>

              <div class="ml-centre-location">
                <i class="bi bi-signpost-2"></i>
                <span><?= e($hc['Address']) ?><?= $hc['Address'] && $hc['City'] ? ', ' : '' ?><?= e($hc['City']) ?></span>
              </div>

              <div class="ml-centre-footer">
                <span class="ml-centre-reg"><i class="bi bi-shield-check"></i> Reg. <?= e($hc['Registration_No']) ?></span>
                <a href="<?= url('client/provider_view.php?id=' . $hc['Provider_ID']) ?>" class="btn btn-teal btn-sm">
                  View doctors <i class="bi bi-arrow-up-right"></i>
                </a>
              </div>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="ml-centres-empty text-center">
        <i class="bi bi-hospital"></i>
        <h3>No partner centres to feature yet</h3>
        <p class="mb-3">Verified healthcare centres will appear here when available.</p>
        <a href="<?= url('client/search.php?type=HEALTHCARE_CENTRE') ?>" class="btn btn-outline-teal">Search healthcare centres</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Home Plans Preview V2 -->
<section class="ml-plans-preview py-5">
  <div class="container">
    <div class="ml-plans-intro">
      <span class="ml-section-kicker"><i class="bi bi-wallet2"></i> Simple membership</span>
      <h2>Choose a plan that fits your booking needs</h2>
      <p>Compare the essentials here, then open the full plans page for complete details before subscribing.</p>
    </div>

    <?php if (!empty($plans)): ?>
      <div class="row g-4 justify-content-center">
        <?php foreach ($plans as $plan): ?>
          <div class="col-lg-4 col-md-6">
            <article class="ml-plan-preview-card">
              <h3 class="ml-plan-name"><?= e($plan['Plan_Name']) ?></h3>
              <p class="ml-plan-description"><?= e($plan['Description']) ?></p>

              <div class="ml-plan-price">
                <strong><?= formatLKR($plan['Price']) ?></strong>
                <span>for <?= (int)$plan['Duration_Days'] ?> days</span>
              </div>

              <div class="ml-plan-benefits">
                <div class="ml-plan-benefit"><i class="bi bi-calendar2-check"></i><span><strong><?= (int)$plan['Max_Book_per_Month'] ?></strong> appointments per month</span></div>
                <div class="ml-plan-benefit"><i class="bi bi-geo-alt"></i><span>Search within <strong><?= (int)$plan['Search_Radius_KM'] ?> km</strong></span></div>
                <div class="ml-plan-benefit"><i class="bi bi-arrow-repeat"></i><span>Booking, cancellation & rescheduling</span></div>
              </div>

              <a href="<?= url('public/plans.php') ?>" class="btn btn-outline-teal w-100 mt-auto">
                View plan details <i class="bi bi-arrow-right ms-1"></i>
              </a>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="ml-plans-action">
        <a href="<?= url('public/plans.php') ?>" class="btn btn-teal">Compare all plans <i class="bi bi-arrow-right ms-1"></i></a>
      </div>
    <?php else: ?>
      <div class="text-center py-4 text-muted"><i class="bi bi-info-circle me-1"></i> No client plans are currently available.</div>
    <?php endif; ?>
  </div>
</section>


<!-- Home Final CTA V2 -->
<section class="ml-home-final-cta" aria-labelledby="homeFinalCtaTitle">
  <div class="container">
    <div class="ml-home-final-panel">
      <div class="ml-home-final-copy">
        <span class="ml-section-kicker"><i class="bi bi-heart-pulse"></i> Your next appointment starts here</span>
        <h2 id="homeFinalCtaTitle">Find verified healthcare without the guesswork.</h2>
        <p>Search by specialty and location, review provider details, and choose an appointment slot that works for you.</p>
        <div class="ml-home-final-trust" aria-label="Platform benefits">
          <span><i class="bi bi-patch-check"></i> Verified providers</span>
          <span><i class="bi bi-shield-check"></i> Protected account access</span>
          <span><i class="bi bi-calendar2-check"></i> Clear booking flow</span>
        </div>
      </div>
      <div class="ml-home-final-actions">
        <a href="<?= url('client/search.php') ?>" class="btn btn-teal btn-lg"><i class="bi bi-search me-2"></i>Find Doctors</a>
        <?php if (!isLoggedIn()): ?>
          <a href="<?= url('public/register.php') ?>" class="btn btn-outline-teal btn-lg"><i class="bi bi-person-plus me-2"></i>Create Account</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
