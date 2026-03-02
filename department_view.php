<?php
require 'db.php';

// 1. ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$current_user_id = $_SESSION['user_id'];
$dept_id = isset($_GET['id']) ? $_GET['id'] : 0;

// 🔥 FIX: ถ้าไม่ได้ระบุ ID มา และเป็น Teacher -> ให้ใช้ ID สาขาของตัวเองอัตโนมัติ
if ($dept_id == 0 && $_SESSION['role'] == 'teacher') {
    $dept_id = $_SESSION['dept_id'];
}

// --------------------------------------------------------------------------
// 🔥 ตรวจสอบสิทธิ์การจัดการ ($canManage)
// --------------------------------------------------------------------------
$canManage = false;
$myRole = $_SESSION['role'] ?? '';
$myDeptId = $_SESSION['dept_id'] ?? 0;

if ($myRole == 'admin') {
    $canManage = true; 
} elseif ($myRole == 'teacher') {
    // เช็คว่า ID สาขาของครู ตรงกับ ID ของหน้านี้ไหม
    if ($myDeptId == $dept_id) {
        $canManage = true;
    }
}

// --- ดึงข้อมูล Master Data ---
$cat_query = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $cat_query->fetchAll(PDO::FETCH_ASSOC);
$cat_map = []; foreach($categories as $c){ $cat_map[$c['code']] = $c['name']; }

// --- Backend Logic ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];

    // 🛡️ SECURITY Check
    if (!$canManage) {
        setFlash('error', 'คุณไม่มีสิทธิ์จัดการสาขานี้');
        header("Location: department_view.php?id=$dept_id"); exit;
    }

    // A. แก้ไขข้อมูลสาขา
    if ($action == 'update_dept') {
        $contact_name = trim($_POST['contact_person']);
        if(empty($contact_name)) $contact_name = "เจ้าหน้าที่สาขา";
        
        $pdo->prepare("UPDATE departments SET contact_person = ? WHERE id = ?")->execute([$contact_name, $dept_id]);
        setFlash('success', 'บันทึกชื่อผู้ติดต่อเรียบร้อยแล้ว');
    }

    // B. เพิ่มพัสดุใหม่
    elseif ($action == 'add_asset') {
        // ดึงรหัสย่อสาขา
        $stmt_d = $pdo->prepare("SELECT code FROM departments WHERE id = ?");
        $stmt_d->execute([$dept_id]);
        $d_code = $stmt_d->fetchColumn();

        $cat_code = $_POST['category_code'];
        $cat_name = $cat_map[$cat_code];
        $useful_life = intval($_POST['useful_life'] ?: 5);
        
        // เช็คเงื่อนไขที่เก็บ
        $location = ($_POST['status'] == 'repair' || $_POST['status'] == 'disposed') ? trim($_POST['custom_location']) : $_POST['building']." ".$_POST['room'];
        if(!$location || trim($location) == "") $location = "-";

        $img = NULL;
        if(!empty($_FILES['image']['name'])) {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $new=uniqid('a_').'.'.$ext;
            if(move_uploaded_file($_FILES['image']['tmp_name'], "uploads/$new")) $img=$new;
        }

        // สร้างรหัสอัตโนมัติ
        $year = (date('Y') + 543) % 100;
        $prefix = "{$d_code}-{$cat_code}-{$year}-";
        $stmt = $pdo->prepare("SELECT asset_code FROM assets WHERE asset_code LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$prefix . '%']);
        $last = $stmt->fetchColumn(); 
        $run_num = 1;
        if($last) { $parts = explode('-', $last); $run_num = intval(end($parts)) + 1; }
        $final_code = $prefix . str_pad($run_num, 4, '0', STR_PAD_LEFT);

        $sql = "INSERT INTO assets (asset_code, asset_name, category, department_id, location, description, price, received_date, status, image, serial_number, useful_life) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
        $pdo->prepare($sql)->execute([$final_code, $_POST['name'], $cat_name, $dept_id, $location, $_POST['desc'], $_POST['price'], $_POST['date'], $_POST['status'], $img, $_POST['serial'], $useful_life]);
        
        $new_id = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO borrow_logs (asset_id, user_id, action) VALUES (?, ?, 'create')")->execute([$new_id, $current_user_id]);
        setFlash('success', "เพิ่มพัสดุสำเร็จ: $final_code");
    }

    // C. แก้ไขพัสดุ
    elseif ($action == 'edit_asset') {
        $cat_code = $_POST['category_code'];
        $cat_name = $cat_map[$cat_code];
        $useful_life = intval($_POST['useful_life'] ?: 5);
        
        $location = ($_POST['status'] == 'repair' || $_POST['status'] == 'disposed') ? trim($_POST['custom_location']) : $_POST['building']." ".$_POST['room'];
        if(!$location || trim($location) == "") $location = "-";

        $img = $_POST['old_image'];
        if(!empty($_FILES['image']['name'])) {
            if(!empty($_POST['old_image'])) {
                $old_path = "uploads/" . $_POST['old_image'];
                if(file_exists($old_path)) unlink($old_path);
            }
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $new = uniqid('a_').'.'.$ext;
            if(move_uploaded_file($_FILES['image']['tmp_name'], "uploads/$new")) $img=$new;
        }

        $sql = "UPDATE assets SET asset_name=?, category=?, location=?, description=?, price=?, received_date=?, status=?, image=?, serial_number=?, useful_life=? WHERE id=?";
        $pdo->prepare($sql)->execute([$_POST['name'], $cat_name, $location, $_POST['desc'], $_POST['price'], $_POST['date'], $_POST['status'], $img, $_POST['serial'], $useful_life, $_POST['edit_id']]);
        
        $pdo->prepare("INSERT INTO borrow_logs (asset_id, user_id, action) VALUES (?, ?, 'edit')")->execute([$_POST['edit_id'], $current_user_id]);
        setFlash('success', 'แก้ไขข้อมูลเรียบร้อย');
    }

    // D. ลบพัสดุ
    elseif ($action == 'delete_asset') {
        $delete_id = $_POST['delete_id'];
        $stmt = $pdo->prepare("SELECT image FROM assets WHERE id = ?");
        $stmt->execute([$delete_id]);
        $target = $stmt->fetch();

        if ($target && !empty($target['image'])) {
            $file_path = "uploads/" . $target['image'];
            if (file_exists($file_path)) unlink($file_path);
        }

        $pdo->prepare("DELETE FROM assets WHERE id=?")->execute([$delete_id]);
        setFlash('success', 'ลบพัสดุเรียบร้อย');
    }

    header("Location: department_view.php?id=$dept_id");
    exit;
}

