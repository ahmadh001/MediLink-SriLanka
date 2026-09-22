<?php
$pageTitle = 'About MediLink Sri Lanka';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="about-hero about-hero-v2">
  <div class="container py-5 py-lg-6">
    <div class="row align-items-center g-5">
      <div class="col-lg-7">
        <span class="section-kicker"><i class="bi bi-heart-pulse"></i> About MediLink</span>
        <h1 class="display-4 fw-bold mt-3 mb-3">Healthcare discovery and booking, made easier.</h1>
        <p class="lead text-secondary mb-4 about-lead">MediLink Sri Lanka brings patients and verified healthcare providers into one clear platform for discovery, availability and appointment reservations.</p>
        <div class="d-flex flex-wrap gap-2 mb-4">
          <a href="<?= url('client/search.php') ?>" class="btn btn-teal btn-lg px-4"><i class="bi bi-search me-2"></i>Find Doctors</a>
          <a href="#how-it-works" class="btn btn-outline-teal btn-lg px-4"><i class="bi bi-arrow-down-circle me-2"></i>How it works</a>
        </div>
        <div class="about-trust-row" aria-label="Platform highlights">
          <span><i class="bi bi-patch-check"></i> Verified provider profiles</span>
          <span><i class="bi bi-calendar2-check"></i> Appointment reservations</span>
          <span><i class="bi bi-shield-lock"></i> Protected account features</span>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="about-visual-card about-visual-v2">
          <div class="about-visual-top">
            <div class="about-icon"><i class="bi bi-hospital"></i></div>
            <div><span class="about-card-label">MEDILINK WORKFLOW</span><h4 class="fw-bold mb-0">From search to appointment</h4></div>
          </div>
          <div class="about-flow-list">
            <div class="about-flow-item"><span class="flow-icon"><i class="bi bi-search"></i></span><div><strong>Search</strong><small>Choose a specialty and location.</small></div><i class="bi bi-chevron-right flow-arrow"></i></div>
            <div class="about-flow-item"><span class="flow-icon"><i class="bi bi-person-vcard"></i></span><div><strong>Review</strong><small>Check provider details and available slots.</small></div><i class="bi bi-chevron-right flow-arrow"></i></div>
            <div class="about-flow-item"><span class="flow-icon"><i class="bi bi-calendar2-check"></i></span><div><strong>Book</strong><small>Sign in and reserve an available time.</small></div><i class="bi bi-check2 flow-arrow"></i></div>
          </div>
          <div class="about-card-note"><i class="bi bi-info-circle"></i><span>Guests can explore first; protected actions require an account.</span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="container py-5 py-lg-6" id="how-it-works">
  <div class="row align-items-end g-3 mb-4 mb-lg-5">
    <div class="col-lg-7"><span class="section-kicker"><i class="bi bi-signpost-split"></i> How MediLink works</span><h2 class="fw-bold mt-2 mb-2">A simple path from discovery to care.</h2><p class="text-muted mb-0">The public experience stays easy to explore, while account-based actions remain protected.</p></div>
    <div class="col-lg-5 text-lg-end"><a href="<?= url('client/search.php') ?>" class="about-text-link">Explore healthcare providers <i class="bi bi-arrow-right"></i></a></div>
  </div>
  <div class="row g-3 g-lg-4 about-process-grid">
    <div class="col-sm-6 col-lg-3"><article class="about-process-card h-100"><div class="process-head"><span>01</span><i class="bi bi-search"></i></div><h5>Discover</h5><p>Search available doctors and healthcare centres using specialty and location.</p></article></div>
    <div class="col-sm-6 col-lg-3"><article class="about-process-card h-100"><div class="process-head"><span>02</span><i class="bi bi-person-lines-fill"></i></div><h5>Review</h5><p>Open provider profiles and review the information available through MediLink.</p></article></div>
    <div class="col-sm-6 col-lg-3"><article class="about-process-card h-100"><div class="process-head"><span>03</span><i class="bi bi-box-arrow-in-right"></i></div><h5>Sign in</h5><p>Create or use a client account when you need protected booking features.</p></article></div>
    <div class="col-sm-6 col-lg-3"><article class="about-process-card h-100"><div class="process-head"><span>04</span><i class="bi bi-calendar2-check"></i></div><h5>Reserve</h5><p>Select an available slot and keep track of the appointment from your account.</p></article></div>
  </div>
</section>


