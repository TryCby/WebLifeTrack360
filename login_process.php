<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // ตรวจสอบว่ากรอกข้อมูลครบถ้วนหรือไม่
    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่านให้ครบถ้วน';
        header("Location: login.php");
        exit;
    }

    try {
        // ค้นหาผู้ใช้จากฐานข้อมูล (รองรับทั้ง Username หรือ Email)
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = :user OR email = :email LIMIT 1");
        $stmt->execute([
            ':user'  => $username,
            ':email' => $username
        ]);
        $user = $stmt->fetch();

        // ตรวจสอบรหัสผ่าน
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true); // ป้องกัน Session Fixation
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            header("Location: index.php");
            exit;
        } else {
            $_SESSION['login_error'] = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
            header("Location: login.php");
            exit;
        }
    } catch (\PDOException $e) {
        $_SESSION['login_error'] = 'เกิดข้อผิดพลาดในการเชื่อมต่อ: ' . $e->getMessage();
        header("Location: login.php");
        exit;
    }
} else {
    header("Location: login.php");
    exit;
}
?>