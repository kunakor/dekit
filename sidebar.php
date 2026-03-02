<?php
// ตรวจสอบการเชื่อมต่อ Database
if (!isset($pdo)) { require_once 'db.php'; }

// หาชื่อไฟล์ปัจจุบัน เพื่อทำปุ่ม Active (ไฮไลท์เมนู)
$current_page = basename($_SERVER['PHP_SELF']);

// ดึงข้อมูลผู้ใช้งานจาก Session
// var_dump($_SESSION["role"]);
$user_id = $_SESSION['user_id'] ?? 0;
$user_name = $_SESSION['fullname'] ?? 'ผู้ใช้งาน';
$user_role = $_SESSION['role'] ?? 'user';
$user_dept_id = $_SESSION['dept_id'] ?? 0;
$dept_name_display = "";

// ถ้าเป็นครู ให้ไปดึงชื่อสาขามาโชว์
if ($user_role == 'teacher' && $user_dept_id != 0) {
    $stmt_dept = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
    $stmt_dept->execute([$user_dept_id]);
    $dept_name_display = $stmt_dept->fetchColumn();
}

// ถ้าเป็น Admin ให้ดึงข้อมูลแผนกทั้งหมดมาแสดงในเมนู
$all_departments = [];
if ($user_role == 'admin') {
    $all_departments = $pdo->query("SELECT id, name, code FROM departments ORDER BY name ASC")->fetchAll();
}
?>

<div class="d-flex flex-column flex-shrink-0 p-3 text-white bg-dark shadow" id="sidebar-wrapper">
    
    <a href="index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
        <i class="bi bi-box-seam fs-3 me-2 text-primary"></i>
        <span class="fs-4 fw-bold">ระบบพัสดุ</span>
    </a>
    
    <hr>
    
    <div class="user-profile mb-3 p-3 rounded" style="background: rgba(255,255,255,0.05); border-left: 4px solid #0d6efd;">
        <div class="d-flex align-items-center">
            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2 text-white fw-bold shadow-sm" style="width: 40px; height: 40px; flex-shrink: 0;">
                <?= mb_substr($user_name, 0, 1) ?>
            </div>
            
            <div style="line-height: 1.2; overflow: hidden;">
                <strong class="d-block text-truncate" title="<?= $user_name ?>"><?= $user_name ?></strong>
                
                <small class="text-info" style="font-size: 0.8rem;">
                    <?php 
                        if($user_role == 'admin') echo '<i class="bi bi-shield-check"></i> ผู้ดูแลระบบ';
                        elseif($user_role == 'teacher') echo '<i class="bi bi-person-workspace"></i> ครู/จนท.';
                        else echo '<i class="bi bi-person"></i> นักเรียน';
                    ?>
                </small>
            </div>
        </div>
        
        <?php if($user_role == 'teacher' && !empty($dept_name_display)): ?>
            <div class="mt-2 pt-2 border-top border-secondary">
                <span class="badge bg-success text-wrap text-start w-100 fw-normal">
                    <i class="bi bi-building"></i> <?= $dept_name_display ?>
                </span>
            </div>
        <?php endif; ?>
    </div>

    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a href="index.php" class="nav-link text-white <?= ($current_page == 'index.php') ? 'active' : '' ?>">
                <i class="bi bi-speedometer2 me-2"></i> ภาพรวม (Dashboard)
            </a>
        </li>

        <li>
            <a href="assets.php" class="nav-link text-white <?= ($current_page == 'assets.php') ? 'active' : '' ?>">
                <i class="bi bi-box-seam me-2"></i> รายการครุภัณฑ์รวม
            </a>
        </li>
        
        <?php if($user_role == 'admin' || $user_role == 'teacher'): ?>
            <hr class="text-secondary my-2">
            <li class="nav-header text-uppercase text-secondary px-3 py-1 mb-1" style="font-size: 0.75rem;">
                เมนูจัดการ
            </li>
            
            <li>
                <a href="users.php" class="nav-link text-white <?= ($current_page == 'users.php') ? 'active' : '' ?>">
                    <i class="bi bi-people me-2"></i> จัดการบุคลากร
                </a>
            </li>
            
            <?php if($user_role == 'admin'): ?>
                <!-- เมนูสำหรับ Admin: เห็นทุกแผนก -->
                <li class="nav-item">
                    <a class="nav-link text-warning d-flex justify-content-between align-items-center" 
                       data-bs-toggle="collapse" 
                       href="#deptCollapse" 
                       role="button"
                       aria-expanded="<?= ($current_page == 'department_view.php') ? 'true' : 'false' ?>">
                        <span><i class="bi bi-buildings me-2"></i> จัดการรายสาขา</span>
                        <i class="bi bi-chevron-down small"></i>
                    </a>
                    <div class="collapse <?= ($current_page == 'department_view.php') ? 'show' : '' ?> ms-3" id="deptCollapse">
                        <ul class="nav nav-pills flex-column mt-1" style="font-size: 0.85rem;">
                            <?php foreach($all_departments as $dept): ?>
                                <li>
                                    <a href="department_view.php?id=<?= $dept['id'] ?>" class="nav-link text-white py-1 <?= (isset($_GET['id']) && $_GET['id'] == $dept['id'] && $current_page == 'department_view.php') ? 'active bg-warning text-dark' : '' ?>">
                                        <i class="bi bi-arrow-right-short"></i> <?= $dept['name'] ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </li>
            <?php elseif($user_role == 'teacher'): ?>
                <!-- เมนูสำหรับ Teacher: เห็นแค่สาขาตัวเอง -->
                <li>
                    <a href="department_view.php?id=<?= $user_dept_id ?>" class="nav-link text-warning <?= ($current_page == 'department_view.php') ? 'active bg-warning text-dark' : '' ?>">
                        <i class="bi bi-house-door-fill me-2"></i> จัดการแผนกของฉัน
                    </a>
                </li>
            <?php endif; ?>

            <li>
                <a href="report.php" class="nav-link text-white <?= ($current_page == 'report.php') ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-bar-graph me-2"></i> รายงาน (Report)
                </a>
            </li>
        <?php endif; ?>
    </ul>
    
    <hr>
    
    <div>
        <a href="logout.php" class="nav-link text-danger bg-dark border border-danger rounded text-center hover-logout">
            <i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ
        </a>
    </div>
</div>

<style>
    /* 1. บังคับให้หน้าเว็บจัดเรียงแนวนอน (Sidebar ซ้าย, เนื้อหา ขวา) */
    body {
        display: flex !important;
        flex-direction: row !important;
        min-height: 100vh;
        margin: 0;
        overflow-x: hidden;
    }

    /* 2. ล็อกขนาด Sidebar ให้คงที่ */
    #sidebar-wrapper {
        width: 280px;
        min-width: 280px;
        height: 100vh;
        position: sticky;
        top: 0;
        overflow-y: auto;
    }

    /* 3. สั่งให้เนื้อหาด้านขวา (div ถัดจาก sidebar) ขยายเต็มที่ */
    #sidebar-wrapper + div, 
    .main-content, 
    .container-fluid {
        flex-grow: 1;
        width: 100%;
        padding: 20px;
        overflow-y: auto;
    }

    /* ตกแต่งปุ่ม Logout */
    .hover-logout:hover {
        background-color: #dc3545 !important;
        color: white !important;
    }

    /* หมุนลูกศรเมื่อกางเมนู */
    [data-bs-toggle="collapse"][aria-expanded="true"] .bi-chevron-down {
        transform: rotate(180deg);
        transition: transform 0.3s;
    }
    .bi-chevron-down {
        transition: transform 0.3s;
    }
</style>