<?php
require 'db.php';

// 1. ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$myRole = $_SESSION['role'];
$myDeptId = $_SESSION['dept_id'] ?? 0;
$myName = $_SESSION['fullname'];

// 2. 🛡️ เช็คสิทธิ์: อนุญาตเฉพาะ Admin และ Teacher เท่านั้น
if ($myRole !== 'admin' && $myRole !== 'teacher') {
    die("❌ ขออภัย เฉพาะผู้ดูแลระบบและครูประจำสาขาเท่านั้นที่สามารถดูรายงานได้");
}

// 3. ดึงข้อมูลรายงานตามสิทธิ์ (Admin เห็นหมด, Teacher เห็นแค่แผนกตัวเอง)
if ($myRole == 'admin') {
    // ดึงครุภัณฑ์ทั้งหมด
    $sql = "SELECT a.*, d.name as dept_name FROM assets a LEFT JOIN departments d ON a.department_id = d.id ORDER BY a.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $report_title = "รายงานครุภัณฑ์รวมทุกสาขา (ภาพรวม)";
} else {
    // ดึงเฉพาะของสาขาครูคนนั้น
    $sql = "SELECT a.*, d.name as dept_name FROM assets a LEFT JOIN departments d ON a.department_id = d.id WHERE a.department_id = ? ORDER BY a.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$myDeptId]);
    
    // หาชื่อสาขามาโชว์ที่หัวรายงาน
    $d_stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
    $d_stmt->execute([$myDeptId]);
    $dept_name = $d_stmt->fetchColumn() ?: 'ไม่ระบุ';
    $report_title = "รายงานครุภัณฑ์ประจำแผนก: " . $dept_name;
}

$assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// คำนวณยอดสรุป
$total_items = count($assets);
$total_price = 0;
$status_count = ['available' => 0, 'stationed' => 0, 'in_use' => 0, 'repair' => 0, 'disposed' => 0];

foreach ($assets as $a) {
    $total_price += $a['price'];
    if(isset($status_count[$a['status']])) {
        $status_count[$a['status']]++;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานครุภัณฑ์</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Sarabun', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            .card { border: none !important; box-shadow: none !important; }
            body { background-color: white; }
        }
    </style>
</head>
<body>
    
    <div class="no-print">
        <?php include 'sidebar.php'; ?>
    </div>

    <div class="main-content">
        <div class="container-fluid py-4">
            
            <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                <h3 class="fw-bold m-0"><i class="bi bi-file-earmark-bar-graph"></i> ระบบรายงาน (Report)</h3>
                <button onclick="window.print()" class="btn btn-success"><i class="bi bi-printer"></i> พิมพ์รายงาน / บันทึก PDF</button>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body text-center p-4">
                    <h4 class="fw-bold mb-1"><?= $report_title ?></h4>
                    <p class="text-muted mb-0">ออกรายงานโดย: <?= $myName ?> (พิมพ์เมื่อ: <?= date('d/m/Y H:i') ?>)</p>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card shadow-sm h-100 p-3 border-start border-4 border-primary">
                        <small class="text-muted">จำนวนครุภัณฑ์ทั้งหมด</small>
                        <h3 class="fw-bold text-primary mb-0"><?= $total_items ?> <span class="fs-6 text-muted">รายการ</span></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm h-100 p-3 border-start border-4 border-success">
                        <small class="text-muted">มูลค่ารวมทั้งหมด</small>
                        <h3 class="fw-bold text-success mb-0"><?= number_format($total_price) ?> <span class="fs-6 text-muted">บาท</span></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm h-100 p-3 border-start border-4 border-info">
                        <small class="text-muted">พร้อมใช้งาน / ประจำจุด</small>
                        <h3 class="fw-bold text-info mb-0"><?= $status_count['available'] + $status_count['stationed'] ?> <span class="fs-6 text-muted">รายการ</span></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm h-100 p-3 border-start border-4 border-danger">
                        <small class="text-muted">ส่งซ่อม / ชำรุด</small>
                        <h3 class="fw-bold text-danger mb-0"><?= $status_count['repair'] ?> <span class="fs-6 text-muted">รายการ</span></h3>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ลำดับ</th>
                                <th>รหัสครุภัณฑ์</th>
                                <th>ชื่อรายการ</th>
                                <?php if($myRole == 'admin') echo "<th>สาขา</th>"; ?>
                                <th>ราคา (บาท)</th>
                                <th>วันที่รับ</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($assets)): ?>
                                <tr><td colspan="7" class="text-center py-3 text-muted">ไม่พบข้อมูลครุภัณฑ์</td></tr>
                            <?php endif; ?>

                            <?php $i=1; foreach($assets as $a): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td class="fw-bold text-primary"><?= $a['asset_code'] ?></td>
                                <td><?= $a['asset_name'] ?></td>
                                <?php if($myRole == 'admin') echo "<td>".($a['dept_name'] ?: '-')."</td>"; ?>
                                <td class="text-end"><?= number_format($a['price']) ?></td>
                                <td><?= date('d/m/Y', strtotime($a['received_date'])) ?></td>
                                <td>
                                    <?php 
                                        $s = $a['status'];
                                        if($s=='available') echo 'ว่าง';
                                        elseif($s=='stationed') echo 'ประจำจุด';
                                        elseif($s=='in_use') echo 'ถูกยืม';
                                        elseif($s=='repair') echo 'ส่งซ่อม';
                                        elseif($s=='disposed') echo 'จำหน่าย';
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</body>
</html>