<?php
session_start();
require_once 'config/database.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";

// รับค่าตัวกรอง: period (ช่วงเวลา) และ view_status (upcoming = ยังไม่จบ, past = จบแล้ว, all = ทั้งหมด)
$selectedPeriod = isset($_GET['period']) ? $_GET['period'] : 'all';
$viewStatus = isset($_GET['view']) ? $_GET['view'] : 'upcoming';

// ลบกำหนดการ
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = intval($_GET['id']);
    $delStmt = $pdo->prepare("DELETE FROM schedules WHERE id = :id AND user_id = :uid");
    $delStmt->execute([':id' => $del_id, ':uid' => $user_id]);
    header("Location: schedules.php?period=" . urlencode($selectedPeriod) . "&view=" . urlencode($viewStatus));
    exit;
}

// เพิ่มกำหนดการใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
    $title = trim($_POST['title']);
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $location = trim($_POST['location']);

    if (!empty($title) && !empty($start_time) && !empty($end_time)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO schedules (user_id, title, start_time, end_time, location) VALUES (:uid, :title, :start_time, :end_time, :location)");
            $stmt->execute([
                ':uid' => $user_id,
                ':title' => $title,
                ':start_time' => $start_time,
                ':end_time' => $end_time,
                ':location' => $location
            ]);
            $message = "บันทึกกำหนดการสำเร็จ!";
        } catch (\PDOException $e) {
            $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    } else {
        $message = "กรุณากรอกข้อมูลให้ครบถ้วน";
    }
}

