<?php
/**
 * assets.php - ทะเบียนครุภัณฑ์ (Premium UI Edition + Fix Bugs)
 */
require 'db.php';

// 1. ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_dept = $_SESSION['dept_id'] ?? 0;
$user_name = $_SESSION['fullname'];

// ===================================================================================
// 2. BACKEND LOGIC (Add / Edit / Delete)
// ===================================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    // --- เพิ่มข้อมูล (ADD) ---
    if ($action == 'add_asset') {
        try {
            $name        = trim($_POST['asset_name']);
            $cat_code    = $_POST['category_code'];
            $price       = floatval($_POST['price']);
            $status      = $_POST['status'];
            $description = trim($_POST['description']);
            $serial      = trim($_POST['serial_number']);
            $location    = trim($_POST['location']);
            $received    = $_POST['received_date'] ?: date('Y-m-d');
            $target_dept = ($user_role == 'admin') ? $_POST['department_id'] : $user_dept;

            // สร้างรหัสอัตโนมัติ
            $stmt_d = $pdo->prepare("SELECT code FROM departments WHERE id = ?");
            $stmt_d->execute([$target_dept]);
            $dept_code = $stmt_d->fetchColumn() ?: 'GEN';
            
            $year_th = (date('Y') + 543) % 100;
            $prefix  = "{$dept_code}-{$cat_code}-{$year_th}-";
            
            $stmt_run = $pdo->prepare("SELECT asset_code FROM assets WHERE asset_code LIKE ? ORDER BY id DESC LIMIT 1");
            $stmt_run->execute([$prefix . '%']);
            $last_code = $stmt_run->fetchColumn();
            $next_num = 1;
            if ($last_code) { $parts = explode('-', $last_code); $next_num = intval(end($parts)) + 1; }
            $final_code = $prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);

            // จัดการรูปภาพ
            $image_file = NULL;
            if (!empty($_FILES['image']['name'])) {
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $new_name = uniqid('asset_') . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $new_name)) { $image_file = $new_name; }
            }

            // หาชื่อหมวดหมู่
            $stmt_c = $pdo->prepare("SELECT name FROM categories WHERE code = ?");
            $stmt_c->execute([$cat_code]);
            $cat_name = $stmt_c->fetchColumn();

            // SQL Insert (ตัด created_at ออกถ้า DB ไม่มี หรือใช้ NOW() ถ้ามี)
            // เพื่อความชัวร์ เราจะบันทึกเท่าที่มีฟิลด์
            $sql = "INSERT INTO assets (asset_code, asset_name, category, department_id, price, status, image, description, serial_number, location, received_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$final_code, $name, $cat_name, $target_dept, $price, $status, $image_file, $description, $serial, $location, $received]);
            
            setFlash('success', "เพิ่มครุภัณฑ์สำเร็จ: $final_code");

        } catch (Exception $e) { setFlash('error', $e->getMessage()); }
    }

    // --- แก้ไขข้อมูล (EDIT) ---
    elseif ($action == 'edit_asset') {
        try {
            $id = $_POST['edit_id'];
            if ($user_role == 'teacher') {
                $chk = $pdo->prepare("SELECT department_id FROM assets WHERE id = ?");
                $chk->execute([$id]);
                if ($chk->fetchColumn() != $user_dept) throw new Exception("ไม่มีสิทธิ์แก้ไขของแผนกอื่น");
            }

            $name        = trim($_POST['asset_name']);
            $price       = floatval($_POST['price']);
            $status      = $_POST['status'];
            $description = trim($_POST['description']);
            $serial      = trim($_POST['serial_number']);
            $location    = trim($_POST['location']);
            $received    = $_POST['received_date'];
            $image_file  = $_POST['old_image']; 
            
            if (!empty($_FILES['image']['name'])) {
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                if (!empty($image_file) && file_exists("uploads/$image_file")) { unlink("uploads/$image_file"); }
                $new_name = uniqid('asset_') . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $new_name)) { $image_file = $new_name; }
            }

            $sql = "UPDATE assets SET asset_name=?, price=?, status=?, description=?, serial_number=?, location=?, received_date=?, image=? WHERE id=?";
            $pdo->prepare($sql)->execute([$name, $price, $status, $description, $serial, $location, $received, $image_file, $id]);
            setFlash('success', 'บันทึกการแก้ไขเรียบร้อย');

        } catch (Exception $e) { setFlash('error', $e->getMessage()); }
    }

    // --- ลบข้อมูล (DELETE) ---
    elseif ($action == 'delete_asset') {
        try {
            $id = $_POST['delete_id'];
            if ($user_role == 'teacher') {
                $chk = $pdo->prepare("SELECT department_id FROM assets WHERE id = ?");
                $chk->execute([$id]);
                if ($chk->fetchColumn() != $user_dept) throw new Exception("ไม่มีสิทธิ์ลบของแผนกอื่น");
            }

            $stmt = $pdo->prepare("SELECT image FROM assets WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row['image'] && file_exists("uploads/" . $row['image'])) { unlink("uploads/" . $row['image']); }

            $pdo->prepare("DELETE FROM assets WHERE id = ?")->execute([$id]);
            setFlash('success', 'ลบรายการเรียบร้อย');
        } catch (Exception $e) { setFlash('error', $e->getMessage()); }
    }
    header("Location: assets.php"); exit;
}

