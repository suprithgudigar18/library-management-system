<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";
$msgType = "success";

// 1. AUTO-CALCULATE FINES (Ensure DB is updated)
$today = new DateTime();
$activeLoans = $pdo->prepare("SELECT id, due_date, fine_paid FROM book_requests WHERE user_id=? AND status='Approved' AND returned_at IS NULL");
$activeLoans->execute([$user_id]);
foreach ($activeLoans->fetchAll() as $req) {
    if ($req['due_date'] && !($req['fine_paid'] ?? 0)) {
        $due = new DateTime($req['due_date']);
        if ($today > $due) {
            $daysLate = (int)$today->diff($due)->days;
            $fine = $daysLate * 5; // ₹5 per day calculation
            $pdo->prepare("UPDATE book_requests SET fine_amount=? WHERE id=?")->execute([$fine, $req['id']]);
        }
    }
}

// 2. SUBMIT PAYMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = $_POST['request_id'];
    
    // VALIDATION: Retrieve the actual fine amount from the DB so user cannot spoof the amount
    $chk = $pdo->prepare("SELECT fine_amount FROM book_requests WHERE id = ? AND user_id = ? AND fine_amount > 0 AND (fine_paid IS NULL OR fine_paid = 0)");
    $chk->execute([$request_id, $user_id]);
    $requestData = $chk->fetch();

    if (!$requestData) {
        $msg = "Invalid request or fine already paid.";
        $msgType = "error";
    } else {
        $actual_amount = $requestData['fine_amount'];
        $upi = $_POST['upi'];
        $filePath = "";

        // Check for duplicate pending attempts
        $chkPending = $pdo->prepare("SELECT id FROM fine_payments WHERE user_id=? AND request_id=? AND status='Pending'");
        $chkPending->execute([$user_id, $request_id]);
        if ($chkPending->fetch()) {
            $msg = "A payment for this fine is already pending verification.";
            $msgType = "error";
        } else {
            if (!empty($_FILES['screenshot']['name'])) {
                $ext = pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION);
                $target = "uploads/" . time() . "_" . $user_id . "." . $ext;
                if (!is_dir("uploads")) {
                    mkdir("uploads", 0777, true);
                }
                move_uploaded_file($_FILES['screenshot']['tmp_name'], $target);
                $filePath = $target;
            }

            try {
                $stmt = $pdo->prepare("INSERT INTO fine_payments 
                    (user_id, request_id, amount, upi_ref, screenshot) 
                    VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $request_id, $actual_amount, $upi, $filePath]);
                $msg = "Payment submitted successfully!";
                $msgType = "success";
            } catch (Exception $e) {
                $msg = "Error submitting payment. " . $e->getMessage();
                $msgType = "error";
            }
        }
    }
}