<section class="about-roles-section py-5 py-lg-6" id="access-levels">
  <div class="container">
    <div class="row align-items-end g-3 mb-4 mb-lg-5">
      <div class="col-lg-8">
        <span class="section-kicker"><i class="bi bi-people"></i> Platform access</span>
        <h2 class="fw-bold mt-2 mb-2">One platform, clear tools for every role.</h2>
        <p class="text-muted mb-0">MediLink keeps patient booking, provider operations and administration separated so each user sees the tools relevant to their work.</p>
      </div>
    </div>
    <div class="row g-4 about-role-grid">
      <div class="col-lg-4">
        <article class="about-role-card h-100">
          <div class="role-card-top"><span class="role-icon"><i class="bi bi-person-heart"></i></span><span class="role-label">CLIENT</span></div>
          <h3>For Clients</h3><p class="role-summary">Discover healthcare providers and manage your own appointment journey.</p>
          <ul class="role-feature-list"><li><i class="bi bi-check2"></i><span>Search by specialty and location</span></li><li><i class="bi bi-check2"></i><span>Review provider profiles and slots</span></li><li><i class="bi bi-check2"></i><span>Book and manage appointments</span></li><li><i class="bi bi-check2"></i><span>Manage profile and subscription access</span></li></ul>
          <a href="<?= url('client/search.php') ?>" class="role-card-link">Explore providers <i class="bi bi-arrow-right"></i></a>
        </article>
      </div>
      <div class="col-lg-4">
        <article class="about-role-card h-100">
          <div class="role-card-top"><span class="role-icon"><i class="bi bi-hospital"></i></span><span class="role-label">PROVIDER</span></div>
          <h3>For Providers</h3><p class="role-summary">Coordinate doctors, availability and appointment activity from one workspace.</p>
          <ul class="role-feature-list"><li><i class="bi bi-check2"></i><span>Maintain doctor information</span></li><li><i class="bi bi-check2"></i><span>Create and manage appointment slots</span></li><li><i class="bi bi-check2"></i><span>Handle appointment status changes</span></li><li><i class="bi bi-check2"></i><span>Manage provider profile and subscription</span></li></ul>
          <a href="<?= url('public/login.php') ?>" class="role-card-link">Provider sign in <i class="bi bi-arrow-right"></i></a>
        </article>
      </div>
      <div class="col-lg-4">
        <article class="about-role-card h-100">
          <div class="role-card-top"><span class="role-icon"><i class="bi bi-shield-lock"></i></span><span class="role-label">ADMIN</span></div>
          <h3>For Administrators</h3><p class="role-summary">Oversee the platform's core records and operational configuration.</p>
          <ul class="role-feature-list"><li><i class="bi bi-check2"></i><span>Manage users and providers</span></li><li><i class="bi bi-check2"></i><span>Review appointments and subscriptions</span></li><li><i class="bi bi-check2"></i><span>Maintain plans, cities and specialties</span></li><li><i class="bi bi-check2"></i><span>Access database and reporting tools</span></li></ul>
          <span class="role-card-note"><i class="bi bi-lock"></i> Restricted administrative access</span>
        </article>
      </div>
    </div>
  </div>
</section>

<section class="about-trust-section py-5 py-lg-6" id="why-medilink">
  <div class="container">
    <div class="row align-items-end g-3 mb-4 mb-lg-5">
      <div class="col-lg-8">
        <span class="section-kicker"><i class="bi bi-shield-check"></i> Why MediLink</span>
        <h2 class="fw-bold mt-2 mb-2">Clear information and focused tools for healthcare booking.</h2>
        <p class="text-muted mb-0">MediLink keeps the experience practical: discover providers, review availability and use protected account features when you are ready to book.</p>
      </div>
    </div>
    <div class="row g-3 g-lg-4 about-trust-grid">
      <div class="col-sm-6 col-lg-3"><article class="about-trust-card h-100"><span class="trust-card-icon"><i class="bi bi-patch-check"></i></span><h3>Provider information</h3><p>Review provider and doctor details available through the platform before choosing a service.</p></article></div>
      <div class="col-sm-6 col-lg-3"><article class="about-trust-card h-100"><span class="trust-card-icon"><i class="bi bi-calendar2-week"></i></span><h3>Visible availability</h3><p>Explore appointment slots and select an available time instead of relying on an unclear booking process.</p></article></div>
      <div class="col-sm-6 col-lg-3"><article class="about-trust-card h-100"><span class="trust-card-icon"><i class="bi bi-person-lock"></i></span><h3>Role-based access</h3><p>Client, provider and administrator functions stay separated so protected tools remain account based.</p></article></div>
      <div class="col-sm-6 col-lg-3"><article class="about-trust-card h-100"><span class="trust-card-icon"><i class="bi bi-geo-alt"></i></span><h3>Sri Lanka focused</h3><p>Search and discovery are organized around local healthcare providers, specialties and locations.</p></article></div>
    </div>
    <div class="about-scope-note mt-4 mt-lg-5">
      <div class="scope-note-icon"><i class="bi bi-info-circle"></i></div>
      <div><strong>MediLink supports discovery and appointment scheduling.</strong><p>It does not provide diagnosis or replace medical consultation. Consultation and treatment charges remain between the patient and healthcare provider; MediLink subscription fees cover platform services.</p></div>
    </div>
  </div>
</section>

