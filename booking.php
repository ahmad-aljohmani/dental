<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
requireDoctorLogin();

$patientName = '';
$phone = '';
$email = '';
$age = '';
$gender = '';
$appointmentDate = (new DateTimeImmutable('today'))->format('Y-m-d');
$appointmentTime = (new DateTimeImmutable())->format('H:i');
$conditionDetails = '';
$medicalHistory = '';
$allergies = '';
$medications = '';
$treatment = '';
$selectedTeeth = [];
$success = false;
$error = '';
$databaseReady = false;
$database = null;
$validAdultTeeth = array_merge(
    range(11, 18),
    range(21, 28),
    range(31, 38),
    range(41, 48)
);
$validPrimaryTeeth = array_merge(
    range(51, 55),
    range(61, 65),
    range(71, 75),
    range(81, 85)
);
$validTeeth = array_merge($validAdultTeeth, $validPrimaryTeeth);

try {
    $database = clinicDatabase();
    $databaseReady = true;
} catch (PDOException $exception) {
    error_log('Patient visit storage initialization failed: ' . $exception->getMessage());
} catch (RuntimeException $exception) {
    error_log('Patient visit storage initialization failed: ' . $exception->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientName = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
    $phone = is_string($_POST['phone'] ?? null) ? trim($_POST['phone']) : '';
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $age = is_string($_POST['patient_age'] ?? null) ? trim($_POST['patient_age']) : '';
    $gender = is_string($_POST['patient_gender'] ?? null) ? trim($_POST['patient_gender']) : '';
    $appointmentDate = is_string($_POST['appointment_date'] ?? null) ? trim($_POST['appointment_date']) : '';
    $appointmentTime = is_string($_POST['appointment_time'] ?? null) ? trim($_POST['appointment_time']) : '';
    $conditionDetails = is_string($_POST['condition_details'] ?? null) ? trim($_POST['condition_details']) : '';
    $medicalHistory = is_string($_POST['medical_history'] ?? null) ? trim($_POST['medical_history']) : '';
    $allergies = is_string($_POST['allergies'] ?? null) ? trim($_POST['allergies']) : '';
    $medications = is_string($_POST['medications'] ?? null) ? trim($_POST['medications']) : '';
    $treatment = is_string($_POST['treatment'] ?? null) ? trim($_POST['treatment']) : '';
    $postedToken = $_POST['csrf_token'] ?? '';
    $csrfToken = is_string($postedToken) ? $postedToken : '';

    $phoneDigits = preg_replace('/\D+/', '', $phone);
    $nameLength = preg_match_all('/./us', $patientName);
    $textFields = [$conditionDetails, $medicalHistory, $allergies, $medications, $treatment];
    $textLengths = array_map(static fn (string $value): int|false => preg_match_all('/./us', $value), $textFields);
    $dateFormatValid = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $appointmentDate) === 1;
    $date = $dateFormatValid ? DateTimeImmutable::createFromFormat('!Y-m-d', $appointmentDate) : false;
    $dateErrors = $dateFormatValid ? DateTimeImmutable::getLastErrors() : null;
    $validDate = $dateFormatValid
        && $date !== false
        && $date->format('Y-m-d') === $appointmentDate
        && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0));

    $rawTeeth = $_POST['teeth'] ?? [];
    if (!is_array($rawTeeth)) {
        $rawTeeth = [];
    }
    $selectedTeeth = array_values(array_unique(array_filter(
        $rawTeeth,
        static fn ($tooth): bool => is_string($tooth) && ctype_digit($tooth) && in_array((int) $tooth, $validTeeth, true)
    )));
    $selectedTeeth = array_map('intval', $selectedTeeth);
    sort($selectedTeeth);

    if (!clinicCsrfIsValid($csrfToken)) {
        $error = 'انتهت صلاحية النموذج. أعد تحميل الصفحة وحاول مرة أخرى.';
    } elseif ($patientName === '' || $nameLength === false || $nameLength > 120) {
        $error = 'يرجى إدخال اسم المريض (بحد أقصى 120 حرفًا).';
    } elseif ($phone !== '' && (strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15)) {
        $error = 'يرجى إدخال رقم هاتف صحيح أو ترك الحقل فارغًا.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'يرجى إدخال بريد إلكتروني صحيح أو ترك الحقل فارغًا.';
    } elseif ($age !== '' && (!ctype_digit($age) || (int) $age > 120)) {
        $error = 'يرجى إدخال عمر صحيح بين 0 و120، أو تركه فارغًا.';
    } elseif ($gender !== '' && !in_array($gender, ['female', 'male'], true)) {
        $error = 'يرجى اختيار قيمة صحيحة لحقل الجنس.';
    } elseif (!$validDate) {
        $error = 'يرجى إدخال تاريخ زيارة صحيح.';
    } elseif (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $appointmentTime)) {
        $error = 'يرجى إدخال وقت زيارة صحيح.';
    } elseif (in_array(false, $textLengths, true) || max($textLengths) > 3000) {
        $error = 'يجب ألا يتجاوز كل حقل للحالة 3000 حرف.';
    } elseif (!$databaseReady) {
        $error = 'تعذّر حفظ سجل الزيارة الآن. يرجى المحاولة لاحقًا.';
    } else {
        try {
            $statement = $database->prepare(
                'INSERT INTO appointment_requests
                    (doctor_id, name, phone, email, appointment_date, appointment_time, notes,
                     patient_age, patient_gender, condition_details, medical_history, allergies, medications, treatment, teeth)
                 VALUES
                    (:doctor_id, :name, :phone, :email, :appointment_date, :appointment_time, :notes,
                     :patient_age, :patient_gender, :condition_details, :medical_history, :allergies, :medications, :treatment, :teeth)'
            );
            $statement->execute([
                ':doctor_id' => $_SESSION['doctor_id'],
                ':name' => $patientName,
                ':phone' => $phone !== '' ? $phone : null,
                ':email' => $email !== '' ? $email : null,
                ':appointment_date' => $appointmentDate,
                ':appointment_time' => $appointmentTime,
                ':notes' => null,
                ':patient_age' => $age !== '' ? (int) $age : null,
                ':patient_gender' => $gender !== '' ? $gender : null,
                ':condition_details' => $conditionDetails !== '' ? $conditionDetails : null,
                ':medical_history' => $medicalHistory !== '' ? $medicalHistory : null,
                ':allergies' => $allergies !== '' ? $allergies : null,
                ':medications' => $medications !== '' ? $medications : null,
                ':treatment' => $treatment !== '' ? $treatment : null,
                ':teeth' => json_encode($selectedTeeth, JSON_THROW_ON_ERROR),
            ]);
            $success = true;
            $patientName = $phone = $email = $age = $gender = '';
            $appointmentDate = (new DateTimeImmutable('today'))->format('Y-m-d');
            $appointmentTime = (new DateTimeImmutable())->format('H:i');
            $conditionDetails = $medicalHistory = $allergies = $medications = $treatment = '';
            $selectedTeeth = [];
        } catch (PDOException $exception) {
            error_log('Patient visit could not be saved: ' . $exception->getMessage());
            $error = 'تعذّر حفظ سجل الزيارة الآن. يرجى المحاولة لاحقًا.';
        } catch (JsonException $exception) {
            error_log('Patient visit tooth selection could not be encoded: ' . $exception->getMessage());
            $error = 'تعذّر حفظ الأسنان المحددة. يرجى المحاولة مرة أخرى.';
        }
    }
}
$csrfToken = clinicCsrfToken();

