<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
requireDoctorLogin();

$appointments = [];
$storageError = false;
$csrfToken = clinicCsrfToken();

try {
    $database = clinicDatabase();
    $statement = $database->prepare(
        'SELECT id, name, phone, email, appointment_date, appointment_time, notes, status, created_at,
                patient_age, patient_gender, condition_details, medical_history, allergies, medications, treatment, teeth
         FROM appointment_requests
         WHERE doctor_id = :doctor_id
         ORDER BY appointment_date DESC, appointment_time DESC, id DESC'
    );
    $statement->execute([':doctor_id' => $_SESSION['doctor_id']]);
    $appointments = $statement->fetchAll(PDO::FETCH_ASSOC);
    foreach ($appointments as &$appointment) {
        try {
            $teeth = json_decode($appointment['teeth'] ?? '[]', true, 32, JSON_THROW_ON_ERROR);
            $appointment['teeth_list'] = is_array($teeth)
                ? array_values(array_filter($teeth, static fn ($tooth): bool => is_int($tooth) && $tooth >= 11 && $tooth <= 85))
                : [];
        } catch (JsonException $exception) {
            error_log('Patient visit contains invalid tooth chart data (record ' . $appointment['id'] . ').');
            $appointment['teeth_list'] = [];
        }
    }
    unset($appointment);
} catch (PDOException $exception) {
    error_log('Doctor appointment list could not be loaded: ' . $exception->getMessage());
    $storageError = true;
} catch (RuntimeException $exception) {
    error_log('Clinic data directory initialization failed: ' . $exception->getMessage());
    $storageError = true;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="السجلات الطبية والزيارات التي سجلتها في عيادة عافية.">
    <title>سجلات المرضى | عيادة عافية</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="site-header">
        <nav class="container navigation" aria-label="القائمة الرئيسية">
            <a class="brand" href="index.php" aria-label="عيادة عافية - الرئيسية"><span class="brand-mark">+</span> عافية</a>
            <button class="menu-toggle" id="menuToggle" aria-label="فتح القائمة" aria-expanded="false">☰</button>
            <div class="nav-links" id="navLinks">
                <a href="index.php">الرئيسية</a>
                <a href="index.php#about">عن العيادة</a>
                <a href="index.php#services">خدماتنا</a>
                <a class="active" href="appointments.php">سجلات المرضى</a>
                <?php if (($_SESSION['doctor_role'] ?? null) === 'admin'): ?>
                    <a href="admin.php">إدارة الحسابات</a>
                <?php endif; ?>
                <a class="nav-appointment" href="booking.php">تسجيل زيارة</a>
            </div>
            <button class="theme-toggle" id="themeToggle" aria-label="تبديل الوضع الليلي">☾</button>
            <span class="doctor-greeting"><?php echo escapeHtml($_SESSION['doctor_name'] ?? ''); ?></span>
            <form class="logout-form" method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?php echo escapeHtml($csrfToken); ?>"><button type="submit" class="logout-button">خروج</button></form>
        </nav>
    </header>

    <main class="appointments-page container">
        <div class="appointments-heading">
            <div>
                <p class="eyebrow">سجلات طبية خاصة</p>
                <h1>سجلات المرضى</h1>
                <p>تظهر هنا الزيارات والسجلات الطبية التي أنشأتها من حسابك.</p>
            </div>
            <a class="button primary" href="booking.php">+ تسجيل زيارة جديدة</a>
        </div>

        <?php if ($storageError): ?>
            <div class="alert error" role="alert">تعذّر تحميل المواعيد. تحقق من قاعدة البيانات وحاول مرة أخرى.</div>
        <?php elseif ($appointments === []): ?>
            <div class="empty-appointments">
                <span aria-hidden="true">◷</span>
                <h2>لا توجد سجلات بعد</h2>
                <p>ابدأ بإضافة أول زيارة لمريض.</p>
                <a class="button primary" href="booking.php">سجّل أول زيارة</a>
            </div>
        <?php else: ?>
            <div class="appointments-grid">
                <?php foreach ($appointments as $appointment): ?>
                    <article class="appointment-card patient-record-card">
                        <div class="appointment-card-top">
                            <span class="appointment-date">زيارة: <?php echo escapeHtml($appointment['appointment_date']); ?></span>
                            <span class="appointment-time"><?php echo escapeHtml($appointment['appointment_time']); ?></span>
                        </div>
                        <h2 class="patient-record-name"><?php echo escapeHtml($appointment['name']); ?>
                            <?php if ($appointment['patient_age'] !== null || $appointment['patient_gender'] !== null): ?>
                                <small>
                                    <?php if ($appointment['patient_age'] !== null): ?><?php echo (int) $appointment['patient_age']; ?> سنة<?php endif; ?>
                                    <?php if ($appointment['patient_age'] !== null && $appointment['patient_gender'] !== null): ?> · <?php endif; ?>
                                    <?php if ($appointment['patient_gender'] === 'female'): ?>أنثى<?php elseif ($appointment['patient_gender'] === 'male'): ?>ذكر<?php endif; ?>
                                </small>
                            <?php endif; ?>
                        </h2>
                        <dl class="appointment-details">
                            <?php if (is_string($appointment['phone']) && $appointment['phone'] !== ''): ?>
                                <div><dt>الهاتف</dt><dd><a dir="ltr" href="tel:<?php echo escapeHtml($appointment['phone']); ?>"><?php echo escapeHtml($appointment['phone']); ?></a></dd></div>
                            <?php endif; ?>
                            <?php if (is_string($appointment['email']) && $appointment['email'] !== ''): ?>
                                <div><dt>البريد الإلكتروني</dt><dd><a href="mailto:<?php echo escapeHtml($appointment['email']); ?>"><?php echo escapeHtml($appointment['email']); ?></a></dd></div>
                            <?php endif; ?>
                            <?php if (is_string($appointment['condition_details']) && $appointment['condition_details'] !== ''): ?>
                                <div class="appointment-notes"><dt>الحالة والتشخيص</dt><dd><?php echo nl2br(escapeHtml($appointment['condition_details'])); ?></dd></div>
                            <?php endif; ?>
                            <?php if (is_string($appointment['medical_history']) && $appointment['medical_history'] !== ''): ?>
                                <div class="appointment-notes"><dt>التاريخ المرضي</dt><dd><?php echo nl2br(escapeHtml($appointment['medical_history'])); ?></dd></div>
                            <?php endif; ?>
                            <?php if (is_string($appointment['allergies']) && $appointment['allergies'] !== ''): ?>
                                <div class="appointment-notes"><dt>الحساسية</dt><dd><?php echo nl2br(escapeHtml($appointment['allergies'])); ?></dd></div>
                            <?php endif; ?>
                            <?php if (is_string($appointment['medications']) && $appointment['medications'] !== ''): ?>
                                <div class="appointment-notes"><dt>الأدوية</dt><dd><?php echo nl2br(escapeHtml($appointment['medications'])); ?></dd></div>
                            <?php endif; ?>
                            <?php if (is_string($appointment['treatment']) && $appointment['treatment'] !== ''): ?>
                                <div class="appointment-notes"><dt>العلاج والإجراء</dt><dd><?php echo nl2br(escapeHtml($appointment['treatment'])); ?></dd></div>
                            <?php endif; ?>
                            <?php if ($appointment['teeth_list'] !== []): ?>
                                <div class="appointment-notes"><dt>الأسنان المعالجة</dt><dd class="record-tooth-list"><?php foreach ($appointment['teeth_list'] as $tooth): ?><span><?php echo (int) $tooth; ?></span><?php endforeach; ?></dd></div>
                            <?php endif; ?>
                            <?php if ($appointment['condition_details'] === null && $appointment['medical_history'] === null && $appointment['allergies'] === null && $appointment['medications'] === null && $appointment['treatment'] === null && $appointment['teeth_list'] === [] && is_string($appointment['notes']) && $appointment['notes'] !== ''): ?>
                                <div class="appointment-notes"><dt>ملاحظة</dt><dd><?php echo nl2br(escapeHtml($appointment['notes'])); ?></dd></div>
                            <?php endif; ?>
                        </dl>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="site-footer">
        <div class="container footer-content">
            <a class="brand" href="index.php"><span class="brand-mark">+</span> عافية</a>
            <p>© <?php echo escapeHtml(date('Y')); ?> عيادة عافية.</p>
            <a href="booking.php">تسجيل زيارة</a>
        </div>
    </footer>
    <script src="script.js"></script>
</body>
</html>