// --- Query ข้อมูลเพื่อแสดงผล ---
$stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
$stmt->execute([$dept_id]);
$dept = $stmt->fetch();

// 🔥 FIX: ถ้าไม่เจอสาขา (หรือ ID=0) ให้เด้งกลับหน้า Dashboard
if (!$dept) { 
    setFlash('error', 'ไม่พบข้อมูลสาขาวิชา หรือไม่ได้ระบุรหัสสาขา');
    header("Location: index.php"); 
    exit; 
}

// ดึงครุภัณฑ์
$sql = "SELECT * FROM assets WHERE department_id = ? ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$dept_id]);
$assets = $stmt->fetchAll();

// สรุปยอด
$total_items = count($assets);
$total_price = 0;
$count_available = 0;
$count_repair = 0;
foreach ($assets as $a) {
    $total_price += $a['price'];
    if($a['status'] == 'available') $count_available++;
    if($a['status'] == 'repair') $count_repair++;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ครุภัณฑ์ - <?= $dept['name'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f0f2f5; font-family: 'Sarabun', sans-serif; }
        .dept-header { background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%); color: white; padding: 40px 20px; border-radius: 0 0 20px 20px; margin-bottom: 30px; position: relative; }
        .edit-dept-btn { position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.2); color: white; border: none; }
        .edit-dept-btn:hover { background: rgba(255,255,255,0.4); }
        .asset-img { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; cursor: pointer; }
    </style>