// ===================================================================================
// 3. FETCH DATA
// ===================================================================================
$categories = $pdo->query("SELECT * FROM categories ORDER BY code ASC")->fetchAll();
$departments = $pdo->query("SELECT * FROM departments ORDER BY id ASC")->fetchAll();

if ($user_role == 'admin') {
    $sql = "SELECT a.*, d.name as dept_name FROM assets a LEFT JOIN departments d ON a.department_id = d.id ORDER BY a.id DESC";
    $stmt = $pdo->prepare($sql); $stmt->execute();
} else {
    $sql = "SELECT a.*, d.name as dept_name FROM assets a LEFT JOIN departments d ON a.department_id = d.id WHERE a.department_id = ? ORDER BY a.id DESC";
    $stmt = $pdo->prepare($sql); $stmt->execute([$user_dept]);
}
$assets = $stmt->fetchAll();

$summary = ['total' => count($assets), 'price' => 0, 'available' => 0, 'repair' => 0];
foreach ($assets as $a) {
    $summary['price'] += $a['price'];
    if ($a['status'] == 'available') $summary['available']++;
    if ($a['status'] == 'repair') $summary['repair']++;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ทะเบียนครุภัณฑ์</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    
    <style>
        body { 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Sarabun', sans-serif; 
            min-height: 100vh;
            color: #2c3e50;
            overflow-x: hidden;
        }

        /* Layout Fixer */
        .main-content {
            flex-grow: 1;
            padding: 30px;
            height: 100vh;
            overflow-y: auto;
        }

        /* --- Modern Cards --- */
        .card {
            border: none;
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }

        /* --- Dashboard Stats --- */
        .stat-card { position: relative; overflow: hidden; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card::after {
            content: ''; position: absolute; right: -15px; bottom: -20px;
            font-family: 'bootstrap-icons'; font-size: 80px; opacity: 0.1; transform: rotate(-15deg);
        }
        
        .stat-primary { background: linear-gradient(135deg, #e0f2fe, #fff); }
        .stat-primary h3 { color: #0284c7; }
        .stat-primary::after { content: '\F1C8'; color: #0284c7; }

        .stat-success { background: linear-gradient(135deg, #dcfce7, #fff); }
        .stat-success h3 { color: #16a34a; }
        .stat-success::after { content: '\F26B'; color: #16a34a; }

        .stat-info { background: linear-gradient(135deg, #e0e7ff, #fff); }
        .stat-info h3 { color: #4f46e5; }
        .stat-info::after { content: '\F26A'; color: #4f46e5; }

        .stat-danger { background: linear-gradient(135deg, #fee2e2, #fff); }
        .stat-danger h3 { color: #dc2626; }
        .stat-danger::after { content: '\F621'; color: #dc2626; }

        /* --- Table Styling --- */
        .table thead th {
            background-color: #f8fafc;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.85rem;
            border-bottom: 2px solid #e2e8f0;
            padding-top: 15px; padding-bottom: 15px;
        }
        .table-hover tbody tr:hover {
            background-color: #f1f5f9;
            transform: scale(1.002);
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: all 0.2s;
        }
        .asset-thumb {
            width: 48px; height: 48px; object-fit: cover;
            border-radius: 12px; border: 2px solid #fff;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            cursor: pointer; transition: 0.2s;
        }
        .asset-thumb:hover { transform: scale(1.2); z-index: 10; }

        /* --- Buttons & Badges --- */
        .badge-status { 
            padding: 8px 12px; border-radius: 30px; font-weight: 600; font-size: 0.75rem; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .btn-gradient {
            background: linear-gradient(45deg, #2563eb, #1d4ed8);
            border: none; color: white;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
            border-radius: 30px; padding: 10px 24px; font-weight: 600;
            transition: all 0.3s;
        }
        .btn-gradient:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4); color: white; }

        .btn-icon {
            width: 34px; height: 34px; border-radius: 50%; padding: 0; display: inline-flex;
            align-items: center; justify-content: center; border: none; transition: 0.2s;
        }
        .btn-edit { background: #fef3c7; color: #d97706; }
        .btn-edit:hover { background: #d97706; color: white; transform: translateY(-3px); }
        
        .btn-del { background: #fee2e2; color: #dc2626; }
        .btn-del:hover { background: #dc2626; color: white; transform: translateY(-3px); }
        
        .btn-hist { background: #e0f2fe; color: #0284c7; }
        .btn-hist:hover { background: #0284c7; color: white; transform: translateY(-3px); }

        /* SweetAlert Custom */
        div:where(.swal2-container) div:where(.swal2-popup) { border-radius: 20px; }
    </style>
</head>

<body class="d-flex">

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        
        <?php if (isset($_SESSION['flash'])): ?>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>Swal.fire({icon: '<?= $_SESSION['flash']['type'] ?>', title: '<?= $_SESSION['flash']['message'] ?>', showConfirmButton: false, timer: 1500});</script>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>

        <div class="card mb-4 p-4 border-0 d-flex flex-row justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1" style="color: #1e293b;">
                    <i class="bi bi-box-seam-fill text-primary me-2"></i>ทะเบียนครุภัณฑ์
                </h4>
                <p class="text-muted mb-0 small">จัดการข้อมูลทรัพย์สิน | ผู้ใช้งาน: <b><?= $user_name ?></b></p>
            </div>
            
            <?php if($user_role == 'admin' || $user_role == 'teacher'): ?>
                <button class="btn btn-gradient" onclick="openAddModal()">
                    <i class="bi bi-plus-lg me-2"></i>เพิ่มครุภัณฑ์ใหม่
                </button>
            <?php endif; ?>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card h-100 stat-card stat-primary">
                    <div class="card-body p-4">
                        <small class="text-uppercase fw-bold text-muted" style="font-size: 0.7rem;">ทั้งหมด</small>
                        <h3 class="fw-bold mb-0"><?= number_format($summary['total']) ?> <span class="fs-6 text-muted fw-normal">รายการ</span></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 stat-card stat-success">
                    <div class="card-body p-4">
                        <small class="text-uppercase fw-bold text-muted" style="font-size: 0.7rem;">มูลค่ารวม</small>
                        <h3 class="fw-bold mb-0"><?= number_format($summary['price']) ?> <span class="fs-6 text-muted fw-normal">บาท</span></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 stat-card stat-info">
                    <div class="card-body p-4">
                        <small class="text-uppercase fw-bold text-muted" style="font-size: 0.7rem;">พร้อมใช้งาน</small>
                        <h3 class="fw-bold mb-0"><?= number_format($summary['available']) ?> <span class="fs-6 text-muted fw-normal">รายการ</span></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 stat-card stat-danger">
                    <div class="card-body p-4">
                        <small class="text-uppercase fw-bold text-muted" style="font-size: 0.7rem;">ส่งซ่อม/ชำรุด</small>
                        <h3 class="fw-bold mb-0"><?= number_format($summary['repair']) ?> <span class="fs-6 text-muted fw-normal">รายการ</span></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="card p-0 overflow-hidden">
            <div class="card-body p-0">
                <div class="table-responsive p-3">
                    <table id="mainTable" class="table table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <th class="text-center" width="70">รูปภาพ</th>
                                <th>รหัสครุภัณฑ์</th>
                                <th>ชื่อรายการ / S/N</th>
                                <th>สาขา / ที่เก็บ</th>
                                <th>สถานะ</th>
                                <th class="text-end">ราคา</th>
                                <th class="text-center" width="120">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assets as $row): ?>
                            <tr>
                                <td class="text-center">
                                    <?php if ($row['image'] && file_exists("uploads/" . $row['image'])): ?>
                                        <img src="uploads/<?= $row['image'] ?>" class="asset-thumb" onclick="showImage('uploads/<?= $row['image'] ?>', '<?= $row['asset_name'] ?>')">
                                    <?php else: ?>
                                        <div class="asset-thumb bg-light d-flex align-items-center justify-content-center text-muted mx-auto"><i class="bi bi-image fs-5"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-bold text-primary"><?= $row['asset_code'] ?></span><br>
                                    
                                    <small class="text-secondary" style="font-size: 0.75rem;">
                                        <?= !empty($row['received_date']) ? date('d/m/Y', strtotime($row['received_date'])) : '-' ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark"><?= $row['asset_name'] ?></span>
                                    <?php if($row['serial_number']): ?>
                                        <br><small class="text-muted"><i class="bi bi-barcode"></i> <?= $row['serial_number'] ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= $row['dept_name'] ?: 'ส่วนกลาง' ?></span><br>
                                    <small class="text-muted"><i class="bi bi-geo-alt-fill text-danger opacity-50"></i> <?= $row['location'] ?></small>
                                </td>
                                <td>
                                    <?php 
                                        $s = $row['status'];
                                        $cls = 'secondary'; $txt = 'ไม่ระบุ';
                                        if($s=='available') { $cls='success'; $txt='พร้อมใช้'; }
                                        elseif($s=='stationed') { $cls='info text-dark'; $txt='ประจำจุด'; }
                                        elseif($s=='repair') { $cls='danger'; $txt='ส่งซ่อม'; }
                                        elseif($s=='in_use') { $cls='warning text-dark'; $txt='ถูกยืม'; }
                                        elseif($s=='disposed') { $cls='dark'; $txt='จำหน่าย'; }
                                        echo "<span class='badge bg-$cls badge-status'>$txt</span>";
                                    ?>
                                </td>
                                <td class="text-end fw-bold text-secondary"><?= number_format($row['price']) ?></td>
                                <td class="text-center">
                                    <a href="history.php?id=<?= $row['id'] ?>" class="btn btn-icon btn-hist me-1" title="ประวัติ"><i class="bi bi-clock-history"></i></a>
                                    
                                    <?php if($user_role == 'admin' || ($user_role == 'teacher' && $row['department_id'] == $user_dept)): ?>
                                        <button class="btn btn-icon btn-edit me-1" onclick='openEdit(<?= json_encode($row) ?>)' title="แก้ไข"><i class="bi bi-pencil-fill"></i></button>
                                        <form method="POST" class="d-inline" onsubmit="return confirmDelete('<?= $row['asset_code'] ?>')">
                                            <input type="hidden" name="action" value="delete_asset">
                                            <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                            <button class="btn btn-icon btn-del" title="ลบ"><i class="bi bi-trash-fill"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div> <div class="modal fade" id="assetModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
                <form method="POST" enctype="multipart/form-data" id="assetForm">
                    <div class="modal-header bg-primary text-white" style="border-top-left-radius: 20px; border-top-right-radius: 20px;">
                        <h5 class="modal-title fw-bold" id="modalTitle">เพิ่มครุภัณฑ์ใหม่</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body bg-light">
                        <input type="hidden" name="action" id="formAction" value="add_asset">
                        <input type="hidden" name="edit_id" id="editId">
                        <input type="hidden" name="old_image" id="oldImage">

                        <div class="card p-3 mb-3 border-0 shadow-sm">
                            <h6 class="text-primary fw-bold border-bottom pb-2">ข้อมูลหลัก</h6>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">ชื่อรายการ <span class="text-danger">*</span></label>
                                    <input type="text" name="asset_name" id="assetName" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">หมวดหมู่</label>
                                    <select name="category_code" id="categoryCode" class="form-select" required>
                                        <option value="" disabled selected>-- เลือก --</option>
                                        <?php foreach($categories as $c): ?><option value="<?= $c['code'] ?>"><?= $c['name'] ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Serial Number</label>
                                    <input type="text" name="serial_number" id="serialNumber" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">วันที่รับ</label>
                                    <input type="date" name="received_date" id="receivedDate" class="form-control" value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="card p-3 border-0 shadow-sm">
                            <h6 class="text-primary fw-bold border-bottom pb-2">สถานะ & ที่เก็บ</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">ราคา (บาท)</label>
                                    <input type="number" name="price" id="price" class="form-control" step="0.01">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">สถานะ</label>
                                    <select name="status" id="status" class="form-select">
                                        <option value="available">🟢 ว่าง (พร้อมใช้)</option>
                                        <option value="stationed" selected>🔵 ประจำจุด</option>
                                        <option value="in_use">🟡 ถูกยืม</option>
                                        <option value="repair">🔴 ส่งซ่อม</option>
                                        <option value="disposed">⚫ จำหน่าย</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ที่เก็บ/ห้อง</label>
                                    <input type="text" name="location" id="location" class="form-control">
                                </div>
                                <?php if($user_role == 'admin'): ?>
                                <div class="col-md-12">
                                    <label class="form-label text-warning fw-bold">สาขาเจ้าของ (Admin Only)</label>
                                    <select name="department_id" id="departmentId" class="form-select">
                                        <?php foreach($departments as $d): ?><option value="<?= $d['id'] ?>"><?= $d['name'] ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-3">
                                <label class="form-label">รูปภาพ</label>
                                <input type="file" name="image" class="form-control">
                            </div>
                            <div class="mt-2">
                                <textarea name="description" id="description" class="form-control" rows="2" placeholder="รายละเอียดเพิ่มเติม..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary px-4 shadow">บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            $('#mainTable').DataTable({ 
                "language": { "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json" },
                "pageLength": 10,
                "order": [[ 1, "desc" ]] 
            });
        });

        function openAddModal() {
            document.getElementById('assetForm').reset();
            document.getElementById('formAction').value = 'add_asset';
            document.getElementById('modalTitle').innerText = 'เพิ่มครุภัณฑ์ใหม่';
            <?php if($user_role == 'admin'): ?>document.getElementById('departmentId').selectedIndex = 0;<?php endif; ?>
            new bootstrap.Modal(document.getElementById('assetModal')).show();
        }

        function openEdit(data) {
            document.getElementById('formAction').value = 'edit_asset';
            document.getElementById('editId').value = data.id;
            document.getElementById('oldImage').value = data.image;
            document.getElementById('modalTitle').innerText = 'แก้ไขข้อมูลครุภัณฑ์';
            
            document.getElementById('assetName').value = data.asset_name;
            document.getElementById('price').value = data.price;
            document.getElementById('serialNumber').value = data.serial_number;
            document.getElementById('receivedDate').value = data.received_date;
            document.getElementById('location').value = data.location;
            document.getElementById('description').value = data.description;
            document.getElementById('status').value = data.status;

            // Match Category
            let catSelect = document.getElementById('categoryCode');
            for(let i=0; i<catSelect.options.length; i++) {
                if(catSelect.options[i].text === data.category) { catSelect.selectedIndex = i; break; }
            }
            <?php if($user_role == 'admin'): ?>document.getElementById('departmentId').value = data.department_id;<?php endif; ?>
            
            new bootstrap.Modal(document.getElementById('assetModal')).show();
        }

        function showImage(src, title) {
            Swal.fire({ imageUrl: src, imageAlt: title, title: title, width: 'auto', showConfirmButton: false, showCloseButton: true });
        }

        function confirmDelete(code) {
            return confirm('⚠️ ต้องการลบครุภัณฑ์รหัส "' + code + '" ใช่หรือไม่?\n(การกระทำนี้ไม่สามารถย้อนกลับได้)');
        }
    </script>
</body>
</html>