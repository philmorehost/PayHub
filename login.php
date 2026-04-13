<?php
// php-version/login.php
require_once 'includes/functions.php';
require_once 'includes/db.php';

if (isLoggedIn()) {
    redirect('merchant/dashboard.php');
}

// Allow clearing the pending login state
if (isset($_GET['clear_session'])) {
    unset($_SESSION['pending_login_user_id'], $_SESSION['pending_login_role'], $_SESSION['pending_login_email']);
    redirect('login.php');
}

$error = '';
$step = 'credentials'; // 'credentials' | 'otp'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? 'verify_credentials';

        if ($action === 'verify_credentials') {
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $db   = Database::connect();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['is_suspended']) {
                $error = "Your account has been suspended. Please contact support.";
            } else {
                // Store pending login in session and send OTP
                $_SESSION['pending_login_user_id'] = $user['id'];
                $_SESSION['pending_login_role']    = $user['role'];

                if (generate_and_send_otp($email, 'login')) {
                    $_SESSION['pending_login_email'] = $email;
                    $step = 'otp';
                } else {
                    $error = 'Failed to send verification email. Please try again.';
                    unset($_SESSION['pending_login_user_id'], $_SESSION['pending_login_role']);
                }
            }
        } else {
            $error = "Invalid email or password.";
        }
    } elseif ($action === 'verify_otp') {
        $otp = trim($_POST['otp'] ?? '');

        if (empty($_SESSION['pending_login_user_id']) || empty($_SESSION['pending_login_email'])) {
            $error = 'Session expired. Please log in again.';
        } else {
            $email = $_SESSION['pending_login_email'];

            if (verify_otp($email, $otp, 'login')) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $_SESSION['pending_login_user_id'];
                $_SESSION['role']    = $_SESSION['pending_login_role'];
                unset($_SESSION['pending_login_user_id'], $_SESSION['pending_login_role'], $_SESSION['pending_login_email']);

                if ($_SESSION['role'] === 'admin') {
                    sendEmail($email, "New Admin Login Alert", "<p>A new login was detected on your admin account at " . date('Y-m-d H:i:s') . ". If this wasn't you, please secure your account.</p>");
                    redirect('admin/index.php');
                } else {
                    redirect('merchant/dashboard.php');
                }
            } else {
                $error = 'Invalid or expired verification code. Please try again.';
                $step  = 'otp';
            }
        }
    } elseif ($action === 'resend_otp') {
        if (empty($_SESSION['pending_login_email'])) {
            $error = 'Session expired. Please log in again.';
        } else {
            $email = $_SESSION['pending_login_email'];
            if (generate_and_send_otp($email, 'login')) {
                $step = 'otp';
            } else {
                $error = 'Failed to resend code. Please try again.';
                $step  = 'otp';
            }
            }
        }
    }
} elseif (isset($_SESSION['pending_login_user_id'])) {
    $step = 'otp';
}

// Guard: if we somehow reach the OTP step without session data, fall back to credentials
if ($step === 'otp' && (empty($_SESSION['pending_login_user_id']) || empty($_SESSION['pending_login_email']))) {
    $step = 'credentials';
}

$pending_email = htmlspecialchars($_SESSION['pending_login_email'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Payhub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-4 font-sans">
    <div class="max-w-md w-full">
        <div class="text-center mb-10">
            <a href="index.php" class="inline-flex items-center gap-2 mb-6">
                <?php $logo = getConfig('site_logo'); ?>
                <?php if ($logo): ?>
                    <img src="<?php echo BASE_URL; ?>uploads/<?php echo htmlspecialchars($logo); ?>" alt="Logo" class="h-12 object-contain">
                <?php else: ?>
                    <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center">
                        <i data-lucide="credit-card" class="text-white w-6 h-6"></i>
                    </div>
                <?php endif; ?>
                <span class="text-2xl font-bold tracking-tight text-slate-900"><?php echo htmlspecialchars(getConfig('site_name', 'Payhub')); ?></span>
            </a>
            <?php if ($step === 'otp'): ?>
                <h1 class="text-3xl font-bold text-slate-900">Check your email</h1>
                <p class="text-slate-500 mt-2">Enter the 6-digit code sent to <strong><?php echo $pending_email; ?></strong>.</p>
            <?php else: ?>
                <h1 class="text-3xl font-bold text-slate-900">Welcome back</h1>
                <p class="text-slate-500 mt-2">Log in to your merchant dashboard</p>
            <?php endif; ?>
        </div>

        <div class="bg-white p-8 rounded-[2rem] shadow-xl shadow-slate-200/50 border border-slate-100">
            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 text-red-600 rounded-2xl text-sm font-medium border border-red-100">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($step === 'otp'): ?>
            <!-- Step 2: OTP Verification -->
            <form method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="verify_otp">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Verification Code</label>
                    <input
                        type="text"
                        name="otp"
                        required
                        maxlength="6"
                        pattern="\d{6}"
                        autofocus
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all text-center text-2xl tracking-[0.5em] font-bold"
                        placeholder="000000"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full bg-indigo-600 text-white py-4 rounded-2xl font-bold hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-200 flex items-center justify-center gap-2"
                >
                    Verify &amp; Log In <i data-lucide="arrow-right" size="20"></i>
                </button>
            </form>

            <div class="mt-6 text-center">
                <form method="POST" class="inline">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="action" value="resend_otp">
                    <button type="submit" class="text-sm text-indigo-600 font-bold hover:text-indigo-700">
                        Resend code
                    </button>
                </form>
                <span class="text-slate-300 mx-2">|</span>
                <a href="login.php?clear_session=1" class="text-sm text-slate-500 hover:text-slate-700">Back to login</a>
            </div>

            <?php else: ?>
            <!-- Step 1: Credentials -->
            <form method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="verify_credentials">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Email Address</label>
                    <input
                        type="email"
                        name="email"
                        required
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                        placeholder="name@company.com"
                    >
                </div>
                <div>
                    <div class="flex justify-between mb-2">
                        <label class="text-sm font-bold text-slate-700">Password</label>
                        <a href="forgot-password.php" class="text-sm font-bold text-indigo-600 hover:text-indigo-700">Forgot?</a>
                    </div>
                    <input
                        type="password"
                        name="password"
                        required
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                        placeholder="••••••••"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full bg-indigo-600 text-white py-4 rounded-2xl font-bold hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-200 flex items-center justify-center gap-2"
                >
                    Continue <i data-lucide="arrow-right" size="20"></i>
                </button>
            </form>
            <?php endif; ?>

            <?php if (isset($_GET['registered'])): ?>
                <div class="mt-6 p-4 bg-emerald-50 text-emerald-700 rounded-2xl text-sm font-medium border border-emerald-100 text-center">
                    Account created! Please log in below.
                </div>
            <?php endif; ?>

            <div class="mt-8 pt-8 border-t border-slate-100 text-center">
                <p class="text-slate-500">
                    Don't have an account? <a href="register.php" class="text-indigo-600 font-bold hover:text-indigo-700">Create one</a>
                </p>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>

