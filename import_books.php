<?php
include("db_connect.php"); // this gives $pdo

if(isset($_POST['upload'])) {

    if(isset($_FILES['file']) && $_FILES['file']['error'] == 0) {

        $file = $_FILES['file']['tmp_name'];

        if (($handle = fopen($file, "r")) !== FALSE) {

            fgetcsv($handle); // skip header

            $count = 0;

            // Prepared statement (SAFE 🔥)
            $stmt = $pdo->prepare("INSERT INTO books 
            (title, author, isbn, category, genre, copies, status, shelf)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

                $title    = $data[0];
                $author   = $data[1];
                $isbn     = $data[2];
                $category = $data[3];
                $genre    = $data[4];
                $copies   = (int)$data[5];
                $shelf    = isset($data[7]) ? $data[7] : '';

                // AUTO STATUS
                $status = ($copies > 0) ? "Available" : "Borrowed";

                // Execute insert
                if($stmt->execute([$title, $author, $isbn, $category, $genre, $copies, $status, $shelf])) {
                    $count++;
                }
            }

            fclose($handle);

            echo "<script>
                    alert('✅ $count Books Uploaded Successfully!');
                    window.location='manage_books.php';
                  </script>";
        } else {
            echo "❌ Unable to open file!";
        }

    } else {
        echo "❌ File upload error!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Bulk Upload Books</title>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: #f4f6f9;
        }

        .container {
            max-width: 420px;
            margin: 120px auto;
            background: #ffffff;
            padding: 35px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        h2 {
            margin-bottom: 20px;
            color: #1e293b;
        }

        /* 🔥 Custom File Upload */
        .file-input {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-input input[type="file"] {
            display: none;
        }

        .file-label {
            display: block;
            padding: 12px;
            border: 2px dashed #3b82f6;
            border-radius: 10px;
            cursor: pointer;
            color: #3b82f6;
            font-weight: 500;
            transition: 0.3s;
        }

        .file-label:hover {
            background: #eff6ff;
        }

        .file-name {
            margin-top: 10px;
            font-size: 14px;
            color: #64748b;
        }

        button {
            margin-top: 20px;
            background: #22c55e;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: 0.3s;
        }

        button:hover {
            background: #16a34a;
        }

        .back {
            display: inline-block;
            margin-top: 20px;
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
        }

        .back:hover {
            text-decoration: underline;
        }

        .icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="icon">📂</div>
    <h2>Bulk Upload Books</h2>

    <form method="POST" enctype="multipart/form-data">
        
        <div class="file-input">
            <label for="file" class="file-label">📁 Choose CSV File</label>
            <input type="file" name="file" id="file" required onchange="showFileName(this)">
            <div class="file-name" id="fileName">No file chosen</div>
        </div>

        <button type="submit" name="upload">Upload CSV</button>
    </form>

    <a href="manage_books.php" class="back">⬅ Back to Dashboard</a>
</div>

<script>
function showFileName(input) {
    const fileName = input.files[0]?.name || "No file chosen";
    document.getElementById("fileName").innerText = fileName;
}
</script>

</body>
</html>