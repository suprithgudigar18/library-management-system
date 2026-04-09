<?php include 'db_connect.php'; session_start(); ?>
<!DOCTYPE html>
<html>
<head><title>Return Books - LIBRITE</title></head>
<body>
    <div class="dashboard">
        <?php include 'sidebar.php'; ?>
        <main class="main-content">
            <h1 class="welcome">Returns Processing</h1>
            <div class="search-container">
                <input type="text" placeholder="Search Issued Records...">
                <button class="btn">Search</button>
            </div>
            <div class="card">
                <h3>Books Currently Out</h3>
                <table>
                    <thead><tr><th>Book</th><th>Due Date</th><th>Action</th></tr></thead>
                    <tbody>
                        <tr><td>The Great Gatsby</td><td>2026-03-08</td>
                        <td><button class="btn btn-sm btn-success">Process Return</button></td></tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>