<?php
session_start();
echo "<h1>🔍 ตรวจสอบสถานะของคุณ</h1>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'ยังไม่ล็อกอิน') . "<br>";
echo "ชื่อ: " . ($_SESSION['fullname'] ?? '-') . "<br>";
echo "<h3>บทบาท (Role): " . ($_SESSION['role'] ?? 'ไม่มี') . "</h3>";
echo "<h3>รหัสสาขา (Dept ID): " . ($_SESSION['dept_id'] ?? 'ไม่มี') . "</h3>";

echo "<hr>";
echo "<b>วิธีแก้:</b><br>";
echo "1. ถ้า Role เป็น 'user' แสดงว่าใน Database คุณยังไม่ได้แก้เป็น 'teacher'<br>";
echo "2. ถ้า Dept ID เป็น 0 หรือ ว่างเปล่า แสดงว่าคุณยังไม่ได้ระบุสาขาให้ครูคนนี้<br>";
echo "<br><a href='logout.php'>คลิกที่นี่เพื่อ Logout แล้ว Login ใหม่</a> (เพื่อให้ค่าอัปเดต)";
?>