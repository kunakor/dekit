<?php
require 'db.php';

// 1. ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

// --- เตรียมข้อมูล (Data Preparation) ---

// 1. Card สรุปยอดรวม
$total_items = $pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();
$total_price = $pdo->query("SELECT SUM(price) FROM assets")->fetchColumn();
$count_repair = $pdo->query("SELECT COUNT(*) FROM assets WHERE status='repair'")->fetchColumn();

// 2. ข้อมูลสำหรับกราฟโดนัท (สัดส่วนสถานะ)
$sql_status = "SELECT status, COUNT(*) as count FROM assets GROUP BY status";
$stmt_status = $pdo->query($sql_status);
$status_data = [];
$status_labels = [];
$status_map = ['available'=>'ว่าง', 'in_use'=>'ถูกยืม', 'repair'=>'ส่งซ่อม', 'disposed'=>'จำหน่าย', 'stationed'=>'ประจำจุด'];
while($row = $stmt_status->fetch()) {
    $status_labels[] = $status_map[$row['status']] ?? $row['status'];
    $status_data[] = $row['count'];
}

// 3. ข้อมูลสำหรับกราฟแท่ง (จำนวนของแต่ละสาขา)
$sql_dept = "SELECT d.code, COUNT(a.id) as count FROM assets a JOIN departments d ON a.department_id = d.id GROUP BY d.id";
$stmt_dept = $pdo->query($sql_dept);
$dept_labels = [];
$dept_data = [];
while($row = $stmt_dept->fetch()) {
    $dept_labels[] = $row['code'];
    $dept_data[] = $row['count'];
}

// 4. หาสินค้าที่ "ใกล้หมดอายุ" (อายุการใช้งานเกิน 90%)
$all_assets = $pdo->query("SELECT * FROM assets WHERE status != 'disposed'")->fetchAll();
$expired_list = [];
foreach($all_assets as $a) {
    $received = strtotime($a['received_date']);
    $limit = $a['useful_life'] ?: 5; // ถ้าไม่ระบุ ถือว่า 5 ปี
    $total_days = $limit * 365;
    $used_days = floor((time() - $received) / (60 * 60 * 24));
    
    $percent = 0;
    if($total_days > 0) $percent = ($used_days / $total_days) * 100;
    
    // เอาเฉพาะที่เกิน 90% มาแสดง
    if($percent >= 90) {
        $a['percent'] = $percent;
        $expired_list[] = $a;
    }
}
// เรียงลำดับเอาอันที่เก่าที่สุดขึ้นก่อน และตัดมาแค่ 5 อันดับแรก
usort($expired_list, function($a, $b) { return $b['percent'] <=> $a['percent']; });
$expired_list = array_slice($expired_list, 0, 5);


// 5. กิจกรรมล่าสุด (Recent Activity Logs)
$sql_log = "SELECT l.*, u.fullname, a.asset_code, a.asset_name 
            FROM borrow_logs l 
            LEFT JOIN users u ON l.user_id = u.id 
            LEFT JOIN assets a ON l.asset_id = a.id 
            ORDER BY l.action_date DESC LIMIT 5";