</head>
<body class="d-flex">
    
    <?php include 'sidebar.php'; ?>

    <div class="main-content" style="flex-grow:1; width:100%;">
        
        <div class="dept-header shadow">
            <div class="container-fluid">
                <h2 class="fw-bold"><i class="bi bi-building"></i> <?= $dept['name'] ?></h2>
                <p class="mb-0 opacity-75">รหัสสาขา: <b><?= $dept['code'] ?></b> | ผู้ดูแล: <b><i class="bi bi-person-badge"></i> <?= $dept['contact_person'] ?></b></p>
                
                <?php if ($canManage): ?>
                    <button class="btn btn-sm edit-dept-btn rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#deptModal">
                        <i class="bi bi-pencil-square"></i> แก้ไขข้อมูลสาขา
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="container-fluid pb-5 px-4">
            <?php if (isset($_SESSION['flash'])): ?>
                <script>Swal.fire({icon: '<?= $_SESSION['flash']['type'] ?>', title: '<?= $_SESSION['flash']['message'] ?>', showConfirmButton: false, timer: 2000});</script>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-md-3"><div class="card shadow-sm h-100 p-3"><small class="text-muted">ทั้งหมด</small><h3 class="fw-bold text-primary"><?= $total_items ?> <span class="fs-6">รายการ</span></h3></div></div>
                <div class="col-md-3"><div class="card shadow-sm h-100 p-3"><small class="text-muted">มูลค่ารวม</small><h3 class="fw-bold text-success"><?= number_format($total_price) ?> <span class="fs-6">บาท</span></h3></div></div>
                <div class="col-md-3"><div class="card shadow-sm h-100 p-3"><small class="text-muted">พร้อมใช้</small><h3 class="fw-bold text-info"><?= $count_available ?> <span class="fs-6">รายการ</span></h3></div></div>
                <div class="col-md-3"><div class="card shadow-sm h-100 p-3"><small class="text-muted">ส่งซ่อม</small><h3 class="fw-bold text-danger"><?= $count_repair ?> <span class="fs-6">รายการ</span></h3></div></div>
            </div>

            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold"><i class="bi bi-list-ul"></i> รายการครุภัณฑ์ในสาขา</h5>
                    
                    <?php if ($canManage): ?>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAssetModal"><i class="bi bi-plus-lg"></i> เพิ่มพัสดุสาขานี้</button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <table id="deptTable" class="table table-hover align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th>รูป</th>
                                <th>รหัส / ชื่อ</th>
                                <th>ที่เก็บ</th>
                                <th>สถานะ</th>
                                <th class="text-end">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assets as $row): ?>
                            <tr>
                                <td>
                                    <?php if($row['image']): ?>
                                        <img src="uploads/<?= $row['image'] ?>" class="asset-img" onclick="Swal.fire({imageUrl:'uploads/<?= $row['image'] ?>', showConfirmButton:false})">
                                    <?php else: ?>
                                        <div class="asset-img bg-light d-flex align-items-center justify-content-center text-muted"><i class="bi bi-image"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-primary"><?= $row['asset_code'] ?></div>
                                    <?= $row['asset_name'] ?><br>
                                    <small class="text-muted">S/N: <?= $row['serial_number']?:'-' ?></small>
                                </td>
                                <td><i class="bi bi-geo-alt"></i> <?= $row['location'] ?></td>
                                <td>
                                    <?php 
                                        $s = $row['status'];
                                        if($s=='available') echo '<span class="badge bg-success">ว่าง</span>';
                                        elseif($s=='stationed') echo '<span class="badge bg-info text-dark">ประจำจุด</span>';
                                        elseif($s=='in_use') echo '<span class="badge bg-warning text-dark">ถูกยืม</span>';
                                        elseif($s=='repair') echo '<span class="badge bg-danger">ส่งซ่อม</span>';
                                        else echo '<span class="badge bg-dark">จำหน่าย</span>';
                                    ?>
                                </td>
                                <td class="text-end">
                                    <?php if($s == 'stationed'): ?>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="Swal.fire({icon: 'info', title: 'ติดต่อขอใช้', html: 'กรุณาติดต่อ: <b><?= $dept['contact_person'] ?></b><br>สาขา: <?= $dept['name'] ?>'})"><i class="bi bi-telephone"></i> ติดต่อ</button>
                                    <?php endif; ?>
                                    
                                    <a href="history.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-info" title="ดูประวัติ"><i class="bi bi-clock-history"></i></a>
                                    
                                    <?php if($canManage): ?>
                                        <button class="btn btn-sm btn-warning" onclick='openEditAssetModal(<?= json_encode($row) ?>)' title="แก้ไข"><i class="bi bi-pencil"></i></button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบรายการนี้?');">
                                            <input type="hidden" name="action" value="delete_asset">
                                            <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                            <button class="btn btn-sm btn-light text-danger" title="ลบ"><i class="bi bi-trash"></i></button>
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
    </div>

    <div class="modal fade" id="deptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title"><i class="bi bi-pencil-square"></i> แก้ไขข้อมูลสาขา</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_dept">
                        <div class="mb-3">
                            <label class="fw-bold">ชื่อสาขาวิชา</label>
                            <input type="text" class="form-control" value="<?= $dept['name'] ?>" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold text-primary">ชื่อครูผู้ดูแล / ผู้ติดต่อ <span class="text-danger">*</span></label>
                            <input type="text" name="contact_person" class="form-control" value="<?= $dept['contact_person'] ?>" placeholder="เช่น อ.สมชาย ใจดี">
                            <small class="text-muted">ชื่อนี้จะปรากฏเมื่อมีคนกดปุ่ม "ติดต่อ" ในรายการพัสดุ</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-info text-white">บันทึกการเปลี่ยนแปลง</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addAssetModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="bi bi-plus-circle"></i> เพิ่มพัสดุใหม่ (สาขา<?= $dept['name'] ?>)</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_asset">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label>หมวดหมู่ <span class="text-danger">*</span></label>
                                <select name="category_code" class="form-select" required>
                                    <?php foreach($categories as $c): ?>
                                        <option value="<?= $c['code'] ?>"><?= $c['name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6"><label>ชื่อครุภัณฑ์ <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6"><label>S/N</label><input type="text" name="serial" class="form-control"></div>
                            <div class="col-6">
                                <label>สถานะ</label>
                                <select name="status" class="form-select" id="add_status" onchange="toggleLoc('add')">
                                    <option value="available">🟢 ว่าง</option>
                                    <option value="stationed" selected>🔵 ประจำจุด (แนะนำ)</option>
                                    <option value="repair">🔴 ส่งซ่อม</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3" id="add_normal_loc">
                            <div class="col-md-6"><label>ตึก/อาคาร</label><select name="building" class="form-select"><option value="">- เลือกตึก -</option><?php for($i=1;$i<=9;$i++) echo "<option>อาคาร $i</option>"; ?></select></div>
                            <div class="col-md-6"><label>ห้อง</label><input type="text" name="room" class="form-control" placeholder="เช่น 401"></div>
                        </div>
                        <div class="mb-3" id="add_custom_loc" style="display:none;"><label>ระบุสถานที่</label><input type="text" name="custom_location" class="form-control"></div>
                        <div class="row mb-3">
                            <div class="col-4"><label>ราคา</label><input type="number" name="price" class="form-control"></div>
                            <div class="col-4"><label>วันที่รับ</label><input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                            <div class="col-4"><label>อายุใช้งาน(ปี)</label><input type="number" name="useful_life" class="form-control" value="5"></div>
                        </div>
                        <div class="mb-3"><label>รูปภาพ</label><input type="file" name="image" class="form-control"></div>
                        <textarea name="desc" class="form-control" rows="2" placeholder="รายละเอียดเพิ่มเติม"></textarea>
                    </div>
                    <div class="modal-footer"><button type="submit" class="btn btn-primary">บันทึกพัสดุ</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editAssetModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title"><i class="bi bi-pencil"></i> แก้ไขข้อมูลพัสดุ</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_asset">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <input type="hidden" name="old_image" id="edit_old_image">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label>หมวดหมู่</label>
                                <select name="category_code" id="edit_cat" class="form-select" required>
                                    <?php foreach($categories as $c): ?>
                                        <option value="<?= $c['code'] ?>"><?= $c['name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6"><label>ชื่อครุภัณฑ์</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-6"><label>S/N</label><input type="text" name="serial" id="edit_serial" class="form-control"></div>
                            <div class="col-6">
                                <label>สถานะ</label>
                                <select name="status" id="edit_status" class="form-select" onchange="toggleLoc('edit')">
                                    <option value="available">🟢 ว่าง</option>
                                    <option value="stationed">🔵 ประจำจุด</option>
                                    <option value="repair">🔴 ส่งซ่อม</option>
                                    <option value="disposed">⚫ จำหน่าย</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3" id="edit_normal_loc">
                            <div class="col-md-6"><label>ตึก/อาคาร</label><select name="building" id="edit_building" class="form-select"><option value="">- เลือกตึก -</option><?php for($i=1;$i<=9;$i++) echo "<option>อาคาร $i</option>"; ?></select></div>
                            <div class="col-md-6"><label>ห้อง</label><input type="text" name="room" id="edit_room" class="form-control"></div>
                        </div>
                        <div class="mb-3" id="edit_custom_loc" style="display:none;"><label>ระบุสถานที่</label><input type="text" name="custom_location" id="edit_custom_loc_in" class="form-control"></div>

                        <div class="row mb-3">
                            <div class="col-4"><label>ราคา</label><input type="number" name="price" id="edit_price" class="form-control"></div>
                            <div class="col-4"><label>วันที่รับ</label><input type="date" name="date" id="edit_date" class="form-control"></div>
                            <div class="col-4"><label>อายุใช้งาน(ปี)</label><input type="number" name="useful_life" id="edit_useful_life" class="form-control"></div>
                        </div>
                        <div class="mb-3"><label>เปลี่ยนรูปภาพ</label><input type="file" name="image" class="form-control"></div>
                        <textarea name="desc" id="edit_desc" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="modal-footer"><button type="submit" class="btn btn-warning">บันทึกการแก้ไข</button></div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#deptTable').DataTable({ "language": { "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json" } });
        });

        // ฟังก์ชัน Toggle ที่เก็บ
        function toggleLoc(prefix) {
            let st = document.getElementById(prefix + '_status').value;
            let isCustom = (st=='repair' || st=='disposed');
            document.getElementById(prefix + '_normal_loc').style.display = isCustom ? 'none' : 'flex';
            document.getElementById(prefix + '_custom_loc').style.display = isCustom ? 'block' : 'none';
        }

        // เปิด Modal แก้ไขพัสดุ และดึงข้อมูลเก่ามาใส่
        function openEditAssetModal(d) {
            ['id','name','price','date','desc','status','serial'].forEach(k => document.getElementById('edit_'+k).value = d[k=='date'?'received_date':(k=='name'?'asset_name':(k=='desc'?'description':(k=='serial'?'serial_number':k)))]);
            
            document.getElementById('edit_useful_life').value = d.useful_life || 5;
            document.getElementById('edit_old_image').value = d.image;

            let catSelect = document.getElementById('edit_cat');
            for(let i=0; i<catSelect.options.length; i++) {
                if(catSelect.options[i].text.includes(d.category)) { catSelect.selectedIndex = i; break; }
            }

            let loc = d.location;
            if(loc.includes('อาคาร')) {
                let parts = loc.split(' ');
                document.getElementById('edit_building').value = parts[0] + (parts[1] && parts[1].length==1 ? ' '+parts[1] : ''); 
                document.getElementById('edit_room').value = parts[parts.length-1];
            } else {
                document.getElementById('edit_custom_loc_in').value = loc;
            }

            toggleLoc('edit');
            new bootstrap.Modal(document.getElementById('editAssetModal')).show();
        }
    </script>
</body>
</html>