<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
requireDoctorLogin();

$year = date('Y');
$csrfToken = clinicCsrfToken();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="عيادة عافية. صفحة تعريفية وسجلات زيارات خاصة بفريق الأطباء.">
    <title>عيادة عافية | رعاية صحية باهتمام</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="site-header">
        <nav class="container navigation" aria-label="القائمة الرئيسية">
            <a class="brand" href="index.php" aria-label="عيادة عافية - الرئيسية"><span class="brand-mark">+</span> عافية</a>
            <button class="menu-toggle" id="menuToggle" aria-label="فتح القائمة" aria-expanded="false">☰</button>
            <div class="nav-links" id="navLinks">
                <a class="active" href="index.php">الرئيسية</a>
                <a href="#about">عن العيادة</a>
                <a href="#services">خدماتنا</a>
                <a href="#visit">معلومات الزيارة</a>
                <a href="appointments.php">سجلات المرضى</a>
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

    <main>
        <section class="clinic-hero" id="home">
            <div class="container hero-layout">
                <div class="hero-content">
                    <p class="eyebrow"><span class="status-dot"></span> أهلاً <?php echo escapeHtml($_SESSION['doctor_name'] ?? ''); ?></p>
                    <h1>صحتك أولويتنا،<br><span>وراحتك تهمنا.</span></h1>
                    <p class="hero-text">في عيادة عافية، نؤمن أن الرعاية الجيدة تبدأ بالاستماع. استخدم هذه الصفحة للتعريف بالعيادة، وسجّل مواعيد مراجعيك من حسابك الطبي.</p>
                    <div class="hero-actions">
                        <a class="button primary" href="booking.php">تسجيل زيارة مريض <span aria-hidden="true">←</span></a>
                        <a class="button secondary" href="appointments.php">عرض سجلات المرضى</a>
                    </div>
                    <div class="hero-note"><span class="note-icon">✓</span><span>سجلات المرضى والأسنان مرتبطة بحسابك فقط.</span></div>
                </div>
                <div class="clinic-illustration" aria-hidden="true">
                    <div class="illustration-orbit orbit-one"></div>
                    <div class="illustration-orbit orbit-two"></div>
                    <div class="medical-cross">+</div>
                    <div class="doctor-card">
                        <div class="doctor-avatar"><span class="avatar-head"></span><span class="avatar-body"></span><span class="avatar-coat"></span></div>
                        <div class="doctor-card-copy"><span class="doctor-label">رعاية باهتمام</span><strong>نحن هنا من أجلك</strong><span class="doctor-card-line"></span></div>
                        <span class="doctor-heart">♡</span>
                    </div>
                    <div class="floating-badge"><span>✦</span> صحتك تستحق الأفضل</div>
                </div>
            </div>
        </section>

        <section class="trust-strip" aria-label="قيم العيادة">
            <div class="container trust-items">
                <div><span class="trust-icon">♡</span><span><strong>رعاية إنسانية</strong><small>اهتمام يبدأ بالاستماع</small></span></div>
                <div><span class="trust-icon">✚</span><span><strong>خدمات متنوعة</strong><small>رعاية تناسب احتياجاتك</small></span></div>
                <div><span class="trust-icon">◷</span><span><strong>تسجيل منظم</strong><small>توثيق الزيارات والعلاجات</small></span></div>
            </div>
        </section>

        <section class="section container about-section" id="about">
            <div class="section-heading">
                <p class="eyebrow">عن عيادة عافية</p>
                <h2>رعاية واضحة،<br>وتجربة أكثر راحة.</h2>
            </div>
            <div class="about-copy">
                <p>نحرص على أن تكون زيارة كل مريض موثقة بوضوح. يتيح الموقع للطبيب تسجيل الحالة والعلاج والأسنان التي تم العمل عليها.</p>
                <p>محتوى هذه الصفحة تعريفي عام، ويمكن تخصيصه باسم الطبيب أو التخصص ومعلومات العيادة الفعلية.</p>
                <a class="text-link" href="booking.php">سجّل زيارة مريض <span aria-hidden="true">←</span></a>
            </div>
        </section>

        <section class="services-section" id="services">
            <div class="container">
                <div class="section-heading centered-heading">
                    <p class="eyebrow">خدماتنا</p>
                    <h2>نهتم بك في كل خطوة.</h2>
                    <p>يوثق الطبيب تفاصيل الزيارة وخطة العلاج لكل مريض.</p>
                </div>
                <div class="services-grid">
                    <article class="service-card">
                        <span class="service-icon">♡</span>
                        <h3>الاستشارات الطبية</h3>
                        <p>استشارة ومتابعة صحية تراعي احتياجاتك الفردية.</p>
                    </article>
                    <article class="service-card">
                        <span class="service-icon">✚</span>
                        <h3>الفحوصات الدورية</h3>
                        <p>متابعة عامة للصحة ومناقشة نتائج الفحوصات مع الطبيب.</p>
                    </article>
                    <article class="service-card">
                        <span class="service-icon">⌁</span>
                        <h3>المتابعة والرعاية</h3>
                        <p>تنظيم زيارات المتابعة والإجابة عن استفساراتك الصحية.</p>
                    </article>
                </div>
                <p class="services-disclaimer">الخدمات الفعلية تعتمد على تخصص العيادة وتوفرها. يرجى التواصل مع العيادة للتأكد من ملاءمة الخدمة.</p>
            </div>
        </section>

        <section class="visit-section container" id="visit">
            <div class="visit-card">
                <div class="visit-copy">
                    <p class="eyebrow">معلومات الزيارة</p>
                    <h2>نحن بانتظارك.</h2>
                    <p>سجّل تفاصيل زيارة المريض والحالة العلاجية من النموذج المخصص للأطباء.</p>
                    <a class="button primary" href="booking.php">سجّل زيارة مريض <span aria-hidden="true">←</span></a>
                </div>
                <div class="visit-details">
                    <div><span class="detail-icon">◷</span><span><strong>أوقات العمل</strong><small>يرجى الاستفسار من العيادة عن الأوقات المتاحة.</small></span></div>
                    <div><span class="detail-icon">⌖</span><span><strong>الموقع</strong><small>تُرسل تفاصيل الموقع عند تأكيد الموعد.</small></span></div>
                    <div><span class="detail-icon">☎</span><span><strong>تسجيل الزيارة</strong><small>أدخل بيانات المريض والحالة والأسنان المعالجة.</small></span></div>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-content">
            <a class="brand" href="index.php"><span class="brand-mark">+</span> عافية</a>
            <p>© <?php echo escapeHtml($year); ?> عيادة عافية. جميع الحقوق محفوظة.</p>
            <a href="appointments.php">سجلات المرضى</a>
        </div>
    </footer>
    <script src="script.js"></script>
</body>
</html>
