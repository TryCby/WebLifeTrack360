<?php
session_start();
require_once 'config/database.php';

// ตรวจสอบสถานะการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);
$message = "";

// รับค่าตัวกรองช่วงเวลา (all หรือ YYYY-MM)
$selectedPeriod = isset($_GET['period']) ? $_GET['period'] : 'all';

// จัดการการเปลี่ยนสถานะงาน (เสร็จสิ้น หรือ ลบ)
if (isset($_GET['action']) && isset($_GET['task_id'])) {
    $task_id = intval($_GET['task_id']);
    $action = $_GET['action'];

    if ($action === 'complete') {
        $stmt = $pdo->prepare("UPDATE tasks SET status = 'completed' WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $task_id, ':uid' => $user_id]);
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $task_id, ':uid' => $user_id]);
    }
    header("Location: tasks.php?period=" . urlencode($selectedPeriod));
    exit;
}

// บันทึกงานใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $priority = $_POST['priority'] ?? 'medium';
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

    if (!empty($title)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, description, priority, due_date, status) VALUES (:uid, :title, :description, :priority, :due_date, 'pending')");
            $stmt->execute([
                ':uid' => $user_id,
                ':title' => $title,
                ':description' => $description,
                ':priority' => $priority,
                ':due_date' => $due_date
            ]);
            $message = "เพิ่มงานใหม่สำเร็จแล้ว!";
        } catch (\PDOException $e) {
            $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    } else {
        $message = "กรุณากรอกชื่องาน";
    }
}

// 1. ดึงรายการเดือนจากทั้ง created_at และ due_date เพื่อทำ Dropdown
$monthListStmt = $pdo->prepare("
    SELECT DISTINCT period FROM (
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS period FROM tasks WHERE user_id = :uid1
        UNION
        SELECT DATE_FORMAT(due_date, '%Y-%m') AS period FROM tasks WHERE user_id = :uid2 AND due_date IS NOT NULL
    ) AS combined_months
    ORDER BY period DESC
");
$monthListStmt->execute([':uid1' => $user_id, ':uid2' => $user_id]);
$availableMonths = $monthListStmt->fetchAll(PDO::FETCH_COLUMN);

// 2. กำหนดเงื่อนไข SQL ตามช่วงเวลา (เช็คทั้ง created_at และ due_date)
$dateCondition = "";
$queryParams = [':uid' => $user_id];
$displayTitle = "ทั้งหมด (All Time)";

if ($selectedPeriod !== 'all' && preg_match('/^\d{4}-\d{2}$/', $selectedPeriod)) {
    list($currentYear, $currentMonth) = explode('-', $selectedPeriod);
    $dateCondition = " AND (
        (MONTH(created_at) = :m1 AND YEAR(created_at) = :y1) 
        OR (due_date IS NOT NULL AND MONTH(due_date) = :m2 AND YEAR(due_date) = :y2)
    )";
    $queryParams[':m1'] = $currentMonth;
    $queryParams[':y1'] = $currentYear;
    $queryParams[':m2'] = $currentMonth;
    $queryParams[':y2'] = $currentYear;
    $displayTitle = date('F Y', strtotime($selectedPeriod . '-01'));
}

// 3. สรุปสัดส่วนงานตามช่วงเวลา
$statStmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) AS completed,
        COALESCE(SUM(CASE WHEN status != 'completed' THEN 1 ELSE 0 END), 0) AS pending
    FROM tasks 
    WHERE user_id = :uid $dateCondition
");
$statStmt->execute($queryParams);
$taskStats = $statStmt->fetch();

$totalTasks = (int)$taskStats['total'];
$completedCount = (int)$taskStats['completed'];
$pendingCount = (int)$taskStats['pending'];
$completedPercent = $totalTasks > 0 ? round(($completedCount / $totalTasks) * 100) : 0;

