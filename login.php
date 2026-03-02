<?php
// 1. เริ่ม Session (เช็คก่อนว่าเริ่มไปหรือยัง เพื่อกัน Error)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- ตรวจสอบว่า Login อยู่แล้วหรือไม่ ---
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
// ------------------------------------

// 2. เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require 'db.php';

$error = '';

// 3. ตรวจสอบเมื่อมีการกดปุ่ม Login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // ค้นหา User ในฐานข้อมูล
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user) {
        // --- ส่วนตรวจสอบรหัสผ่าน (Hybrid Check) ---
        $isPasswordCorrect = false;

        // แบบที่ 1: ตรวจสอบแบบเข้ารหัส (สำหรับ User ใหม่ที่สร้างจากหน้า users.php)
        if (password_verify($password, $user['password'])) {
            $isPasswordCorrect = true;
        } 
        // แบบที่ 2: ตรวจสอบแบบธรรมดา (สำหรับ User เก่า หรือที่แก้ใน DB ตรงๆ)
        elseif ($password === $user['password']) {
            $isPasswordCorrect = true;
        }

        // --- ถ้าถูกต้อง ---
        if ($isPasswordCorrect) {
            // เก็บข้อมูลสำคัญลง Session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];       // admin, teacher, user
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['dept_id'] = $user['department_id']; // จำเป็นมากสำหรับสิทธิ์ Teacher

            // บันทึก Log การเข้าสู่ระบบ (ถ้ามีตาราง logs)
            // $pdo->prepare("INSERT INTO login_logs ...")->execute([...]);

            // ไปที่หน้า Dashboard
            header("Location: index.php");
            exit;
        } else {
            $error = "รหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error = "ไม่พบชื่อผู้ใช้งานนี้ในระบบ";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - ระบบบริหารพัสดุ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Sarabun', sans-serif;
        }
        .login-card {
            background: white;
            padding: 2.5rem;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 420px;
        }
        .brand-logo {
            width: 80px;
            height: 80px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
            font-size: 2rem;
            color: #4e73df;
            border: 2px solid #e3e6f0;
        }
        .form-control {
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
        }
        .btn-login {
            border-radius: 0.5rem;
            padding: 0.75rem;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(78, 115, 223, 0.4);
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="brand-logo">
            📦
        </div>
        
        <h3 class="text-center fw-bold text-dark mb-1">ระบบบริหารพัสดุ</h3>
        <p class="text-center text-muted mb-4">วิทยาลัยพณิชยการบางนา</p>

        <?php if($error): ?>
            <div class="alert alert-danger text-center py-2 mb-3" role="alert">
                <small><?= $error ?></small>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="username" class="form-label text-secondary small fw-bold">ชื่อผู้ใช้งาน</label>
                <input type="text" class="form-control" id="username" name="username" placeholder="Enter username" required autofocus>
            </div>
            
            <div class="mb-4">
                <label for="password" class="form-label text-secondary small fw-bold">รหัสผ่าน</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-login">
                เข้าสู่ระบบ
            </button>
        </form>

        <div class="text-center mt-4">
            <small class="text-muted">ติดต่อฝ่าย IT หากลืมรหัสผ่าน | <a href="#" class="text-decoration-none">ช่วยเหลือ</a></small>
        </div>
    </div>

</body>
</html>