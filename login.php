<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeTrack 360 - เข้าสู่ระบบ</title>
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
        .label-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #334155;
            margin-bottom: 6px;
        }
        .forgot-link {
            font-size: 12px;
            color: #2563eb;
            text-decoration: none;
        }
        .forgot-link:hover {
            text-decoration: underline;
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
            <h1>LifeTrack 360</h1>
            <p>ระบบจัดการสมดุลชีวิตและกิจวัตรประจำวัน</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-error">
                <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="login_process.php" method="POST">
            <div class="form-group">
                <label for="username">ชื่อผู้ใช้ หรือ อีเมล *</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="เช่น yourname หรือ name@email.com" required>
            </div>

            <div class="form-group">
                <div class="label-row">
                    <label for="password" style="margin-bottom: 0;">รหัสผ่าน *</label>
                    <a href="forgot_password.php" class="forgot-link">ลืมรหัสผ่าน?</a>
                </div>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" class="form-control" placeholder="รหัสผ่านของคุณ" required>
                    <button type="button" class="toggle-password" onclick="togglePassword('password', this)" title="แสดง/ซ่อนรหัสผ่าน">👁️</button>
                </div>
            </div>

            <button type="submit" class="btn-submit">เข้าสู่ระบบ</button>
        </form>

        <div class="auth-footer">
            ยังไม่มีบัญชีใช่หรือไม่? <a href="register.php">สมัครสมาชิกใหม่</a>
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