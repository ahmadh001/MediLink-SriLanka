<?php
/**
 * Unified Login Page
 */

$pageTitle = 'Sign In';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = getCurrentUserRole();
    if ($role === ROLE_ADMIN) redirect('admin/dashboard.php');
    elseif ($role === ROLE_PROVIDER) redirect('provider/dashboard.php');
    else redirect('client/dashboard.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::check();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email and password.';
    } else {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM `USER` WHERE Email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['Password_Hash'])) {
            if ($user['Account_Status'] === 'SUSPENDED') {
                $error = 'Your account has been suspended by the administrator. Please contact support.';
            } else {
                // Log in user and establish session
                loginUser($user);
                setFlash('success', getTimeBasedGreeting() . ', ' . e($user['First_Name']) . '! Welcome back to MediLink.');

                // Redirect to intended page or role dashboard
                $returnTo = $_SESSION['return_to'] ?? null;
                unset($_SESSION['return_to']);

                if ($returnTo && strpos($returnTo, 'public/login.php') === false && strpos($returnTo, 'public/logout.php') === false) {
                    redirect($returnTo);
                } else {
                    if ($user['Role_Type'] === ROLE_ADMIN) {
                        redirect('admin/dashboard.php');
                    } elseif ($user['Role_Type'] === ROLE_PROVIDER) {
                        redirect('provider/dashboard.php');
                    } else {
                        redirect('client/dashboard.php');
                    }
                }
            }
        } else {
            $error = 'Invalid email address or password. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      <div class="card card-custom p-4 p-md-5 shadow">
        <div class="text-center mb-4">
          <div class="brand-icon mx-auto mb-2" style="width: 50px; height: 50px; font-size: 1.75rem;">
            <i class="bi bi-hospital"></i>
          </div>
          <h3 class="fw-bold">Sign In to MediLink</h3>
          <p class="text-muted small">Access your patient, doctor, centre or admin portal</p>
        </div>

        <?php if (!empty($error)): ?>
          <div class="alert alert-danger alert-dismissible fade show small" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <form method="POST" action="<?= url('public/login.php') ?>">
          <?= CSRF::inputField() ?>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Email Address</label>
            <div class="input-group">
              <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
              <input type="email" name="email" id="login-email" class="form-control" placeholder="name@example.com" value="<?= e($email) ?>" required autofocus>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Password</label>
            <div class="input-group">
              <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
              <input type="password" name="password" id="login-password" class="form-control" placeholder="••••••••" required>
            </div>
          </div>

          <button type="submit" class="btn btn-teal w-100 py-2 fw-semibold mb-3">
            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
          </button>
        </form>

        <div class="text-center small text-muted mb-4">
          Don't have an account yet? <a href="<?= url('public/register.php') ?>" class="text-teal fw-semibold">Register Now</a>
        </div>

        <div class="border-top pt-3 text-center">
          <small class="text-muted d-block mb-1"><i class="bi bi-shield-lock text-success me-1"></i> 256-bit SSL encrypted authentication</small>
          <small class="text-muted">Need assistance? <a href="<?= url('public/about.php') ?>" class="text-secondary text-decoration-underline">Contact MediLink Support</a></small>
        </div>

      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