$quadrants = [
    'upper-right' => ['label' => 'الفك العلوي — يمين المريض', 'numbers' => range(18, 11)],
    'upper-left' => ['label' => 'الفك العلوي — يسار المريض', 'numbers' => range(21, 28)],
    'lower-right' => ['label' => 'الفك السفلي — يمين المريض', 'numbers' => range(48, 41)],
    'lower-left' => ['label' => 'الفك السفلي — يسار المريض', 'numbers' => range(31, 38)],
];
$primaryQuadrants = [
    'primary-upper-right' => ['label' => 'علوي — يمين المريض', 'numbers' => range(55, 51)],
    'primary-upper-left' => ['label' => 'علوي — يسار المريض', 'numbers' => range(61, 65)],
    'primary-lower-right' => ['label' => 'سفلي — يمين المريض', 'numbers' => range(85, 81)],
    'primary-lower-left' => ['label' => 'سفلي — يسار المريض', 'numbers' => range(71, 75)],
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="تسجيل زيارة وبيانات علاج الأسنان للمريض في عيادة عافية.">
    <title>تسجيل زيارة | عيادة عافية</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="site-header">
        <nav class="container navigation" aria-label="القائمة الرئيسية">
            <a class="brand" href="index.php" aria-label="عيادة عافية - الرئيسية"><span class="brand-mark">+</span> عافية</a>
            <button class="menu-toggle" id="menuToggle" aria-label="فتح القائمة" aria-expanded="false">☰</button>
            <div class="nav-links" id="navLinks">
                <a href="index.php">الرئيسية</a>
                <a href="appointments.php">سجلات المرضى</a>
                <?php if (($_SESSION['doctor_role'] ?? null) === 'admin'): ?>
                    <a href="admin.php">إدارة الحسابات</a>
                <?php endif; ?>
                <a class="active nav-appointment" href="booking.php">تسجيل زيارة</a>
            </div>
            <button class="theme-toggle" id="themeToggle" aria-label="تبديل الوضع الليلي">☾</button>
            <span class="doctor-greeting"><?php echo escapeHtml($_SESSION['doctor_name'] ?? ''); ?></span>
            <form class="logout-form" method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?php echo escapeHtml($csrfToken); ?>"><button type="submit" class="logout-button">خروج</button></form>
        </nav>
    </header>

    <main class="visit-entry-page container">
        <div class="visit-entry-heading">
            <div>
                <p class="eyebrow">سجل طبي جديد</p>
                <h1>تسجيل <span>زيارة مريض</span></h1>
                <p>أدخل بيانات الزيارة، وسجّل الحالة والعلاج والأسنان التي تم العمل عليها.</p>
            </div>
            <a class="button secondary" href="appointments.php">عرض سجلات المرضى ←</a>
        </div>

        <?php if ($success): ?>
            <div class="alert success" role="status">تم حفظ سجل الزيارة وربطه بحسابك.</div>
        <?php elseif ($error !== ''): ?>
            <div class="alert error" role="alert"><?php echo escapeHtml($error); ?></div>
        <?php endif; ?>
        <?php if (!$databaseReady): ?>
            <div class="alert error" role="alert">تعذّر فتح قاعدة بيانات العيادة؛ لا يمكن حفظ السجل الآن.</div>
        <?php endif; ?>

        <form class="visit-form" method="post" action="booking.php">
            <input type="hidden" name="csrf_token" value="<?php echo escapeHtml($csrfToken); ?>">
            <section class="visit-form-section">
                <div class="form-section-heading"><span class="form-step">01</span><div><h2>بيانات المريض</h2><p>المعلومات الأساسية للتواصل والتوثيق.</p></div></div>
                <div class="visit-fields-grid">
                    <div class="field-wide">
                        <label for="name">اسم المريض <span>*</span></label>
                        <input id="name" name="name" type="text" autocomplete="name" maxlength="120" placeholder="الاسم الكامل" value="<?php echo escapeHtml($patientName); ?>" required>
                    </div>
                    <div>
                        <label for="phone">رقم الهاتف <small>(اختياري)</small></label>
                        <input id="phone" name="phone" type="tel" autocomplete="tel" maxlength="30" placeholder="+962 7X XXX XXXX" value="<?php echo escapeHtml($phone); ?>">
                    </div>
                    <div>
                        <label for="email">البريد الإلكتروني <small>(اختياري)</small></label>
                        <input id="email" name="email" type="email" autocomplete="email" maxlength="254" placeholder="name@example.com" value="<?php echo escapeHtml($email); ?>">
                    </div>
                    <div>
                        <label for="patient_age">العمر <small>(اختياري)</small></label>
                        <input id="patient_age" name="patient_age" type="number" min="0" max="120" inputmode="numeric" placeholder="العمر بالسنوات" value="<?php echo escapeHtml($age); ?>">
                    </div>
                    <div>
                        <label for="patient_gender">الجنس <small>(اختياري)</small></label>
                        <select id="patient_gender" name="patient_gender">
                            <option value="">غير محدد</option>
                            <option value="female" <?php echo $gender === 'female' ? 'selected' : ''; ?>>أنثى</option>
                            <option value="male" <?php echo $gender === 'male' ? 'selected' : ''; ?>>ذكر</option>
                        </select>
                    </div>
                    <div>
                        <label for="appointment_date">تاريخ الزيارة <span>*</span></label>
                        <input id="appointment_date" name="appointment_date" type="date" value="<?php echo escapeHtml($appointmentDate); ?>" required>
                    </div>
                    <div>
                        <label for="appointment_time">وقت الزيارة <span>*</span></label>
                        <input id="appointment_time" name="appointment_time" type="time" value="<?php echo escapeHtml($appointmentTime); ?>" required>
                    </div>
                </div>
            </section>

            <section class="visit-form-section">
                <div class="form-section-heading"><span class="form-step">02</span><div><h2>مخطط الأسنان</h2><p>اختر الأسنان التي تم فحصها أو العمل عليها في هذه الزيارة.</p></div></div>
                <fieldset class="odontogram" id="odontogram">
                    <legend class="visually-hidden">اختيار الأسنان</legend>
                    <div class="tooth-type-tabs" role="group" aria-label="نوع الأسنان">
                        <button class="tooth-type-tab active" type="button" data-tooth-type="adult" aria-pressed="true">الأسنان الدائمة <span>32</span></button>
                        <button class="tooth-type-tab" type="button" data-tooth-type="primary" aria-pressed="false">الأسنان اللبنية <span>20</span></button>
                    </div>
                    <div class="odontogram-legend"><span><i></i> اضغط على السن لتحديده</span><strong><b id="selectedTeethCount">0</b> سن محدد</strong></div>
                    <div class="tooth-chart" data-tooth-chart="adult">
                        <div class="tooth-jaw-label">الفك العلوي</div>
                        <div class="tooth-quadrants">
                            <?php foreach ($quadrants as $quadrant): ?>
                                <div class="tooth-quadrant">
                                    <span class="quadrant-caption"><?php echo escapeHtml($quadrant['label']); ?></span>
                                    <div class="tooth-row">
                                        <?php foreach ($quadrant['numbers'] as $tooth): ?>
                                            <label class="tooth-choice" title="السن رقم <?php echo $tooth; ?>">
                                                <input class="visually-hidden tooth-checkbox" type="checkbox" name="teeth[]" value="<?php echo $tooth; ?>" <?php echo in_array($tooth, $selectedTeeth, true) ? 'checked' : ''; ?>>
                                                <span class="tooth-icon" aria-hidden="true"><svg viewBox="0 0 40 46" focusable="false"><path d="M8 6C3 9 4 17 7 22c2 4 2 9 5 16 1 3 4 3 5 0l3-8 3 8c1 3 4 3 5 0 3-7 3-12 5-16 3-5 4-13-1-16-4-3-8 0-12 0S12 3 8 6Z"/></svg></span>
                                                <span class="tooth-number"><?php echo $tooth; ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="tooth-jaw-label lower-jaw-label">الفك السفلي</div>
                    </div>
                    <div class="tooth-chart" data-tooth-chart="primary" hidden>
                        <div class="tooth-jaw-label">الفك العلوي</div>
                        <div class="tooth-quadrants">
                            <?php foreach ($primaryQuadrants as $quadrant): ?>
                                <div class="tooth-quadrant">
                                    <span class="quadrant-caption"><?php echo escapeHtml($quadrant['label']); ?></span>
                                    <div class="tooth-row">
                                        <?php foreach ($quadrant['numbers'] as $tooth): ?>
                                            <label class="tooth-choice" title="السن اللبني رقم <?php echo $tooth; ?>">
                                                <input class="visually-hidden tooth-checkbox" type="checkbox" name="teeth[]" value="<?php echo $tooth; ?>" <?php echo in_array($tooth, $selectedTeeth, true) ? 'checked' : ''; ?>>
                                                <span class="tooth-icon primary-tooth-icon" aria-hidden="true"><svg viewBox="0 0 40 46" focusable="false"><path d="M8 6C3 9 4 17 7 22c2 4 2 9 5 16 1 3 4 3 5 0l3-8 3 8c1 3 4 3 5 0 3-7 3-12 5-16 3-5 4-13-1-16-4-3-8 0-12 0S12 3 8 6Z"/></svg></span>
                                                <span class="tooth-number"><?php echo $tooth; ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="tooth-jaw-label lower-jaw-label">الفك السفلي</div>
                    </div>
                    <div class="tooth-selected-list" id="selectedTeethList" aria-live="polite">لم يتم اختيار أسنان</div>
                </fieldset>
            </section>

            <section class="visit-form-section">
                <div class="form-section-heading"><span class="form-step">03</span><div><h2>الحالة والتاريخ الصحي</h2><p>أضف المعلومات المهمة لمتابعة المريض.</p></div></div>
                <div class="visit-fields-grid">
                    <div class="field-wide">
                        <label for="condition_details">شكوى المريض والحالة الحالية</label>
                        <textarea id="condition_details" name="condition_details" rows="3" maxlength="3000" placeholder="الأعراض، موضع الألم، الفحص السريري، أو التشخيص"><?php echo escapeHtml($conditionDetails); ?></textarea>
                    </div>
                    <div>
                        <label for="medical_history">الأمراض والتاريخ المرضي</label>
                        <textarea id="medical_history" name="medical_history" rows="3" maxlength="3000" placeholder="مثال: السكري، الضغط، أمراض القلب، عمليات سابقة"><?php echo escapeHtml($medicalHistory); ?></textarea>
                    </div>
                    <div>
                        <label for="allergies">الحساسية</label>
                        <textarea id="allergies" name="allergies" rows="3" maxlength="3000" placeholder="الأدوية أو المواد التي يتحسس منها المريض، أو لا يوجد"><?php echo escapeHtml($allergies); ?></textarea>
                    </div>
                    <div>
                        <label for="medications">الأدوية الحالية</label>
                        <textarea id="medications" name="medications" rows="3" maxlength="3000" placeholder="اسم الدواء والجرعة إن لزم"><?php echo escapeHtml($medications); ?></textarea>
                    </div>
                    <div>
                        <label for="treatment">الإجراء أو العلاج</label>
                        <textarea id="treatment" name="treatment" rows="3" maxlength="3000" placeholder="العلاج المنفذ، الوصفة، أو خطة المتابعة"><?php echo escapeHtml($treatment); ?></textarea>
                    </div>
                </div>
            </section>

            <div class="visit-form-footer">
                <p>السجلات الطبية خاصة وتظهر ضمن حساب الطبيب الذي أنشأها فقط.</p>
                <button class="button primary" type="submit" <?php echo !$databaseReady ? 'disabled' : ''; ?>>حفظ سجل الزيارة <span aria-hidden="true">←</span></button>
            </div>
        </form>
    </main>

    <footer class="site-footer">
        <div class="container footer-content">
            <a class="brand" href="index.php"><span class="brand-mark">+</span> عافية</a>
            <p>© <?php echo escapeHtml(date('Y')); ?> عيادة عافية.</p>
            <a href="appointments.php">سجلات المرضى</a>
        </div>
    </footer>
    <script src="script.js"></script>
</body>
</html>
