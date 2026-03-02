<?php
require 'db.php';

echo "<h2>🕵️‍♂️ กำลังตรวจสอบระบบ Login...</h2>";

// 1. เช็คการเชื่อมต่อ Database
if ($pdo) {
    echo "✅ เชื่อมต่อฐานข้อมูลสำเร็จ!<br>";
} else {
    die("❌ เชื่อมต่อฐานข้อมูลไม่ได้! ไปเช็คไฟล์ db.php");
}

// 2. เช็คว่ามี User 'admin' ไหม
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
$stmt->execute();
$user = $stmt->fetch();

if ($user) {
    echo "✅ พบผู้ใช้ 'admin' ในฐานข้อมูล<br>";
    echo "🔑 รหัสผ่านในฐานข้อมูลคือ: <b>" . $user['password'] . "</b><br>";
    echo "👉 กรุณาเอาค่าสีหนานี้ไปกรอกในช่องรหัสผ่านครับ";
} else {
    echo "❌ ไม่พบผู้ใช้ 'admin' ในฐานข้อมูล! (กรุณารัน SQL ในขั้นตอนที่ 1 ใหม่)";
}
?>