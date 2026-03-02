<?php
session_start();
echo "<h1>🕵️‍♂️ ตรวจสอบสถานะปัจจุบัน</h1>";
echo "<b>ชื่อ:</b> " . ($_SESSION['fullname'] ?? '-') . "<br>";
echo "<b>บทบาท (Role):</b> " . ($_SESSION['role'] ?? '-') . "<br>";
echo "<b>สังกัดสาขา (Dept ID):</b> " . ($_SESSION['dept_id'] ?? 'ไม่มี') . "<br>";

echo "<hr>";
echo "<h3>วิเคราะห์ปัญหา:</h3>";

if (($_SESSION['role'] ?? '') == 'user') {
    echo "❌ <b>ปัญหา:</b> ระบบมองว่าคุณเป็น 'user' ธรรมดา<br>";
    echo "💡 <b>วิธีแก้:</b> ต้องแก้ใน Database ให้เป็น 'teacher' แล้ว Logout เข้าใหม่";
} 
elseif (empty($_SESSION['dept_id'])) {
    echo "❌ <b>ปัญหา:</b> คุณเป็นครูแล้ว แต่ 'ไม่มีสังกัดสาขา' (Dept ID เป็น 0 หรือว่าง)<br>";
    echo "💡 <b>วิธีแก้:</b> ต้องใส่เลขสาขาใน Database ให้ถูกต้อง";
} 
else {
    echo "✅ <b>สถานะถูกต้อง:</b> คุณเป็น Teacher และมีสาขาแล้ว (ถ้ายังกดไม่ได้ แปลว่าโค้ดหน้าเว็บผิด)";
}

echo "<br><br><a href='logout.php'>🔴 คลิกเพื่อ Logout แล้วเข้าใหม่ (สำคัญมาก)</a>";
?>
