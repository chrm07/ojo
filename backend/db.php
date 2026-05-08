<?php
$host = 'localhost';
$dbname = 'jiojo_construction';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Auto-seed default admin dun sa BAGONG 'admins' table
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
    $stmt->execute(['admin@construction.com']);
    if ($stmt->rowCount() == 0) {
        $hash = password_hash('Admin123', PASSWORD_DEFAULT);
        $insert = $pdo->prepare("INSERT INTO admins (email, password) VALUES ('admin@construction.com', ?)");
        $insert->execute([$hash]);
    }
} catch (PDOException $e) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]));
}
?>