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
$today = date('Y-m-d');

// รับค่าเดือนและปีที่เลือกดู (ถ้าไม่มีให้ใช้เดือน-ปีปัจจุบัน)
$selectedPeriod = isset($_GET['period']) ? $_GET['period'] : date('Y-m');
list($currentYear, $currentMonth) = explode('-', $selectedPeriod);

// ==================== 1. ข้อมูลภาพรวมวันนี้ ====================
$incomeStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM finance_transactions WHERE user_id = :uid AND type = 'income'");
$incomeStmt->execute([':uid' => $user_id]);
$totalIncome = (float)$incomeStmt->fetch()['total'];

$expenseStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM finance_transactions WHERE user_id = :uid AND type = 'expense'");
$expenseStmt->execute([':uid' => $user_id]);
$totalExpense = (float)$expenseStmt->fetch()['total'];
$balance = $totalIncome - $totalExpense;

$taskStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM tasks WHERE user_id = :uid AND status != 'completed'");
$taskStmt->execute([':uid' => $user_id]);
$pendingTasks = $taskStmt->fetch()['total'] ?? 0;

$scheduleStmt = $pdo->prepare("SELECT * FROM schedules WHERE user_id = :uid AND end_time >= NOW() ORDER BY start_time ASC LIMIT 1");
$scheduleStmt->execute([':uid' => $user_id]);
$nextSchedule = $scheduleStmt->fetch();

$healthStmt = $pdo->prepare("SELECT water_intake_ml, sleep_hours FROM health_logs WHERE user_id = :uid AND log_date = :today");
$healthStmt->execute([':uid' => $user_id, ':today' => $today]);
$todayHealth = $healthStmt->fetch();

$waterToday = $todayHealth ? (int)$todayHealth['water_intake_ml'] : 0;
$sleepToday = $todayHealth ? (float)$todayHealth['sleep_hours'] : 0;
$waterTarget = 2000;
$waterPercent = min(100, round(($waterToday / $waterTarget) * 100));

