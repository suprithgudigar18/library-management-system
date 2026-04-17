<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";

// Submit payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $request_id = $_POST['request_id'];
    $amount = $_POST['amount'];
    $upi = $_POST['upi'];

    $filePath = "";

    if (!empty($_FILES['screenshot']['name'])) {
        $target = "uploads/" . time() . "_" . basename($_FILES['screenshot']['name']);
        move_uploaded_file($_FILES['screenshot']['tmp_name'], $target);
        $filePath = $target;
    }

    $stmt = $pdo->prepare("INSERT INTO fine_payments 
        (user_id, request_id, amount, upi_ref, screenshot) 
        VALUES (?, ?, ?, ?, ?)");

    $stmt->execute([$user_id, $request_id, $amount, $upi, $filePath]);

    $msg = "Payment submitted successfully!";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Pay Fine</title>
</head>
<body style="background:#111;color:white;font-family:sans-serif">

<h2>Submit Fine Payment</h2>

<?php if($msg) echo "<p style='color:lightgreen'>$msg</p>"; ?>

<form method="POST" enctype="multipart/form-data">
    
    <label>Request ID:</label><br>
    <input type="number" name="request_id" required><br><br>

    <label>Amount:</label><br>
    <input type="number" name="amount" required><br><br>

    <label>UPI Ref:</label><br>
    <input type="text" name="upi"><br><br>

    <label>Upload Screenshot:</label><br>
    <input type="file" name="screenshot"><br><br>

    <button type="submit">Submit Payment</button>

</form>

</body>
</html>