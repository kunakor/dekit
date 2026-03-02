<?php
require 'db.php';

// 1. ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

// 2. ตรวจสอบ ID
if (!isset($_GET['id']) || empty($_GET['id'])) { header("Location: assets.php"); exit; }
$asset_id = $_GET['id'];
$current_user_id = $_SESSION['user_id'];

// --- Backend Logic: บันทึกการซ่อม ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_repair') {
    $issue = $_POST['issue'];
    $cost = $_POST['cost'];
    $vendor = $_POST['vendor'];
    $r_date = $_POST['repair_date'];

    $sql = "INSERT INTO maintenance_logs (asset_id, issue_description, repair_cost, vendor_name, repair_date, created_by) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    if($stmt->execute([$asset_id, $issue, $cost, $vendor, $r_date, $current_user_id])) {
        // อัปเดตสถานะ Asset เป็น 'repair' (ส่งซ่อม) อัตโนมัติ
        $pdo->prepare("UPDATE assets SET status = 'repair' WHERE id = ?")->execute([$asset_id]);
        
        // Log ลง Timeline หลักด้วย
        $pdo->prepare("INSERT INTO borrow_logs (asset_id, user_id, action) VALUES (?, ?, 'repair')")->execute([$asset_id, $current_user_id]);

        setFlash('success', 'บันทึกประวัติการซ่อมเรียบร้อย');
    }
    header("Location: history.php?id=$asset_id");
    exit;
}

