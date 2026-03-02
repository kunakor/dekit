<?php
require 'db.php';

// เช็คสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// ดึงข้อมูล Log เชื่อมกับตาราง Users และ Assets
$sql = "SELECT l.*, a.asset_name, a.asset_code, u.fullname 
        FROM borrow_logs l
        JOIN assets a ON l.asset_id = a.id
        JOIN users u ON l.user_id = u.id
        ORDER BY l.id DESC";
$logs = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ประวัติการยืม-คืน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="d-flex justify-content-between mb-3">
            <h3>📜 ประวัติการยืม-คืนพัสดุ</h3>
            <a href="index.php" class="btn btn-secondary">กลับหน้าหลัก</a>
        </div>
        
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>เวลา</th>
                            <th>ผู้ทำรายการ</th>
                            <th>รหัสครุภัณฑ์</th>
                            <th>ชื่อครุภัณฑ์</th>
                            <th>การกระทำ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($logs as $log): ?>
                        <tr>
                            <td><?= $log['log_date'] ?></td>
                            <td><?= $log['fullname'] ?></td>
                            <td><?= $log['asset_code'] ?></td>
                            <td><?= $log['asset_name'] ?></td>
                            <td>
                                <?php if($log['action'] == 'borrow'): ?>
                                    <span class="badge bg-warning text-dark">ยืมออก</span>
                                <?php else: ?>
                                    <span class="badge bg-success">คืนของ</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>