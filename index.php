<?php
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection (use environment variables in production)
try {
    $pdo = new PDO("mysql:host=sdb-s.hosting.stackcp.net;dbname=Mrbeta-3230370f28", "Mrbetauser", "Mr9494499304M@ni");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Pagination setup
$records_per_page = 15;
$profit_page = isset($_GET['profit_page']) && $_GET['profit_page'] > 0 ? (int)$_GET['profit_page'] : 1;
$expense_page = isset($_GET['expense_page']) && $_GET['expense_page'] > 0 ? (int)$_GET['expense_page'] : 1;
$profit_offset = ($profit_page - 1) * $records_per_page;
$expense_offset = ($expense_page - 1) * $records_per_page;

// Date range filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$use_filter = ($start_date && $end_date);

// Edit state
$edit_profit_data = null;
$edit_expense_data = null;
$edit_profit_id = null;
$edit_expense_id = null;

// Notification message
$notification = null;
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'profit_added': $notification = ['type' => 'success', 'message' => 'Profit added successfully!']; break;
        case 'expense_added': $notification = ['type' => 'success', 'message' => 'Expense added successfully!']; break;
        case 'profit_updated': $notification = ['type' => 'success', 'message' => 'Profit updated successfully!']; break;
        case 'expense_updated': $notification = ['type' => 'success', 'message' => 'Expense updated successfully!']; break;
        case 'profit_deleted': $notification = ['type' => 'success', 'message' => 'Profit deleted successfully!']; break;
        case 'expense_deleted': $notification = ['type' => 'success', 'message' => 'Expense deleted successfully!']; break;
    }
}

// Handle deletion
if (isset($_GET['delete_profit'])) {
    $stmt = $pdo->prepare("DELETE FROM profits WHERE id = ?");
    $stmt->execute([(int)$_GET['delete_profit']]);
    header("Location: ?action=profit_deleted");
    exit;
}
if (isset($_GET['delete_expense'])) {
    $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
    $stmt->execute([(int)$_GET['delete_expense']]);
    header("Location: ?action=expense_deleted");
    exit;
}

