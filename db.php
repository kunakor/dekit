<?php
// 1. ป้องกันปัญหา Header already sent (เปลี่ยนหน้าไม่ได้)
ob_start();

// 2. ตั้งค่า Timezone เป็นไทย (เวลาบันทึกจะได้ตรง)
date_default_timezone_set('Asia/Bangkok');

// 3. เริ่มต้น Session อย่างปลอดภัย (เช็คก่อนว่าเปิดหรือยัง)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 4. ตั้งค่าการเชื่อมต่อฐานข้อมูล
$host = 'https://110.78.30.118/';
$db   = 'items';
$user = 'dekit';      // XAMPP ปกติใช้ root
$pass = 'bnccitemsconfig';          // XAMPP ปกติรหัสว่าง
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // แจ้งเตือนเมื่อ SQL ผิดพลาด
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // ดึงข้อมูลเป็น Array ชื่อคอลัมน์
    PDO::ATTR_EMULATE_PREPARES   => false,                  // ป้องกัน SQL Injection
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // ถ้าเชื่อมต่อไม่ได้ ให้หยุดและแจ้งเตือน
    die("❌ เชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage());
}

// --- 🛠️ HELPER FUNCTIONS (ฟังก์ชันตัวช่วย) ---

/**
 * ฟังก์ชันสร้าง Flash Message (แจ้งเตือนแล้วหายไป)
 * ใช้ร่วมกับ SweetAlert2 ในหน้าอื่นๆ
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,       // success, error, warning, info
        'message' => $message
    ];
}

/**
 * ฟังก์ชันตรวจสอบว่าเป็น Admin หรือไม่
 * @return bool
 */
function isAdmin() {
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
}

/**
 * ฟังก์ชันคำนวณอายุพัสดุ (จากวันที่รับ - ปัจจุบัน)
 * @param string $received_date วันที่รับ (Y-m-d)
 * @return string เช่น "2 ปี 3 เดือน"
 */
function getAssetAge($received_date) {
    // ถ้าไม่มีวันที่ หรือเป็นค่าว่าง
    if (!$received_date || $received_date == '0000-00-00') {
        return "-";
    }

    try {
        $date1 = new DateTime($received_date);
        $date2 = new DateTime(); // วันที่ปัจจุบัน
        $interval = $date1->diff($date2);

        $parts = [];
        if ($interval->y > 0) $parts[] = $interval->y . " ปี";
        if ($interval->m > 0) $parts[] = $interval->m . " เดือน";
        
        // ถ้าไม่ถึง 1 เดือน ให้โชว์วัน
        if (empty($parts) && $interval->d > 0) {
            $parts[] = $interval->d . " วัน";
        }
        
        // ถ้าเพิ่งรับวันนี้
        if (empty($parts)) {
            return "เพิ่งรับวันนี้";
        }

        return implode(" ", $parts);
    } catch (Exception $e) {
        return "รูปแบบวันที่ผิด";
    }
}
?>