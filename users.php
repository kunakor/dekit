<?php
require 'db.php';

// 1. ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$myRole = $_SESSION['role'];     // admin หรือ teacher
$myDeptId = $_SESSION['dept_id'] ?? 0;
$myId = $_SESSION['user_id'];
$myName = $_SESSION['fullname'];

// 2. ตรวจสอบสิทธิ์ (Admin หรือ Teacher เท่านั้น)
if ($myRole !== 'admin' && $myRole !== 'teacher') {
    die("❌ คุณไม่มีสิทธิ์เข้าถึงหน้านี้ (Access Denied)");
}

// --- Backend Logic: บันทึกข้อมูล ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];

    // A. บันทึกข้อมูล (เพิ่ม/แก้ไข)
    if ($action == 'save_user') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $fullname = trim($_POST['fullname']);
        $role = $_POST['role'];
        $dept_id = $_POST['department_id'];
        $edit_id = $_POST['edit_id'];

        // 🛡️ กฎเหล็กสำหรับ Teacher
        if ($myRole == 'teacher') {
            // ห้ามสร้าง Admin
            if ($role == 'admin') die("❌ ครูไม่สามารถสร้าง Admin ได้");
            // บังคับให้คนใหม่ต้องอยู่แผนกเดียวกับครูคนสร้างเสมอ
            $dept_id = $myDeptId; 
        }

        // กรณีเพิ่มใหม่ (INSERT)
        if (empty($edit_id)) {
            // เช็ค Username ซ้ำ
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                setFlash('error', 'Username นี้มีผู้ใช้งานแล้ว');
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // ถ้าเลือกเป็น Admin ให้เคลียร์ค่าแผนกเป็น NULL
                if ($role == 'admin') $dept_id = NULL;

                $sql = "INSERT INTO users (username, password, fullname, role, department_id) VALUES (?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([$username, $hashed_password, $fullname, $role, $dept_id]);
                setFlash('success', 'เพิ่มบุคลากรเรียบร้อย');
            }
        } 
        // กรณีแก้ไข (UPDATE)
        else {
            // Teacher เช็คสิทธิ์: ห้ามแก้คนนอกสาขา
            if ($myRole == 'teacher') {
                $check = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
                $check->execute([$edit_id]);
                $targetDept = $check->fetchColumn();
                // ถ้ามีสังกัดและไม่ตรงกับเรา ห้ามแก้
                if ($targetDept != 0 && $targetDept != $myDeptId) {
                    die("❌ คุณไม่มีสิทธิ์แก้ไขคนนอกสาขา");
                }
            }

            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET fullname=?, role=?, department_id=?, password=? WHERE id=?";
                $pdo->prepare($sql)->execute([$fullname, $role, $dept_id, $hashed_password, $edit_id]);
            } else {
                $sql = "UPDATE users SET fullname=?, role=?, department_id=? WHERE id=?";
                $pdo->prepare($sql)->execute([$fullname, $role, $dept_id, $edit_id]);
            }
            setFlash('success', 'แก้ไขข้อมูลเรียบร้อย');
        }
    }

    // B. ลบข้อมูล (Delete)
    elseif ($action == 'delete_user') {
        $del_id = $_POST['delete_id'];
        
        if ($del_id == $myId) {
            setFlash('error', 'ไม่สามารถลบตัวเองได้');
        } else {
            if ($myRole == 'teacher') {
                $check = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
                $check->execute([$del_id]);
                if ($check->fetchColumn() != $myDeptId) die("❌ ห้ามลบคนนอกสาขา");
            }
            
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$del_id]);
            setFlash('success', 'ลบผู้ใช้งานสำเร็จ');
        }
    }
    
    header("Location: users.php"); exit;
}

// --- Query ข้อมูลเพื่อแสดงผล ---

// 1. ดึงรายชื่อสาขา
$depts = $pdo->query("SELECT * FROM departments")->fetchAll(PDO::FETCH_ASSOC);
$deptNameMap = []; foreach($depts as $d) $deptNameMap[$d['id']] = $d['name'];
$myDeptName = $deptNameMap[$myDeptId] ?? '-';

// 2. ดึงรายชื่อ Users ตามสิทธิ์
if ($myRole == 'admin') {
    $sql = "SELECT u.*, d.name as dept_name FROM users u LEFT JOIN departments d ON u.department_id = d.id ORDER BY u.role, u.department_id";
    $users = $pdo->query($sql)->fetchAll();
} else {
    // Teacher เห็นเฉพาะคนในสาขาตัวเอง
    $sql = "SELECT u.*, d.name as dept_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.department_id = ? ORDER BY u.role";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$myDeptId]);
    $users = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการบุคลากร</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f4f6f9; font-family: 'Sarabun', sans-serif; min-height: 100vh; }
        .main-content { width: 100%; padding: 20px; overflow-y: auto; }
    </style>
</head>

