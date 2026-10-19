<?php
session_start();
require_once 'config/database.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $new_password = $_POST['password'] ?? '';

    if (empty($username) || empty($new_password)) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :user OR email = :email LIMIT 1");
            $stmt->execute([':user' => $username, ':email' => $username]);
            $user = $stmt->fetch();

            if ($user) {
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
        body {
            background-color: #f4f6f8;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 15px;
            margin: 0;
            font-family: inherit;
        }
        .auth-box {
            width: 100%;
            max-width: 400px;
            background: #ffffff;
            padding: 28px 24px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            box-sizing: border-box;
        }
        .brand-header {
            text-align: center;
            margin-bottom: 22px;
        }
        .brand-header h1 {
            font-size: 22px;
            color: #2563eb;
            margin-bottom: 6px;
            font-weight: 700;
        }
        .brand-header p {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }
        .alert-error {
            background-color: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 18px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #334155;
            margin-bottom: 6px;
        }
        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .form-control {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            background: #ffffff;
            box-sizing: border-box;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .input-wrapper input {
            padding-right: 40px;
        }
        .toggle-password {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 15px;
            color: #64748b;
            padding: 4px;
            line-height: 1;
        }
        .btn-submit {
            width: 100%;
            padding: 10px 0;
            background: #2563eb;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background-color 0.2s ease;
            margin-top: 6px;
        }
        .btn-submit:hover {
            background: #1d4ed8;
        }
        .auth-footer {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
            text-align: center;
            font-size: 13px;
            color: #64748b;
        }
        .auth-footer a {
            color: #2563eb;
            font-weight: 600;
            text-decoration: none;
        }
        .auth-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="auth-box">
        <div class="brand-header">
            <h1>รีเซ็ตรหัสผ่าน</h1>
            <p>กรอกข้อมูลบัญชีเพื่อตั้งรหัสผ่านใหม่</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-error">
                <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="forgot_password.php" method="POST">
            <div class="form-group">
                <label for="username">ชื่อผู้ใช้ หรือ อีเมล *</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="กรอกชื่อผู้ใช้หรืออีเมลของคุณ" required>
            </div>

            <div class="form-group">
                <label for="password">รหัสผ่านใหม่ (New Password) *</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" class="form-control" placeholder="รหัสผ่านใหม่" required>
                    <button type="button" class="toggle-password" onclick="togglePassword('password', this)" title="แสดง/ซ่อนรหัสผ่าน">👁️</button>
                </div>
            </div>

            <button type="submit" class="btn-submit">เปลี่ยนรหัสผ่าน</button>
        </form>

        <div class="auth-footer">
            จำรหัสผ่านได้แล้ว? <a href="login.php">กลับไปเข้าสู่ระบบ</a>
        </div>
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