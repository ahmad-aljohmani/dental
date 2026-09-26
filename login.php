<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

if (isset($_SESSION['doctor_id']) && is_int($_SESSION['doctor_id'])) {
    header('Location: index.php');
    exit;
}

$username = '';
$error = '';
$storageError = false;
$doctorCount = 0;
$database = null;
$now = time();
$failures = $_SESSION['login_failures'] ?? [];
if (!is_array($failures)) {
    $failures = [];
}
$failures = array_values(array_filter($failures, static fn ($attempt): bool => is_int($attempt) && $attempt > $now - 600));
$_SESSION['login_failures'] = $failures;
$lockedOut = count($failures) >= 5;

try {
    $database = clinicDatabase();
    $doctorCount = (int) $database->query('SELECT COUNT(*) FROM clinic_doctors WHERE active = 1')->fetchColumn();
} catch (PDOException $exception) {
    error_log('Clinic database initialization failed: ' . $exception->getMessage());
    $storageError = true;
} catch (RuntimeException $exception) {
    error_log('Clinic data directory initialization failed: ' . $exception->getMessage());
    $storageError = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$storageError) {
    $postedUsername = $_POST['username'] ?? '';
    $postedPassword = $_POST['password'] ?? '';
    $postedToken = $_POST['csrf_token'] ?? '';
    $username = is_string($postedUsername) ? trim($postedUsername) : '';
    $password = is_string($postedPassword) ? $postedPassword : '';
    $csrfToken = is_string($postedToken) ? $postedToken : '';

    if (!clinicCsrfIsValid($csrfToken)) {
        $error = 'انتهت صلاحية النموذج. أعد تحميل الصفحة وحاول مرة أخرى.';
    } elseif ($lockedOut) {
        $error = 'تم إيقاف محاولات الدخول مؤقتًا. يرجى الانتظار عشر دقائق ثم المحاولة.';
    } elseif ($username === '' || strlen($username) > 64 || $password === '' || strlen($password) > 1024) {
        $error = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
        $_SESSION['login_failures'][] = $now;
    } else {
        try {
            $statement = $database->prepare(
                'SELECT id, username, full_name, password_hash, role, auth_version
                 FROM clinic_doctors
                 WHERE username = :username AND active = 1'
            );
            $statement->execute([':username' => $username]);
            $doctor = $statement->fetch(PDO::FETCH_ASSOC);

            static $dummyPasswordHash = null;
            if ($dummyPasswordHash === null) {
                $dummyPasswordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
            }
            $passwordMatches = password_verify($password, is_array($doctor) ? $doctor['password_hash'] : $dummyPasswordHash);

            if (is_array($doctor) && $passwordMatches) {
                session_regenerate_id(true);
                $_SESSION['doctor_id'] = (int) $doctor['id'];
                $_SESSION['doctor_name'] = $doctor['full_name'];
                $_SESSION['doctor_role'] = $doctor['role'];
                $_SESSION['doctor_auth_version'] = (int) $doctor['auth_version'];
                $_SESSION['login_failures'] = [];
                unset($_SESSION['clinic_csrf_token']);
                header('Location: index.php');
                exit;
            }

            $_SESSION['login_failures'][] = $now;
            $lockedOut = count($_SESSION['login_failures']) >= 5;
            $error = $lockedOut
                ? 'تم إيقاف محاولات الدخول مؤقتًا. يرجى الانتظار عشر دقائق ثم المحاولة.'
                : 'اسم المستخدم أو كلمة المرور غير صحيحة.';
        } catch (PDOException $exception) {
            error_log('Doctor authentication failed: ' . $exception->getMessage());
            $error = 'تعذّر إكمال تسجيل الدخول الآن. يرجى المحاولة لاحقًا.';
        }
    }
}

$csrfToken = clinicCsrfToken();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="تسجيل دخول الأطباء إلى موقع عيادة عافية.">
    <title>تسجيل الدخول | عيادة عافية</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <main class="login-layout">
        <section class="login-welcome">
            <a class="brand login-brand" href="login.php"><span class="brand-mark">+</span> عافية</a>
            <div class="login-welcome-copy">
                <p class="eyebrow"><span class="status-dot"></span> بوابة الفريق الطبي</p>
                <h1>رعاية أفضل،<br><span>تبدأ من هنا.</span></h1>
                <p>سجّل الدخول للوصول إلى الصفحة التعريفية وطلبات المواعيد الخاصة بحسابك.</p>
            </div>
            <p class="login-footer-note">مساحة عمل خاصة بأطباء العيادة.</p>
        </section>

        <section class="login-panel">
            <div class="login-form-wrap">
                <div class="login-mobile-brand"><a class="brand" href="login.php"><span class="brand-mark">+</span> عافية</a></div>
                <p class="eyebrow">مرحبًا بعودتك</p>
                <h2>تسجيل الدخول</h2>
                <p class="login-subtitle">أدخل بيانات حساب الطبيب للمتابعة.</p>

                <?php if ($storageError): ?>
                    <div class="alert error" role="alert">تعذّر الاتصال بقاعدة بيانات العيادة. تحقق من تفعيل PDO SQLite وصلاحية مجلد التخزين.</div>
                <?php elseif ($doctorCount === 0): ?>
                    <div class="alert error" role="alert">لم يتم إعداد حسابات الأطباء بعد. شغّل <code>C:\xampp\php\php.exe setup-doctors.php</code> من مجلد المشروع لإنشاء الحسابات الأولى.</div>
                <?php endif; ?>
                <?php if ($error !== ''): ?>
                    <div class="alert error" role="alert"><?php echo escapeHtml($error); ?></div>
                <?php endif; ?>

                <form class="login-form" method="post" action="login.php">
                    <input type="hidden" name="csrf_token" value="<?php echo escapeHtml($csrfToken); ?>">
                    <label for="username">اسم المستخدم</label>
                    <input id="username" name="username" type="text" autocomplete="username" maxlength="64" placeholder="أدخل اسم المستخدم" value="<?php echo escapeHtml($username); ?>" required>
                    <label for="password">كلمة المرور</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" placeholder="أدخل كلمة المرور" required>
                    <button class="button primary submit-button" type="submit" <?php echo $storageError || $doctorCount === 0 ? 'disabled' : ''; ?>>دخول إلى الموقع <span aria-hidden="true">←</span></button>
                </form>
                <p class="login-security-note"><span aria-hidden="true">◇</span> صفحة آمنة مخصصة لمستخدمي العيادة.</p>
            </div>
        </section>
    </main>
</body>
</html>
