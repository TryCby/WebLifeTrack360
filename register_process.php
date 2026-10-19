<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // ตรวจสอบว่ากรอกข้อมูลครบทุกช่องหรือไม่
    if (empty($username) || empty($email) || empty($password)) {
        $_SESSION['register_error'] = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
        header("Location: register.php");
        exit;
    }

    // ตรวจสอบรูปแบบอีเมล
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['register_error'] = 'รูปแบบอีเมลไม่ถูกต้อง';
        header("Location: register.php");
        exit;
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        // เช็คว่าชื่อผู้ใช้หรืออีเมลนี้มีคนสมัครไปแล้วหรือยัง
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
        $checkStmt->execute([':username' => $username, ':email' => $email]);

        if ($checkStmt->fetch()) {
            $_SESSION['register_error'] = 'ชื่อผู้ใช้หรืออีเมลนี้ถูกใช้งานแล้ว';
            header("Location: register.php");
            exit;
        }

        // บันทึกลงฐานข้อมูล
        $insertStmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (:username, :email, :password_hash, 'user')");
        $insertStmt->execute([
            ':username'      => $username,
            ':email'         => $email,
            ':password_hash' => $password_hash
        ]);

        // สมัครสำเร็จ ส่งกลับไปหน้า login พร้อมแจ้งเตือนหรือให้ล็อกอินได้ทันที
        header("Location: login.php");
        exit;

    } catch (\PDOException $e) {
        $_SESSION['register_error'] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
        header("Location: register.php");
        exit;
    }
} else {
    header("Location: register.php");
    exit;
}
?>