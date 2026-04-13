<?php
// php-version/login.php
require_once 'includes/functions.php';
require_once 'includes/db.php';

if (isLoggedIn()) {
    redirect('merchant/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];

    $db = Database::connect();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if ($user['is_suspended']) {
            $error = "Your account has been suspended. Please contact support.";
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            if ($user['role'] === 'admin') {
                redirect('admin/index.php');
            } else {
                redirect('merchant/dashboard.php');
            }
        }
    } else {
        $error = "Invalid email or password.";
    }
}
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
                    <img src="<?php echo BASE_URL; ?>uploads/<?php echo $logo; ?>" alt="Logo" class="h-12 object-contain">
                <?php else: ?>
                    <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center">
                        <i data-lucide="credit-card" class="text-white w-6 h-6"></i>
                    </div>
                <?php endif; ?>
                <span class="text-2xl font-bold tracking-tight text-slate-900"><?php echo getConfig('site_name', 'Payhub'); ?></span>
            </a>
            <h1 class="text-3xl font-bold text-slate-900">Welcome back</h1>
            <p class="text-slate-500 mt-2">Log in to your merchant dashboard</p>
        </div>

        <div class="bg-white p-8 rounded-[2rem] shadow-xl shadow-slate-200/50 border border-slate-100">
            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 text-red-600 rounded-2xl text-sm font-medium border border-red-100">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-6">
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
                    Log In <i data-lucide="arrow-right" size="20"></i>
                </button>
            </form>

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
