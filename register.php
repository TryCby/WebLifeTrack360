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
            padding-right: 42px;
        }
        .toggle-password {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            padding: 2px 6px;
            line-height: 1;
        }
        .btn-submit {
            width: 100%;
            padding: 10px;
            background: #007bff;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-submit:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <h2 style="text-align: center; margin-bottom: 20px;">สมัครสมาชิก LifeTrack 360</h2>
        <form action="register_process.php" method="POST">
            <div class="form-group">
                <label for="username">ชื่อผู้ใช้ (Username)</label>
                <!-- เปลี่ยน autocomplete เป็น off เพื่อป้องกันเบราว์เซอร์ดึงอีเมลมาใส่ -->
                <input type="text" id="username" name="username" autocomplete="off" placeholder="เช่น myusername" required[cite: 23]>
            </div>
            <div class="form-group">
                <label for="email">อีเมล (Email)</label>
                <input type="email" id="email" name="email" autocomplete="email" placeholder="name@email.com" required[cite: 23]>
            </div>
            <div class="form-group">
                <label for="password">รหัสผ่าน (Password)</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" autocomplete="new-password" placeholder="รหัสผ่าน" required[cite: 23]>
                    <button type="button" class="toggle-password" onclick="togglePassword('password', this)">👁️</button>
                </div>
            </div>
            <button type="submit" class="btn-submit">ลงทะเบียน</button>
        </form>
        <p style="text-align: center; margin-top: 15px; font-size: 14px;">
            มีบัญชีแล้ว? <a href="login.php">เข้าสู่ระบบ</a>[cite: 23]
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