// 4. ดึงรายการงานตามช่วงเวลา
$taskStmt = $pdo->prepare("
    SELECT * FROM tasks 
    WHERE user_id = :uid $dateCondition 
    ORDER BY status = 'completed' ASC, due_date ASC, id DESC
");
$taskStmt->execute($queryParams);
$tasks = $taskStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LifeTrack 360 - จัดการงาน</title>
  <link rel="stylesheet" href="assets/css/reset.css">
  <link rel="stylesheet" href="assets/css/base.css">
  <link rel="stylesheet" href="assets/css/layout.css">
  <link rel="stylesheet" href="assets/css/components.css">
  <style>
    .tasks-container { max-width: 960px; margin: 20px auto; padding: 0 15px; }
    .form-card, .list-card, .stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 25px; }
    .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-family: inherit; }
    .btn-submit { padding: 9px 20px; background: #ffc107; color: #000; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
    .btn-submit:hover { background: #e0a800; }
    table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.data-table th, table.data-table td { border-bottom: 1px solid #eee; padding: 10px; text-align: left; font-size: 14px; }
    table.data-table th { background: #f8f9fa; }
    .badge-p-high { background: #ffebee; color: #c62828; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
    .badge-p-medium { background: #fff8e1; color: #f57f17; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
    .badge-p-low { background: #e8f5e9; color: #2e7d32; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
    .task-done { text-decoration: line-through; color: #888; }
    .btn-action { padding: 4px 8px; border-radius: 4px; font-size: 12px; text-decoration: none; margin-right: 5px; display: inline-block; }
    .btn-done { background: #28a745; color: #fff; }
    .btn-delete { background: #dc3545; color: #fff; }
  </style>
</head>
<body>

  <header>
    <div style="display: flex; justify-content: space-between; align-items: center;">
      <h1>LifeTrack 360</h1>
      <button id="menu-toggle-btn" class="btn btn-outline" style="padding: 0.25rem 0.5rem; cursor: pointer;">☰</button>
    </div>
    <nav id="main-nav">
      <ul>
        <li><a href="index.php">แดชบอร์ด</a></li>
        <li><a href="finance.php">การเงิน</a></li>
        <li><a href="schedules.php">ตารางเวลา</a></li>
        <li><a href="tasks.php" class="active">งาน</a></li>
        <li><a href="health.php">สุขภาพ</a></li>
        <li><a href="logout.php" style="color: #dc3545;">ออกจากระบบ</a></li>
      </ul>
    </nav>
  </header>

  <main class="tasks-container">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">
      <div>
        <h2>จัดการงานที่ต้องทำ (Tasks)</h2>
        <p style="color: #666;">สรุปงาน: <strong><?php echo $displayTitle; ?></strong></p>
      </div>

      <form action="tasks.php" method="GET">
        <label for="period" style="font-size: 0.9rem; font-weight: bold; margin-right: 5px;">ช่วงเวลา:</label>
        <select name="period" id="period" onchange="this.form.submit()" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #ccc; background: #fff; cursor: pointer;">
          <option value="all" <?php echo ($selectedPeriod === 'all') ? 'selected' : ''; ?>>ทั้งหมด (All Time)</option>
          <?php foreach ($availableMonths as $p): ?>
            <option value="<?php echo $p; ?>" <?php echo ($p === $selectedPeriod) ? 'selected' : ''; ?>>
              <?php echo date('F Y', strtotime($p . '-01')); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <?php if (!empty($message)): ?>
      <div style="padding: 10px; background: #e8f4fd; color: #0c5460; border-radius: 5px; margin-bottom: 15px;">
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>

    <div class="stat-card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <h3 style="font-size: 1.1rem; color: #333;">ความคืบหน้าของงาน (<?php echo $displayTitle; ?>)</h3>
        <span style="font-weight: bold; color: #28a745;"><?php echo $completedPercent; ?>% เสร็จสมบูรณ์</span>
      </div>
      
      <?php if ($totalTasks > 0): ?>
        <div style="width: 100%; height: 18px; background: #f3f4f6; border-radius: 9px; overflow: hidden; display: flex;">
          <div style="width: <?php echo $completedPercent; ?>%; background: #28a745; transition: width 0.4s ease;"></div>
          <div style="width: <?php echo 100 - $completedPercent; ?>%; background: #f59e0b; transition: width 0.4s ease;"></div>
        </div>
        <div style="display: flex; gap: 20px; margin-top: 12px; font-size: 13px;">
          <span><strong style="color: #28a745;">✔ เสร็จแล้ว:</strong> <?php echo $completedCount; ?> งาน</span>
          <span><strong style="color: #f59e0b;">⏳ รอทำ:</strong> <?php echo $pendingCount; ?> งาน</span>
          <span><strong>รวม:</strong> <?php echo $totalTasks; ?> งาน</span>
        </div>
      <?php else: ?>
        <p style="color: #888; font-size: 14px; margin: 10px 0 0 0;">ไม่มีรายการงานในช่วงเวลานี้</p>
      <?php endif; ?>
    </div>

    <div class="form-card">
      <h3 style="margin-bottom: 15px;">+ เพิ่มงานใหม่</h3>
      <form action="tasks.php?period=<?php echo urlencode($selectedPeriod); ?>" method="POST">
        <input type="hidden" name="add_task" value="1">
        <div class="form-row">
          <div class="form-group" style="grid-column: span 2;">
            <label for="title">ชื่องาน</label>
            <input type="text" name="title" id="title" placeholder="เช่น ทำการบ้านวิชา Database, ส่งโปรเจกต์" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="priority">ระดับความสำคัญ</label>
            <select name="priority" id="priority">
              <option value="low">ต่ำ (Low)</option>
              <option value="medium" selected>ปานกลาง (Medium)</option>
              <option value="high">ด่วน / สูง (High)</option>
            </select>
          </div>
          <div class="form-group">
            <label for="due_date">กำหนดส่ง (Due Date)</label>
            <input type="date" name="due_date" id="due_date">
          </div>
        </div>
        <div class="form-group" style="margin-bottom: 15px;">
          <label for="description">รายละเอียดงาน (ไม่บังคับ)</label>
          <textarea name="description" id="description" rows="2" placeholder="รายละเอียดเนื้อหางาน..."></textarea>
        </div>
        <button type="submit" class="btn-submit">เพิ่มงาน</button>
      </form>
    </div>

    <div class="list-card">
      <h3>รายการงาน (<?php echo count($tasks); ?> รายการ)</h3>
      <?php if (count($tasks) > 0): ?>
        <table class="data-table">
          <thead>
            <tr>
              <th>สถานะ</th>
              <th>ชื่องาน</th>
              <th>ความสำคัญ</th>
              <th>กำหนดส่ง</th>
              <th style="text-align: right;">จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tasks as $t): ?>
              <tr>
                <td>
                  <?php if ($t['status'] === 'completed'): ?>
                    <span style="color: #28a745; font-weight: bold;">✔ เสร็จแล้ว</span>
                  <?php else: ?>
                    <span style="color: #f57f17; font-weight: bold;">⏳ กำลังทำ</span>
                  <?php endif; ?>
                </td>
                <td class="<?php echo $t['status'] === 'completed' ? 'task-done' : ''; ?>">
                  <strong><?php echo htmlspecialchars($t['title']); ?></strong>
                  <?php if (!empty($t['description'])): ?>
                    <br><small style="color: #666;"><?php echo htmlspecialchars($t['description']); ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($t['priority'] === 'high'): ?>
                    <span class="badge-p-high">สูง</span>
                  <?php elseif ($t['priority'] === 'low'): ?>
                    <span class="badge-p-low">ต่ำ</span>
                  <?php else: ?>
                    <span class="badge-p-medium">ปานกลาง</span>
                  <?php endif; ?>
                </td>
                <td><?php echo !empty($t['due_date']) ? htmlspecialchars($t['due_date']) : '-'; ?></td>
                <td style="text-align: right;">
                  <?php if ($t['status'] !== 'completed'): ?>
                    <a href="tasks.php?action=complete&task_id=<?php echo $t['id']; ?>&period=<?php echo urlencode($selectedPeriod); ?>" class="btn-action btn-done">เสร็จ</a>
                  <?php endif; ?>
                  <a href="tasks.php?action=delete&task_id=<?php echo $t['id']; ?>&period=<?php echo urlencode($selectedPeriod); ?>" class="btn-action btn-delete" onclick="return confirm('ยืนยันที่จะลบงานนี้หรือไม่?');">ลบ</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="color: #888; margin-top: 10px;">ไม่มีรายการงานในช่วงเวลานี้</p>
      <?php endif; ?>
    </div>
  </main>

  <footer>
    <p>&copy; 2026 LifeTrack 360</p>
  </footer>

  <script src="assets/js/dom-controller.js" defer></script>
</body>
</html>