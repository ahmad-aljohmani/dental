<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
requireAdmin();

$databaseError = false;
$error = '';
$success = '';
$doctors = [];
$newDoctorCredentials = $_SESSION['new_doctor_credentials'] ?? null;
unset($_SESSION['new_doctor_credentials']);

try {
    $database = clinicDatabase();
} catch (PDOException $exception) {
    error_log('Admin panel could not connect to clinic database: ' . $exception->getMessage());
    $databaseError = true;
} catch (RuntimeException $exception) {
    error_log('Admin panel could not initialize clinic database: ' . $exception->getMessage());
    $databaseError = true;
}

if (!$databaseError && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    $csrfToken = is_string($postedToken) ? $postedToken : '';
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

    if (!clinicCsrfIsValid($csrfToken)) {
        $error = 'انتهت صلاحية النموذج. أعد تحميل الصفحة وحاول مرة أخرى.';
    } elseif ($action === 'add_doctor') {
        $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
        $fullName = is_string($_POST['full_name'] ?? null) ? trim($_POST['full_name']) : '';
        $nameLength = preg_match_all('/./us', $fullName);

        if (!preg_match('/^[A-Za-z0-9._-]{3,32}$/D', $username)) {
            $error = 'اسم المستخدم يجب أن يكون من 3 إلى 32 حرفًا إنجليزيًا أو رقمًا، ويمكن استخدام النقطة والشرطة.';
        } elseif ($fullName === '' || $nameLength === false || $nameLength > 120) {
            $error = 'يرجى إدخال اسم الطبيب (بحد أقصى 120 حرفًا).';
        } else {
            $temporaryPassword = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
            try {
                $statement = $database->prepare(
                    'INSERT INTO clinic_doctors (username, full_name, password_hash, role, active)
                     VALUES (:username, :full_name, :password_hash, \'doctor\', 1)'
                );
                $statement->execute([
                    ':username' => $username,
                    ':full_name' => $fullName,
                    ':password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
                ]);
                $_SESSION['new_doctor_credentials'] = [
                    'username' => $username,
                    'full_name' => $fullName,
                    'password' => $temporaryPassword,
                ];
                header('Location: admin.php');
                exit;
            } catch (PDOException $exception) {
                if (str_contains($exception->getMessage(), 'UNIQUE constraint failed')) {
                    $error = 'اسم المستخدم مستخدم بالفعل. اختر اسمًا آخر.';
                } else {
                    error_log('Admin could not add doctor: ' . $exception->getMessage());
                    $error = 'تعذّرت إضافة الحساب الآن. حاول مرة أخرى لاحقًا.';
                }
            }
        }
    } elseif ($action === 'deactivate_doctor') {
        $doctorId = filter_var($_POST['doctor_id'] ?? null, FILTER_VALIDATE_INT);
        if ($doctorId === false || $doctorId === null || $doctorId < 1) {
            $error = 'حساب الطبيب المحدد غير صالح.';
        } else {
            try {
                $statement = $database->prepare(
                    'UPDATE clinic_doctors
                     SET active = 0
                     WHERE id = :id AND role = \'doctor\' AND active = 1'
                );
                $statement->execute([':id' => $doctorId]);
                if ($statement->rowCount() === 1) {
                    $success = 'تم تعطيل الحساب. بقيت مواعيده محفوظة ولن يتمكن من تسجيل الدخول.';
                } else {
                    $error = 'لم يتم العثور على حساب طبيب نشط لتعطيله.';
                }
            } catch (PDOException $exception) {
                error_log('Admin could not deactivate doctor: ' . $exception->getMessage());
                $error = 'تعذّر تعطيل الحساب الآن. حاول مرة أخرى لاحقًا.';
            }
        }
    } elseif ($action === 'update_doctor') {
        $doctorId = filter_var($_POST['doctor_id'] ?? null, FILTER_VALIDATE_INT);
        $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
        $newPassword = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';

        if ($doctorId === false || $doctorId === null || $doctorId < 1) {
            $error = 'حساب الطبيب المحدد غير صالح.';
        } elseif (!preg_match('/^[A-Za-z0-9._-]{3,32}$/D', $username)) {
            $error = 'اسم المستخدم يجب أن يكون من 3 إلى 32 حرفًا إنجليزيًا أو رقمًا، ويمكن استخدام النقطة والشرطة.';
        } elseif ($newPassword !== '' && (trim($newPassword) === '' || strlen($newPassword) < 12 || strlen($newPassword) > 128)) {
            $error = 'كلمة المرور الجديدة يجب أن تكون بين 12 و128 حرفًا.';
        } else {
            try {
                if ($newPassword === '') {
                    $statement = $database->prepare(
                        'UPDATE clinic_doctors
                         SET username = :username, auth_version = auth_version + 1
                         WHERE id = :id AND role = \'doctor\''
                    );
                    $statement->execute([':username' => $username, ':id' => $doctorId]);
                } else {
                    $statement = $database->prepare(
                        'UPDATE clinic_doctors
                         SET username = :username, password_hash = :password_hash,
                             auth_version = auth_version + 1
                         WHERE id = :id AND role = \'doctor\''
                    );
                    $statement->execute([
                        ':username' => $username,
                        ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                        ':id' => $doctorId,
                    ]);
                }

                if ($statement->rowCount() === 1) {
                    $success = $newPassword === ''
                        ? 'تم تحديث اسم المستخدم. سيتعين على الطبيب تسجيل الدخول مجددًا.'
                        : 'تم تحديث اسم المستخدم وكلمة المرور. سيتعين على الطبيب تسجيل الدخول مجددًا.';
                } else {
                    $error = 'لم يتم العثور على حساب طبيب.';
                }
            } catch (PDOException $exception) {
                if (str_contains($exception->getMessage(), 'UNIQUE constraint failed')) {
                    $error = 'اسم المستخدم مستخدم بالفعل. اختر اسمًا آخر.';
                } else {
                    error_log('Admin could not update doctor credentials: ' . $exception->getMessage());
                    $error = 'تعذّر تحديث بيانات الحساب الآن. حاول مرة أخرى لاحقًا.';
                }
            }
        }
    } else {
        $error = 'العملية المطلوبة غير معروفة.';
    }
}

if (!$databaseError) {
    try {
        $doctors = $database->query(
            'SELECT id, username, full_name, role, active, created_at
             FROM clinic_doctors
             ORDER BY CASE role WHEN \'admin\' THEN 0 ELSE 1 END, full_name COLLATE NOCASE'
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $exception) {
        error_log('Admin could not load user list: ' . $exception->getMessage());
        $databaseError = true;
    }
}

$csrfToken = clinicCsrfToken();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="إدارة حسابات أطباء عيادة عافية.">
    <title>إدارة الحسابات | عيادة عافية</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="site-header">
        <nav class="container navigation" aria-label="القائمة الرئيسية">
            <a class="brand" href="index.php"><span class="brand-mark">+</span> عافية</a>
            <button class="menu-toggle" id="menuToggle" aria-label="فتح القائمة" aria-expanded="false">☰</button>
            <div class="nav-links" id="navLinks">
                <a href="index.php">الرئيسية</a>
                <a href="appointments.php">مواعيدي</a>
                <a class="active" href="admin.php">إدارة الحسابات</a>
                <a href="admin.php#admin-users">حسابات الأطباء</a>
            </div>
            <button class="theme-toggle" id="themeToggle" aria-label="تبديل الوضع الليلي">☾</button>
            <span class="doctor-greeting"><?php echo escapeHtml($_SESSION['doctor_name'] ?? ''); ?></span>
            <form class="logout-form" method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?php echo escapeHtml($csrfToken); ?>"><button type="submit" class="logout-button">خروج</button></form>
        </nav>
    </header>

    <main class="admin-page container">
        <div class="appointments-heading">
            <div>
                <p class="eyebrow">مدير النظام</p>
                <h1>إدارة حسابات الأطباء</h1>
                <p>أضف حسابات، أو حدّث اسم المستخدم وكلمة المرور، أو عطّل حسابًا مع الاحتفاظ بمواعيده.</p>
            </div>
        </div>

        <?php if ($databaseError): ?>
            <div class="alert error" role="alert">تعذّر تحميل بيانات الحسابات. تحقق من قاعدة البيانات وحاول مرة أخرى.</div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert error" role="alert"><?php echo escapeHtml($error); ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert success" role="status"><?php echo escapeHtml($success); ?></div>
        <?php endif; ?>
        <?php if (is_array($newDoctorCredentials)): ?>
            <section class="new-credentials alert success" role="status">
                <h2>تم إنشاء حساب الطبيب</h2>
                <p>احفظ كلمة المرور الآن؛ لن تظهر مرة أخرى.</p>
                <dl>
                    <div><dt>الاسم</dt><dd><?php echo escapeHtml($newDoctorCredentials['full_name']); ?></dd></div>
                    <div><dt>اسم المستخدم</dt><dd><code><?php echo escapeHtml($newDoctorCredentials['username']); ?></code></dd></div>
                    <div><dt>كلمة المرور</dt><dd><code><?php echo escapeHtml($newDoctorCredentials['password']); ?></code></dd></div>
                </dl>
            </section>
        <?php endif; ?>

        <section class="admin-add-section">
            <div>
                <p class="eyebrow">حساب جديد</p>
                <h2>إضافة طبيب</h2>
                <p>سينشئ النظام كلمة مرور عشوائية قوية ويعرضها لك مرة واحدة.</p>
            </div>
            <form class="admin-add-form" method="post" action="admin.php">
                <input type="hidden" name="csrf_token" value="<?php echo escapeHtml($csrfToken); ?>">
                <input type="hidden" name="action" value="add_doctor">
                <div>
                    <label for="full_name">اسم الطبيب</label>
                    <input id="full_name" name="full_name" type="text" maxlength="120" autocomplete="off" required>
                </div>
                <div>
                    <label for="username">اسم المستخدم</label>
                    <input id="username" name="username" type="text" pattern="[A-Za-z0-9._-]{3,32}" maxlength="32" autocomplete="off" required>
                </div>
                <button class="button primary" type="submit" <?php echo $databaseError ? 'disabled' : ''; ?>>إنشاء الحساب</button>
            </form>
        </section>

        <section class="admin-users-section" id="admin-users">
            <div class="admin-section-heading">
                <div><p class="eyebrow">الوصول إلى الموقع</p><h2>الحسابات الحالية</h2></div>
                <span class="account-count"><?php echo count($doctors); ?> حسابات</span>
            </div>
            <?php if (!$databaseError && $doctors === []): ?>
                <p class="empty-appointments">لا توجد حسابات بعد.</p>
            <?php elseif (!$databaseError): ?>
                <div class="admin-table-wrap">
                    <table class="admin-users-table">
                        <thead><tr><th>الاسم</th><th>الصلاحية</th><th>الحالة</th><th>بيانات الدخول والإجراء</th></tr></thead>
                        <tbody>
                            <?php foreach ($doctors as $doctor): ?>
                                <tr>
                                    <td class="admin-doctor-name"><?php echo escapeHtml($doctor['full_name']); ?><small><code><?php echo escapeHtml($doctor['username']); ?></code></small></td>
                                    <td><?php echo $doctor['role'] === 'admin' ? 'مدير النظام' : 'طبيب'; ?></td>
                                    <td><span class="account-status <?php echo (int) $doctor['active'] === 1 ? 'is-active' : 'is-disabled'; ?>"><?php echo (int) $doctor['active'] === 1 ? 'نشط' : 'معطّل'; ?></span></td>
                                    <td>
                                        <?php if ($doctor['role'] === 'doctor'): ?>
                                            <div class="admin-account-actions">
                                                <form class="admin-credentials-form" method="post" action="admin.php">
                                                    <input type="hidden" name="csrf_token" value="<?php echo escapeHtml($csrfToken); ?>">
                                                    <input type="hidden" name="action" value="update_doctor">
                                                    <input type="hidden" name="doctor_id" value="<?php echo (int) $doctor['id']; ?>">
                                                    <label class="visually-hidden" for="username-<?php echo (int) $doctor['id']; ?>">اسم المستخدم الجديد</label>
                                                    <input id="username-<?php echo (int) $doctor['id']; ?>" name="username" type="text" value="<?php echo escapeHtml($doctor['username']); ?>" pattern="[A-Za-z0-9._-]{3,32}" maxlength="32" aria-label="اسم المستخدم الجديد" required>
                                                    <label class="visually-hidden" for="password-<?php echo (int) $doctor['id']; ?>">كلمة المرور الجديدة</label>
                                                    <input id="password-<?php echo (int) $doctor['id']; ?>" name="new_password" type="password" minlength="12" maxlength="128" autocomplete="new-password" placeholder="كلمة مرور جديدة (اختياري)" aria-label="كلمة المرور الجديدة، اتركها فارغة للإبقاء على الحالية">
                                                    <button type="submit" class="admin-save-button" <?php echo $databaseError ? 'disabled' : ''; ?>>حفظ</button>
                                                </form>
                                                <?php if ((int) $doctor['active'] === 1): ?>
                                                    <form method="post" action="admin.php" onsubmit="return confirm('سيُمنع الطبيب من تسجيل الدخول، مع الاحتفاظ بمواعيده. هل تريد المتابعة؟');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo escapeHtml($csrfToken); ?>">
                                                        <input type="hidden" name="action" value="deactivate_doctor">
                                                        <input type="hidden" name="doctor_id" value="<?php echo (int) $doctor['id']; ?>">
                                                        <button type="submit" class="deactivate-button">تعطيل</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="admin-no-action">يُدار حساب المدير من الخادم</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-content">
            <a class="brand" href="index.php"><span class="brand-mark">+</span> عافية</a>
            <p>© <?php echo escapeHtml(date('Y')); ?> عيادة عافية.</p>
            <a href="index.php">الصفحة الرئيسية</a>
        </div>
    </footer>
    <script src="script.js"></script>
</body>
</html>
