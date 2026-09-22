<?php


$pageTitle = 'Sign In';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';


if (isLoggedIn()) {
    $role = getCurrentUserRole();
    if ($role === ROLE_OWNER) redirect('owner/dashboard.php');
    if ($role === ROLE_ADMIN) redirect('admin/dashboard.php');
    elseif ($role === ROLE_PROVIDER) redirect('provider/dashboard.php');
    else redirect('client/dashboard.php');
}

$error = '';
$email = '';
if (isset($_GET['approved'])) { unset($_SESSION['pending_provider_user_id']); }


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
            } elseif ($user['Account_Status'] === 'PENDING' && $user['Role_Type'] === ROLE_PROVIDER) {
                // Valid provider credentials, but dashboard access waits for System Admin verification.
                $_SESSION['pending_provider_user_id'] = (int)$user['User_ID'];
                redirect('public/provider-status.php');
            } elseif ($user['Account_Status'] === 'PENDING') {
                $error = 'Your account is pending activation. Please contact the administrator.';
            } else {
                
                loginUser($user);
                setFlash('success', getTimeBasedGreeting() . ', ' . e($user['First_Name']) . '! Welcome back to MediLink.');

                
                
                // Resume a provider plan chosen during registration. This is stored
                // in the database, so browser/session loss does not lose the choice.
                if ($user['Role_Type'] === ROLE_PROVIDER) {
                    $pendingPlanStmt = $db->prepare("
                        SELECT ppp.Plan_ID
                        FROM `PROVIDER_PENDING_PLAN` ppp
                        JOIN `PROVIDER` p ON p.Provider_ID = ppp.Provider_ID
                        JOIN `SUBSCRIPTION_PLAN` sp ON sp.Plan_ID = ppp.Plan_ID
                        WHERE p.User_ID = ? AND sp.Status = 'ACTIVE' AND sp.Target_Role IN ('PROVIDER','ALL')
                        LIMIT 1
                    ");
                    $pendingPlanStmt->execute([(int)$user['User_ID']]);
                    $pendingProviderPlanId = (int)($pendingPlanStmt->fetchColumn() ?: 0);
                    if ($pendingProviderPlanId > 0) {
                        redirect('provider/subscription.php?plan_id=' . $pendingProviderPlanId);
                    }
                }

                $returnTo = $_SESSION['return_to'] ?? null;
                unset($_SESSION['return_to']);

                if ($returnTo && strpos($returnTo, 'public/login.php') === false && strpos($returnTo, 'public/logout.php') === false) {
                    redirect($returnTo);
                } else {
                    if ($user['Role_Type'] === ROLE_OWNER) {
                        redirect('owner/dashboard.php');
                    } elseif ($user['Role_Type'] === ROLE_ADMIN) {
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

<div class="auth-page ml-login-page"><div class="container">
  <div class="row justify-content-center">
    <div class="col-sm-11 col-md-7 col-lg-5 col-xl-4">
      <div class="card card-custom auth-card ml-login-card">
        <div class="ml-login-heading">
          <div class="ml-login-icon"><i class="bi bi-person-lock"></i></div>
          <span class="ml-login-eyebrow">Secure account access</span>
          <h1>Welcome back</h1>
          <p>Sign in to continue to your MediLink workspace.</p>
        </div>

        <?php if (!empty($error)): ?>
          <div class="alert alert-danger ml-login-alert" role="alert">
            <i class="bi bi-exclamation-circle"></i><span><?= e($error) ?></span>
          </div>
        <?php endif; ?>

        <form method="POST" action="<?= url('public/login.php') ?>" class="ml-login-form">
          <?= CSRF::inputField() ?>
          <div class="ml-field">
            <label for="login-email" class="form-label">Email address</label>
            <div class="ml-input-wrap">
              <i class="bi bi-envelope"></i>
              <input type="email" name="email" id="login-email" class="form-control" placeholder="name@example.com" value="<?= e($email) ?>" autocomplete="email" required autofocus>
            </div>
          </div>
          <div class="ml-field">
            <label for="login-password" class="form-label">Password</label>
            <div class="ml-input-wrap ml-password-wrap">
              <i class="bi bi-lock"></i>
              <input type="password" name="password" id="login-password" class="form-control" placeholder="Enter your password" autocomplete="current-password" required>
              <button type="button" class="ml-password-toggle" id="login-password-toggle" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button>
            </div>
          </div>
          <div class="text-end mb-3"><a href="<?= url('public/forgot-password.php') ?>" class="small">Forgot password?</a></div>
          <button type="submit" class="btn btn-teal w-100 ml-login-submit"><i class="bi bi-box-arrow-in-right"></i>Sign in</button>
        </form>

        <p class="ml-login-register">New to MediLink? <a href="<?= url('public/register.php') ?>">Create an account</a></p>
        <div class="ml-login-divider"><span>or</span></div>
        <a href="<?= url('public/index.php') ?>" class="btn btn-outline-secondary w-100 ml-guest-btn"><i class="bi bi-search"></i>Browse doctors as guest</a>
        <div class="ml-login-meta">
          <span><i class="bi bi-shield-check"></i>Protected sign-in</span>
          <a href="<?= url('public/about.php#faq') ?>"><i class="bi bi-question-circle"></i>Need help?</a>
        </div>
      </div>
    </div>
  </div>
</div></div>
<script>
(function(){
  const button=document.getElementById('login-password-toggle');
  const input=document.getElementById('login-password');
  if(!button||!input)return;
  button.addEventListener('click',function(){
    const showing=input.type==='text';
    input.type=showing?'password':'text';
    button.setAttribute('aria-pressed',String(!showing));
    button.setAttribute('aria-label',showing?'Show password':'Hide password');
    button.innerHTML=showing?'<i class="bi bi-eye"></i>':'<i class="bi bi-eye-slash"></i>';
    input.focus();
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