// ==================== 2. สถิติตามเดือนที่เลือก ====================
// ดึงรายการเดือนทั้งหมดที่มีข้อมูลจากตารางการเงินเพื่อสร้าง Dropdown
$monthListStmt = $pdo->prepare("
    SELECT DISTINCT DATE_FORMAT(transaction_date, '%Y-%m') AS period 
    FROM finance_transactions 
    WHERE user_id = :uid 
    ORDER BY period DESC
");
$monthListStmt->execute([':uid' => $user_id]);
$availableMonths = $monthListStmt->fetchAll(PDO::FETCH_COLUMN);

// ถ้าเดือนปัจจุบันไม่มีในรายการ ให้เพิ่มเข้าไปด้วย
if (!in_array(date('Y-m'), $availableMonths)) {
    array_unshift($availableMonths, date('Y-m'));
}

// การเงินตามเดือนที่เลือก
$mFinanceStmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS m_income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS m_expense
    FROM finance_transactions 
    WHERE user_id = :uid AND MONTH(transaction_date) = :m AND YEAR(transaction_date) = :y
");
$mFinanceStmt->execute([':uid' => $user_id, ':m' => $currentMonth, ':y' => $currentYear]);
$mFinance = $mFinanceStmt->fetch();

// งานที่ทำเสร็จตามเดือนที่เลือก
$mTaskStmt = $pdo->prepare("
    SELECT COUNT(*) AS completed_count 
    FROM tasks 
    WHERE user_id = :uid AND status = 'completed' AND MONTH(created_at) = :m AND YEAR(created_at) = :y
");
$mTaskStmt->execute([':uid' => $user_id, ':m' => $currentMonth, ':y' => $currentYear]);
$mTaskDone = $mTaskStmt->fetch()['completed_count'] ?? 0;

// นัดหมายตามเดือนที่เลือก
$mSchedStmt = $pdo->prepare("
    SELECT COUNT(*) AS total_schedules 
    FROM schedules 
    WHERE user_id = :uid AND MONTH(start_time) = :m AND YEAR(start_time) = :y
");
$mSchedStmt->execute([':uid' => $user_id, ':m' => $currentMonth, ':y' => $currentYear]);
$mSchedTotal = $mSchedStmt->fetch()['total_schedules'] ?? 0;

// ค่าเฉลี่ยสุขภาพตามเดือนที่เลือก
$mHealthStmt = $pdo->prepare("
    SELECT 
        COALESCE(AVG(water_intake_ml), 0) AS avg_water,
        COALESCE(AVG(sleep_hours), 0) AS avg_sleep
    FROM health_logs 
    WHERE user_id = :uid AND MONTH(log_date) = :m AND YEAR(log_date) = :y
");
$mHealthStmt->execute([':uid' => $user_id, ':m' => $currentMonth, ':y' => $currentYear]);
$mHealth = $mHealthStmt->fetch();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LifeTrack 360 - Dashboard</title>
  
  <link rel="stylesheet" href="assets/css/reset.css">
  <link rel="stylesheet" href="assets/css/base.css">
  <link rel="stylesheet" href="assets/css/layout.css">
  <link rel="stylesheet" href="assets/css/components.css">
</head>
<body>

  <header>
    <div style="display: flex; justify-content: space-between; align-items: center;">
      <h1>LifeTrack 360</h1>
      <button id="menu-toggle-btn" class="btn btn-outline" style="padding: 0.25rem 0.5rem; cursor: pointer;">☰</button>
    </div>
    <nav id="main-nav">
      <ul>
        <li><a href="index.php" class="active">แดชบอร์ด</a></li>
        <li><a href="finance.php">การเงิน</a></li>
        <li><a href="schedules.php">ตารางเวลา</a></li>
        <li><a href="tasks.php">งาน</a></li>
        <li><a href="health.php">สุขภาพ</a></li>
        <li><a href="logout.php" style="color: #dc3545;">ออกจากระบบ</a></li>
      </ul>
    </nav>
  </header>

  <main>
    <!-- แถวที่ 1: ภาพรวมวันนี้ -->
    <section>
      <h2>ภาพรวมวันนี้</h2>
      <p>ยินดีต้อนรับคุณ <strong><?php echo $username; ?></strong> สู่ระบบจัดการชีวิตประจำวัน</p>
    </section>

    <section>
      <article id="finance-card">
        <h3>การเงิน <span class="badge badge-success">ปกติ</span></h3>
        <p class="stat-value">฿<?php echo number_format($balance, 2); ?></p>
      </article>

      <article id="task-card">
        <h3>งานที่ต้องทำ <span class="badge badge-warning">รอทำ</span></h3>
        <p class="stat-value"><?php echo $pendingTasks; ?> งาน</p>
      </article>

      <article id="schedule-card">
        <h3>ตารางเวลา</h3>
        <?php if ($nextSchedule): ?>
          <p class="stat-value" style="font-size: 1.1rem;"><?php echo htmlspecialchars($nextSchedule['title']); ?></p>
          <small style="color: #666;"><?php echo date('d/m H:i', strtotime($nextSchedule['start_time'])); ?> - <?php echo date('H:i', strtotime($nextSchedule['end_time'])); ?></small>
        <?php else: ?>
          <p class="stat-value">ยังไม่มีรายการ</p>
        <?php endif; ?>
      </article>

      <article id="health-card">
        <h3>สุขภาพ <span class="badge badge-success"><?php echo $waterPercent; ?>%</span></h3>
        <p class="stat-value"><?php echo number_format($waterToday); ?> / 2,000 ml</p>
        <div class="progress-container" style="background: #e9ecef; border-radius: 4px; height: 8px; margin: 8px 0; overflow: hidden;">
          <div class="progress-fill" style="background: #28a745; height: 100%; width: <?php echo $waterPercent; ?>%; transition: width 0.3s ease;"></div>
        </div>
        <small style="color: #666;">นอนหลับ: <?php echo number_format($sleepToday, 1); ?> ชม.</small>
      </article>
    </section>

    <!-- แถวที่ 2: สถิติสรุปตามเดือน พร้อม Dropdown เลือกเดือน -->
    <section style="margin-top: 2rem; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 10px;">
      <div>
        <h2>สรุปสถิติประจำเดือน</h2>
        <p style="color: #6b7280; font-size: 0.95rem;">ผลการดำเนินงานในเดือน <strong><?php echo date('F Y', strtotime($selectedPeriod . '-01')); ?></strong></p>
      </div>
      
      <!-- ฟอร์มเลือกเดือน -->
      <form action="index.php" method="GET" style="margin-bottom: 5px;">
        <label for="period" style="font-size: 0.9rem; font-weight: bold; margin-right: 5px;">เลือกเดือน:</label>
        <select name="period" id="period" onchange="this.form.submit()" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #ccc; background: #fff; cursor: pointer;">
          <?php foreach ($availableMonths as $p): ?>
            <option value="<?php echo $p; ?>" <?php echo ($p === $selectedPeriod) ? 'selected' : ''; ?>>
              <?php echo date('F Y', strtotime($p . '-01')); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </section>

    <section>
      <article>
        <h3>กระแสเงินสดเดือนนี้</h3>
        <p style="font-size: 1rem; color: #28a745; margin-bottom: 4px;">รับ: +฿<?php echo number_format($mFinance['m_income'], 2); ?></p>
        <p style="font-size: 1rem; color: #dc3545;">จ่าย: -฿<?php echo number_format($mFinance['m_expense'], 2); ?></p>
      </article>

      <article>
        <h3>งานที่ปิดจ๊อบแล้ว</h3>
        <p class="stat-value" style="color: #28a745;"><?php echo $mTaskDone; ?> งาน</p>
        <small style="color: #666;">งานที่สถานะเสร็จสมบูรณ์</small>
      </article>

      <article>
        <h3>นัดหมายในเดือนนี้</h3>
        <p class="stat-value" style="color: #17a2b8;"><?php echo $mSchedTotal; ?> รายการ</p>
        <small style="color: #666;">กิจกรรม/คลาสเรียนทั้งหมด</small>
      </article>

      <article>
        <h3>วินัยสุขภาพเฉลี่ย</h3>
        <p style="font-size: 1rem; color: #333; margin-bottom: 4px;">💧 น้ำ: <?php echo number_format($mHealth['avg_water']); ?> ml/วัน</p>
        <p style="font-size: 1rem; color: #333;">🛌 นอน: <?php echo number_format($mHealth['avg_sleep'], 1); ?> ชม./วัน</p>
      </article>
    </section>
  </main>

  <footer>
    <p>&copy; 2026 LifeTrack 360</p>
  </footer>

  <script src="assets/js/dom-controller.js" defer></script>
</body>
</html>