$recent_logs = $pdo->query($sql_log)->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - ระบบบริหารพัสดุ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body { background-color: #f4f6f9; font-family: 'Sarabun', sans-serif; }
        
        /* สไตล์การ์ด */
        .card-stat { border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); overflow: hidden; color: white; position: relative; transition: all 0.3s; }
        .card-stat .icon-bg { position: absolute; right: 10px; bottom: -10px; font-size: 5rem; opacity: 0.2; transform: rotate(-15deg); }
        
        /* ทำให้การ์ดกดได้ */
        .clickable-card { text-decoration: none; display: block; }
        .clickable-card:hover .card-stat { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }

        .bg-gradient-primary { background: linear-gradient(45deg, #4e73df, #224abe); }
        .bg-gradient-success { background: linear-gradient(45deg, #1cc88a, #13855c); }
        .bg-gradient-warning { background: linear-gradient(45deg, #f6c23e, #dda20a); }
        .bg-gradient-danger { background: linear-gradient(45deg, #e74a3b, #be2617); }

        .chart-container { position: relative; height: 300px; width: 100%; }
        .dashboard-card { border: none; border-radius: 12px; box-shadow: 0 0 15px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid py-4">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold text-dark m-0"><i class="bi bi-speedometer2"></i> ภาพรวมระบบ (Dashboard)</h3>
                    <p class="text-muted m-0">ยินดีต้อนรับคุณ <strong><?= $_SESSION['fullname'] ?? 'User' ?></strong></p>
                </div>
                <div class="text-end">
                    <span class="badge bg-light text-dark border px-3 py-2"><?= date('d F Y') ?></span>
                </div>
            </div>

            <div class="row g-3 mb-4">
                
                <div class="col-xl-3 col-md-6">
                    <a href="assets.php" class="clickable-card">
                        <div class="card card-stat bg-gradient-primary h-100 py-2">
                            <div class="card-body">
                                <div class="text-uppercase small fw-bold mb-1">จำนวนครุภัณฑ์ทั้งหมด</div>
                                <div class="h3 mb-0 fw-bold"><?= number_format($total_items) ?></div>
                                <i class="bi bi-box-seam icon-bg"></i>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-xl-3 col-md-6">
                    <a href="assets.php" class="clickable-card">
                        <div class="card card-stat bg-gradient-success h-100 py-2">
                            <div class="card-body">
                                <div class="text-uppercase small fw-bold mb-1">มูลค่าทรัพย์สินรวม (บาท)</div>
                                <div class="h3 mb-0 fw-bold"><?= number_format($total_price) ?></div>
                                <i class="bi bi-currency-bitcoin icon-bg"></i>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-xl-3 col-md-6">
                    <a href="assets.php?status=broken" class="clickable-card">
                        <div class="card card-stat bg-gradient-danger h-100 py-2">
                            <div class="card-body">
                                <div class="text-uppercase small fw-bold mb-1">รายการแจ้งซ่อม</div>
                                <div class="h3 mb-0 fw-bold"><?= number_format($count_repair) ?></div>
                                <i class="bi bi-wrench icon-bg"></i>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-xl-3 col-md-6">
                    <a href="assets.php" class="clickable-card">
                        <div class="card card-stat bg-gradient-warning h-100 py-2">
                            <div class="card-body">
                                <div class="text-uppercase small fw-bold mb-1">ควรพิจารณาจำหน่าย (90%+)</div>
                                <div class="h3 mb-0 fw-bold"><?= count($expired_list) ?></div>
                                <i class="bi bi-hourglass-bottom icon-bg"></i>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-5 mb-4">
                    <div class="card dashboard-card h-100">
                        <div class="card-header bg-white py-3 border-0">
                            <h6 class="m-0 fw-bold text-primary"><i class="bi bi-pie-chart-fill"></i> สัดส่วนสถานะพัสดุ</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 mb-4">
                    <div class="card dashboard-card h-100">
                        <div class="card-header bg-white py-3 border-0">
                            <h6 class="m-0 fw-bold text-primary"><i class="bi bi-bar-chart-fill"></i> จำนวนพัสดุแยกตามสาขา</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="deptChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card dashboard-card h-100">
                        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-danger"><i class="bi bi-exclamation-circle-fill"></i> รายการเสื่อมสภาพ (เกิน 90%)</h6>
                            <a href="assets.php" class="btn btn-sm btn-outline-secondary">ดูทั้งหมด</a>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped mb-0 text-center" style="font-size: 0.9rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>รหัส</th>
                                        <th class="text-start">รายการ</th>
                                        <th>สภาพ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($expired_list)): ?>
                                        <tr><td colspan="3" class="py-4 text-muted">เยี่ยมมาก! ไม่มีรายการเสื่อมสภาพ</td></tr>
                                    <?php endif; ?>

                                    <?php foreach($expired_list as $ex): ?>
                                    <tr>
                                        <td class="fw-bold text-primary"><?= $ex['asset_code'] ?></td>
                                        <td class="text-start text-truncate" style="max-width: 150px;"><?= $ex['asset_name'] ?></td>
                                        <td>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-danger" style="width: <?= $ex['percent'] ?>%"></div>
                                            </div>
                                            <small class="text-danger fw-bold"><?= round($ex['percent']) ?>%</small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="card dashboard-card h-100">
                        <div class="card-header bg-white py-3 border-0">
                            <h6 class="m-0 fw-bold text-info"><i class="bi bi-clock-history"></i> กิจกรรมล่าสุดในระบบ</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach($recent_logs as $log): 
                                    $badge_color = 'bg-secondary';
                                    $action_text = $log['action'];
                                    if($log['action']=='create') { $badge_color='bg-primary'; $action_text='ลงทะเบียน'; }
                                    if($log['action']=='edit') { $badge_color='bg-warning text-dark'; $action_text='แก้ไข'; }
                                    if($log['action']=='repair') { $badge_color='bg-danger'; $action_text='ส่งซ่อม'; }
                                    if($log['action']=='return') { $badge_color='bg-success'; $action_text='คืนของ'; }
                                ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge <?= $badge_color ?> me-2"><?= $action_text ?></span>
                                        <span class="fw-bold text-dark"><?= $log['asset_code'] ?></span>
                                        <small class="text-muted d-block ms-1">โดย: <?= $log['fullname'] ?></small>
                                    </div>
                                    <small class="text-muted"><?= date('d/m H:i', strtotime($log['action_date'])) ?></small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 1. กราฟวงกลม (Status Chart)
        const ctxStatus = document.getElementById('statusChart').getContext('2d');
        new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($status_labels) ?>,
                datasets: [{
                    data: <?= json_encode($status_data) ?>,
                    backgroundColor: ['#1cc88a', '#f6c23e', '#e74a3b', '#858796', '#36b9cc'],
                    borderWidth: 1
                }]
            },
            options: { maintainAspectRatio: false }
        });

        // 2. กราฟแท่ง (Dept Chart)
        const ctxDept = document.getElementById('deptChart').getContext('2d');
        new Chart(ctxDept, {
            type: 'bar',
            data: {
                labels: <?= json_encode($dept_labels) ?>,
                datasets: [{
                    label: 'จำนวนรายการ',
                    data: <?= json_encode($dept_data) ?>,
                    backgroundColor: '#4e73df',
                    borderRadius: 5
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>
</body>
</html>