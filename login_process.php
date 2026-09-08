<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // ตรวจสอบว่ากรอกข้อมูลมาครบถ้วนหรือไม่
    if (empty($username) || empty($password)) {
        echo "<script>
            alert('กรุณากรอกชื่อผู้ใช้และรหัสผ่านให้ครบถ้วน');
            window.location.href = 'login.php';
        </script>";
        exit;
    }

    try {
        // ค้นหาผู้ใช้จากฐานข้อมูล (รองรับการล็อกอินด้วย Username หรือ Email)
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = :user OR email = :email LIMIT 1");
        $stmt->execute([
            ':user'  => $username,
            ':email' => $username
        ]);
        $user = $stmt->fetch();

        // ตรวจสอบว่าพบผู้ใช้และรหัสผ่านถูกต้องตามที่เข้ารหัสไว้หรือไม่
        if ($user && password_verify($password, $user['password_hash'])) {
            // บันทึก Session การเข้าสู่ระบบ
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            // พาไปหน้าแดชบอร์ดหลัก
            header("Location: index.php");
            exit;
        } else {
            // แจ้งเตือนเมื่อข้อมูลไม่ถูกต้อง
            echo "<script>
                alert('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
                window.location.href = 'login.php';
            </script>";
            exit;
        }
    } catch (\PDOException $e) {
        die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: " . $e->getMessage());
    }
} else {
    // ป้องกันการเข้าไฟล์นี้โดยตรงผ่าน URL
    header("Location: login.php");
    exit;
}
?>