<section class="about-faq-section py-5 py-lg-6" id="faq">
  <div class="container">
    <div class="row justify-content-between align-items-end g-3 mb-4 mb-lg-5">
      <div class="col-lg-7">
        <span class="section-kicker"><i class="bi bi-question-circle"></i> Frequently asked questions</span>
        <h2 class="fw-bold mt-2 mb-2">Quick answers before you book.</h2>
        <p class="text-muted mb-0">The essentials about accounts, appointments, providers and MediLink subscriptions.</p>
      </div>
      <div class="col-lg-auto"><a href="<?= url('public/support.php') ?>" class="about-text-link">Need more help? <i class="bi bi-arrow-right"></i></a></div>
    </div>

    <div class="accordion about-faq-accordion" id="medilinkFaq">
      <div class="accordion-item">
        <h3 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faqOne" aria-expanded="true" aria-controls="faqOne"><span class="faq-icon"><i class="bi bi-search"></i></span>Can I search for doctors without an account?</button></h3>
        <div id="faqOne" class="accordion-collapse collapse show" data-bs-parent="#medilinkFaq"><div class="accordion-body">Yes. You can browse available healthcare providers and review their information as a guest. An account is required when you use protected booking features.</div></div>
      </div>
      <div class="accordion-item">
        <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqTwo" aria-expanded="false" aria-controls="faqTwo"><span class="faq-icon"><i class="bi bi-calendar2-check"></i></span>How does appointment booking work?</button></h3>
        <div id="faqTwo" class="accordion-collapse collapse" data-bs-parent="#medilinkFaq"><div class="accordion-body">Choose a provider, review the available appointment slots, sign in to your client account and reserve a suitable time. Your appointments can then be managed from your account.</div></div>
      </div>
      <div class="accordion-item">
        <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqThree" aria-expanded="false" aria-controls="faqThree"><span class="faq-icon"><i class="bi bi-patch-check"></i></span>What does provider verification mean on MediLink?</button></h3>
        <div id="faqThree" class="accordion-collapse collapse" data-bs-parent="#medilinkFaq"><div class="accordion-body">MediLink can display provider and doctor information recorded in the platform, including verification status where available. You should review the displayed provider details before making a booking.</div></div>
      </div>
      <div class="accordion-item">
        <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqFour" aria-expanded="false" aria-controls="faqFour"><span class="faq-icon"><i class="bi bi-arrow-repeat"></i></span>Can an appointment be cancelled or rescheduled?</button></h3>
        <div id="faqFour" class="accordion-collapse collapse" data-bs-parent="#medilinkFaq"><div class="accordion-body">Appointment actions depend on the current appointment status and the options available in your MediLink account. Providers can also manage appointment status from their workspace.</div></div>
      </div>
      <div class="accordion-item">
        <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqFive" aria-expanded="false" aria-controls="faqFive"><span class="faq-icon"><i class="bi bi-card-checklist"></i></span>What do MediLink plans and quotas cover?</button></h3>
        <div id="faqFive" class="accordion-collapse collapse" data-bs-parent="#medilinkFaq"><div class="accordion-body">Plans control platform features such as the booking quota and search radius shown on the Plans page. The exact limits depend on the plan selected.</div></div>
      </div>
      <div class="accordion-item">
        <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqSix" aria-expanded="false" aria-controls="faqSix"><span class="faq-icon"><i class="bi bi-cash-coin"></i></span>Does the MediLink subscription include consultation fees?</button></h3>
        <div id="faqSix" class="accordion-collapse collapse" data-bs-parent="#medilinkFaq"><div class="accordion-body">No. MediLink subscription fees cover platform services. Consultation, treatment and other healthcare charges remain between the patient and the healthcare provider.</div></div>
      </div>
      <div class="accordion-item">
        <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqSeven" aria-expanded="false" aria-controls="faqSeven"><span class="faq-icon"><i class="bi bi-shield-lock"></i></span>Which features are protected by sign-in?</button></h3>
        <div id="faqSeven" class="accordion-collapse collapse" data-bs-parent="#medilinkFaq"><div class="accordion-body">Booking and account-management features use role-based access. Clients, providers and administrators receive different protected tools after authentication.</div></div>
      </div>
      <div class="accordion-item">
        <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqEight" aria-expanded="false" aria-controls="faqEight"><span class="faq-icon"><i class="bi bi-building-add"></i></span>How do healthcare providers use MediLink?</button></h3>
        <div id="faqEight" class="accordion-collapse collapse" data-bs-parent="#medilinkFaq"><div class="accordion-body">Provider accounts have a dedicated workspace for maintaining doctor information, creating appointment slots, managing appointments, and handling provider profile and subscription details.</div></div>
      </div>
    </div>
  </div>
</section>

<section class="container py-5">
  <div class="cta-panel d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4">
    <div><span class="section-kicker">Ready to explore?</span><h2 class="fw-bold mb-1 mt-2">Find the right healthcare provider.</h2><p class="text-muted mb-0">Browse as a guest now. Sign in only when you need protected features.</p></div>
    <div class="d-flex flex-wrap gap-2"><a href="<?= url('client/search.php') ?>" class="btn btn-teal px-4 py-2">Browse Providers</a><a href="<?= url('public/plans.php') ?>" class="btn btn-outline-teal px-4 py-2">View Plans</a></div>
  </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
