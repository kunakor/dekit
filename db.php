<?php
// 1. ป้องกันปัญหา Header already sent
ob_start();

// 2. ตั้งค่า Timezone
date_default_timezone_set('Asia/Bangkok');

// 3. เริ่มต้น Session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 4. ตั้งค่าการเชื่อมต่อฐานข้อมูล
// *** แก้ไขตรงนี้ ***
$host = 'localhost';  // ใส่แค่ IP Address ห้ามมี https:// หรือ /
$db   = 'items';
$user = 'items';
$pass = 'bnccitemsconfig';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // echo "✅ เชื่อมต่อสำเร็จ"; // ปิดไว้เมื่อใช้งานจริง
} catch (\PDOException $e) {
    // แจ้งเตือนเมื่อเชื่อมต่อไม่ได้
    die("❌ เชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage());
}

// --- 🛠️ HELPER FUNCTIONS ---
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function isAdmin() {
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
}

function getAssetAge($received_date) {
    if (!$received_date || $received_date == '0000-00-00') {
        return "-";
    }
    try {
        $date1 = new DateTime($received_date);
        $date2 = new DateTime();
        $interval = $date1->diff($date2);
        $parts = [];
        if ($interval->y > 0) $parts[] = $interval->y . " ปี";
        if ($interval->m > 0) $parts[] = $interval->m . " เดือน";
        if (empty($parts) && $interval->d > 0) {
            $parts[] = $interval->d . " วัน";
        }
        if (empty($parts)) {
            return "เพิ่งรับวันนี้";
        }
        return implode(" ", $parts);
    } catch (Exception $e) {
        return "รูปแบบวันที่ผิด";
    }
}
?>