// 3. FETCH UNPAID FINES FOR THE DROPDOWN
$stmt = $pdo->prepare("
    SELECT br.id as request_id, b.title, br.fine_amount, br.due_date 
    FROM book_requests br 
    JOIN books b ON b.id = br.book_id 
    WHERE br.user_id=? AND br.fine_amount > 0 AND (br.fine_paid IS NULL OR br.fine_paid = 0)
    AND br.id NOT IN (SELECT request_id FROM fine_payments WHERE user_id=? AND status='Pending')
");
$stmt->execute([$user_id, $user_id]);
$fineRequests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Fine & Orders - LIBRITE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { background: #ffffff !important; }
        .back-btn {
            display:inline-flex; align-items:center; gap:6px;
            padding:8px 16px; border-radius:8px;
            background:#ffffff; border:1px solid #cbd5e1;
            color:#475569; text-decoration:none; font-size:.85rem; font-weight:600;
            transition:all .25s ease; font-family:'Inter', sans-serif;
        }
        .back-btn:hover { border-color:#0284c7; color:#0284c7; background:#f0f9ff; }
    </style>
</head>
<body class="bg-white min-h-screen font-sans text-slate-800">

<header class="bg-white border-b border-slate-200 sticky top-0 z-10 px-6 py-4 flex items-center justify-between shadow-sm">
    <div class="flex items-center gap-4">
        <a href="user_dashboard.php" class="back-btn"><i data-lucide="arrow-left" class="w-4 h-4 text-slate-500"></i> Dashboard</a>
        <div class="bg-blue-600 p-2 rounded-lg ml-2">
            <i data-lucide="credit-card" class="w-6 h-6 text-white"></i>
        </div>
        <h1 class="text-xl font-bold text-slate-800 flex items-center gap-2">Librite <span class="text-sm font-normal text-slate-500 mt-1">Payment & Order</span></h1>
    </div>
</header>

<main class="p-6 max-w-3xl mx-auto mt-6">
    <div class="bg-white p-8 rounded-2xl shadow-xl border border-slate-100">
        <h2 class="text-3xl font-extrabold text-slate-900 mb-2">Submit Fine Payment</h2>
        <p class="text-slate-500 mb-8">Select the overdue book to officially fetch your DB calculated fine amount. Ensure you provide a correct UPI reference and screenshot.</p>

        <?php if($msg): ?>
            <div class="mb-6 p-4 rounded-xl <?= $msgType === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-700' : 'bg-red-50 border border-red-200 text-red-700' ?> flex items-center gap-3">
                <i data-lucide="<?= $msgType === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5"></i>
                <span class="font-medium"><?= htmlspecialchars($msg) ?></span>
            </div>
        <?php endif; ?>

        <?php if(empty($fineRequests)): ?>
            <div class="p-6 rounded-xl bg-emerald-50 border border-emerald-200 text-center">
                <i data-lucide="check-circle" class="w-12 h-12 text-emerald-500 mx-auto mb-3"></i>
                <h3 class="text-xl font-bold text-emerald-800 mb-1">No Active Fines!</h3>
                <p class="text-emerald-600">You do not have any pending fines to pay. Great job returning your books on time!</p>
            </div>
        <?php else: ?>
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Select Book with Fine</label>
                        <select name="request_id" id="requestSelect" required 
                                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all outline-none"
                                onchange="updateAmount()">
                            <option value="">-- Choose a request --</option>
                            <?php foreach ($fineRequests as $req): ?>
                                <option value="<?= $req['request_id'] ?>" data-amount="<?= $req['fine_amount'] ?>">
                                    <?= htmlspecialchars($req['title']) ?> (Due: <?= date('d M Y', strtotime($req['due_date'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Calculated Amount (₹)</label>
                        <input type="number" name="amount" id="amountDisplay" required step="0.01" readonly
                               class="w-full px-4 py-3 bg-slate-100 border border-slate-200 rounded-xl outline-none text-slate-600 font-bold"
                               placeholder="0.00">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">UPI Reference No.</label>
                    <input type="text" name="upi" required
                           class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none"
                           placeholder="Enter 12-digit UPI transaction ID">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Upload Screenshot</label>
                    <div class="relative">
                        <input type="file" name="screenshot" accept="image/*" required
                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-all">
                    </div>
                    <p class="text-xs text-slate-400 mt-2">Accepted formats: JPG, PNG, JPEG</p>
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full flex justify-center items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 px-6 rounded-xl transition-all shadow-md hover:shadow-lg">
                        <i data-lucide="send" class="w-5 h-5"></i>
                        Submit Payment Details
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>

<script>
    lucide.createIcons();

    function updateAmount() {
        const select = document.getElementById('requestSelect');
        const amountDisplay = document.getElementById('amountDisplay');
        const selectedOption = select.options[select.selectedIndex];
        
        if (selectedOption.value) {
            amountDisplay.value = selectedOption.getAttribute('data-amount');
        } else {
            amountDisplay.value = '';
        }
    }
</script>
</body>
</html>