<?php
require 'db.php';

// กำหนดรหัสผ่านใหม่ที่ต้องการ
$teacher_id = "T001";
$new_password = "123456";
$fullname = "Admin Teacher";

// ทำการ Hash รหัสผ่าน (หัวใจสำคัญคือบรรทัดนี้)
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

try {
    // เช็คว่ามี User นี้หรือยัง
    $check = $pdo->prepare("SELECT * FROM users WHERE teacher_id = ?");
    $check->execute([$teacher_id]);
    
    if ($check->rowCount() > 0) {
        // ถ้ามีแล้ว -> อัปเดตรหัสผ่านใหม่
        $sql = "UPDATE users SET password = ?, role = 'admin' WHERE teacher_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$hashed_password, $teacher_id]);
        echo "<h1>✅ รีเซ็ตรหัสผ่านสำเร็จ!</h1>";
        echo "<p>User: <b>$teacher_id</b></p>";
        echo "<p>Pass: <b>$new_password</b></p>";
    } else {
        // ถ้ายังไม่มี -> สร้างใหม่เลย
        $sql = "INSERT INTO users (teacher_id, password, fullname, role) VALUES (?, ?, ?, 'admin')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$teacher_id, $hashed_password, $fullname]);
        echo "<h1>✅ สร้าง User Admin สำเร็จ!</h1>";
        echo "<p>User: <b>$teacher_id</b></p>";
        echo "<p>Pass: <b>$new_password</b></p>";
    }
    
    echo "<br><a href='login.php'>👉 คลิกที่นี่เพื่อไปหน้า Login</a>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>