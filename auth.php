<?php
if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

function clinicDatabase(): PDO
{
    static $database = null;
    if ($database instanceof PDO) {
        return $database;
    }

    $databaseDirectory = getenv('APPOINTMENTS_DB_DIR');
    if ($databaseDirectory === false || $databaseDirectory === '') {
        $databaseDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'clinic-booking-hhh';
    }

    if (!is_dir($databaseDirectory) && !mkdir($databaseDirectory, 0700, true) && !is_dir($databaseDirectory)) {
        throw new RuntimeException('Could not create the clinic data directory.');
    }

    $database = new PDO('sqlite:' . $databaseDirectory . DIRECTORY_SEPARATOR . 'appointments.sqlite');
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $database->exec('PRAGMA foreign_keys = ON');
    $database->exec(
        'CREATE TABLE IF NOT EXISTS clinic_doctors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            full_name TEXT NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT \'doctor\',
            auth_version INTEGER NOT NULL DEFAULT 1,
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );
    $doctorColumns = $database->query('PRAGMA table_info(clinic_doctors)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('role', $doctorColumns, true)) {
        $database->exec('ALTER TABLE clinic_doctors ADD COLUMN role TEXT NOT NULL DEFAULT \'doctor\'');
    }
    if (!in_array('auth_version', $doctorColumns, true)) {
        $database->exec('ALTER TABLE clinic_doctors ADD COLUMN auth_version INTEGER NOT NULL DEFAULT 1');
    }

    $database->exec(
        'CREATE TABLE IF NOT EXISTS appointment_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER REFERENCES clinic_doctors(id),
            name TEXT NOT NULL,
            phone TEXT NOT NULL,
            email TEXT,
            appointment_date TEXT NOT NULL,
            appointment_time TEXT NOT NULL,
            notes TEXT,
            status TEXT NOT NULL DEFAULT \'pending\',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $columns = $database->query('PRAGMA table_info(appointment_requests)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('doctor_id', $columns, true)) {
        $database->exec('ALTER TABLE appointment_requests ADD COLUMN doctor_id INTEGER REFERENCES clinic_doctors(id)');
    }
    $visitColumns = [
        'patient_age' => 'INTEGER',
        'patient_gender' => 'TEXT',
        'condition_details' => 'TEXT',
        'medical_history' => 'TEXT',
        'allergies' => 'TEXT',
        'medications' => 'TEXT',
        'treatment' => 'TEXT',
        'teeth' => 'TEXT',
    ];
    foreach ($visitColumns as $column => $type) {
        if (!in_array($column, $columns, true)) {
            $database->exec('ALTER TABLE appointment_requests ADD COLUMN ' . $column . ' ' . $type);
        }
    }

    return $database;
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function requireDoctorLogin(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    if (!isset($_SESSION['doctor_id']) || !is_int($_SESSION['doctor_id'])) {
        header('Location: login.php');
        exit;
    }

    try {
        $statement = clinicDatabase()->prepare(
            'SELECT full_name, role, auth_version
             FROM clinic_doctors
             WHERE id = :id AND active = 1'
        );
        $statement->execute([':id' => $_SESSION['doctor_id']]);
        $doctor = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($doctor)
            || !isset($_SESSION['doctor_auth_version'])
            || (int) $doctor['auth_version'] !== $_SESSION['doctor_auth_version']) {
            $_SESSION = [];
            session_regenerate_id(true);
            header('Location: login.php');
            exit;
        }

        $_SESSION['doctor_name'] = $doctor['full_name'];
        $_SESSION['doctor_role'] = $doctor['role'];
    } catch (PDOException $exception) {
        error_log('Active doctor verification failed: ' . $exception->getMessage());
        http_response_code(503);
        exit('تعذّر التحقق من حسابك الآن. حاول مرة أخرى لاحقًا.');
    } catch (RuntimeException $exception) {
        error_log('Active doctor verification failed: ' . $exception->getMessage());
        http_response_code(503);
        exit('تعذّر التحقق من حسابك الآن. حاول مرة أخرى لاحقًا.');
    }
}

function requireAdmin(): void
{
    requireDoctorLogin();
    if (($_SESSION['doctor_role'] ?? null) !== 'admin') {
        http_response_code(403);
        exit('هذه الصفحة مخصصة لمدير النظام.');
    }
}

function clinicCsrfToken(): string
{
    if (!isset($_SESSION['clinic_csrf_token']) || !is_string($_SESSION['clinic_csrf_token'])) {
        $_SESSION['clinic_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['clinic_csrf_token'];
}

function clinicCsrfIsValid(string $token): bool
{
    return isset($_SESSION['clinic_csrf_token'])
        && is_string($_SESSION['clinic_csrf_token'])
        && hash_equals($_SESSION['clinic_csrf_token'], $token);
}
