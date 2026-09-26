<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

try {
    $database = clinicDatabase();
    $existingAdmin = (int) $database->query('SELECT COUNT(*) FROM clinic_doctors WHERE role = \'admin\'')->fetchColumn();
    if ($existingAdmin > 0) {
        fwrite(STDERR, "An administrator account already exists. No accounts were changed.\n");
        exit(1);
    }

    $username = 'admin';
    $fullName = 'مدير النظام';
    $password = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    $statement = $database->prepare(
        'INSERT INTO clinic_doctors (username, full_name, password_hash, role, active)
         VALUES (:username, :full_name, :password_hash, \'admin\', 1)'
    );
    $statement->execute([
        ':username' => $username,
        ':full_name' => $fullName,
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    fwrite(STDOUT, "Administrator account created. Save the password now; it is not stored in plain text.\n");
    fwrite(STDOUT, 'Username: ' . $username . ' | Password: ' . $password . "\n");
} catch (PDOException $exception) {
    error_log('Administrator setup failed: ' . $exception->getMessage());
    fwrite(STDERR, "Could not initialize the clinic database or create the administrator. Verify PDO SQLite and account uniqueness.\n");
    exit(1);
} catch (RuntimeException $exception) {
    error_log('Administrator setup failed: ' . $exception->getMessage());
    fwrite(STDERR, "Could not create the clinic data directory.\n");
    exit(1);
}
