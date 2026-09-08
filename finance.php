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

// รับค่าตัวกรองช่วงเวลาและประเภทกราฟ (all_flow = ภาพรวมรับ vs จ่าย, expense, income)
$selectedPeriod = isset($_GET['period']) ? $_GET['period'] : 'all';
$chartType = isset($_GET['chart_type']) ? $_GET['chart_type'] : 'all_flow';

// บันทึกข้อมูลเมื่อกดส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_transaction'])) {
    $type = $_POST['type'];
    $amount = floatval($_POST['amount']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    $transaction_date = !empty($_POST['transaction_date']) ? $_POST['transaction_date'] : date('Y-m-d');

    if ($amount > 0 && !empty($category) && in_array($type, ['income', 'expense'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO finance_transactions (user_id, type, amount, category, description, transaction_date) VALUES (:user_id, :type, :amount, :category, :description, :transaction_date)");
            $stmt->execute([
                ':user_id' => $user_id,
                ':type' => $type,
                ':amount' => $amount,
                ':category' => $category,
                ':description' => $description,
                ':transaction_date' => $transaction_date
            ]);
            $message = "บันทึกรายการสำเร็จแล้ว!";
        } catch (\PDOException $e) {
            $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    } else {
        $message = "กรุณากรอกข้อมูลให้ถูกต้องครบถ้วน";
    }
}

// 1. ดึงรายการเดือนทั้งหมดที่มีข้อมูลมาทำ Dropdown
$monthListStmt = $pdo->prepare("
    SELECT DISTINCT DATE_FORMAT(transaction_date, '%Y-%m') AS period 
    FROM finance_transactions 
    WHERE user_id = :uid 
    ORDER BY period DESC
");
$monthListStmt->execute([':uid' => $user_id]);
$availableMonths = $monthListStmt->fetchAll(PDO::FETCH_COLUMN);

// 2. สร้างเงื่อนไข SQL ตามตัวเลือกช่วงเวลา
$dateCondition = "";
$queryParams = [':uid' => $user_id];
$displayTitle = "ทั้งหมด (All Time)";

if ($selectedPeriod !== 'all' && preg_match('/^\d{4}-\d{2}$/', $selectedPeriod)) {
    list($currentYear, $currentMonth) = explode('-', $selectedPeriod);
    $dateCondition = " AND MONTH(transaction_date) = :m AND YEAR(transaction_date) = :y";
    $queryParams[':m'] = $currentMonth;
    $queryParams[':y'] = $currentYear;
    $displayTitle = date('F Y', strtotime($selectedPeriod . '-01'));
}

// 3. คำนวณยอดรวมรายรับ รายจ่าย
$incomeStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM finance_transactions WHERE user_id = :uid AND type = 'income' $dateCondition");
$incomeStmt->execute($queryParams);
$totalIncome = (float)$incomeStmt->fetch()['total'];

$expenseStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM finance_transactions WHERE user_id = :uid AND type = 'expense' $dateCondition");
$expenseStmt->execute($queryParams);
$totalExpense = (float)$expenseStmt->fetch()['total'];

$balance = $totalIncome - $totalExpense;

// 4. จัดเตรียมข้อมูลสำหรับกราฟ Donut ตามแท็บที่เลือก
$chartLabels = [];
$chartTotals = [];
$chartColors = [];

if ($chartType === 'all_flow') {
    // โหมดภาพรวมรวมกัน: รายรับ vs รายจ่าย
    if ($totalIncome > 0 || $totalExpense > 0) {
        $chartLabels = ['รายรับรวม', 'รายจ่ายรวม'];
        $chartTotals = [$totalIncome, $totalExpense];
        $chartColors = ['#10b981', '#ef4444'];
    }
} else {
    // โหมดเจาะลึกเฉพาะหมวดหมู่
    $chartQueryParams = array_merge($queryParams, [':chart_type' => $chartType]);
    $catStmt = $pdo->prepare("
        SELECT category, SUM(amount) AS total 
        FROM finance_transactions 
        WHERE user_id = :uid AND type = :chart_type $dateCondition
        GROUP BY category
        ORDER BY total DESC
    ");
    $catStmt->execute($chartQueryParams);
    $categoriesData = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    $chartLabels = array_column($categoriesData, 'category');
    $chartTotals = array_map('floatval', array_column($categoriesData, 'total'));
    $chartColors = $chartType === 'income' 
        ? ['#10b981', '#06b6d4', '#3b82f6', '#84cc16', '#14b8a6'] 
        : ['#ef4444', '#f97316', '#f59e0b', '#8b5cf6', '#ec4899', '#6366f1'];
}

// 5. ดึงรายการธุรกรรม
$listStmt = $pdo->prepare("
    SELECT * FROM finance_transactions 
    WHERE user_id = :uid $dateCondition 
    ORDER BY transaction_date DESC, id DESC
");
$listStmt->execute($queryParams);
$transactions = $listStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LifeTrack 360 - ระบบการเงิน</title>
  <link rel="stylesheet" href="assets/css/reset.css">
  <link rel="stylesheet" href="assets/css/base.css">
  <link rel="stylesheet" href="assets/css/layout.css">
  <link rel="stylesheet" href="assets/css/components.css">
  <style>
    .finance-container { max-width: 960px; margin: 20px auto; padding: 0 15px; }
    .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .summary-box { background: #fff; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .summary-box h4 { margin-bottom: 5px; color: #555; }
    .summary-box .amount { font-size: 1.5rem; font-weight: bold; }
    .text-income { color: #28a745; }
    .text-expense { color: #dc3545; }
    .text-balance { color: #007bff; }
    .form-card, .table-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 25px; }
    .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 12px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px; }
    .form-group input, .form-group select { width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
    .btn-submit { padding: 9px 20px; background: #007bff; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
    .btn-submit:hover { background: #0056b3; }
    table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.data-table th, table.data-table td { border-bottom: 1px solid #eee; padding: 10px; text-align: left; font-size: 14px; }
    table.data-table th { background: #f8f9fa; }
    
    .chart-layout {
      display: grid;
      grid-template-columns: 320px 1fr;
      gap: 20px;
      align-items: center;
      margin-top: 15px;
    }
    .tab-btn {
      padding: 6px 14px;
      border: 1px solid #ccc;
      background: #f8f9fa;
      border-radius: 6px;
      text-decoration: none;
      color: #555;
      font-size: 13px;
      font-weight: 600;
    }
    .tab-btn.active-all {
      background: #0284c7;
      color: #fff;
      border-color: #0284c7;
    }
    .tab-btn.active-expense {
      background: #dc3545;
      color: #fff;
      border-color: #dc3545;
    }
    .tab-btn.active-income {
      background: #28a745;
      color: #fff;
      border-color: #28a745;
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
        <li><a href="finance.php" class="active">การเงิน</a></li>
        <li><a href="schedules.php">ตารางเวลา</a></li>
        <li><a href="tasks.php">งาน</a></li>
        <li><a href="health.php">สุขภาพ</a></li>
        <li><a href="logout.php" style="color: #dc3545;">ออกจากระบบ</a></li>
      </ul>
    </nav>
  </header>

  <main class="finance-container">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">
      <div>
        <h2>จัดการการเงิน</h2>
        <p style="color: #666;">สรุปรายการเงิน: <strong><?php echo $displayTitle; ?></strong></p>
      </div>

      <form action="finance.php" method="GET">
        <input type="hidden" name="chart_type" value="<?php echo $chartType; ?>">
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

    <div class="summary-grid">
      <div class="summary-box">
        <h4>รายรับ</h4>
        <div class="amount text-income">+฿<?php echo number_format($totalIncome, 2); ?></div>
      </div>
      <div class="summary-box">
        <h4>รายจ่าย</h4>
        <div class="amount text-expense">-฿<?php echo number_format($totalExpense, 2); ?></div>
      </div>
      <div class="summary-box">
        <h4>คงเหลือ</h4>
        <div class="amount text-balance">฿<?php echo number_format($balance, 2); ?></div>
      </div>
    </div>

    <!-- กล่องกราฟ Donut พร้อม 3 แท็บตัวเลือก -->
    <div class="form-card">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <h3 style="font-size: 1.1rem; color: #333;">
          <?php if ($chartType === 'all_flow'): ?>
            สัดส่วนกระแสเงินสด: <span style="color: #0284c7;">ภาพรวม (รับ vs จ่าย)</span>
          <?php elseif ($chartType === 'expense'): ?>
            สัดส่วนหมวดหมู่: <span style="color: #dc3545;">รายจ่าย</span>
          <?php else: ?>
            สัดส่วนหมวดหมู่: <span style="color: #28a745;">รายรับ</span>
          <?php endif; ?>
          (<?php echo $displayTitle; ?>)
        </h3>
        <div style="display: flex; gap: 6px;">
          <a href="finance.php?period=<?php echo $selectedPeriod; ?>&chart_type=all_flow" class="tab-btn <?php echo $chartType === 'all_flow' ? 'active-all' : ''; ?>">ภาพรวม (รับ/จ่าย)</a>
          <a href="finance.php?period=<?php echo $selectedPeriod; ?>&chart_type=expense" class="tab-btn <?php echo $chartType === 'expense' ? 'active-expense' : ''; ?>">รายจ่าย</a>
          <a href="finance.php?period=<?php echo $selectedPeriod; ?>&chart_type=income" class="tab-btn <?php echo $chartType === 'income' ? 'active-income' : ''; ?>">รายรับ</a>
        </div>
      </div>
      
      <?php if (!empty($chartTotals) && array_sum($chartTotals) > 0): ?>
        <div class="chart-layout">
          <div style="text-align: center;">
            <canvas id="expenseDonutCanvas" width="240" height="240"></canvas>
          </div>
          <div>
            <table style="width: 100%; border-collapse: collapse;">
              <thead>
                <tr style="border-bottom: 2px solid #f3f4f6; color: #6b7280; font-size: 13px;">
                  <th style="padding: 6px 0; text-align: left;">รายการ</th>
                  <th style="padding: 6px 0; text-align: right;">จำนวนเงิน</th>
                  <th style="padding: 6px 0; text-align: right;">สัดส่วน</th>
                </tr>
              </thead>
              <tbody id="chart-table-body"></tbody>
            </table>
          </div>
        </div>
      <?php else: ?>
        <p style="color: #888; text-align: center; padding: 25px 0;">ไม่มีข้อมูลธุรกรรมในช่วงเวลานี้</p>
      <?php endif; ?>
    </div>

    <!-- ฟอร์มบันทึกข้อมูล -->
    <div class="form-card">
      <h3 style="margin-bottom: 15px;">+ บันทึกรายการใหม่</h3>
      <form action="finance.php?period=<?php echo $selectedPeriod; ?>&chart_type=<?php echo $chartType; ?>" method="POST">
        <input type="hidden" name="add_transaction" value="1">
        <div class="form-row">
          <div class="form-group">
            <label for="type">ประเภท</label>
            <select name="type" id="type" onchange="updateCategorySuggestions()" required>
              <option value="expense">รายจ่าย</option>
              <option value="income">รายรับ</option>
            </select>
          </div>
          <div class="form-group">
            <label for="category">หมวดหมู่</label>
            <input type="text" name="category" id="category" list="category-list" placeholder="เช่น อาหาร, เงินเดือน, เดินทาง" required>
            <datalist id="category-list"></datalist>
          </div>
          <div class="form-group">
            <label for="amount">จำนวนเงิน (บาท)</label>
            <input type="number" step="0.01" min="0.01" name="amount" id="amount" placeholder="0.00" required>
          </div>
          <div class="form-group">
            <label for="transaction_date">วันที่</label>
            <input type="date" name="transaction_date" id="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
        </div>
        <div class="form-group" style="margin-bottom: 15px;">
          <label for="description">รายละเอียดเพิ่มเติม (ไม่บังคับ)</label>
          <input type="text" name="description" id="description" placeholder="รายละเอียด เช่น ค่าข้าวเที่ยง">
        </div>
        <button type="submit" class="btn-submit">+ บันทึกรายการ</button>
      </form>
    </div>

    <div class="table-card">
      <h3>รายการธุรกรรม (<?php echo count($transactions); ?> รายการ)</h3>
      <?php if (count($transactions) > 0): ?>
        <table class="data-table">
          <thead>
            <tr>
              <th>วันที่</th>
              <th>หมวดหมู่</th>
              <th>รายละเอียด</th>
              <th>ประเภท</th>
              <th style="text-align: right;">จำนวนเงิน</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($transactions as $row): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['transaction_date']); ?></td>
                <td><?php echo htmlspecialchars($row['category']); ?></td>
                <td><?php echo htmlspecialchars($row['description'] ?? '-'); ?></td>
                <td>
                  <?php if ($row['type'] === 'income'): ?>
                    <span style="color: #28a745; font-weight: bold;">รายรับ</span>
                  <?php else: ?>
                    <span style="color: #dc3545; font-weight: bold;">รายจ่าย</span>
                  <?php endif; ?>
                </td>
                <td style="text-align: right; font-weight: bold; color: <?php echo $row['type'] === 'income' ? '#28a745' : '#dc3545'; ?>;">
                  <?php echo ($row['type'] === 'income' ? '+' : '-') . number_format($row['amount'], 2); ?> ฿
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="color: #888; margin-top: 10px;">ยังไม่มีรายการ</p>
      <?php endif; ?>
    </div>
  </main>

  <footer>
    <p>&copy; 2026 LifeTrack 360</p>
  </footer>

  <script src="assets/js/dom-controller.js" defer></script>
  <script>
    function updateCategorySuggestions() {
      const type = document.getElementById('type').value;
      const datalist = document.getElementById('category-list');
      datalist.innerHTML = '';

      const incomeSuggestions = ['เงินเดือน', 'โบนัส', 'ขายของออนไลน์', 'เงินปันผล', 'รับจ้างทั่วไป'];
      const expenseSuggestions = ['อาหารและเครื่องดื่ม', 'เดินทาง/ค่าน้ำมัน', 'ที่พัก/ค่าเช่า', 'ค่าน้ำค่าไฟ', 'ของใช้ส่วนตัว', 'ความบันเทิง'];

      const activeList = type === 'income' ? incomeSuggestions : expenseSuggestions;
      activeList.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item;
        datalist.appendChild(opt);
      });
    }

    document.addEventListener("DOMContentLoaded", () => {
      updateCategorySuggestions();

      const labels = <?php echo json_encode($chartLabels); ?>;
      const values = <?php echo json_encode($chartTotals); ?>;
      const colors = <?php echo json_encode($chartColors); ?>;
      const currentChartType = '<?php echo $chartType; ?>';

      const canvas = document.getElementById('expenseDonutCanvas');
      const tableBody = document.getElementById('chart-table-body');

      if (canvas && values.length > 0) {
        const ctx = canvas.getContext('2d');
        const total = values.reduce((a, b) => a + b, 0);
        let startAngle = -0.5 * Math.PI;

        values.forEach((val, i) => {
          const sliceAngle = (val / total) * 2 * Math.PI;
          ctx.beginPath();
          ctx.arc(120, 120, 100, startAngle, startAngle + sliceAngle);
          ctx.lineTo(120, 120);
          ctx.fillStyle = colors[i % colors.length];
          ctx.fill();
          startAngle += sliceAngle;
        });

        // เจาะรูกราฟ Donut
        ctx.beginPath();
        ctx.arc(120, 120, 55, 0, 2 * Math.PI);
        ctx.fillStyle = '#ffffff';
        ctx.fill();

        // แสดงสัดส่วนในตารางข้างกราฟ
        if (tableBody) {
          labels.forEach((label, i) => {
            const pct = ((values[i] / total) * 100).toFixed(1);
            const formattedVal = Number(values[i]).toLocaleString('th-TH', { minimumFractionDigits: 2 });
            
            let valColor = colors[i % colors.length];
            let prefix = '฿';
            if (currentChartType === 'all_flow') {
              prefix = (i === 0) ? '+฿' : '-฿';
            } else if (currentChartType === 'income') {
              prefix = '+฿';
            } else {
              prefix = '-฿';
            }

            tableBody.innerHTML += `
              <tr style="border-bottom: 1px solid #f9fafb; font-size: 14px;">
                <td style="padding: 8px 0; display: flex; align-items: center; gap: 8px;">
                  <span style="width: 12px; height: 12px; border-radius: 50%; background: ${colors[i % colors.length]}; display: inline-block;"></span>
                  <strong>${label}</strong>
                </td>
                <td style="padding: 8px 0; text-align: right; color: ${valColor}; font-weight: 500;">${prefix}${formattedVal}</td>
                <td style="padding: 8px 0; text-align: right; color: #6b7280;">${pct}%</td>
              </tr>
            `;
          });
        }
      }
    });
  </script>
</body>
</html>