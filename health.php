<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";
$today = date('Y-m-d');

// รับค่าตัวกรองช่วงเวลา และตัวชี้วัดของกราฟ (water, sleep, steps, exercise, reading)
$selectedPeriod = isset($_GET['period']) ? $_GET['period'] : 'all';
$chartMetric = isset($_GET['metric']) ? $_GET['metric'] : 'water';

// บันทึกหรืออัปเดตข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_health'])) {
    $water    = intval($_POST['water_intake_ml'] ?? 0);
    $sleep    = floatval($_POST['sleep_hours'] ?? 0);
    $steps    = intval($_POST['steps'] ?? 0);
    $weight   = !empty($_POST['weight_kg']) ? floatval($_POST['weight_kg']) : null;
    $exercise = intval($_POST['exercise_min'] ?? 0);
    $reading  = intval($_POST['reading_pages'] ?? 0);
    $mood     = trim($_POST['mood'] ?? 'happy');
    $log_date = !empty($_POST['log_date']) ? $_POST['log_date'] : $today;

    try {
        $checkStmt = $pdo->prepare("SELECT id FROM health_logs WHERE user_id = :uid AND log_date = :ldate");
        $checkStmt->execute([':uid' => $user_id, ':ldate' => $log_date]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare("
                UPDATE health_logs 
                SET water_intake_ml = :water, sleep_hours = :sleep, steps = :steps, 
                    weight_kg = :weight, exercise_min = :exercise, reading_pages = :reading, mood = :mood 
                WHERE id = :id
            ");
            $stmt->execute([
                ':water' => $water, ':sleep' => $sleep, ':steps' => $steps,
                ':weight' => $weight, ':exercise' => $exercise, ':reading' => $reading, 
                ':mood' => $mood, ':id' => $existing['id']
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO health_logs (user_id, water_intake_ml, sleep_hours, steps, weight_kg, exercise_min, reading_pages, mood, log_date) 
                VALUES (:uid, :water, :sleep, :steps, :weight, :exercise, :reading, :mood, :ldate)
            ");
            $stmt->execute([
                ':uid' => $user_id, ':water' => $water, ':sleep' => $sleep, ':steps' => $steps,
                ':weight' => $weight, ':exercise' => $exercise, ':reading' => $reading, 
                ':mood' => $mood, ':ldate' => $log_date
            ]);
        }
        $message = "บันทึกข้อมูลสำเร็จเรียบร้อย!";
    } catch (\PDOException $e) {
        $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// ลบรายการ
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = intval($_GET['id']);
    $delStmt = $pdo->prepare("DELETE FROM health_logs WHERE id = :id AND user_id = :uid");
    $delStmt->execute([':id' => $del_id, ':uid' => $user_id]);
    header("Location: health.php?period=" . urlencode($selectedPeriod) . "&metric=" . urlencode($chartMetric));
    exit;
}

// 1. ดึงเดือนสำหรับ Dropdown
$monthListStmt = $pdo->prepare("
    SELECT DISTINCT DATE_FORMAT(log_date, '%Y-%m') AS period 
    FROM health_logs 
    WHERE user_id = :uid 
    ORDER BY period DESC
");
$monthListStmt->execute([':uid' => $user_id]);
$availableMonths = $monthListStmt->fetchAll(PDO::FETCH_COLUMN);

// 2. เงื่อนไขช่วงเวลา SQL
$dateCondition = "";
$queryParams = [':uid' => $user_id];
$displayTitle = "ทั้งหมด (All Time)";

if ($selectedPeriod !== 'all' && preg_match('/^\d{4}-\d{2}$/', $selectedPeriod)) {
    list($currentYear, $currentMonth) = explode('-', $selectedPeriod);
    $dateCondition = " AND MONTH(log_date) = :m AND YEAR(log_date) = :y";
    $queryParams[':m'] = $currentMonth;
    $queryParams[':y'] = $currentYear;
    $displayTitle = date('F Y', strtotime($selectedPeriod . '-01'));
}

// 3. ข้อมูลกราฟ
$chartQuery = $pdo->prepare("
    SELECT log_date, water_intake_ml, sleep_hours, steps, exercise_min, reading_pages 
    FROM health_logs 
    WHERE user_id = :uid $dateCondition 
    ORDER BY log_date ASC 
    LIMIT 15
");
$chartQuery->execute($queryParams);
$chartLogs = $chartQuery->fetchAll(PDO::FETCH_ASSOC);

$chartLabels  = array_map(fn($i) => date('d/m', strtotime($i['log_date'])), $chartLogs);
$waterData    = array_map('intval', array_column($chartLogs, 'water_intake_ml'));
$sleepData    = array_map('floatval', array_column($chartLogs, 'sleep_hours'));
$stepsData    = array_map('intval', array_column($chartLogs, 'steps'));
$exerciseData = array_map('intval', array_column($chartLogs, 'exercise_min'));
$readingData  = array_map('intval', array_column($chartLogs, 'reading_pages'));

// 4. ดึงประวัติรายการทั้งหมด
$listStmt = $pdo->prepare("
    SELECT * FROM health_logs 
    WHERE user_id = :uid $dateCondition 
    ORDER BY log_date DESC
");
$listStmt->execute($queryParams);
$logs = $listStmt->fetchAll();

// แมปอารมณ์เป็น Emoji
$moodMap = [
    'great'     => '😄 ยอดเยี่ยม',
    'happy'     => '🙂 มีความสุข',
    'neutral'   => '😐 ปกติทั่วไป',
    'tired'     => '🥱 เหนื่อยล้า',
    'stressed'  => '😫 เครียด/กังวล'
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LifeTrack 360 - สุขภาพ & กิจวัตร</title>
  <link rel="stylesheet" href="assets/css/reset.css">
  <link rel="stylesheet" href="assets/css/base.css">
  <link rel="stylesheet" href="assets/css/layout.css">
  <link rel="stylesheet" href="assets/css/components.css">
  <style>
    .health-container { max-width: 980px; margin: 20px auto; padding: 0 15px; }
    .form-card, .list-card, .chart-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 25px; }
    .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 12px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px; }
    .form-group input, .form-group select { width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
    .btn-submit { padding: 9px 20px; background: #28a745; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
    .btn-submit:hover { background: #218838; }
    table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.data-table th, table.data-table td { border-bottom: 1px solid #eee; padding: 10px; text-align: left; font-size: 13px; }
    table.data-table th { background: #f8f9fa; }
    .btn-delete { padding: 4px 8px; border-radius: 4px; font-size: 12px; text-decoration: none; background: #dc3545; color: #fff; }
    
    .metric-tab {
      padding: 6px 12px;
      border: 1px solid #ccc;
      background: #f8f9fa;
      border-radius: 6px;
      text-decoration: none;
      color: #555;
      font-size: 12px;
      font-weight: 600;
    }
    .metric-tab.active-water    { background: #0ea5e9; color: #fff; border-color: #0ea5e9; }
    .metric-tab.active-sleep    { background: #6366f1; color: #fff; border-color: #6366f1; }
    .metric-tab.active-steps    { background: #10b981; color: #fff; border-color: #10b981; }
    .metric-tab.active-exercise { background: #f97316; color: #fff; border-color: #f97316; }
    .metric-tab.active-reading  { background: #8b5cf6; color: #fff; border-color: #8b5cf6; }
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
        <li><a href="tasks.php">งาน</a></li>
        <li><a href="health.php" class="active">สุขภาพ</a></li>
        <li><a href="logout.php" style="color: #dc3545;">ออกจากระบบ</a></li>
      </ul>
    </nav>
  </header>

  <main class="health-container">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">
      <div>
        <h2>บันทึกสุขภาพ & กิจวัตรประจำวัน</h2>
        <p style="color: #666;">สรุปข้อมูล: <strong><?php echo $displayTitle; ?></strong></p>
      </div>

      <form action="health.php" method="GET">
        <input type="hidden" name="metric" value="<?php echo htmlspecialchars($chartMetric); ?>">
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

    <!-- ส่วนแสดงผลกราฟสถิติ -->
    <div class="chart-card">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 15px;">
        <h3 style="font-size: 1.1rem; color: #333;">
          <?php 
            if ($chartMetric === 'sleep')        echo "สถิติการนอนหลับ (ชั่วโมง)";
            elseif ($chartMetric === 'steps')    echo "สถิติจำนวนก้าวเดิน (ก้าว)";
            elseif ($chartMetric === 'exercise') echo "สถิติออกกำลังกาย (นาที)";
            elseif ($chartMetric === 'reading')  echo "สถิติการอ่านหนังสือ (หน้า)";
            else                                 echo "สถิติปริมาณน้ำดื่ม (มิลลิลิตร)";
          ?>
        </h3>
        
        <div style="display: flex; gap: 4px; flex-wrap: wrap;">
          <a href="health.php?period=<?php echo urlencode($selectedPeriod); ?>&metric=water" class="metric-tab <?php echo $chartMetric === 'water' ? 'active-water' : ''; ?>">💧 น้ำ</a>
          <a href="health.php?period=<?php echo urlencode($selectedPeriod); ?>&metric=sleep" class="metric-tab <?php echo $chartMetric === 'sleep' ? 'active-sleep' : ''; ?>">🛌 นอน</a>
          <a href="health.php?period=<?php echo urlencode($selectedPeriod); ?>&metric=steps" class="metric-tab <?php echo $chartMetric === 'steps' ? 'active-steps' : ''; ?>">👟 ก้าว</a>
          <a href="health.php?period=<?php echo urlencode($selectedPeriod); ?>&metric=exercise" class="metric-tab <?php echo $chartMetric === 'exercise' ? 'active-exercise' : ''; ?>">🏃 ออกกำลังกาย</a>
          <a href="health.php?period=<?php echo urlencode($selectedPeriod); ?>&metric=reading" class="metric-tab <?php echo $chartMetric === 'reading' ? 'active-reading' : ''; ?>">📖 หนังสือ</a>
        </div>
      </div>

      <?php if (!empty($chartLabels)): ?>
        <div style="text-align: center;">
          <canvas id="healthBarCanvas" width="750" height="230" style="max-width: 100%; height: auto;"></canvas>
        </div>
      <?php else: ?>
        <p style="color: #888; text-align: center; padding: 25px 0;">ยังไม่มีข้อมูลบันทึกสำหรับช่วงเวลานี้</p>
      <?php endif; ?>
    </div>

    <!-- ฟอร์มบันทึกข้อมูลประจำวัน -->
    <div class="form-card">
      <h3 style="margin-bottom: 15px;">+ บันทึกกิจกรรม & สุขภาพประจำวัน</h3>
      <form action="health.php?period=<?php echo urlencode($selectedPeriod); ?>&metric=<?php echo urlencode($chartMetric); ?>" method="POST">
        <input type="hidden" name="save_health" value="1">
        
        <div class="form-row">
          <div class="form-group">
            <label for="log_date">วันที่บันทึก</label>
            <input type="date" name="log_date" id="log_date" value="<?php echo $today; ?>" required>
          </div>
          <div class="form-group">
            <label for="water_intake_ml">ดื่มน้ำ (มิลลิลิตร / ml)</label>
            <input type="number" name="water_intake_ml" id="water_intake_ml" min="0" step="50" placeholder="เป้าหมาย 2,000" required>
          </div>
          <div class="form-group">
            <label for="sleep_hours">นอนหลับ (ชั่วโมง)</label>
            <input type="number" name="sleep_hours" id="sleep_hours" min="0" max="24" step="0.5" placeholder="เป้าหมาย 8.0" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="steps">จำนวนก้าวเดิน (ก้าว)</label>
            <input type="number" name="steps" id="steps" min="0" step="100" placeholder="เช่น 6,000">
          </div>
          <div class="form-group">
            <label for="weight_kg">น้ำหนักตัว (กก. / kg)</label>
            <input type="number" name="weight_kg" id="weight_kg" min="20" max="250" step="0.1" placeholder="เช่น 65.5">
          </div>
          <div class="form-group">
            <label for="exercise_min">ออกกำลังกาย (นาที)</label>
            <input type="number" name="exercise_min" id="exercise_min" min="0" step="5" placeholder="เช่น 30 หรือ 45">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="reading_pages">อ่านหนังสือ (จำนวนหน้า)</label>
            <input type="number" name="reading_pages" id="reading_pages" min="0" step="1" placeholder="เช่น 15 หรือ 30 หน้า">
          </div>
          <div class="form-group">
            <label for="mood">อารมณ์ / ความรู้สึกวันนี้</label>
            <select name="mood" id="mood">
              <option value="great">😄 ยอดเยี่ยม (Great)</option>
              <option value="happy" selected>🙂 มีความสุข (Happy)</option>
              <option value="neutral">😐 ปกติทั่วไป (Neutral)</option>
              <option value="tired">🥱 เหนื่อยล้า (Tired)</option>
              <option value="stressed">😫 เครียด/กังวล (Stressed)</option>
            </select>
          </div>
        </div>

        <button type="submit" class="btn-submit">+ บันทึกข้อมูล</button>
      </form>
    </div>

    <!-- ตารางแสดงประวัติข้อมูล -->
    <div class="list-card">
      <h3>ประวัติบันทึกทั้งหมด (<?php echo count($logs); ?> รายการ)</h3>
      <?php if (count($logs) > 0): ?>
        <div style="overflow-x: auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th>วันที่</th>
                <th>อารมณ์</th>
                <th>น้ำ</th>
                <th>นอน</th>
                <th>ก้าวเดิน</th>
                <th>ออกกำลัง</th>
                <th>อ่านหนังสือ</th>
                <th>น้ำหนัก</th>
                <th style="text-align: right;">จัดการ</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($logs as $l): ?>
                <tr>
                  <td><?php echo date('d/m/Y', strtotime($l['log_date'])); ?></td>
                  <td><strong><?php echo $moodMap[$l['mood'] ?? 'happy'] ?? '🙂 มีความสุข'; ?></strong></td>
                  <td><?php echo number_format($l['water_intake_ml']); ?> ml</td>
                  <td><?php echo number_format($l['sleep_hours'], 1); ?> ชม.</td>
                  <td><?php echo !empty($l['steps']) ? number_format($l['steps']) : '-'; ?></td>
                  <td><?php echo !empty($l['exercise_min']) ? $l['exercise_min'] . ' น.' : '-'; ?></td>
                  <td><?php echo !empty($l['reading_pages']) ? $l['reading_pages'] . ' หน้า' : '-'; ?></td>
                  <td><?php echo !empty($l['weight_kg']) ? number_format($l['weight_kg'], 1) . ' kg' : '-'; ?></td>
                  <td style="text-align: right;">
                    <a href="health.php?action=delete&id=<?php echo $l['id']; ?>&period=<?php echo urlencode($selectedPeriod); ?>&metric=<?php echo urlencode($chartMetric); ?>" class="btn-delete" onclick="return confirm('ยืนยันลบรายการนี้หรือไม่?');">ลบ</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p style="color: #888; margin-top: 10px;">ยังไม่มีบันทึกข้อมูล</p>
      <?php endif; ?>
    </div>
  </main>

  <footer>
    <p>&copy; 2026 LifeTrack 360</p>
  </footer>

  <script src="assets/js/dom-controller.js" defer></script>
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const labels = <?php echo json_encode($chartLabels); ?>;
      const metric = '<?php echo $chartMetric; ?>';
      
      let data = [];
      let targetVal = 0;
      let targetText = '';
      let barColor = '#0ea5e9';
      let maxScale = 10;

      if (metric === 'sleep') {
        data = <?php echo json_encode($sleepData); ?>;
        targetVal = 8.0;
        targetText = 'เป้าหมาย 8 ชม.';
        barColor = '#6366f1';
        maxScale = Math.max(...data, 12);
      } else if (metric === 'steps') {
        data = <?php echo json_encode($stepsData); ?>;
        targetVal = 10000;
        targetText = 'เป้าหมาย 10,000 ก้าว';
        barColor = '#10b981';
        maxScale = Math.max(...data, 12000);
      } else if (metric === 'exercise') {
        data = <?php echo json_encode($exerciseData); ?>;
        targetVal = 30;
        targetText = 'เป้าหมาย 30 นาที';
        barColor = '#f97316';
        maxScale = Math.max(...data, 60);
      } else if (metric === 'reading') {
        data = <?php echo json_encode($readingData); ?>;
        targetVal = 20;
        targetText = 'เป้าหมาย 20 หน้า';
        barColor = '#8b5cf6';
        maxScale = Math.max(...data, 40);
      } else {
        data = <?php echo json_encode($waterData); ?>;
        targetVal = 2000;
        targetText = 'เป้าหมาย 2,000 ml';
        barColor = '#0ea5e9';
        maxScale = Math.max(...data, 2500);
      }

      const canvas = document.getElementById('healthBarCanvas');
      if (canvas && data.length > 0) {
        const ctx = canvas.getContext('2d');
        const chartHeight = 150;
        const chartWidth = canvas.width - 60;
        const barWidth = Math.min(36, (chartWidth / data.length) - 16);
        const startX = 40;
        const baseY = 185;

        // วาดเส้นประเป้าหมาย
        if (targetVal > 0 && targetVal <= maxScale) {
          const targetY = baseY - ((targetVal / maxScale) * chartHeight);
          ctx.strokeStyle = '#ef4444';
          ctx.setLineDash([4, 4]);
          ctx.beginPath();
          ctx.moveTo(startX, targetY);
          ctx.lineTo(canvas.width - 20, targetY);
          ctx.stroke();
          ctx.setLineDash([]);
          ctx.fillStyle = '#ef4444';
          ctx.font = '11px sans-serif';
          ctx.fillText(targetText, canvas.width - 130, targetY - 5);
        }

        // วาดแท่งกราฟ
        data.forEach((val, i) => {
          const x = startX + i * (chartWidth / data.length) + 10;
          const barH = (val / maxScale) * chartHeight;
          const y = baseY - barH;

          ctx.fillStyle = (targetVal > 0 && val >= targetVal) ? '#10b981' : barColor;
          ctx.beginPath();
          ctx.roundRect ? ctx.roundRect(x, y, barWidth, barH, [4, 4, 0, 0]) : ctx.fillRect(x, y, barWidth, barH);
          ctx.fill();

          // ตัวเลขค่าด้านบน
          ctx.fillStyle = '#374151';
          ctx.font = 'bold 11px sans-serif';
          ctx.textAlign = 'center';
          ctx.fillText(val, x + (barWidth / 2), y - 6);

          // ป้ายกำกับแกนวันที่
          ctx.fillStyle = '#6b7280';
          ctx.font = '11px sans-serif';
          ctx.fillText(labels[i], x + (barWidth / 2), baseY + 18);
        });

        // เส้นแกนระนาบ X
        ctx.strokeStyle = '#e5e7eb';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(startX, baseY);
        ctx.lineTo(canvas.width - 20, baseY);
        ctx.stroke();
      }
    });
  </script>
</body>
</html>