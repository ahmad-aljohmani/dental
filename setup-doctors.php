<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

try {
    $database = clinicDatabase();
    $existingDoctors = (int) $database->query('SELECT COUNT(*) FROM clinic_doctors')->fetchColumn();
    if ($existingDoctors > 0) {
        fwrite(STDERR, "Doctor accounts already exist. No accounts were changed.\n");
        exit(1);
    }

    $doctors = [
        ['doctor1', 'الطبيب الأول'],
        ['doctor2', 'الطبيب الثاني'],
        ['doctor3', 'الطبيب الثالث'],
    ];
    $credentials = [];
    $database->beginTransaction();
    try {
        $statement = $database->prepare(
            'INSERT INTO clinic_doctors (username, full_name, password_hash)
             VALUES (:username, :full_name, :password_hash)'
        );

        foreach ($doctors as [$username, $fullName]) {
            $password = rtrim(strtr(base64_encode(random_bytes(15)), '+/', '-_'), '=');
            $statement->execute([
                ':username' => $username,
                ':full_name' => $fullName,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $credentials[] = [$username, $fullName, $password];
        }
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        throw $exception;
    }

    fwrite(STDOUT, "Doctor accounts created. Save these passwords now; they are not stored in plain text.\n\n");
    foreach ($credentials as [$username, $fullName, $password]) {
        fwrite(STDOUT, $fullName . " | Username: " . $username . " | Password: " . $password . "\n");
    }
} catch (PDOException $exception) {
    error_log('Doctor account setup failed: ' . $exception->getMessage());
    fwrite(STDERR, "Could not initialize the clinic database. Verify that PDO SQLite is enabled.\n");
    exit(1);
} catch (RuntimeException $exception) {
    error_log('Doctor account setup failed: ' . $exception->getMessage());
    fwrite(STDERR, "Could not create the clinic data directory.\n");
    exit(1);
}
