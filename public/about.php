<?php
/**
 * About & Disclaimer Page
 */

$pageTitle = 'About & Healthcare Mediation Model';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-9">
      <div class="card card-custom p-4 p-md-5">
        <span class="badge bg-teal-subtle text-teal px-3 py-2 rounded-pill fw-semibold mb-3 align-self-start">Healthcare Platform Architecture</span>
        <h1 class="fw-bold mb-4">About MediLink Sri Lanka</h1>

        <p class="lead text-secondary mb-4">
          MediLink Sri Lanka is an advanced, subscription-gated digital healthcare mediator platform designed to connect patients with verified Sri Lanka Medical Council (SLMC) registered medical doctors and Ministry of Health registered Healthcare Centres.
        </p>

        <h4 class="fw-bold text-teal mt-4 mb-3"><i class="bi bi-shield-check me-2"></i> Core Business & Revenue Model</h4>
        <p class="text-secondary">
          MediLink operates as a technological scheduling mediator. Our business model is powered by tiered monthly subscriptions for patients and healthcare providers.
        </p>

        <div class="card bg-light border-0 p-4 rounded-3 my-4">
          <h5 class="fw-bold text-dark mb-2"><i class="bi bi-exclamation-octagon text-danger me-2"></i> Explicit Separation of Medical Consultation Fees</h5>
          <p class="small text-secondary mb-0">
            <strong>The platform does NOT process or charge consultation fees between patients and doctors.</strong> 
            Consultation, diagnosis, and medical procedure charges are agreed upon and settled directly between the patient and the healthcare provider at the clinical premises. 
            MediLink's subscription charges solely cover platform access, quota management, distance-based search, and schedule reservation services.
          </p>
        </div>


        <div class="d-flex gap-3 mt-4 pt-3 border-top">
          <a href="<?= url('public/plans.php') ?>" class="btn btn-teal px-4 py-2">Explore Subscription Plans</a>
          <a href="<?= url('public/register.php') ?>" class="btn btn-outline-secondary px-4 py-2">Create an Account</a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
