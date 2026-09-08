<?php
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        echo "<script>
            alert('กรุณากรอกข้อมูลให้ครบทุกช่อง');
            window.location.href = 'register.php';
        </script>";
        exit;
    }

    // ตรวจสอบรูปแบบอีเมลว่าถูกต้องตามหลักสากลไหม
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>
            alert('รูปแบบอีเมลไม่ถูกต้อง');
            window.location.href = 'register.php';
        </script>";
        exit;
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        // เช็คว่าชื่อผู้ใช้หรืออีเมลนี้มีคนสมัครไปแล้วหรือยัง
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
        $checkStmt->execute([':username' => $username, ':email' => $email]);

        if ($checkStmt->fetch()) {
            echo "<script>
                alert('ชื่อผู้ใช้หรืออีเมลนี้ถูกใช้งานแล้ว');
                window.location.href = 'register.php';
            </script>";
            exit;
        }

        // บันทึกลงฐานข้อมูล
        $insertStmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (:username, :email, :password_hash, 'user')");
        $insertStmt->execute([
            ':username'      => $username,
            ':email'         => $email,
            ':password_hash' => $password_hash
        ]);

        echo "<script>
            alert('สมัครสมาชิกสำเร็จ!');
            window.location.href = 'login.php';
        </script>";
        exit;

    } catch (\PDOException $e) {
        die("เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage());
    }
} else {
    header("Location: register.php");
    exit;
}
?>