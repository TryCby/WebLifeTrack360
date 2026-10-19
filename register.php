<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
$error = $_SESSION['register_error'] ?? '';
unset($_SESSION['register_error']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeTrack 360 - สมัครสมาชิก</title>
    <link rel="stylesheet" href="assets/css/reset.css">
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: #f4f6f8;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            font-family: inherit;
        }

        .auth-box {
            width: 100%;
            max-width: 400px;
            background: #ffffff;
            padding: 32px 28px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }

        .brand-header {
            text-align: center;
            margin-bottom: 24px;
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
            line-height: 1.4;
        }

        .alert-error {
            background-color: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 18px;
            word-break: break-word;
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
            width: 100%;
        }

        .form-control {
            width: 100%;
            height: 42px;
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            background: #ffffff;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .input-wrapper input {
            padding-right: 44px;
        }

        .toggle-password {
            position: absolute;
            right: 0;
            height: 100%;
            width: 42px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            touch-action: manipulation;
        }

        .btn-submit {
            width: 100%;
            height: 44px;
            background: #2563eb;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background-color 0.2s ease, transform 0.1s ease;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            touch-action: manipulation;
        }

        .btn-submit:hover {
            background: #1d4ed8;
        }

        .btn-submit:active {
            transform: scale(0.99);
        }

        .auth-footer {
            margin-top: 22px;
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
            padding: 4px 2px;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        /* Responsive Breakpoints */
        @media (max-width: 480px) {
            body {
                padding: 16px 12px;
            }

            .auth-box {
                padding: 24px 18px;
                border-radius: 8px;
            }

            .brand-header h1 {
                font-size: 20px;
            }

            .form-control {
                font-size: 16px;
                height: 44px;
            }

            .btn-submit {
                height: 46px;
                font-size: 15px;
            }
        }

        @media (max-width: 340px) {
            .auth-box {
                padding: 20px 14px;
            }

            .brand-header h1 {
                font-size: 18px;
            }

            .brand-header p {
                font-size: 12px;
            }
        }

        @media (max-height: 600px) {
            body {
                align-items: flex-start;
                padding-top: 24px;
                padding-bottom: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-box">
        <div class="brand-header">
            <h1>สมัครสมาชิก LifeTrack 360</h1>
            <p>เริ่มต้นจัดการตารางชีวิตและสุขภาพของคุณ</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-error">
                <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="register_process.php" method="POST">
            <div class="form-group">
                <label for="username">ชื่อผู้ใช้ (Username) *</label>
                <input type="text" id="username" name="username" class="form-control" autocomplete="off" placeholder="เช่น myusername" required>
            </div>
            
            <div class="form-group">
                <label for="email">อีเมล (Email) *</label>
                <input type="email" id="email" name="email" class="form-control" autocomplete="email" placeholder="name@email.com" required>
            </div>
            
            <div class="form-group">
                <label for="password">รหัสผ่าน (Password) *</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" placeholder="กำหนดรหัสผ่าน" required>
                    <button type="button" class="toggle-password" onclick="togglePassword('password', this)" title="แสดง/ซ่อนรหัสผ่าน">👁️</button>
                </div>
            </div>
            
            <button type="submit" class="btn-submit">ลงทะเบียน</button>
        </form>

        <div class="auth-footer">
            มีบัญชีแล้วใช่หรือไม่? <a href="login.php">เข้าสู่ระบบ</a>
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