// 1. ดึงรายการเดือนสำหรับ Dropdown
$monthListStmt = $pdo->prepare("
    SELECT DISTINCT DATE_FORMAT(start_time, '%Y-%m') AS period 
    FROM schedules 
    WHERE user_id = :uid 
    ORDER BY period DESC
");
$monthListStmt->execute([':uid' => $user_id]);
$availableMonths = $monthListStmt->fetchAll(PDO::FETCH_COLUMN);

// 2. เงื่อนไขช่วงเวลาเดือน
$dateCondition = "";
$queryParams = [':uid' => $user_id];
$displayTitle = "ทั้งหมด (All Time)";

if ($selectedPeriod !== 'all' && preg_match('/^\d{4}-\d{2}$/', $selectedPeriod)) {
    list($currentYear, $currentMonth) = explode('-', $selectedPeriod);
    $dateCondition = " AND MONTH(start_time) = :m AND YEAR(start_time) = :y";
    $queryParams[':m'] = $currentMonth;
    $queryParams[':y'] = $currentYear;
    $displayTitle = date('F Y', strtotime($selectedPeriod . '-01'));
}

// 3. กราฟสรุปความหนาแน่นรายสัปดาห์ (นับตามตัวกรองเดือน)
$dowStmt = $pdo->prepare("
    SELECT DAYOFWEEK(start_time) AS dow, COUNT(*) AS total
    FROM schedules
    WHERE user_id = :uid $dateCondition
    GROUP BY DAYOFWEEK(start_time)
");
$dowStmt->execute($queryParams);
$dowRaw = $dowStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$dayNames = ['จันทร์', 'อังคาร', 'พุธ', 'พฤหัสฯ', 'ศุกร์', 'เสาร์', 'อาทิตย์'];
$dayMapping = [2, 3, 4, 5, 6, 7, 1];
$scheduleDayCounts = [];
foreach ($dayMapping as $dowIndex) {
    $scheduleDayCounts[] = isset($dowRaw[$dowIndex]) ? (int)$dowRaw[$dowIndex] : 0;
}

// 4. กรองรายการตารางเวลาตามสถานะเวลาปัจจุบัน
$statusCondition = "";
if ($viewStatus === 'upcoming') {
    $statusCondition = " AND end_time >= NOW()";
} elseif ($viewStatus === 'past') {
    $statusCondition = " AND end_time < NOW()";
}

$scheduleStmt = $pdo->prepare("
    SELECT * FROM schedules 
    WHERE user_id = :uid $dateCondition $statusCondition 
    ORDER BY start_time ASC
");
$scheduleStmt->execute($queryParams);
$schedules = $scheduleStmt->fetchAll();
$totalEvents = count($schedules);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LifeTrack 360 - ตารางเวลา</title>
  <link rel="stylesheet" href="assets/css/reset.css">
  <link rel="stylesheet" href="assets/css/base.css">
  <link rel="stylesheet" href="assets/css/layout.css">
  <link rel="stylesheet" href="assets/css/components.css">
  <style>
    .schedule-container { max-width: 960px; margin: 20px auto; padding: 0 15px; }
    .form-card, .list-card, .chart-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 25px; }
    .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px; }
    .form-group input { width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
    .btn-submit { padding: 9px 20px; background: #2563eb; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; transition: background 0.2s ease; }
    .btn-submit:hover { background: #1d4ed8; }
    table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.data-table th, table.data-table td { border-bottom: 1px solid #eee; padding: 10px; text-align: left; font-size: 14px; }
    table.data-table th { background: #f8f9fa; }
    .btn-delete { padding: 4px 8px; border-radius: 4px; font-size: 12px; text-decoration: none; background: #dc3545; color: #fff; }
    
    .chart-layout {
      display: grid;
      grid-template-columns: 1fr 260px;
      gap: 20px;
      align-items: center;
      margin-top: 15px;
    }
    .view-btn {
      padding: 5px 12px;
      border-radius: 6px;
      border: 1px solid #d1d5db;
      background: #f9fafb;
      color: #4b5563;
      text-decoration: none;
      font-size: 13px;
      font-weight: 500;
      transition: all 0.15s ease;
    }
    .view-btn.active {
      background: #2563eb;
      color: #fff;
      border-color: #2563eb;
      font-weight: 600;
    }
    @media (max-width: 768px) {
      .chart-layout { grid-template-columns: 1fr; }
    }
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
        <li><a href="schedules.php" class="active">ตารางเวลา</a></li>
        <li><a href="tasks.php">งาน</a></li>
        <li><a href="health.php">สุขภาพ</a></li>
        <li><a href="logout.php" style="color: #dc3545;">ออกจากระบบ</a></li>
      </ul>
    </nav>
  </header>

  <main class="schedule-container">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">
      <div>
        <h2>จัดการตารางเวลาและนัดหมาย</h2>
        <p style="color: #666;">สรุปกิจกรรม: <strong><?php echo $displayTitle; ?></strong></p>
      </div>

      <form action="schedules.php" method="GET">
        <input type="hidden" name="view" value="<?php echo htmlspecialchars($viewStatus); ?>">
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

    <!-- กล่องกราฟสถิติตารางเวลา -->
    <div class="chart-card">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-size: 1.1rem; color: #333;">ความหนาแน่นกิจกรรมรายสัปดาห์ (<?php echo $displayTitle; ?>)</h3>
      </div>

      <div class="chart-layout">
        <div style="text-align: center;">
          <canvas id="scheduleBarCanvas" width="550" height="200" style="max-width: 100%; height: auto;"></canvas>
        </div>
        <div>
          <table style="width: 100%; border-collapse: collapse;">
            <thead>
              <tr style="border-bottom: 2px solid #f3f4f6; color: #6b7280; font-size: 13px;">
                <th style="padding: 5px 0; text-align: left;">วัน</th>
                <th style="padding: 5px 0; text-align: right;">จำนวนกิจกรรม</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($dayNames as $i => $name): ?>
                <tr style="border-bottom: 1px solid #f9fafb; font-size: 13px;">
                  <td style="padding: 6px 0; color: #4b5563;"><?php echo $name; ?></td>
                  <td style="padding: 6px 0; text-align: right; font-weight: bold; color: <?php echo $scheduleDayCounts[$i] > 0 ? '#2563eb' : '#9ca3af'; ?>;">
                    <?php echo $scheduleDayCounts[$i]; ?> รายการ
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ฟอร์มเพิ่มกิจกรรม -->
    <div class="form-card">
      <h3 style="margin-bottom: 15px;">+ เพิ่มกิจกรรมใหม่</h3>
      <form action="schedules.php?period=<?php echo urlencode($selectedPeriod); ?>&view=<?php echo urlencode($viewStatus); ?>" method="POST">
        <input type="hidden" name="add_schedule" value="1">
        <div class="form-row">
          <div class="form-group" style="grid-column: span 2;">
            <label for="title">ชื่อกิจกรรม / นัดหมาย</label>
            <input type="text" name="title" id="title" placeholder="เช่น คลาสเรียน Software Testing, ประชุมโปรเจกต์" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="start_time">เวลาเริ่ม</label>
            <input type="datetime-local" name="start_time" id="start_time" required>
          </div>
          <div class="form-group">
            <label for="end_time">เวลาสิ้นสุด</label>
            <input type="datetime-local" name="end_time" id="end_time" required>
          </div>
        </div>
        <div class="form-group" style="margin-bottom: 15px;">
          <label for="location">สถานที่ / ลิงก์ห้องประชุม (ไม่บังคับ)</label>
          <input type="text" name="location" id="location" placeholder="เช่น ตึกวิทย์ 78-618/2 หรือ Zoom">
        </div>
        <button type="submit" class="btn-submit">+ บันทึกตารางเวลา</button>
      </form>
    </div>

    <!-- ตารางรายการกิจกรรม พร้อมปุ่มกรองซ่อนกิจกรรมที่จบแล้ว -->
    <div class="list-card">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
        <h3 style="margin: 0;">
          <?php 
            if ($viewStatus === 'upcoming') echo "กิจกรรมที่กำลังจะมาถึง (" . $totalEvents . " รายการ)";
            elseif ($viewStatus === 'past') echo "ประวัติกิจกรรมที่สิ้นสุดแล้ว (" . $totalEvents . " รายการ)";
            else echo "กำหนดการทั้งหมด (" . $totalEvents . " รายการ)";
          ?>
        </h3>
        
        <!-- ตัวเลือกสถานะ -->
        <div style="display: flex; gap: 6px;">
          <a href="schedules.php?period=<?php echo urlencode($selectedPeriod); ?>&view=upcoming" class="view-btn <?php echo $viewStatus === 'upcoming' ? 'active' : ''; ?>">ที่ต้องทำ (Upcoming)</a>
          <a href="schedules.php?period=<?php echo urlencode($selectedPeriod); ?>&view=past" class="view-btn <?php echo $viewStatus === 'past' ? 'active' : ''; ?>">จบแล้ว (Past)</a>
          <a href="schedules.php?period=<?php echo urlencode($selectedPeriod); ?>&view=all" class="view-btn <?php echo $viewStatus === 'all' ? 'active' : ''; ?>">ทั้งหมด</a>
        </div>
      </div>

      <?php if ($totalEvents > 0): ?>
        <table class="data-table">
          <thead>
            <tr>
              <th>กิจกรรม</th>
              <th>เริ่ม</th>
              <th>สิ้นสุด</th>
              <th>สถานที่</th>
              <th style="text-align: right;">จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($schedules as $s): ?>
              <?php $isPast = strtotime($s['end_time']) < time(); ?>
              <tr style="<?php echo $isPast ? 'opacity: 0.65;' : ''; ?>">
                <td>
                  <strong><?php echo htmlspecialchars($s['title']); ?></strong>
                  <?php if ($isPast): ?>
                    <span style="font-size: 11px; background: #e5e7eb; color: #4b5563; padding: 2px 6px; border-radius: 4px; margin-left: 6px;">สิ้นสุดแล้ว</span>
                  <?php endif; ?>
                </td>
                <td><?php echo date('d/m/Y H:i', strtotime($s['start_time'])); ?></td>
                <td><?php echo date('d/m/Y H:i', strtotime($s['end_time'])); ?></td>
                <td><?php echo !empty($s['location']) ? htmlspecialchars($s['location']) : '-'; ?></td>
                <td style="text-align: right;">
                  <a href="schedules.php?action=delete&id=<?php echo $s['id']; ?>&period=<?php echo urlencode($selectedPeriod); ?>&view=<?php echo urlencode($viewStatus); ?>" class="btn-delete" onclick="return confirm('ยืนยันลบกำหนดการนี้หรือไม่?');">ลบ</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="color: #888; margin-top: 15px;">
          <?php echo $viewStatus === 'upcoming' ? 'ไม่มีกิจกรรมที่ต้องทำในขณะนี้' : 'ไม่พบข้อมูลกำหนดการ'; ?>
        </p>
      <?php endif; ?>
    </div>
  </main>

  <footer>
    <p>&copy; 2026 LifeTrack 360</p>
  </footer>

  <script src="assets/js/dom-controller.js" defer></script>
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const labels = <?php echo json_encode($dayNames); ?>;
      const data = <?php echo json_encode($scheduleDayCounts); ?>;
      const canvas = document.getElementById('scheduleBarCanvas');

      if (canvas && data.length > 0) {
        const ctx = canvas.getContext('2d');
        const maxVal = Math.max(...data, 4);
        const chartHeight = 130;
        const paddingLeft = 30;
        const paddingRight = 20;
        const chartWidth = canvas.width - paddingLeft - paddingRight;
        const stepWidth = chartWidth / data.length;
        const barWidth = 32;
        const baseY = 160;

        data.forEach((val, i) => {
          const centerX = paddingLeft + (i * stepWidth) + (stepWidth / 2);
          const x = centerX - (barWidth / 2);
          const barH = val > 0 ? (val / maxVal) * chartHeight : 4;
          const y = baseY - barH;

          ctx.fillStyle = val > 0 ? '#2563eb' : '#e5e7eb';
          ctx.beginPath();
          ctx.roundRect ? ctx.roundRect(x, y, barWidth, barH, [4, 4, 0, 0]) : ctx.fillRect(x, y, barWidth, barH);
          ctx.fill();

          if (val > 0) {
            ctx.fillStyle = '#1d4ed8';
            ctx.font = 'bold 11px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(val, centerX, y - 6);
          }

          ctx.fillStyle = '#4b5563';
          ctx.font = '11px sans-serif';
          ctx.textAlign = 'center';
          ctx.fillText(labels[i], centerX, baseY + 18);
        });

        ctx.strokeStyle = '#e5e7eb';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(paddingLeft - 10, baseY);
        ctx.lineTo(canvas.width - paddingRight + 10, baseY);
        ctx.stroke();
      }
    });
  </script>
</body>
</html>