// Load data for editing
if (isset($_GET['edit_profit'])) {
    $edit_profit_id = (int)$_GET['edit_profit'];
    $stmt = $pdo->prepare("SELECT * FROM profits WHERE id = ?");
    $stmt->execute([$edit_profit_id]);
    $edit_profit_data = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (isset($_GET['edit_expense'])) {
    $edit_expense_id = (int)$_GET['edit_expense'];
    $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ?");
    $stmt->execute([$edit_expense_id]);
    $edit_expense_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle form submissions with validation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    if (isset($_POST['add_profit']) || isset($_POST['edit_profit'])) {
        $desc = trim($_POST['profit_desc'] ?? '');
        $amount = filter_var($_POST['profit_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
        $date = $_POST['profit_date'] ?? '';

        if (empty($desc)) $errors[] = 'Profit description is required.';
        if ($amount === false || $amount <= 0) $errors[] = 'Valid positive profit amount is required.';
        if (!DateTime::createFromFormat('Y-m-d', $date)) $errors[] = 'Valid profit date is required.';

        if (empty($errors)) {
            if (isset($_POST['add_profit'])) {
                $stmt = $pdo->prepare("INSERT INTO profits (description, amount, date) VALUES (?, ?, ?)");
                $stmt->execute([$desc, $amount, $date]);
                header("Location: ?action=profit_added");
            } elseif (isset($_POST['edit_profit'])) {
                $stmt = $pdo->prepare("UPDATE profits SET description = ?, amount = ?, date = ? WHERE id = ?");
                $stmt->execute([$desc, $amount, $date, (int)$_POST['edit_id']]);
                header("Location: ?action=profit_updated");
            }
            exit;
        }
        $notification = ['type' => 'danger', 'message' => implode('<br>', $errors)];
    } elseif (isset($_POST['add_expense']) || isset($_POST['edit_expense'])) {
        $desc = trim($_POST['expense_desc'] ?? '');
        $amount = filter_var($_POST['expense_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
        $date = $_POST['expense_date'] ?? '';

        if (empty($desc)) $errors[] = 'Expense description is required.';
        if ($amount === false || $amount <= 0) $errors[] = 'Valid positive expense amount is required.';
        if (!DateTime::createFromFormat('Y-m-d', $date)) $errors[] = 'Valid expense date is required.';

        if (empty($errors)) {
            if (isset($_POST['add_expense'])) {
                $stmt = $pdo->prepare("INSERT INTO expenses (description, amount, date) VALUES (?, ?, ?)");
                $stmt->execute([$desc, $amount, $date]);
                header("Location: ?action=expense_added");
            } elseif (isset($_POST['edit_expense'])) {
                $stmt = $pdo->prepare("UPDATE expenses SET description = ?, amount = ?, date = ? WHERE id = ?");
                $stmt->execute([$desc, $amount, $date, (int)$_POST['edit_id']]);
                header("Location: ?action=expense_updated");
            }
            exit;
        }
        $notification = ['type' => 'danger', 'message' => implode('<br>', $errors)];
    }
}

// Fetch records with pagination and date range
$currentMonth = (int)date('m');
$currentYear = (int)date('Y');

$profit_where = $use_filter ? "date BETWEEN :p_start AND :p_end" : "MONTH(date) = :p_m AND YEAR(date) = :p_y";
$profit_sql = "SELECT * FROM profits WHERE $profit_where ORDER BY id DESC LIMIT :p_limit OFFSET :p_offset";
$profits_stmt = $pdo->prepare($profit_sql);
if ($use_filter) {
    $profits_stmt->bindValue(':p_start', $start_date, PDO::PARAM_STR);
    $profits_stmt->bindValue(':p_end', $end_date, PDO::PARAM_STR);
} else {
    $profits_stmt->bindValue(':p_m', $currentMonth, PDO::PARAM_INT);
    $profits_stmt->bindValue(':p_y', $currentYear, PDO::PARAM_INT);
}
$profits_stmt->bindValue(':p_limit', $records_per_page, PDO::PARAM_INT);
$profits_stmt->bindValue(':p_offset', $profit_offset, PDO::PARAM_INT);
$profits_stmt->execute();
$profits = $profits_stmt->fetchAll(PDO::FETCH_ASSOC);

$expense_where = $use_filter ? "date BETWEEN :e_start AND :e_end" : "MONTH(date) = :e_m AND YEAR(date) = :e_y";
$expense_sql = "SELECT * FROM expenses WHERE $expense_where ORDER BY id DESC LIMIT :e_limit OFFSET :e_offset";
$expenses_stmt = $pdo->prepare($expense_sql);
if ($use_filter) {
    $expenses_stmt->bindValue(':e_start', $start_date, PDO::PARAM_STR);
    $expenses_stmt->bindValue(':e_end', $end_date, PDO::PARAM_STR);
} else {
    $expenses_stmt->bindValue(':e_m', $currentMonth, PDO::PARAM_INT);
    $expenses_stmt->bindValue(':e_y', $currentYear, PDO::PARAM_INT);
}
$expenses_stmt->bindValue(':e_limit', $records_per_page, PDO::PARAM_INT);
$expenses_stmt->bindValue(':e_offset', $expense_offset, PDO::PARAM_INT);
$expenses_stmt->execute();
$expenses = $expenses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Totals
if ($use_filter) {
    $total_profit_stmt = $pdo->prepare("SELECT SUM(amount) FROM profits WHERE date BETWEEN ? AND ?");
    $total_expense_stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE date BETWEEN ? AND ?");
    $total_profit_stmt->execute([$start_date, $end_date]);
    $total_expense_stmt->execute([$start_date, $end_date]);
} else {
    $total_profit_stmt = $pdo->prepare("SELECT SUM(amount) FROM profits WHERE YEAR(date) = YEAR(CURDATE()) AND MONTH(date) = MONTH(CURDATE())");
    $total_expense_stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE YEAR(date) = YEAR(CURDATE()) AND MONTH(date) = MONTH(CURDATE())");
    $total_profit_stmt->execute();
    $total_expense_stmt->execute();
}
$total_profit = (float)($total_profit_stmt->fetchColumn() ?: 0);
$total_expense = (float)($total_expense_stmt->fetchColumn() ?: 0);
$net = $total_profit - $total_expense;

// Pagination counts
$profit_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM profits WHERE $profit_where");
$expense_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE $expense_where");
if ($use_filter) {
    $profit_count_stmt->bindValue(':p_start', $start_date, PDO::PARAM_STR);
    $profit_count_stmt->bindValue(':p_end', $end_date, PDO::PARAM_STR);
    $expense_count_stmt->bindValue(':e_start', $start_date, PDO::PARAM_STR);
    $expense_count_stmt->bindValue(':e_end', $end_date, PDO::PARAM_STR);
} else {
    $profit_count_stmt->bindValue(':p_m', $currentMonth, PDO::PARAM_INT);
    $profit_count_stmt->bindValue(':p_y', $currentYear, PDO::PARAM_INT);
    $expense_count_stmt->bindValue(':e_m', $currentMonth, PDO::PARAM_INT);
    $expense_count_stmt->bindValue(':e_y', $currentYear, PDO::PARAM_INT);
}
$profit_count_stmt->execute();
$expense_count_stmt->execute();
$profit_count = (int)$profit_count_stmt->fetchColumn();
$expense_count = (int)$expense_count_stmt->fetchColumn();
$profit_pages = max(1, (int)ceil($profit_count / $records_per_page));
$expense_pages = max(1, (int)ceil($expense_count / $records_per_page));

// Month-to-date vs Previous Month-to-date (for Summary tab)
$todayDay = (int)date('d');
$currentMonthStart = date('Y-m-01');
$currentDate = date('Y-m-d');

// Current MTD
$stmt = $pdo->prepare("SELECT SUM(amount) FROM profits WHERE date BETWEEN ? AND ?");
$stmt->execute([$currentMonthStart, $currentDate]);
$current_profit = (float)($stmt->fetchColumn() ?: 0);

$stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE date BETWEEN ? AND ?");
$stmt->execute([$currentMonthStart, $currentDate]);
$current_expense = (float)($stmt->fetchColumn() ?: 0);

$current_net = $current_profit - $current_expense;

// Previous month MTD
$lastMonthStartTs = strtotime("first day of last month");
$prevMonthStart = date('Y-m-01', $lastMonthStartTs);
$prevMonthDays = (int)date('t', $lastMonthStartTs);
$prevDay = min($todayDay, $prevMonthDays);
$prevMonthEnd = date('Y-m-' . str_pad((string)$prevDay, 2, '0', STR_PAD_LEFT), $lastMonthStartTs);

$stmt = $pdo->prepare("SELECT SUM(amount) FROM profits WHERE date BETWEEN ? AND ?");
$stmt->execute([$prevMonthStart, $prevMonthEnd]);
$prev_profit = (float)($stmt->fetchColumn() ?: 0);

$stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE date BETWEEN ? AND ?");
$stmt->execute([$prevMonthStart, $prevMonthEnd]);
$prev_expense = (float)($stmt->fetchColumn() ?: 0);

$prev_net = $prev_profit - $prev_expense;

// % change helper
function percentChange($current, $previous) {
    if ($previous == 0 && $current == 0) return "0%";
    if ($previous == 0) return "+100%";
    $change = (($current - $previous) / $previous) * 100;
    return ($change >= 0 ? "+" : "") . round($change, 1) . "%";
}
$profit_change = percentChange($current_profit, $prev_profit);
$expense_change = percentChange($current_expense, $prev_expense);
$net_change = percentChange($current_net, $prev_net);

// Month-on-month data (last 12 months)
$months = [];
$profit_data = [];
$expense_data = [];
$net_data = [];

$endDate = new DateTime();
$startDate = new DateTime(date('Y-01-01')); // Start of current year

for ($date = clone $startDate; $date <= $endDate; $date->modify('+1 month')) {
    $year = $date->format('Y');
    $month = $date->format('m');
    $monthName = $date->format('M Y');
    $months[] = $monthName;

    // Profit for the month
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM profits WHERE YEAR(date) = ? AND MONTH(date) = ?");
    $stmt->execute([$year, $month]);
    $month_profit = (float)($stmt->fetchColumn() ?: 0);

    // Expense for the month
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE YEAR(date) = ? AND MONTH(date) = ?");
    $stmt->execute([$year, $month]);
    $month_expense = (float)($stmt->fetchColumn() ?: 0);

    // Store data
    $profit_data[] = $month_profit;
    $expense_data[] = $month_expense;
    $net_data[] = $month_profit - $month_expense;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Finance Dashboard</title>
    <link rel="icon" href="favicon.jpg" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
     body {
  background-size: contain;  /* use cover if you want it to stretch */
  opacity: 1; /* keep body content normal */
  padding:40px;
}

/* Optional: make watermark faint */
body::before {
  content: "";
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: url("img2.png");
  background-size: 400px auto; /* adjust size */
  opacity: 0.08; /* faint watermark */
  z-index: -1; /* keeps it behind everything */
}
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 24px;
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .card-header {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            font-size: 1.25rem;
            font-weight: 600;
            padding: 16px 24px;
        }
        .card-body {
            padding: 24px;
        }
        .nav-tabs {
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 24px;
        }
        .nav-tabs .nav-link {
            color: #6b7280;
            font-weight: 500;
            padding: 12px 24px;
            border-radius: 6px 6px 0 0;
        }
        .nav-tabs .nav-link.active {
            color: #3b82f6;
            background: #ffffff;
            border-color: #e2e8f0 #e2e8f0 #ffffff;
            border-bottom: 2px solid #3b82f6;
        }
        .nav-tabs .nav-link:hover {
            color: #2563eb;
            background: #f8fafc;
        }
        .form-control {
            border-radius: 6px;
            border: 1px solid #d1d5db;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .btn {
            border-radius: 6px;
            padding: 8px 16px;
            font-weight: 500;
        }
        .btn-primary {
            background: #3b82f6;
            border: none;
        }
        .btn-primary:hover {
            background: #2563eb;
        }
        .btn-outline-secondary {
            border-color: #6b7280;
            color: #6b7280;
        }
        .btn-outline-secondary:hover {
            background: #f1f5f9;
        }
        .table {
            border-radius: 8px;
            overflow: hidden;
        }
        .table thead th {
            background: #f1f5f9;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
            color: #374151;
        }
        .table tbody tr:hover {
            background: #f9fafb;
        }
        .filter-form {
            margin-bottom: 32px;
        }
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            border-radius: 8px;
        }
        .pagination .page-link {
            color: #3b82f6;
            border-radius: 6px;
        }
        .pagination .active .page-link {
            background: #3b82f6;
            border-color: #3b82f6;
            color: #fff;
        }
        h2 {
            font-weight: 600;
            color: #1a1a1a;
        }
        .summary-card .card-body {
            font-size: 1.5rem;
            font-weight: 600;
            padding: 24px;
            text-align: center;
        }
        .muted {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 8px;
        }
        .growth {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
        }
        #chart-container canvas {
            max-height: 400px;
        }
        @media (max-width: 768px) {
            body { padding: 20px; }
            .card-header { font-size: 1.1rem; }
            .summary-card .card-body { font-size: 1.25rem; }
            .nav-tabs .nav-link { padding: 8px 16px; }
        }
    </style>
</head>
<body>
    <h2 class="text-center">5 AmmaAmogham Finance Dashboard 6</h2>
    <!-- Notification Toast -->
    <?php if ($notification): ?>
        <div class="toast show align-items-center text-bg-<?= $notification['type'] ?> border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <?= htmlspecialchars($notification['message']) ?>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs" id="financeTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab" aria-controls="summary" aria-selected="true">Summary</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="records-tab" data-bs-toggle="tab" data-bs-target="#records" type="button" role="tab" aria-controls="records" aria-selected="false">Records</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="charts-tab" data-bs-toggle="tab" data-bs-target="#charts" type="button" role="tab" aria-controls="charts" aria-selected="false">Charts</button>
        </li>
    </ul>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <?php
    // Ensure values are numbers (default to 0 if null)
    $current_profit  = $current_profit  ?? 0;
    $current_expense = $current_expense ?? 0;
    $current_net     = $current_net     ?? 0;
    $prev_profit  = $prev_profit  ?? 0;
    $prev_expense = $prev_expense ?? 0;
    $prev_net     = $prev_net     ?? 0;

    // Calculate absolute differences
    $profit_diff  = $current_profit  - $prev_profit;
    $expense_diff = $current_expense - $prev_expense;
    $net_diff     = $current_net     - $prev_net;

    // Calculate percentage changes safely
    $profit_change  = $prev_profit  != 0 ? (($current_profit  - $prev_profit)  / $prev_profit)  * 100 : 0;
    $expense_change = $prev_expense != 0 ? (($current_expense - $prev_expense) / $prev_expense) * 100 : 0;
    $net_change     = $prev_net     != 0 ? (($current_net     - $prev_net)     / $prev_net)     * 100 : 0;

    // Format function with badge
    function formatChangeRow($percent, $diff, $label, $chartId, $trendData = []) {
        if ($diff == 0) {
            $bgClass = "bg-light";
            $badge   = "<span class='badge bg-secondary'>0% (No change)</span>";
        } else {
            $sign    = $diff > 0 ? '+' : '-';
            $bgClass = $diff > 0 ? "bg-light-success" : "bg-light-danger";
            $color   = $diff > 0 ? "bg-success" : "bg-danger";
            $status  = $diff > 0 ? "gain" : "drop";
            $icon    = $diff > 0
                         ? "<i class='bi bi-arrow-up-right'></i>"
                         : "<i class='bi bi-arrow-down-right'></i>";

            $badge = "<span class='badge $color fs-6 p-2'>" .
                $icon . " " .
                sprintf("%.1f%% (%s₹%s %s)",
                    $percent,
                    $sign,
                    number_format(abs($diff), 0),
                    $status
                ) .
            "</span>";
        }

        // JSON encode trend data for JS
        $trendJson = htmlspecialchars(json_encode($trendData));

        return "
            <div class='p-2 mb-2 rounded d-flex justify-content-between align-items-center $bgClass'>
                <div><strong>$label:</strong> $badge</div>
                <canvas id='$chartId' width='80' height='30' data-trend='$trendJson'></canvas>
            </div>
        ";
    }
    ?>
    <!-- Tab Content -->
    <div class="tab-content" id="financeTabContent">
        <!-- Summary Tab -->
        <div class="tab-pane fade show active" id="summary" role="tabpanel" aria-labelledby="summary-tab">
            <div class="row mb-2">
                <div class="col-md-4">
                    <div class="card summary-card bg-warning text-white">
                        <div class="card-body">Total Profit: ₹<?= number_format($total_profit, 2) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card summary-card bg-danger text-white">
                        <div class="card-body">Total Expenses: ₹<?= number_format($total_expense, 2) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card summary-card bg-success text-white">
                        <div class="card-body">Net Profit: ₹<?= number_format($net, 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="row g-3">
                <!-- Comparison -->
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="row text-center">
                                <?php
                                function diffBlock($label, $current, $prev, $inverse = false) {
                                    $diff    = $current - $prev;
                                    $percent = ($prev > 0) ? ($diff / $prev) * 100 : 0;
                                    $isGood  = $inverse ? ($diff <= 0) : ($diff >= 0);
                                    $color   = $isGood ? "text-success" : "text-danger";
                                    $icon    = $isGood ? "fa-arrow-up" : "fa-arrow-down";

                                    echo "
                                        <div class='col-md-4'>
                                            <h6 class='mb-1'>$label</h6>
                                            <p class='$color mb-0'>
                                                <i class='fa $icon'></i> " . round($percent, 1) . "% (₹" . number_format(abs($diff), 2) . ")
                                                <br><small class='text-muted'>vs last month</small>
                                            </p>
                                        </div>
                                    ";
                                }
                                diffBlock("Profit",  $current_profit,  $prev_profit);
                                diffBlock("Expense", $current_expense, $prev_expense, true);
                                diffBlock("Net",     $current_net,     $prev_net);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- This Month -->
                <div class="col-md-3">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <p class="mb-1">This Month: <span style="font-size:14px;">(<?= date('d M Y', strtotime($currentMonthStart)) ?> – <?= date('d M Y', strtotime($currentDate)) ?>)</span></p>
                            <p class="mb-1">Profit: <strong>₹<?= number_format($current_profit, 0) ?></strong></p>
                            <p class="mb-1">Expense: <strong>₹<?= number_format($current_expense, 0) ?></strong></p>
                            <p class="mb-0">Net: <strong>₹<?= number_format($current_net, 0) ?></strong></p>
                        </div>
                    </div>
                </div>
                <!-- Last Month -->
                <div class="col-md-3">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <p class="mb-1">Last Month: <span style="font-size:14px;">(<?= date('d M Y', strtotime($prevMonthStart)) ?> – <?= date('d M Y', strtotime($prevMonthEnd)) ?>)</span></p>
                            <p class="mb-1">Profit: <strong>₹<?= number_format($prev_profit, 0) ?></strong></p>
                            <p class="mb-1">Expense: <strong>₹<?= number_format($prev_expense, 0) ?></strong></p>
                            <p class="mb-0">Net: <strong>₹<?= number_format($prev_net, 0) ?></strong></p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Date Range Filter -->
            <div class="filter-form">
                <div class="card">
                    <div class="card-header">Filter by Date Range</div>
                    <div class="card-body">
                        <form method="get" class="row g-3 needs-validation" novalidate>
                            <div class="col-md-5">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" id="start_date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>" required>
                                <div class="invalid-feedback">Please select a start date.</div>
                            </div>
                            <div class="col-md-5">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" id="end_date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>" required>
                                <div class="invalid-feedback">Please select an end date.</div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary w-100">Apply</button>
                                <a href="?" class="btn btn-outline-secondary w-100">Reset</a>
                            </div>
                            <input type="hidden" name="profit_page" value="<?= $profit_page ?>">
                            <input type="hidden" name="expense_page" value="<?= $expense_page ?>">
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- Records Tab -->
        <div class="tab-pane fade" id="records" role="tabpanel" aria-labelledby="records-tab">
            <div class="row">
                <!-- Expense Form -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><?= $edit_expense_data ? 'Edit Expense' : 'Add New Expense' ?></div>
                        <div class="card-body">
                            <form method="post" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <label for="expense_desc" class="form-label">Description</label>
                                    <input type="text" name="expense_desc" id="expense_desc" class="form-control" placeholder="Enter description" required value="<?= $edit_expense_data ? htmlspecialchars($edit_expense_data['description']) : '' ?>">
                                    <div class="invalid-feedback">Please enter a description.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="expense_amount" class="form-label">Amount (₹)</label>
                                    <input type="number" step="0.01" min="0.01" name="expense_amount" id="expense_amount" class="form-control" placeholder="Enter amount" required value="<?= $edit_expense_data ? htmlspecialchars($edit_expense_data['amount']) : '' ?>">
                                    <div class="invalid-feedback">Please enter a valid positive amount.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="expense_date" class="form-label">Date</label>
                                    <input type="date" name="expense_date" id="expense_date" class="form-control" required value="<?= $edit_expense_data ? $edit_expense_data['date'] : date('Y-m-d') ?>">
                                    <div class="invalid-feedback">Please select a valid date.</div>
                                </div>
                                <?php if ($edit_expense_data): ?>
                                    <input type="hidden" name="edit_id" value="<?= $edit_expense_data['id'] ?>">
                                    <button name="edit_expense" class="btn btn-danger w-100">Update Expense</button>
                                    <a href="?" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
                                <?php else: ?>
                                    <button name="add_expense" class="btn btn-danger w-100 mt-2">Add Expense</button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- Profit Form -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><?= $edit_profit_data ? 'Edit Profit' : 'Add New Profit' ?></div>
                        <div class="card-body">
                            <form method="post" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <label for="profit_desc" class="form-label">Description</label>
                                    <input type="text" name="profit_desc" id="profit_desc" class="form-control" placeholder="Enter description" required value="<?= $edit_profit_data ? htmlspecialchars($edit_profit_data['description']) : '' ?>">
                                    <div class="invalid-feedback">Please enter a description.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="profit_amount" class="form-label">Amount (₹)</label>
                                    <input type="number" step="0.01" min="0.01" name="profit_amount" id="profit_amount" class="form-control" placeholder="Enter amount" required value="<?= $edit_profit_data ? htmlspecialchars($edit_profit_data['amount']) : '' ?>">
                                    <div class="invalid-feedback">Please enter a valid positive amount.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="profit_date" class="form-label">Date</label>
                                    <input type="date" name="profit_date" id="profit_date" class="form-control" required value="<?= $edit_profit_data ? $edit_profit_data['date'] : date('Y-m-d') ?>">
                                    <div class="invalid-feedback">Please select a valid date.</div>
                                </div>
                                <?php if ($edit_profit_data): ?>
                                    <input type="hidden" name="edit_id" value="<?= $edit_profit_data['id'] ?>">
                                    <button name="edit_profit" class="btn btn-success w-100">Update Profit</button>
                                    <a href="?" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
                                <?php else: ?>
                                    <button name="add_profit" class="btn btn-success w-100 mt-2">Add Profit</button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-5">
                <!-- Expense Table -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">Expenses</div>
                        <div class="card-body">
                            <table class="table" id="expense-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expenses as $expense): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($expense['id']) ?></td>
                                            <td><?= htmlspecialchars($expense['description']) ?></td>
                                            <td>₹<?= number_format($expense['amount'], 2) ?></td>
                                            <td><?= htmlspecialchars($expense['date']) ?></td>
                                            <td>
                                                <a href="?edit_expense=<?= $expense['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                                <a href="?delete_expense=<?= $expense['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this expense?')">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <nav>
                                <ul class="pagination">
                                    <?php for ($i = 1; $i <= $expense_pages; $i++): ?>
                                        <li class="page-item <?= $i === $expense_page ? 'active' : '' ?>">
                                            <a class="page-link" href="?expense_page=<?= $i ?>&profit_page=<?= $profit_page ?>&start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
                <!-- Profit Table -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">Profits</div>
                        <div class="card-body">
                            <table class="table" id="profit-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($profits as $profit): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($profit['id']) ?></td>
                                            <td><?= htmlspecialchars($profit['description']) ?></td>
                                            <td>₹<?= number_format($profit['amount'], 2) ?></td>
                                            <td><?= htmlspecialchars($profit['date']) ?></td>
                                            <td>
                                                <a href="?edit_profit=<?= $profit['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                                <a href="?delete_profit=<?= $profit['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this profit?')">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <nav>
                                <ul class="pagination">
                                    <?php for ($i = 1; $i <= $profit_pages; $i++): ?>
                                        <li class="page-item <?= $i === $profit_page ? 'active' : '' ?>">
                                            <a class="page-link" href="?profit_page=<?= $i ?>&expense_page=<?= $expense_page ?>&start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Charts Tab -->
        <div class="tab-pane fade" id="charts" role="tabpanel" aria-labelledby="charts-tab">
            <div class="card mb-2" style="display:none">
                <div class="card-header">Current Period Totals</div>
                <div class="card-body">
                    <canvas id="totalsChart"></canvas>
                </div>
            </div>
            <div class="card">
                <div class="card-header">Month-on-Month Comparison</div>
                <div class="card-body">
                    <canvas id="comparisonChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script>
        // Chart 1: Current totals
        const totalsCtx = document.getElementById('totalsChart').getContext('2d');
        new Chart(totalsCtx, {
            type: 'bar',
            data: {
                labels: ['Profit', 'Expenses', 'Net Profit'],
                datasets: [{
                    label: 'Amount (₹)',
                    data: [<?= $total_profit ?>, <?= $total_expense ?>, <?= $net ?>],
                    backgroundColor: ['#22c55e', '#ef4444', '#3b82f6'],
                    borderRadius: 6,
                    barThickness: 50
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 500,
                            callback: value => '₹' + value.toLocaleString('en-IN')
                        },
                        grid: { color: '#e2e8f0' }
                    },
                    x: { grid: { display: false } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: context => `₹${context.raw.toLocaleString('en-IN')}`
                        }
                    }
                }
            }
        });

        // Chart 2: Month-on-Month Comparison
        const comparisonCtx = document.getElementById('comparisonChart').getContext('2d');
        new Chart(comparisonCtx, {
            type: 'bar',
            data: {
                labels: [<?= "'" . implode("','", $months) . "'" ?>],
                datasets: [
                    {
                        label: 'Profit',
                        data: [<?= implode(',', $profit_data) ?>],
                        backgroundColor: 'rgba(34, 197, 94, 0.8)',
                        borderRadius: 6
                    },
                    {
                        label: 'Expenses',
                        data: [<?= implode(',', $expense_data) ?>],
                        backgroundColor: 'rgba(239, 68, 68, 0.8)',
                        borderRadius: 6
                    },
                    {
                        label: 'Net Profit',
                        data: [<?= implode(',', $net_data) ?>],
                        backgroundColor: 'rgba(59, 130, 246, 0.8)',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 500,
                            callback: value => '₹' + value.toLocaleString('en-IN')
                        },
                        grid: { color: '#e2e8f0' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 45, minRotation: 45 }
                    }
                },
                plugins: {
                    legend: { position: 'top', labels: { font: { size: 12, family: 'Inter' } } },
                    tooltip: {
                        callbacks: {
                            label: context => `${context.dataset.label}: ₹${context.raw.toLocaleString('en-IN')}`
                        }
                    }
                }
            }
        });

        // Bootstrap form validation
        (function () {
            'use strict';
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();

        // Auto-hide toast after 3 seconds
        const toastEl = document.querySelector('.toast');
        if (toastEl) {
            const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
            toast.show();
        }
    </script>
</body>
</html>