<body class="d-flex">
    
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        
        <?php if (isset($_SESSION['flash'])): ?>
            <script>Swal.fire({icon: '<?= $_SESSION['flash']['type'] ?>', title: '<?= $_SESSION['flash']['message'] ?>', showConfirmButton: false, timer: 1500});</script>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded shadow-sm">
            <div>
                <h4 class="fw-bold m-0 text-dark"><i class="bi bi-people-fill text-primary"></i> จัดการบุคลากร</h4>
                <small class="text-muted">จัดการรายชื่อครูและนักเรียนในสังกัด</small>
            </div>
            <div class="text-end">
                 <div class="badge bg-light text-dark border p-2">
                    <i class="bi bi-person-circle"></i> <?= $myName ?> 
                    <span class="text-muted">|</span> 
                    <?= ($myRole=='admin') ? 'Admin' : 'Teacher' ?>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="m-0 fw-bold text-secondary">รายชื่อผู้ใช้งานทั้งหมด</h5>
                <button class="btn btn-primary btn-sm shadow-sm" onclick="openUserModal()">
                    <i class="bi bi-person-plus"></i> เพิ่มผู้ใช้งานใหม่
                </button>
            </div>
            <div class="card-body">
                <table id="userTable" class="table table-hover align-middle w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Username</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>บทบาท</th>
                            <th>สังกัดสาขา</th>
                            <th class="text-end">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="fw-bold text-primary"><?= $u['username'] ?></td>
                            <td><?= $u['fullname'] ?></td>
                            <td>
                                <?php 
                                    if($u['role']=='admin') echo '<span class="badge bg-danger">Admin (ผู้ดูแลสูงสุด)</span>';
                                    elseif($u['role']=='teacher') echo '<span class="badge bg-primary">Teacher (ครู/จนท.)</span>';
                                    else echo '<span class="badge bg-secondary">User (นักเรียน)</span>';
                                ?>
                            </td>
                            <td><?= $u['dept_name'] ?: '<span class="text-muted">-</span>' ?></td>
                            <td class="text-end">
                                <?php if ($u['id'] != $myId): ?>
                                    <button class="btn btn-sm btn-outline-warning" onclick='openUserModal(<?= json_encode($u) ?>)' title="แก้ไข"><i class="bi bi-pencil"></i></button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันลบผู้ใช้นี้?')">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" title="ลบ"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">(ตัวคุณเอง)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="modalTitle">ข้อมูลผู้ใช้งาน</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="save_user">
                        <input type="hidden" name="edit_id" id="edit_id">

                        <div class="mb-3">
                            <label>Username (สำหรับ Login) <span class="text-danger">*</span></label>
                            <input type="text" name="username" id="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Password</label>
                            <input type="text" name="password" id="password" class="form-control" placeholder="ตั้งรหัสผ่านใหม่...">
                            <small class="text-muted" id="pass_hint">กรณีแก้ไข: ถ้าไม่เปลี่ยนรหัส ให้เว้นว่างไว้</small>
                        </div>
                        <div class="mb-3">
                            <label>ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                            <input type="text" name="fullname" id="fullname" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="fw-bold text-primary">กำหนดบทบาท <span class="text-danger">*</span></label>
                            <select name="role" id="role" class="form-select border-primary" onchange="toggleDept()" required>
                                <option value="" disabled selected>-- กรุณาเลือก --</option>
                                <option value="teacher">🔵 Teacher - ครู/จนท. (จัดการพัสดุได้)</option>
                                <option value="user">⚪ User - นักเรียน (ดู/ยืมได้อย่างเดียว)</option>
                                
                                <?php if($myRole == 'admin'): ?>
                                    <option value="admin">🔴 Admin - ผู้ดูแลระบบสูงสุด</option>
                                <?php endif; ?>
                            </select>
                            <small class="text-muted">⚠️ ถ้าต้องการให้จัดการแผนกได้ ต้องเลือก <b>Teacher</b></small>
                        </div>

                        <div class="mb-3">
                            <label>สังกัดสาขา</label>
                            <?php if($myRole == 'admin'): ?>
                                <select name="department_id" id="department_id" class="form-select">
                                    <option value="">-- ไม่ระบุ (Admin) --</option>
                                    <?php foreach($depts as $d): ?>
                                        <option value="<?= $d['id'] ?>"><?= $d['name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="hidden" name="department_id" value="<?= $myDeptId ?>">
                                <input type="text" class="form-control bg-light" value="<?= $myDeptName ?>" readonly>
                                <small class="text-success"><i class="bi bi-lock-fill"></i> ระบบล็อกให้เป็นแผนกของคุณอัตโนมัติ</small>
                            <?php endif; ?>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() { $('#userTable').DataTable({language:{url:"//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json"}}); });

        function openUserModal(data = null) {
            if (data) {
                // โหมดแก้ไข
                $('#modalTitle').text('แก้ไขข้อมูล');
                $('#edit_id').val(data.id);
                $('#username').val(data.username).prop('readonly', true);
                $('#fullname').val(data.fullname);
                $('#role').val(data.role); // ดึงค่า Role เดิมมาแสดง
                $('#password').prop('required', false).attr('placeholder', 'เว้นว่างไว้หากใช้รหัสเดิม');
                $('#pass_hint').show();
                
                <?php if($myRole == 'admin'): ?>
                    $('#department_id').val(data.department_id);
                <?php endif; ?>
            } else {
                // โหมดเพิ่มใหม่
                $('#modalTitle').text('เพิ่มข้อมูลใหม่');
                $('#edit_id').val('');
                $('#username').val('').prop('readonly', false);
                $('#fullname').val('');
                $('#role').val(''); // ล้างค่า Role ให้เลือกเอง
                $('#password').prop('required', true).attr('placeholder', 'ตั้งรหัสผ่าน...');
                $('#pass_hint').hide();
                
                <?php if($myRole == 'admin'): ?>
                    $('#department_id').val('');
                <?php endif; ?>
            }
            toggleDept();
            new bootstrap.Modal(document.getElementById('userModal')).show();
        }

        function toggleDept() {
            let role = $('#role').val();
            let myRole = '<?= $myRole ?>';
            
            // ถ้าเป็น Admin -> ถ้าเลือกบทบาท admin ช่องสาขาจะปิด
            if (myRole === 'admin') {
                if (role === 'admin') {
                    $('#department_id').val('').prop('disabled', true);
                } else {
                    $('#department_id').prop('disabled', false);
                }
            }
        }
    </script>
</body>
</html>