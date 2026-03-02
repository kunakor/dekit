<?php
session_start();
$_SESSION = array(); // ล้างข้อมูล session ทั้งหมดในตัวแปร
session_destroy();   // ทำลาย session ใน server
header("Location: login.php");
exit;