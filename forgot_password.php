<?php
session_start();
require_once 'config/database.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $new_password = $_POST['password'];

    if (empty($username) || empty($new_password)) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } else {
        try {
            // ตรวจสอบว่ามีผู้ใช้นี้จริงไหม (รองรับทั้ง username และ email)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :user OR email = :email LIMIT 1");
            $stmt->execute([':user' => $username, ':email' => $username]);
            $user = $stmt->fetch();

            if ($user) {
                // เข้ารหัสและอัปเดตรหัสผ่านใหม่ทันที
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE users SET password_hash = :p_hash WHERE id = :id");
                $updateStmt->execute([':p_hash' => $password_hash, ':id' => $user['id']]);

                echo "<script>
                    alert('เปลี่ยนรหัสผ่านสำเร็จ! กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่');
                    window.location.href = 'login.php';
                </script>";
                exit;
            } else {
                $error = "ไม่พบชื่อผู้ใช้หรืออีเมลนี้ในระบบ";
            }
        } catch (\PDOException $e) {
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeTrack 360 - ลืมรหัสผ่าน</title>
    <link rel="stylesheet" href="assets/css/reset.css">
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <style>
        .auth-container {
            max-width: 400px;
            margin: 60px auto;
            padding: 30px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
        }
        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .password-wrapper input {
            padding-right: 40px;
        }
        .toggle-password {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #666;
            padding: 4px;
        }
        .btn-submit {
            width: 100%;
            padding: 10px;
            background: #ffc107;
            color: #000;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        .btn-submit:hover {
            background: #e0a800;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <h2 style="text-align: center; margin-bottom: 20px;">รีเซ็ตรหัสผ่าน</h2>
        
        <?php if (!empty($error)): ?>
            <div style="padding: 10px; background: #ffebee; color: #c62828; border-radius: 5px; margin-bottom: 15px; font-size: 14px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="forgot_password.php" method="POST">
            <div class="form-group">
                <label for="username">ชื่อผู้ใช้ หรือ อีเมล</label>
                <input type="text" id="username" name="username" placeholder="กรอกชื่อผู้ใช้หรืออีเมลของคุณ" required>
            </div>
            <div class="form-group">
                <label for="password">รหัสผ่านใหม่ (New Password)</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" placeholder="รหัสผ่านใหม่" required>
                    <button type="button" class="toggle-password" onclick="togglePassword('password', this)">👁️</button>
                </div>
            </div>
            <button type="submit" class="btn-submit">เปลี่ยนรหัสผ่าน</button>
        </form>
        <p style="text-align: center; margin-top: 15px; font-size: 14px;">
            จำรหัสผ่านได้แล้ว? <a href="login.php">กลับไปเข้าสู่ระบบ</a>
        </p>
    </div>

    <script>
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                btn.textContent = "🙈";
            } else {
                input.type = "password";
                btn.textContent = "👁️";
            }
        }
    </script>
</body>
</html>