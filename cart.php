<?php
require 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }

// --- จัดการ Action (ลบจากตะกร้า / ยืนยันการยืม) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];

    // 1. ลบรายการออกจากตะกร้า
    if ($action == 'remove') {
        $remove_id = $_POST['asset_id'];
        // ค้นหาและลบ ID ออกจาก Array Session
        if (($key = array_search($remove_id, $_SESSION['cart'])) !== false) {
            unset($_SESSION['cart'][$key]);
        }
        setFlash('success', 'ลบรายการออกแล้ว');
    }
    
    // 2. ยืนยันการยืม (Checkout)
    elseif ($action == 'checkout') {
        if (empty($_SESSION['cart'])) {
            setFlash('error', 'ไม่มีสินค้าในตะกร้า');
        } else {
            try {
                $pdo->beginTransaction();
                $borrowed_count = 0;
                $user_id = $_SESSION['user_id'];
                $user_name = $_SESSION['fullname'];

                // วน Loop ยืมทีละชิ้น
                foreach ($_SESSION['cart'] as $asset_id) {
                    // เช็คว่าของยังว่างอยู่ไหม (เผื่อมีคนอื่นชิงตัดหน้าตอนเราเลือกอยู่)
                    $stmt = $pdo->prepare("SELECT status FROM assets WHERE id = ? FOR UPDATE");
                    $stmt->execute([$asset_id]);
                    $status = $stmt->fetchColumn();

                    if ($status === 'available') {
                        // อัปเดตสถานะ
                        $pdo->prepare("UPDATE assets SET status = 'in_use', borrowed_by = ?, current_user_id = ? WHERE id = ?")
                            ->execute([$user_name, $user_id, $asset_id]);
                        
                        // บันทึก Log
                        $pdo->prepare("INSERT INTO borrow_logs (asset_id, user_id, action) VALUES (?, ?, 'borrow')")
                            ->execute([$asset_id, $user_id]);
                        
                        $borrowed_count++;
                    }
                }

                $pdo->commit();
                
                // เคลียร์ตะกร้า
                $_SESSION['cart'] = [];
                
                setFlash('success', "ยืมสำเร็จทั้งหมด $borrowed_count รายการ");
                header("Location: assets.php"); // กลับไปหน้าหลัก
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                setFlash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
            }
        }
    }
    
    // Refresh หน้า
    header("Location: cart.php");
    exit;
}

// --- ดึงข้อมูลของในตะกร้ามาแสดง ---
$cart_items = [];
if (!empty($_SESSION['cart'])) {
    // แปลง Array ID เป็น String เช่น "1,2,5" เพื่อใช้ใน SQL IN (...)
    $ids = implode(',', $_SESSION['cart']);
    $sql = "SELECT * FROM assets WHERE id IN ($ids)";
    $cart_items = $pdo->query($sql)->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ตะกร้าการยืม</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-light">

    <div class="container mt-5">
        
        <?php if (isset($_SESSION['flash'])): ?>
            <script>
                Swal.fire({ icon: '<?= $_SESSION['flash']['type'] ?>', title: '<?= $_SESSION['flash']['message'] ?>', showConfirmButton: false, timer: 1500 });
            </script>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-warning text-dark">
                        <h4 class="mb-0"><i class="bi bi-cart-fill"></i> ตะกร้าของที่จะยืม</h4>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>รหัส</th>
                                    <th>ชื่อครุภัณฑ์</th>
                                    <th>หมวดหมู่</th>
                                    <th class="text-end">ลบ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cart_items)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5 text-muted">
                                            <i class="bi bi-cart-x display-4"></i><br>
                                            ไม่มีรายการในตะกร้า
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($cart_items as $item): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary"><?= $item['asset_code'] ?></span></td>
                                        <td><?= $item['asset_name'] ?></td>
                                        <td><?= $item['category'] ?></td>
                                        <td class="text-end">
                                            <form method="POST">
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="asset_id" value="<?= $item['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-between p-3">
                        <a href="assets.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> เลือกของเพิ่ม
                        </a>
                        
                        <?php if (!empty($cart_items)): ?>
                            <form method="POST" onsubmit="return confirm('ยืนยันการยืมทั้งหมด <?= count($cart_items) ?> รายการ?');">
                                <input type="hidden" name="action" value="checkout">
                                <button type="submit" class="btn btn-success btn-lg shadow">
                                    <i class="bi bi-check-circle-fill"></i> ยืนยันการยืมทั้งหมด
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</body>
</html>