try {
    // 3. ดึงรายละเอียดครุภัณฑ์
    $stmt = $pdo->prepare("SELECT a.*, d.name as dept_name, c.name as cat_name FROM assets a 
                           LEFT JOIN departments d ON a.department_id = d.id 
                           LEFT JOIN categories c ON c.code = a.category
                           WHERE a.id = ?");
    $stmt->execute([$asset_id]);
    $asset = $stmt->fetch();

    if (!$asset) die("ไม่พบข้อมูลครุภัณฑ์");

    // 4. ดึง Timeline (ยืม-คืน)
    $sql_log = "SELECT l.*, u.fullname FROM borrow_logs l LEFT JOIN users u ON l.user_id = u.id WHERE l.asset_id = ? ORDER BY l.action_date DESC";
    $stmt = $pdo->prepare($sql_log);
    $stmt->execute([$asset_id]);
    $logs = $stmt->fetchAll();

    // 5. ดึงประวัติการซ่อม (Maintenance Logs) 🔥 เพิ่มส่วนนี้
    $sql_maint = "SELECT * FROM maintenance_logs WHERE asset_id = ? ORDER BY repair_date DESC";
    $stmt = $pdo->prepare($sql_maint);
    $stmt->execute([$asset_id]);
    $repairs = $stmt->fetchAll();

    // คำนวณค่าซ่อมรวม
    $total_repair_cost = 0;
    foreach($repairs as $r) $total_repair_cost += $r['repair_cost'];

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ประวัติ - <?= htmlspecialchars($asset['asset_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f6f9; }
        .timeline { border-left: 3px solid #e9ecef; margin-left: 20px; padding-left: 30px; position: relative; }
        .timeline-item { position: relative; margin-bottom: 30px; }
        .timeline-icon { position: absolute; left: -46px; top: 0; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); font-size: 14px; }
        .asset-header-img { width: 100px; height: 100px; object-fit: cover; border-radius: 12px; border: 4px solid white; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .no-img-header { width: 100px; height: 100px; border-radius: 12px; background-color: #e9ecef; color: #adb5bd; display: flex; align-items: center; justify-content: center; font-size: 40px; border: 4px solid white; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid pb-5">
            
            <?php if (isset($_SESSION['flash'])): ?>
                <script>Swal.fire({icon: '<?= $_SESSION['flash']['type'] ?>', title: '<?= $_SESSION['flash']['message'] ?>', showConfirmButton: false, timer: 2000});</script>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <a href="assets.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> กลับหน้ารายการ</a>
                <button class="btn btn-danger shadow-sm" onclick="openRepairModal()"><i class="bi bi-wrench"></i> บันทึกการซ่อม</button>
            </div>

            <div class="row">
                <div class="col-lg-5 mb-4">
                    
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4 text-center">
                            <?php if(!empty($asset['image']) && file_exists("uploads/".$asset['image'])): ?>
                                <img src="uploads/<?= $asset['image'] ?>" class="asset-header-img mb-3">
                            <?php else: ?>
                                <div class="no-img-header mx-auto mb-3"><i class="bi bi-box-seam"></i></div>
                            <?php endif; ?>
                            
                            <h4 class="fw-bold text-primary mb-1"><?= $asset['asset_name'] ?></h4>
                            <span class="badge bg-dark mb-3"><?= $asset['asset_code'] ?></span>
                            
                            <div class="text-start mt-3">
                                <p class="mb-1"><i class="bi bi-tag text-muted me-2"></i> หมวด: <b><?= $asset['category'] ?></b></p>
                                <p class="mb-1"><i class="bi bi-building text-muted me-2"></i> สาขา: <b><?= $asset['dept_name'] ?></b></p>
                                <p class="mb-1"><i class="bi bi-geo-alt text-muted me-2"></i> ที่เก็บ: <b><?= $asset['location'] ?></b></p>
                                <p class="mb-1"><i class="bi bi-calendar text-muted me-2"></i> รับเมื่อ: <b><?= date('d/m/Y', strtotime($asset['received_date'])) ?></b></p>
                                <p class="mb-1"><i class="bi bi-cash text-muted me-2"></i> ราคาซื้อ: <b><?= number_format($asset['price']) ?></b> บาท</p>
                                <p class="mb-0"><i class="bi bi-upc-scan text-muted me-2"></i> S/N: <b><?= $asset['serial_number'] ?: '-' ?></b></p>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold py-3">
                            <i class="bi bi-tools text-danger"></i> ประวัติการซ่อมบำรุง
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped mb-0 text-center" style="font-size: 0.9rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>วันที่</th>
                                        <th class="text-start">อาการ / ร้าน</th>
                                        <th class="text-end">ค่าใช้จ่าย</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($repairs) == 0): ?>
                                        <tr><td colspan="3" class="text-muted py-4">ไม่เคยมีประวัติการซ่อม</td></tr>
                                    <?php endif; ?>

                                    <?php foreach($repairs as $r): ?>
                                    <tr>
                                        <td><?= date('d/m/y', strtotime($r['repair_date'])) ?></td>
                                        <td class="text-start">
                                            <div class="fw-bold"><?= $r['issue_description'] ?></div>
                                            <small class="text-muted"><i class="bi bi-shop"></i> <?= $r['vendor_name'] ?></small>
                                        </td>
                                        <td class="text-end text-danger fw-bold"><?= number_format($r['repair_cost']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="2" class="text-end fw-bold">รวมค่าซ่อมทั้งหมด</td>
                                        <td class="text-end fw-bold text-danger text-decoration-underline"><?= number_format($total_repair_cost) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                </div>

                <div class="col-lg-7">
                    <h5 class="mb-4 fw-bold text-secondary ps-2 border-start border-4 border-primary">
                        Timeline การใช้งาน
                    </h5>

                    <div class="timeline">
                        <?php if(count($logs) == 0): ?>
                            <div class="alert alert-light text-center border-0 shadow-sm py-4">
                                <i class="bi bi-hourglass text-muted fs-1"></i><br>ยังไม่มีประวัติการทำรายการ
                            </div>
                        <?php endif; ?>

                        <?php foreach($logs as $log): ?>
                            <?php 
                                $action = $log['action'];
                                $icon = 'bi-circle'; $bg = 'bg-secondary'; $text = 'ทำรายการ'; $desc = '';

                                if ($action == 'create') { $icon = 'bi-plus-lg'; $bg = 'bg-primary'; $text = 'ลงทะเบียนใหม่'; }
                                elseif ($action == 'edit') { $icon = 'bi-pencil-fill'; $bg = 'bg-warning text-dark'; $text = 'แก้ไขข้อมูล'; }
                                elseif ($action == 'borrow') { $icon = 'bi-person-up'; $bg = 'bg-info text-dark'; $text = 'ถูกยืม / มอบหมาย'; }
                                elseif ($action == 'return') { $icon = 'bi-arrow-return-left'; $bg = 'bg-success'; $text = 'คืนของ / สถานะปกติ'; }
                                elseif ($action == 'repair') { $icon = 'bi-wrench'; $bg = 'bg-danger'; $text = 'ส่งซ่อม'; }
                                elseif ($action == 'disposed') { $icon = 'bi-trash-fill'; $bg = 'bg-dark'; $text = 'จำหน่าย / ตัดของ'; }
                            ?>
                            
                            <div class="timeline-item">
                                <div class="timeline-icon <?= $bg ?>"><i class="bi <?= $icon ?>"></i></div>
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body py-3 px-4">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="fw-bold mb-1 <?= ($action=='repair'||$action=='disposed') ? 'text-danger' : 'text-dark' ?>">
                                                    <?= $text ?>
                                                </h6>
                                            </div>
                                            <small class="text-secondary" style="font-size: 0.8rem;">
                                                <i class="bi bi-clock"></i> 
                                                <?= date('d/m/Y H:i', strtotime($log['action_date'])) ?>
                                            </small>
                                        </div>
                                        <div class="d-flex align-items-center small text-secondary mt-1">
                                            <i class="bi bi-person-circle me-2"></i> โดย: <?= htmlspecialchars($log['fullname']) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="modal fade" id="repairModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="bi bi-wrench"></i> บันทึกการส่งซ่อม</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_repair">
                        <div class="mb-3">
                            <label>อาการเสีย / สาเหตุ <span class="text-danger">*</span></label>
                            <textarea name="issue" class="form-control" rows="2" required placeholder="เช่น เปิดไม่ติด, จอฟ้า, เปลี่ยนอะไหล่..."></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <label>ค่าซ่อม (บาท)</label>
                                <input type="number" name="cost" class="form-control" value="0">
                            </div>
                            <div class="col-6">
                                <label>วันที่ส่งซ่อม</label>
                                <input type="date" name="repair_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>ชื่อร้าน / ศูนย์บริการ</label>
                            <input type="text" name="vendor" class="form-control" placeholder="เช่น ร้านช่างดำ IT, ศูนย์ Dell...">
                        </div>
                        <div class="alert alert-warning small mb-0">
                            <i class="bi bi-exclamation-triangle"></i> เมื่อบันทึกแล้ว สถานะพัสดุจะเปลี่ยนเป็น <b>"ส่งซ่อม (Repair)"</b> ทันที
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-danger">บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openRepairModal() {
            var myModal = new bootstrap.Modal(document.getElementById('repairModal'));
            myModal.show();
        }
    </script>
</body>
</html>