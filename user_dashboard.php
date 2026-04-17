<?php
session_start();
include("db_connect.php");
if (!isset($_SESSION['user_id'])) { header("Location: user_login.php"); exit(); }

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];
$uploadMsg = '';

// ── Profile Photo ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['profile_photo'])) {
    $file = $_FILES['profile_photo'];
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (in_array($file['type'],$allowed) && $file['size']<2*1024*1024) {
        $ext  = pathinfo($file['name'],PATHINFO_EXTENSION);
        $dest = "uploads/profiles/user_{$user_id}_".time().".$ext";
        @mkdir("uploads/profiles",0755,true);
        if (move_uploaded_file($file['tmp_name'],$dest)) {
            $pdo->prepare("UPDATE users SET profile_photo=? WHERE id=?")->execute([$dest,$user_id]);
            $uploadMsg='photo_ok';
        }
    } else { $uploadMsg='photo_err'; }
}

// ── Profile Save ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_profile'])) {
    $pdo->prepare("UPDATE users SET full_name=?,reg_no=?,department=?,dob=?,address=?,phone=? WHERE id=?")
        ->execute([trim($_POST['full_name']??''),trim($_POST['reg_no']??''),
                   trim($_POST['department']??''),$_POST['dob']?:null,
                   trim($_POST['address']??''),trim($_POST['phone']??''),$user_id]);
    $uploadMsg='profile_ok';
}

// ── Book Request ──────────────────────────────────────────────────────────────
$requestMsg='';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['request_book_id'])) {
    $book_id=(int)$_POST['request_book_id'];
    $chk=$pdo->prepare("SELECT id FROM book_requests WHERE user_id=? AND book_id=? AND status IN ('Pending','Approved')");
    $chk->execute([$user_id,$book_id]);
    if ($chk->fetch()) { $requestMsg='already_requested'; }
    else {
        $c=$pdo->prepare("SELECT copies FROM books WHERE id=? AND copies>0");
        $c->execute([$book_id]);
        if (!$c->fetch()) { $requestMsg='no_copies'; }
        else {
            $pdo->prepare("INSERT INTO book_requests (user_id,book_id,status) VALUES (?,?,'Pending')")->execute([$user_id,$book_id]);
            $requestMsg='success';
        }
    }
}

// ── Deadline Extension ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['extend_request_id'])) {
    $req_id = (int)$_POST['extend_request_id'];
    // Check current extension count and status
    $ext_chk = $pdo->prepare("SELECT br.*, b.title FROM book_requests br JOIN books b ON b.id=br.book_id WHERE br.id=? AND br.user_id=? AND br.status='Approved'");
    $ext_chk->execute([$req_id, $user_id]);
    $ext_row = $ext_chk->fetch();
    $ext_count = (int)($ext_row['extension_count'] ?? 0);
    if ($ext_row && $ext_count < 2 && !$ext_row['returned_at']) {
        $new_due = date('Y-m-d', strtotime($ext_row['due_date'] . ' +10 days'));
        $pdo->prepare("UPDATE book_requests SET due_date=?, extension_count=extension_count+1 WHERE id=?")
            ->execute([$new_due, $req_id]);
        $uploadMsg = 'extend_ok';
    } else {
        $uploadMsg = 'extend_err';
    }
}

// ── Book Review/Report ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['submit_review'])) {
    $book_id = (int)$_POST['review_book_id'];
    $rating  = (int)$_POST['review_rating'];
    $comment = trim($_POST['review_comment'] ?? '');
    $type    = $_POST['review_type'] ?? 'review';
    if ($rating >= 1 && $rating <= 5 && $book_id) {
        $rv = $pdo->prepare("SELECT id FROM book_reviews WHERE user_id=? AND book_id=? AND type=?");
        $rv->execute([$user_id, $book_id, $type]);
        if ($rv->fetch()) {
            $pdo->prepare("UPDATE book_reviews SET rating=?,comment=?,updated_at=NOW() WHERE user_id=? AND book_id=? AND type=?")
                ->execute([$rating,$comment,$user_id,$book_id,$type]);
        } else {
            $pdo->prepare("INSERT INTO book_reviews (user_id,book_id,rating,comment,type) VALUES (?,?,?,?,?)")
                ->execute([$user_id,$book_id,$rating,$comment,$type]);
        }
        $uploadMsg = 'review_ok';
    }
}

// Report book issue (damage/missing/other)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['submit_report'])) {
    $book_id     = (int)$_POST['report_book_id'];
    $report_type = $_POST['report_type'] ?? 'other';
    $report_note = trim($_POST['report_note'] ?? '');
    if ($book_id) {
        $comment_text = "Issue: " . $report_type . "\nNote: " . $report_note;
        $rv = $pdo->prepare("SELECT id FROM book_reviews WHERE user_id=? AND book_id=? AND type='report'");
        $rv->execute([$user_id, $book_id]);
        if ($rv->fetch()) {
            $pdo->prepare("UPDATE book_reviews SET comment=?,updated_at=NOW() WHERE user_id=? AND book_id=? AND type='report'")
                ->execute([$comment_text, $user_id, $book_id]);
        } else {
            $pdo->prepare("INSERT INTO book_reviews (user_id,book_id,rating,comment,type) VALUES (?,?,0,?,'report')")
                ->execute([$user_id,$book_id,$comment_text]);
        }
        $uploadMsg = 'report_ok';
    }
}

// Report website issue
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['submit_website_report'])) {
    $issue_type = $_POST['web_issue_type'] ?? 'other';
    $description = trim($_POST['web_issue_desc'] ?? '');
    if ($description) {
        $pdo->prepare("INSERT INTO website_reports (user_id, issue_type, description) VALUES (?, ?, ?)")
            ->execute([$user_id, $issue_type, $description]);
        $uploadMsg = 'web_report_ok';
    }
}

// ── Purchase Request ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['purchase_title'])) {
    $t=trim($_POST['purchase_title']??'');
    if ($t) {
        $pdo->prepare("INSERT INTO book_purchase_requests (user_id,book_title,author,reason) VALUES (?,?,?,?)")
            ->execute([$user_id,$t,trim($_POST['purchase_author']??''),trim($_POST['purchase_reason']??'')]);
        $uploadMsg='purchase_ok';
    }
}

// ── Fine Payment ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['submit_payment'])) {
    $ss='';
    if (isset($_FILES['pay_screenshot']) && $_FILES['pay_screenshot']['error']===0) {
        $ext=pathinfo($_FILES['pay_screenshot']['name'],PATHINFO_EXTENSION);
        $fn="pay_{$user_id}_".time().".$ext";
        @mkdir("uploads/payments",0755,true);
        if (move_uploaded_file($_FILES['pay_screenshot']['tmp_name'],"uploads/payments/$fn"))
            $ss="uploads/payments/$fn";
    }
    $pdo->prepare("INSERT INTO fine_payments (user_id,request_id,amount,upi_ref,screenshot) VALUES (?,?,?,?,?)")
        ->execute([$user_id,(int)$_POST['pay_request_id'],(float)$_POST['pay_amount'],trim($_POST['upi_ref']??''),$ss]);
    $uploadMsg='payment_ok';
}

// ── Fetch User ────────────────────────────────────────────────────────────────
$uStmt=$pdo->prepare("SELECT * FROM users WHERE id=?"); $uStmt->execute([$user_id]);
$userData=$uStmt->fetch();
if (!$userData) {
    $userData = [
        'full_name' => '', 'reg_no' => '', 'department' => '', 
        'dob' => '', 'phone' => '', 'address' => '', 'profile_photo' => ''
    ];
}
$profilePhoto=$userData['profile_photo']??'';

// ── Fetch Books (deduplicated) with avg rating ────────────────────────────────
$books=$pdo->query("
    SELECT b.*, 
           COALESCE(AVG(r.rating),0) AS avg_rating,
           COUNT(r.id) AS review_count
    FROM (
        SELECT MIN(id) AS id, title, author, isbn,
               SUM(copies) AS copies, MAX(status) AS status,
               category, genre, shelf, description
        FROM books
        GROUP BY LOWER(TRIM(title)), LOWER(TRIM(author))
    ) b
    LEFT JOIN book_reviews r ON r.book_id=b.id
    GROUP BY b.id
    ORDER BY b.title ASC
")->fetchAll();

// ── Course Detection: Categorize books by course keywords ─────────────────────
function detectCourse(string $title, string $author='', string $category='', string $genre=''): string {
    $text = strtolower($title . ' ' . $author . ' ' . $category . ' ' . $genre);

    // BCA – Computer Applications / Programming
    $bcaKeywords = ['programming','python','java','c++','c programming','data structure','algorithm','database','dbms','networking','web development','html','css','javascript','php','software engineering','operating system','computer graphics','microprocessor','computer network','information technology','system analysis','oops','object oriented','visual basic','vb.net','asp.net','dot net','sql','mysql','computer architecture','digital electronics','compiler','automata','theory of computation','bca','computer application'];
    foreach ($bcaKeywords as $kw) { if (str_contains($text, $kw)) return 'BCA'; }

    // MCA – Advanced CS
    $mcaKeywords = ['advanced java','design pattern','software testing','cloud computing','machine learning','artificial intelligence','deep learning','data mining','big data','cyber security','cryptography','mca','distributed system','mobile computing'];
    foreach ($mcaKeywords as $kw) { if (str_contains($text, $kw)) return 'MCA'; }

    // BCom – Commerce
    $bcomKeywords = ['accountancy','accounting','financial accounting','cost accounting','taxation','income tax','gst','auditing','commerce','business law','mercantile','banking','insurance','financial management','corporate law','bcom','tally','economics','macro','micro','statistics','business statistics','entrepreneurship','management accounting'];
    foreach ($bcomKeywords as $kw) { if (str_contains($text, $kw)) return 'BCom'; }

    // BBA – Business Administration
    $bbaKeywords = ['marketing','human resource','hr management','organizational behavior','business management','strategic management','operations management','supply chain','bba','retail management','international business','advertising','consumer behavior','brand management','business communication','principles of management','managerial economics'];
    foreach ($bbaKeywords as $kw) { if (str_contains($text, $kw)) return 'BBA'; }

    // MBA
    $mbaKeywords = ['mba','corporate finance','investment','portfolio','leadership','change management','business ethics','research methodology','quantitative methods','project management'];
    foreach ($mbaKeywords as $kw) { if (str_contains($text, $kw)) return 'MBA'; }

    // MCom – Master of Commerce
    $mcomKeywords = ['mcom','m.com','advanced accounting','advanced cost','advanced financial','indirect tax','direct tax','international finance','commerce research','corporate accounting','securities','capital market','monetary theory','banking theory','financial institutions'];
    foreach ($mcomKeywords as $kw) { if (str_contains($text, $kw)) return 'MCom'; }

    return 'General';
}

// Attach course to each book
foreach ($books as &$b) {
    $b['course'] = detectCourse($b['title'], $b['author'] ?? '', $b['category'] ?? '', $b['genre'] ?? '');
}
unset($b);

// Build course counts for the filter tabs
$courseCounts = ['All' => count($books)];
foreach ($books as $b) {
    $c = $b['course'];
    $courseCounts[$c] = ($courseCounts[$c] ?? 0) + 1;
}
// MCom keywords
// (added in detectCourse below — MCom detection added separately via $mcomKeywords)
$courseOrder = ['All', 'BCA', 'MCA', 'BCom', 'MCom', 'BBA', 'MBA', 'General'];
$courseEmojis = ['All'=>'📚','BCA'=>'💻','MCA'=>'🖥️','BCom'=>'📊','MCom'=>'📒','BBA'=>'📈','MBA'=>'🏢','General'=>'📖'];

// ── Fetch My Requests ─────────────────────────────────────────────────────────
$rs=$pdo->prepare("SELECT br.*,b.title,b.author,b.shelf,b.category FROM book_requests br JOIN books b ON b.id=br.book_id WHERE br.user_id=? ORDER BY br.requested_at DESC LIMIT 20");
$rs->execute([$user_id]);
$myRequests=$rs->fetchAll();

// ── Auto Fines ₹5/day ─────────────────────────────────────────────────────────
$today=new DateTime();
foreach ($myRequests as &$req) {
    if ($req['status']==='Approved' && $req['due_date'] && !$req['returned_at'] && !($req['fine_paid']??0)) {
        $due=new DateTime($req['due_date']);
        if ($today>$due) {
            $daysLate = (int)$today->diff($due)->days;
            $fine = $daysLate * 5; // ₹5 per day
            $pdo->prepare("UPDATE book_requests SET fine_amount=? WHERE id=?")->execute([$fine,$req['id']]);
            $req['fine_amount'] = $fine;
            $req['days_late']   = $daysLate;
        }
    }
}
unset($req);

$totalFineAmt=array_sum(array_column(array_filter($myRequests,fn($r)=>($r['fine_amount']??0)>0&&!$r['returned_at']),'fine_amount'));

// ── Pending Payments ──────────────────────────────────────────────────────────
$pp=$pdo->prepare("SELECT request_id FROM fine_payments WHERE user_id=? AND status='Pending'");
$pp->execute([$user_id]);
$pendingPayIds=array_column($pp->fetchAll(),'request_id');

// ── New Approvals ─────────────────────────────────────────────────────────────
$na=$pdo->prepare("SELECT br.id,b.title,br.due_date FROM book_requests br JOIN books b ON b.id=br.book_id WHERE br.user_id=? AND br.status='Approved' AND (br.notified IS NULL OR br.notified=0)");
$na->execute([$user_id]);
$newApprovals=$na->fetchAll();
foreach ($newApprovals as $a) $pdo->prepare("UPDATE book_requests SET notified=1 WHERE id=?")->execute([$a['id']]);

// ── User's own reviews ────────────────────────────────────────────────────────
$myReviews=$pdo->prepare("SELECT book_id,rating,comment,type FROM book_reviews WHERE user_id=? AND type='review'");
$myReviews->execute([$user_id]);
$myReviewsMap=[];
foreach($myReviews->fetchAll() as $rv) $myReviewsMap[$rv['book_id']]=$rv;

// ── UPI Config ────────────────────────────────────────────────────────────────
$UPI_ID="library@upi"; $UPI_NAME="LIBRITE Library"; $QR_IMAGE="uploads/qr_code.jpeg";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard | LIBRITE</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#02040a;--card:#0d1117;--card2:#161b22;--border:#30363d;--accent:#238636;--muted:#8b949e;--main:#c9d1d9;--err:#f85149;--warn:#e3b341;--blue:#58a6ff;}
*{box-sizing:border-box;}
body{background:var(--bg);color:var(--main);font-family:'Space Grotesk',sans-serif;margin:0;}
.card{background:var(--card);border:1px solid var(--border);border-radius:12px;}
.dark-input{background:var(--card);border:1px solid var(--border);color:white;border-radius:6px;padding:8px 12px;outline:none;width:100%;}
.dark-input:focus{border-color:var(--blue);}
.badge{padding:2px 10px;border-radius:20px;font-size:.75rem;font-weight:600;display:inline-block;}
.badge-available{background:rgba(35,134,54,.15);color:#3fb950;border:1px solid rgba(63,185,80,.3);}
.badge-borrowed{background:rgba(210,153,34,.15);color:#d29922;border:1px solid rgba(210,153,34,.3);}
.badge-lost{background:rgba(248,81,73,.15);color:#f85149;border:1px solid rgba(248,81,73,.3);}
.badge-pending{background:rgba(88,166,255,.15);color:#58a6ff;border:1px solid rgba(88,166,255,.3);}
.badge-approved{background:rgba(35,134,54,.15);color:#3fb950;border:1px solid rgba(63,185,80,.3);}
.badge-rejected{background:rgba(248,81,73,.15);color:#f85149;border:1px solid rgba(248,81,73,.3);}
th{background:#161b22;padding:12px 20px;color:var(--muted);font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--border);}
td{padding:12px 20px;border-bottom:1px solid var(--border);vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover{background:rgba(255,255,255,.02);}
.tab-active{border-bottom:2px solid var(--blue);color:var(--blue);}
.tab-inactive{border-bottom:2px solid transparent;color:var(--muted);}

/* Profile panel */
#profilePanel{position:fixed;top:0;right:-440px;width:420px;height:100vh;background:var(--card);border-left:1px solid var(--border);z-index:9000;overflow-y:auto;transition:right .35s cubic-bezier(.4,0,.2,1);}
#profilePanel.open{right:0;box-shadow:-20px 0 60px rgba(0,0,0,.6);}
#panelOverlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:8999;}
#panelOverlay.open{display:block;}
.avatar-ring{width:96px;height:96px;border-radius:50%;background:conic-gradient(#238636 0deg,#3fb950 120deg,#58a6ff 240deg,#238636 360deg);padding:3px;position:relative;cursor:pointer;}
.avatar-inner{width:100%;height:100%;border-radius:50%;overflow:hidden;background:#21262d;display:flex;align-items:center;justify-content:center;}
.avatar-overlay{position:absolute;inset:0;border-radius:50%;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .2s;}
.avatar-ring:hover .avatar-overlay{opacity:1;}
.profile-hero{background:linear-gradient(135deg,#0d2818 0%,#0d1117 50%,#0d1526 100%);border-bottom:1px solid var(--border);padding:28px 24px 22px;}
.info-row{display:flex;align-items:flex-start;gap:12px;padding:11px 0;border-bottom:1px solid rgba(48,54,61,.5);}
.info-row:last-child{border-bottom:none;}
.info-label{color:var(--muted);font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px;}
.info-value{color:var(--main);font-size:.875rem;font-weight:500;}
.info-icon{width:32px;height:32px;border-radius:8px;background:rgba(88,166,255,.1);border:1px solid rgba(88,166,255,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;}
.btn-save{background:#238636;color:white;border:none;padding:9px 20px;border-radius:8px;font-weight:600;cursor:pointer;font-size:.875rem;}
.btn-save:hover{background:#2ea043;}
.btn-cancel{background:transparent;color:var(--muted);border:1px solid var(--border);padding:9px 16px;border-radius:8px;cursor:pointer;}
.file-input-label{display:flex;align-items:center;gap:8px;padding:8px 14px;border:1px dashed var(--border);border-radius:8px;cursor:pointer;font-size:.82rem;color:var(--muted);}
.file-input-label:hover{border-color:var(--blue);color:var(--blue);}
input[type=file]{display:none;}
.header-avatar{width:36px;height:36px;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid var(--accent);font-weight:700;font-size:.82rem;background:#21262d;}

/* Toast */
#toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(60px);opacity:0;transition:all .3s;z-index:9999;border-radius:999px;padding:10px 22px;font-size:.875rem;font-weight:600;white-space:nowrap;}
#toast.show{transform:translateX(-50%) translateY(0);opacity:1;}

/* Fine payment */
.fine-bar{background:linear-gradient(90deg,rgba(248,81,73,.12),rgba(248,81,73,.04));border:1px solid rgba(248,81,73,.25);border-radius:12px;padding:16px 20px;}
.pay-method-btn{padding:8px 18px;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;border:none;}
.pay-method-btn.active{background:#238636;color:white;}
.pay-method-btn.inactive{background:transparent;color:var(--muted);border:1px solid var(--border);}
.qr-wrap{background:white;border-radius:14px;padding:14px;display:inline-flex;}
.qr-wrap img{width:200px;height:200px;border-radius:4px;}
.step-badge{width:24px;height:24px;border-radius:50%;background:rgba(35,134,54,.2);border:1px solid rgba(63,185,80,.3);color:#3fb950;font-size:.72rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;}

/* ═══ ENHANCED BOOK CARDS ══════════════════════════════════════════════════ */
.books-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px;}

.book-card{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:18px;
    overflow:hidden;
    display:flex;
    flex-direction:column;
    position:relative;
    transition:transform .3s cubic-bezier(.4,0,.2,1), box-shadow .3s, border-color .3s;
    cursor:pointer;
}
.book-card:hover{
    transform:translateY(-8px) scale(1.01);
    box-shadow:0 24px 60px rgba(0,0,0,.7), 0 0 0 1px rgba(88,166,255,.25);
    border-color:rgba(88,166,255,.45);
}

/* Cover */
.bc-img{height:200px;overflow:hidden;position:relative;background:#161b22;flex-shrink:0;}
.bc-img img{width:100%;height:100%;object-fit:cover;transition:transform .4s ease;}
.book-card:hover .bc-img img{transform:scale(1.09);}
.bc-placeholder{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;}
/* Gradient overlay that reveals on hover */
.bc-overlay{
    position:absolute;inset:0;
    background:linear-gradient(to top, rgba(2,4,10,.97) 0%, rgba(2,4,10,.75) 45%, rgba(2,4,10,.1) 100%);
    opacity:0;transition:opacity .3s;
    display:flex;flex-direction:column;justify-content:flex-end;padding:14px;
}
.book-card:hover .bc-overlay{opacity:1;}
.bc-detail-desc{
    font-size:.72rem;color:rgba(255,255,255,.82);line-height:1.55;
    display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden;
    margin-bottom:8px;
}
.bc-detail-meta{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:4px;}
.bc-detail-isbn{font-size:.6rem;font-family:monospace;color:rgba(255,255,255,.35);margin-top:2px;}

/* Copies pill */
.bc-copies{position:absolute;top:8px;right:8px;border-radius:20px;padding:3px 10px;font-size:.68rem;font-weight:700;backdrop-filter:blur(8px);display:flex;align-items:center;gap:4px;background:rgba(0,0,0,.8);}
.bc-copies.c-ok {border:1px solid rgba(63,185,80,.5);color:#3fb950;}
.bc-copies.c-low{border:1px solid rgba(251,191,36,.5);color:#fbbf24;}
.bc-copies.c-out{border:1px solid rgba(248,81,73,.5);color:#f85149;}

/* Rating stars */
.bc-stars{position:absolute;top:8px;left:8px;backdrop-filter:blur(8px);background:rgba(0,0,0,.75);border:1px solid rgba(255,215,0,.25);border-radius:20px;padding:3px 9px;font-size:.68rem;font-weight:700;color:#fbbf24;display:flex;align-items:center;gap:3px;}

/* Body */
.bc-body{padding:14px 15px 10px;flex:1;display:flex;flex-direction:column;gap:5px;}
.bc-title{font-weight:700;color:white;font-size:.92rem;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.bc-author{color:var(--muted);font-size:.76rem;font-weight:500;font-style:italic;}
.bc-tags{display:flex;gap:5px;flex-wrap:wrap;margin-top:3px;}
.bc-tag{font-size:.65rem;padding:2px 7px;border-radius:10px;font-weight:600;}
.bc-tag-cat{background:rgba(88,166,255,.1);color:#58a6ff;border:1px solid rgba(88,166,255,.2);}
.bc-tag-shelf{background:rgba(255,255,255,.05);color:var(--muted);border:1px solid rgba(255,255,255,.07);}
.bc-tag-genre{background:rgba(167,139,250,.1);color:#a78bfa;border:1px solid rgba(167,139,250,.2);}
.bc-desc{font-size:.75rem;color:rgba(255,255,255,.45);line-height:1.55;margin-top:4px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;flex:1;}
.bc-isbn{font-size:.62rem;font-family:monospace;color:rgba(255,255,255,.2);margin-top:2px;}

/* Footer */
.bc-footer{padding:0 14px 14px;display:flex;gap:6px;}
.bc-btn{flex:1;padding:9px 6px;border-radius:10px;font-weight:700;font-size:.78rem;cursor:pointer;border:none;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:5px;}
.bc-btn.can{background:linear-gradient(135deg,#238636,#1a7f37);color:white;box-shadow:0 4px 14px rgba(35,134,54,.3);}
.bc-btn.can:hover{background:linear-gradient(135deg,#2ea043,#238636);transform:translateY(-1px);}
.bc-btn.done{background:rgba(88,166,255,.1);color:#58a6ff;border:1px solid rgba(88,166,255,.3);cursor:default;}
.bc-btn.none{background:rgba(255,255,255,.04);color:var(--muted);border:1px solid rgba(255,255,255,.07);cursor:not-allowed;}
.bc-btn.action-btn{background:rgba(255,255,255,.05);color:var(--muted);border:1px solid rgba(255,255,255,.15);flex:0 0 auto;padding:9px 10px;}
.bc-btn.action-btn:hover{background:rgba(255,255,255,.1);color:white;}

/* ═══ MODAL ════════════════════════════════════════════════════════════════ */
.modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.85);backdrop-filter:blur(8px);z-index:9500;display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;pointer-events:none;transition:opacity .25s;}
.modal-bg.open{opacity:1;pointer-events:all;}
.modal-box{background:var(--card);border:1px solid var(--border);border-radius:20px;width:100%;max-width:440px;max-height:90vh;overflow-y:auto;transform:translateY(20px) scale(.97);transition:transform .25s;}
.modal-bg.open .modal-box{transform:none;}
.star-selector{display:flex;gap:6px;justify-content:center;margin:12px 0;}
.star-selector label{cursor:pointer;font-size:1.6rem;transition:transform .15s;user-select:none;}
.star-selector label:hover,.star-selector input:checked ~ label{transform:scale(1.2);}
.star-selector input{display:none;}

/* Extension button */
.btn-extend{background:linear-gradient(135deg,#1d4ed8,#1e40af);color:white;border:none;padding:7px 14px;border-radius:8px;font-weight:600;font-size:.78rem;cursor:pointer;display:inline-flex;align-items:center;gap:5px;}
.btn-extend:hover{background:linear-gradient(135deg,#2563eb,#1d4ed8);}
.btn-extend:disabled{background:rgba(255,255,255,.05);color:var(--muted);border:1px solid var(--border);cursor:not-allowed;}

/* AI FAB */
#ai-fab{position:fixed;bottom:28px;right:28px;z-index:8000;width:58px;height:58px;border-radius:50%;background:linear-gradient(135deg,#238636,#1a7f37);border:2px solid rgba(63,185,80,.4);box-shadow:0 8px 30px rgba(35,134,54,.45);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.5rem;transition:transform .25s,box-shadow .25s;text-decoration:none;animation:fabPulse 2.5s ease-in-out infinite;}
#ai-fab:hover{transform:scale(1.12);box-shadow:0 12px 40px rgba(35,134,54,.65);}
@keyframes fabPulse{0%,100%{box-shadow:0 8px 30px rgba(35,134,54,.45)}50%{box-shadow:0 8px 40px rgba(35,134,54,.7)}}
#ai-fab-label{position:absolute;bottom:68px;right:8px;background:var(--card);border:1px solid var(--border);color:white;font-size:.72rem;font-weight:600;padding:4px 10px;border-radius:8px;white-space:nowrap;box-shadow:0 4px 12px rgba(0,0,0,.4);pointer-events:none;opacity:0;transition:opacity .2s;}
#ai-fab:hover ~ #ai-fab-label,#ai-fab-wrap:hover #ai-fab-label{opacity:1;}

@media(max-width:480px){#profilePanel{width:100vw;right:-100vw;}.books-grid{grid-template-columns:repeat(2,1fr);gap:10px;}#ai-fab{bottom:16px;right:16px;}}
</style>
</head>
<body>
<div id="toast"></div>

<?php
$toastMap=[
    'photo_ok'=>['✅ Photo updated!','#238636'],
    'profile_ok'=>['✅ Profile saved!','#238636'],
    'photo_err'=>['❌ Use JPG/PNG under 2MB','#f85149'],
    'purchase_ok'=>['✅ Request sent to admin!','#238636'],
    'payment_ok'=>['✅ Payment submitted!','#238636'],
    'review_ok'=>['⭐ Review submitted!','#d97706'],
    'report_ok'=>['🚨 Report submitted!','#b91c1c'],
    'extend_ok'=>['📅 Deadline extended by 10 days!','#1d4ed8'],
    'extend_err'=>['❌ Cannot extend further (max 2 times).','#f85149'],
    'web_report_ok'=>['🚨 Website report sent to admin!','#b91c1c'],
];
if (isset($toastMap[$uploadMsg])): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast('<?=$toastMap[$uploadMsg][0]?>','<?=$toastMap[$uploadMsg][1]?>'));</script>
<?php endif; ?>

<!-- Approval popup -->
<?php if (!empty($newApprovals)): ?>
<div id="apprOv" class="fixed inset-0 z-[9999] flex items-center justify-center p-4" style="background:rgba(0,0,0,.85);backdrop-filter:blur(6px)">
  <div class="card max-w-md w-full p-6 text-center">
    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background:rgba(35,134,54,.2);border:1px solid rgba(63,185,80,.3)">
        <i data-lucide="check-circle-2" class="w-8 h-8 text-emerald-400"></i>
    </div>
    <h2 class="text-xl font-bold text-white mb-2">🎉 Request Approved!</h2>
    <?php foreach ($newApprovals as $a): ?>
    <p class="mb-1" style="color:var(--muted)"><strong class="text-white"><?=htmlspecialchars($a['title'])?></strong> approved.
    <?php if($a['due_date']):?><br><span class="text-yellow-400 text-sm">Return by <strong><?=date('d M Y',strtotime($a['due_date']))?></strong></span><?php endif;?></p>
    <?php endforeach; ?>
    <p class="text-xs mt-3 mb-5" style="color:var(--muted)">Collect from library counter. Return within <strong>10 days</strong>. Fine: ₹5/day after due date. You may extend up to <strong>2 times (10 days each)</strong>.</p>
    <button onclick="document.getElementById('apprOv').remove()" class="bg-emerald-600 text-white px-8 py-2 rounded-lg font-bold">Got it!</button>
  </div>
</div>
<?php endif; ?>

<?php
$hasLateWarning = false;
foreach($myRequests as $r) {
    if (($r['days_late'] ?? 0) >= 29) {
        $hasLateWarning = true;
        break;
    }
}
if ($hasLateWarning): ?>
<div class="fixed inset-0 z-[9999] flex items-center justify-center p-4" style="background:rgba(0,0,0,.85);backdrop-filter:blur(6px)" id="lateWarningOv">
  <div class="card max-w-md w-full p-6 text-center" style="border-color:#f87171;">
    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background:rgba(248,81,73,.2);border:1px solid rgba(248,81,73,.3)">
        <i data-lucide="alert-triangle" class="w-8 h-8 text-red-500"></i>
    </div>
    <h2 class="text-xl font-bold text-white mb-2">⚠️ Message from Admin</h2>
    <p class="mb-4 text-red-400 font-semibold text-lg">Your account will be reported if you do not pay the fine.</p>
    <p class="text-sm mb-5" style="color:var(--muted)">You have a book that is 29 (or more) days overdue. Return the book immediately and clear your fines to avoid strict actions.</p>
    <button onclick="document.getElementById('lateWarningOv').remove()" class="bg-red-600 hover:bg-red-500 text-white px-8 py-2 rounded-lg font-bold transition-colors">Acknowledge</button>
  </div>
</div>
<?php endif; ?>

<div id="panelOverlay" onclick="closeProfile()"></div>

<!-- ══ REVIEW MODAL ══════════════════════════════════════════════════════════ -->
<div id="reviewModal" class="modal-bg">
  <div class="modal-box">
    <div class="flex items-center justify-between px-6 pt-5 pb-4" style="border-bottom:1px solid var(--border)">
      <h3 class="text-white font-bold text-base flex items-center gap-2">⭐ Rate &amp; Review Book</h3>
      <button onclick="closeModal('reviewModal')" class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-white/10"><i data-lucide="x" class="w-4 h-4" style="color:var(--muted)"></i></button>
    </div>
    <form method="POST" class="px-6 py-5 space-y-4">
      <input type="hidden" name="submit_review" value="1">
      <input type="hidden" name="review_book_id" id="reviewBookId">
      <input type="hidden" name="review_type" value="review">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wider mb-1" style="color:var(--muted)">Book</p>
        <p class="text-white font-bold" id="reviewBookTitle">—</p>
        <p class="text-xs italic" style="color:var(--muted)" id="reviewBookAuthor">—</p>
      </div>
      <div>
        <p class="text-xs font-semibold uppercase tracking-wider mb-2" style="color:var(--muted)">Your Rating</p>
        <div class="star-selector" id="starSelector">
          <?php for($s=5;$s>=1;$s--): ?>
          <input type="radio" name="review_rating" id="star<?=$s?>" value="<?=$s?>">
          <label for="star<?=$s?>" title="<?=$s?> stars">★</label>
          <?php endfor; ?>
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Your Review (optional)</label>
        <textarea name="review_comment" id="reviewComment" rows="3" class="dark-input resize-none" placeholder="What did you think of this book?"></textarea>
      </div>
      <div class="flex gap-3">
        <button type="submit" class="btn-save flex-1">Submit Review</button>
        <button type="button" onclick="closeModal('reviewModal')" class="btn-cancel">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ REPORT MODAL ══════════════════════════════════════════════════════════ -->
<div id="reportModal" class="modal-bg">
  <div class="modal-box">
    <div class="flex items-center justify-between px-6 pt-5 pb-4" style="border-bottom:1px solid var(--border)">
      <h3 class="text-white font-bold text-base flex items-center gap-2"><span style="color:#f87171">🚨</span> Report Book Issue</h3>
      <button onclick="closeModal('reportModal')" class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-white/10"><i data-lucide="x" class="w-4 h-4" style="color:var(--muted)"></i></button>
    </div>
    <form method="POST" class="px-6 py-5 space-y-4">
      <input type="hidden" name="submit_report" value="1">
      <input type="hidden" name="report_book_id" id="reportBookId">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wider mb-1" style="color:var(--muted)">Book</p>
        <p class="text-white font-bold" id="reportBookTitle">—</p>
      </div>
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Issue Type</label>
        <select name="report_type" class="dark-input">
          <option value="damaged">📖 Damaged / Torn Pages</option>
          <option value="missing_pages">📄 Missing Pages</option>
          <option value="wrong_shelf">📍 Wrong Shelf Location</option>
          <option value="lost">🔍 Book Cannot Be Found</option>
          <option value="duplicate">♊ Duplicate Entry</option>
          <option value="other">⚠️ Other Issue</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Details</label>
        <textarea name="report_note" rows="3" class="dark-input resize-none" placeholder="Describe the issue in detail..."></textarea>
      </div>
      <div class="flex gap-3">
        <button type="submit" class="w-full py-2.5 rounded-lg font-bold text-white text-sm" style="background:#b91c1c">🚨 Submit Report</button>
        <button type="button" onclick="closeModal('reportModal')" class="btn-cancel">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ WEBSITE REPORT MODAL ══════════════════════════════════════════════════════════ -->
<div id="webReportModal" class="modal-bg">
  <div class="modal-box">
    <div class="flex items-center justify-between px-6 pt-5 pb-4" style="border-bottom:1px solid var(--border)">
      <h3 class="text-white font-bold text-base flex items-center gap-2"><span style="color:#e3b341">⚠️</span> Report Website Issue</h3>
      <button onclick="closeModal('webReportModal')" class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-white/10"><i data-lucide="x" class="w-4 h-4" style="color:var(--muted)"></i></button>
    </div>
    <form method="POST" class="px-6 py-5 space-y-4">
      <input type="hidden" name="submit_website_report" value="1">
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Issue Type</label>
        <select name="web_issue_type" class="dark-input">
          <option value="bug">🐛 Bug / Error</option>
          <option value="ui">🎨 UI / Layout Issue</option>
          <option value="feature">💡 Feature Request</option>
          <option value="other">⚠️ Other</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Details</label>
        <textarea name="web_issue_desc" rows="4" class="dark-input resize-none" placeholder="Describe the issue or mistake you found..." required></textarea>
      </div>
      <div class="flex gap-3">
        <button type="submit" class="w-full py-2.5 rounded-lg font-bold text-white text-sm" style="background:#b91c1c">🚀 Submit Report</button>
        <button type="button" onclick="closeModal('webReportModal')" class="btn-cancel">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ ACTION MODAL ══════════════════════════════════════════════════════════ -->
<div id="actionModal" class="modal-bg">
  <div class="modal-box" style="max-width:320px">
    <div class="flex items-center justify-between px-6 pt-5 pb-4" style="border-bottom:1px solid var(--border)">
      <h3 class="text-white font-bold text-base flex items-center gap-2">Book Options</h3>
      <button onclick="closeModal('actionModal')" class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-white/10"><i data-lucide="x" class="w-4 h-4" style="color:var(--muted)"></i></button>
    </div>
    <div class="px-6 py-5 space-y-3">
        <button onclick="closeModal('actionModal'); setTimeout(openActionReview, 200);" class="w-full flex justify-center items-center gap-2 py-2.5 rounded-lg font-bold text-sm transition-colors" style="background:rgba(251,191,36,.1);color:#fbbf24;border:1px solid rgba(251,191,36,.3)">
            <i data-lucide="star" class="w-4 h-4"></i> Rate &amp; Review
        </button>
        <button onclick="closeModal('actionModal'); setTimeout(openActionReport, 200);" class="w-full flex justify-center items-center gap-2 py-2.5 rounded-lg font-bold text-sm transition-colors" style="background:rgba(248,81,73,.1);color:#f87171;border:1px solid rgba(248,81,73,.3)">
            <i data-lucide="flag" class="w-4 h-4"></i> Report an Issue
        </button>
    </div>
  </div>
</div>

<!-- ══ PROFILE PANEL ══════════════════════════════════════════════════════════ -->
<div id="profilePanel">
    <div class="profile-hero">
        <div class="flex items-start justify-between mb-5">
            <span class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted)">My Profile</span>
            <button onclick="closeProfile()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white/10">
                <i data-lucide="x" class="w-4 h-4" style="color:var(--muted)"></i>
            </button>
        </div>
        <div class="flex items-end gap-4">
            <div class="avatar-ring" onclick="switchProfileTab('edit');document.getElementById('photoFileInput').click()">
                <div class="avatar-inner">
                    <?php if($profilePhoto && file_exists($profilePhoto)):?>
                        <img src="<?=htmlspecialchars($profilePhoto)?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php else:?>
                        <span class="text-2xl font-bold text-white"><?=strtoupper(substr($userData['full_name']?:$username,0,1))?></span>
                    <?php endif;?>
                </div>
                <div class="avatar-overlay"><i data-lucide="camera" class="w-5 h-5 text-white"></i></div>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-white font-bold text-lg truncate"><?=htmlspecialchars($userData['full_name']?:$username)?></h3>
                <?php if(!empty($userData['reg_no'])):?><p class="text-xs font-mono mt-0.5" style="color:var(--muted)"><?=htmlspecialchars($userData['reg_no'])?></p><?php endif;?>
                <?php if(!empty($userData['department'])):?><span class="text-xs px-2 py-0.5 rounded-full mt-1.5 inline-block" style="background:rgba(35,134,54,.2);color:#3fb950;border:1px solid rgba(35,134,54,.3)"><?=htmlspecialchars($userData['department'])?></span><?php endif;?>
            </div>
        </div>
        <?php
        $tReqs=count($myRequests);
        $aBooks=count(array_filter($myRequests,fn($r)=>$r['status']==='Approved'&&!($r['returned_at']??null)));
        $tFine=array_sum(array_column(array_filter($myRequests,fn($r)=>($r['fine_amount']??0)>0),'fine_amount'));
        ?>
        <div class="grid grid-cols-3 gap-2 mt-5">
            <div class="text-center p-2.5 rounded-lg" style="background:rgba(255,255,255,.04);border:1px solid var(--border)">
                <div class="text-lg font-bold text-white"><?=$tReqs?></div>
                <div class="text-[10px]" style="color:var(--muted)">Requests</div>
            </div>
            <div class="text-center p-2.5 rounded-lg" style="background:rgba(255,255,255,.04);border:1px solid var(--border)">
                <div class="text-lg font-bold text-emerald-400"><?=$aBooks?></div>
                <div class="text-[10px]" style="color:var(--muted)">Holding</div>
            </div>
            <div class="text-center p-2.5 rounded-lg" style="background:rgba(255,255,255,.04);border:1px solid var(--border)">
                <div class="text-lg font-bold <?=$tFine>0?'text-red-400':'text-white'?>">₹<?=$tFine?></div>
                <div class="text-[10px]" style="color:var(--muted)">Fine</div>
            </div>
        </div>
    </div>
    <div class="flex border-b" style="border-color:var(--border)">
        <button onclick="switchProfileTab('view')" id="ptab-view" class="flex-1 py-3 text-sm font-medium tab-active">Details</button>
        <button onclick="switchProfileTab('edit')" id="ptab-edit" class="flex-1 py-3 text-sm font-medium tab-inactive">Edit Profile</button>
    </div>
    <div id="ppane-view" class="p-5">
        <?php $pFields=[['user','Full Name',$userData['full_name']??'—'],['hash','Register No.',$userData['reg_no']??'—'],['graduation-cap','Department',$userData['department']??'—'],['phone','Phone',$userData['phone']??'—'],['mail','Username',$username],['map-pin','Address',$userData['address']??'—']];
        foreach($pFields as [$ic,$lb,$vl]):?>
        <div class="info-row">
            <div class="info-icon"><i data-lucide="<?=$ic?>" class="w-3.5 h-3.5" style="color:#58a6ff"></i></div>
            <div><div class="info-label"><?=$lb?></div><div class="info-value"><?=htmlspecialchars($vl)?></div></div>
        </div>
        <?php endforeach;?>
        <div class="mt-6 pt-5" style="border-top:1px solid var(--border)">
            <button onclick="openModal('webReportModal')" class="flex items-center gap-2 text-sm font-medium w-full text-left mb-4" style="color:var(--warn);transition:color .2s;">
                <i data-lucide="alert-triangle" class="w-4 h-4"></i> Report Website Issue
            </button>
            <a href="index.php" class="flex items-center gap-2 text-sm font-medium text-red-400 hover:text-red-300 transition-colors"><i data-lucide="log-out" class="w-4 h-4"></i> Logout</a>
        </div>
    </div>
    <div id="ppane-edit" class="p-5 hidden">
        <form method="POST" enctype="multipart/form-data" class="mb-5 pb-5" style="border-bottom:1px solid var(--border)">
            <p class="text-xs font-semibold uppercase tracking-wider mb-2.5" style="color:var(--muted)">Profile Photo</p>
            <div class="flex items-center gap-3">
                <label class="file-input-label flex-1" for="photoFileInput"><i data-lucide="upload" class="w-4 h-4"></i><span id="fileLabel">Choose image</span></label>
                <input type="file" id="photoFileInput" name="profile_photo" accept="image/*" onchange="previewPhoto(this)">
                <button type="submit" class="btn-save">Upload</button>
            </div>
        </form>
        <form method="POST">
            <input type="hidden" name="save_profile" value="1">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Full Name</label><input type="text" name="full_name" class="dark-input" value="<?=htmlspecialchars($userData['full_name']??'')?>"></div>
                    <div><label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Register No.</label><input type="text" name="reg_no" class="dark-input" value="<?=htmlspecialchars($userData['reg_no']??'')?>"></div>
                </div>
                <div><label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Department</label>
                <select name="department" class="dark-input">
                    <option value="">— Select —</option>
                    <?php foreach(['BCA','Information Technology','BCom','BBA','MBA','MCA','Other'] as $d):?>
                    <option value="<?=$d?>" <?=($userData['department']??'')===$d?'selected':''?>><?=$d?></option>
                    <?php endforeach;?>
                </select></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">DOB</label><input type="date" name="dob" class="dark-input" value="<?=htmlspecialchars($userData['dob']??'')?>"></div>
                    <div><label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Phone</label><input type="text" name="phone" class="dark-input" value="<?=htmlspecialchars($userData['phone']??'')?>"></div>
                </div>
                <div><label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Address</label><textarea name="address" rows="3" class="dark-input resize-none"><?=htmlspecialchars($userData['address']??'')?></textarea></div>
            </div>
            <div class="flex gap-3 mt-5">
                <button type="submit" class="btn-save flex-1">Save Changes</button>
                <button type="button" class="btn-cancel" onclick="switchProfileTab('view')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ HEADER ════════════════════════════════════════════════════════════════ -->
<header style="background:var(--card);border-bottom:1px solid var(--border)" class="sticky top-0 z-50 px-6 py-3 flex items-center justify-between">
    <div class="flex items-center gap-3">
        <div style="background:#238636" class="p-2 rounded-lg"><i data-lucide="book-open" class="w-5 h-5 text-white"></i></div>
        <span class="font-bold text-white text-lg">LIBRITE</span>
    </div>
    <div class="flex items-center gap-3">
        <?php $pendingCount=count(array_filter($myRequests,fn($r)=>$r['status']==='Pending'));?>
        <?php if($pendingCount>0):?>
        <span class="text-xs px-2 py-1 rounded-full" style="background:rgba(227,179,65,.2);color:#e3b341;border:1px solid rgba(227,179,65,.3)">
            <i data-lucide="clock" class="inline w-3 h-3"></i> <?=$pendingCount?> pending
        </span>
        <?php endif;?>
        <?php if($totalFineAmt>0):?>
        <button onclick="switchTab('fines')" class="text-xs px-2 py-1 rounded-full" style="background:rgba(248,81,73,.2);color:#f85149;border:1px solid rgba(248,81,73,.3)">
            <i data-lucide="alert-triangle" class="inline w-3 h-3"></i> ₹<?=$totalFineAmt?> fine
        </button>
        <?php endif;?>
        <div class="header-avatar" onclick="openProfile()">
            <?php if($profilePhoto && file_exists($profilePhoto)):?>
                <img src="<?=htmlspecialchars($profilePhoto)?>" style="width:100%;height:100%;object-fit:cover;">
            <?php else:?><?=strtoupper(substr($userData['full_name']?:$username,0,1))?><?php endif;?>
        </div>
    </div>
</header>

<!-- ══ MAIN ══════════════════════════════════════════════════════════════════ -->
<div class="max-w-6xl mx-auto px-4 py-8">
<div class="mb-8 pb-6" style="border-bottom:1px solid var(--border)">
    <h1 class="text-2xl font-bold text-white">Welcome back, <?=htmlspecialchars($userData['full_name']?:$username)?> 👋</h1>
    <p class="italic mt-1 text-sm" style="color:var(--muted)">"A reader lives a thousand lives before he dies."</p>
    <div class="flex gap-3 mt-3 flex-wrap">
        <div class="text-xs px-3 py-1.5 rounded" style="background:rgba(227,179,65,.1);border-left:3px solid var(--warn)">📅 Loan limit: <strong>10 days</strong></div>
        <div class="text-xs px-3 py-1.5 rounded" style="background:rgba(248,81,73,.1);border-left:3px solid var(--err)">💰 Fine: <strong>₹5/day</strong> after due date</div>
        <div class="text-xs px-3 py-1.5 rounded" style="background:rgba(29,78,216,.1);border-left:3px solid #3b82f6">🔄 Extensions: <strong>Max 2× (10 days each)</strong></div>
    </div>
</div>

<!-- TABS -->
<div class="flex gap-4 mb-6 border-b overflow-x-auto" style="border-color:var(--border)">
    <button onclick="switchTab('browse')"   id="tab-browse"   class="tab-btn pb-3 text-sm font-medium tab-active whitespace-nowrap">📚 Browse Books</button>
    <button onclick="switchTab('requests')" id="tab-requests" class="tab-btn pb-3 text-sm font-medium tab-inactive whitespace-nowrap">🗂 My Requests (<?=count($myRequests)?>)</button>
    <button onclick="switchTab('fines')"    id="tab-fines"    class="tab-btn pb-3 text-sm font-medium tab-inactive whitespace-nowrap">💳 Fine<?php if($totalFineAmt>0):?> <span class="text-red-400 font-bold">₹<?=$totalFineAmt?></span><?php endif;?></button>
    <button onclick="switchTab('purchase')" id="tab-purchase" class="tab-btn pb-3 text-sm font-medium tab-inactive whitespace-nowrap">📋 Request a Book</button>
</div>

<!-- ══ BROWSE ════════════════════════════════════════════════════════════════ -->
<div id="pane-browse">
    <?php if($requestMsg==='success'):?><div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium" style="background:rgba(35,134,54,.15);border:1px solid rgba(63,185,80,.3);color:#3fb950">✅ Request submitted! Admin will review shortly.</div>
    <?php elseif($requestMsg==='already_requested'):?><div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium" style="background:rgba(248,81,73,.1);border:1px solid rgba(248,81,73,.3);color:#f85149">⚠️ You already have an active request for this book.</div>
    <?php elseif($requestMsg==='no_copies'):?><div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium" style="background:rgba(248,81,73,.1);border:1px solid rgba(248,81,73,.3);color:#f85149">⚠️ No copies available. Request a purchase in the last tab!</div>
    <?php endif;?>

    <!-- ── Search & Filter ──────────────────────────────────── -->
    <div class="flex flex-col md:flex-row gap-3 mb-5">
        <div class="relative flex-1">
            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color:var(--muted)"></i>
            <input type="text" id="searchInput" onkeyup="filterBooks()" placeholder="Search title, author, ISBN..." class="dark-input pl-9 py-2.5">
        </div>
        <select id="statusFilter" onchange="filterBooks()" class="dark-input" style="width:auto">
            <option value="All">All Status</option>
            <option>Available</option>
            <option>Borrowed</option>
            <option>Lost</option>
        </select>
        <select id="catFilter" onchange="filterBooks()" class="dark-input" style="width:auto">
            <option value="All">All Categories & Courses</option>
            <optgroup label="Categories">
                <option value="cat_Fiction">Fiction</option>
                <option value="cat_Non-Fiction">Non-Fiction</option>
                <option value="cat_Reference">Reference</option>
                <option value="cat_Academic">Academic</option>
            </optgroup>
            <optgroup label="Courses">
                <?php foreach(['BCA','MCA','BCom','MCom','BBA','MBA'] as $c): ?>
                <option value="course_<?=$c?>"><?=$c?></option>
                <?php endforeach; ?>
                <option value="course_General">General</option>
            </optgroup>
        </select>
    </div>

    <?php if(empty($books)):?>
    <div class="card p-12 text-center" style="color:var(--muted)"><i data-lucide="book" class="w-10 h-10 mx-auto mb-3 opacity-30"></i><p>No books yet.</p></div>
    <?php else:
    $reqBookIds=array_column(array_filter($myRequests,fn($r)=>in_array($r['status'],['Pending','Approved'])),'book_id');
    ?>
    <div class="books-grid" id="booksGrid">
    <?php foreach($books as $b):
        $copies=(int)($b['copies']??0);
        $alreadyReq=in_array($b['id'],$reqBookIds);
        $canReq=$copies>0 && !$alreadyReq && $b['status']==='Available';
        $cClass=$copies>2?'c-ok':($copies>0?'c-low':'c-out');
        $isbnClean = preg_replace('/[^0-9Xx]/', '', $b['isbn'] ?? '');
        
        // Fallback to a beautiful, realistic photograph
        $seed = abs($b['id']*37+(int)(crc32($b['title'])%1000))%1000;
        $fallbackUrl="https://picsum.photos/seed/lib{$seed}/300/420";
        $coverUrl = !empty($isbnClean) ? "https://covers.openlibrary.org/b/isbn/{$isbnClean}-L.jpg?default=false" : $fallbackUrl;
        $catBg=['Fiction'=>'#3b0764','Non-Fiction'=>'#0c4a6e','Academic'=>'#064e3b','Reference'=>'#78350f','Horror'=>'#450a0a'];
        $bg=$catBg[$b['category']??'']??'#0f172a';
        $desc=trim($b['description']??'');
        $avgRating=round((float)($b['avg_rating']??0),1);
        $reviewCount=(int)($b['review_count']??0);
        $myRev=$myReviewsMap[$b['id']]??null;
        // Build star string
        $starsHtml='';
        for($si=1;$si<=5;$si++) $starsHtml.=$si<=$avgRating?'★':'☆';
    ?>
    <div class="book-card"
         data-title="<?=strtolower(htmlspecialchars($b['title']))?>"
         data-author="<?=strtolower(htmlspecialchars($b['author']))?>"
         data-isbn="<?=strtolower(htmlspecialchars($b['isbn']??''))?>"
         data-status="<?=htmlspecialchars($b['status'])?>"
         data-cat="<?=htmlspecialchars($b['category']??'')?>"
         data-course="<?=htmlspecialchars($b['course']??'General')?>">

        <div class="bc-img">
            <img src="<?=$coverUrl?>" alt="<?=htmlspecialchars($b['title'])?>" loading="lazy"
                 onerror="if(this.src!=='<?=$fallbackUrl?>') this.src='<?=$fallbackUrl?>'; else {this.style.display='none';this.nextElementSibling.style.display='flex';}"
                 style="width:100%;height:100%;object-fit:cover;">
            <div class="bc-placeholder" style="display:none;background:linear-gradient(155deg,<?=$bg?>,<?=$bg?>cc)">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.3)" stroke-width="1.5"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M8 7h6M8 11h8M8 15h5"/></svg>
                <span style="font-size:.68rem;color:rgba(255,255,255,.4);padding:0 12px;text-align:center;line-height:1.4"><?=htmlspecialchars(substr($b['title'],0,30))?></span>
            </div>

            <!-- Hover overlay with full details -->
            <div class="bc-overlay">
                <?php if($desc):?>
                <div class="bc-detail-desc"><?=htmlspecialchars($desc)?></div>
                <?php endif;?>
                <div class="bc-detail-meta">
                    <?php if(!empty($b['category'])):?><span class="bc-tag bc-tag-cat" style="font-size:.62rem"><?=htmlspecialchars($b['category'])?></span><?php endif;?>
                    <?php if(!empty($b['genre']) && $b['genre']!==$b['category']):?><span class="bc-tag bc-tag-genre" style="font-size:.62rem"><?=htmlspecialchars($b['genre'])?></span><?php endif;?>
                    <?php if(!empty($b['shelf'])):?><span class="bc-tag bc-tag-shelf" style="font-size:.62rem">📍 <?=htmlspecialchars($b['shelf'])?></span><?php endif;?>
                </div>
                <?php if(!empty($b['isbn'])):?><div class="bc-detail-isbn">ISBN: <?=htmlspecialchars($b['isbn'])?></div><?php endif;?>
                <?php if($reviewCount>0):?><div style="font-size:.65rem;color:rgba(251,191,36,.9);margin-top:3px"><?=$starsHtml?> (<?=$reviewCount?> review<?=$reviewCount>1?'s':''?>)</div><?php endif;?>
            </div>

            <span class="bc-copies <?=$cClass?>">
                <?php if($copies>0):?><svg width="9" height="9" viewBox="0 0 24 24" fill="currentColor"><path d="M9 3H4a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h5V3zm2 0v16h9a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1h-9z"/></svg><?=$copies?> cop<?=$copies===1?'y':'ies'?><?php else:?>Out<?php endif;?>
            </span>

            <?php if($avgRating>0):?>
            <span class="bc-stars">★ <?=number_format($avgRating,1)?></span>
            <?php endif;?>

            <span style="position:absolute;bottom:8px;left:8px"><span class="badge badge-<?=strtolower($b['status'])?>"><?=$b['status']?></span></span>
        </div>

        <div class="bc-body">
            <div class="bc-title"><?=htmlspecialchars($b['title'])?></div>
            <div class="bc-author">— <?=htmlspecialchars($b['author'])?></div>
            <?php
            $courseBadgeColors = ['BCA'=>'rgba(88,166,255,.15);color:#58a6ff;border-color:rgba(88,166,255,.35)','MCA'=>'rgba(167,139,250,.15);color:#a78bfa;border-color:rgba(167,139,250,.35)','BCom'=>'rgba(52,211,153,.15);color:#34d399;border-color:rgba(52,211,153,.35)','BBA'=>'rgba(251,191,36,.15);color:#fbbf24;border-color:rgba(251,191,36,.35)','MBA'=>'rgba(249,115,22,.15);color:#f97316;border-color:rgba(249,115,22,.35)','General'=>'rgba(139,148,158,.12);color:#8b949e;border-color:rgba(139,148,158,.25)'];
            $courseIcons = ['BCA'=>'💻','MCA'=>'🖥️','BCom'=>'📊','BBA'=>'📈','MBA'=>'🏢','General'=>'📖'];
            $crs = $b['course'] ?? 'General';
            $crsStyle = $courseBadgeColors[$crs] ?? $courseBadgeColors['General'];
            $crsIcon  = $courseIcons[$crs] ?? '📖';
            ?>
            <div style="margin-top:5px">
                <span style="font-size:.65rem;font-weight:700;padding:2px 8px;border-radius:20px;border:1px solid;background:<?=$crsStyle?>"><?=$crsIcon?> <?=$crs?></span>
            </div>
            <?php if($desc):?>
            <div class="bc-desc"><?=htmlspecialchars($desc)?></div>
            <?php endif;?>
        </div>

        <div class="bc-footer">
            <?php if($alreadyReq):?>
                <button class="bc-btn done" disabled>
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Requested
                </button>
            <?php else:?>
                <form method="POST" style="flex:1">
                    <input type="hidden" name="request_book_id" value="<?=$b['id']?>">
                    <button type="submit" class="bc-btn <?=$canReq?'can':'none'?>" <?=!$canReq?'disabled':''?> style="width:100%">
                        <?php if($canReq):?>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg> Request
                        <?php else:?>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg> Unavailable
                        <?php endif;?>
                    </button>
                </form>
            <?php endif;?>
            <!-- Action button -->
            <button class="bc-btn action-btn" title="Options"
                onclick='openActionModal(
    <?=$b["id"]?>,
    <?=htmlspecialchars(json_encode($b["title"]), ENT_QUOTES, "UTF-8")?>,
    <?=htmlspecialchars(json_encode($b["author"]), ENT_QUOTES, "UTF-8")?>,
    <?=$myRev ? $myRev["rating"] : 0?>,
    <?=htmlspecialchars(json_encode($myRev ? $myRev["comment"] : ""), ENT_QUOTES, "UTF-8")?>
)'>
                <i data-lucide="more-vertical" class="w-4 h-4"></i>
            </button>
        </div>
    </div>
    <?php endforeach;?>
    </div>
        <div class="mt-4 text-sm" style="color:var(--muted)"><span id="recordCount">Showing <?=count($books)?> books</span></div>
        <?php endif;?>

</div>

<!-- ══ MY REQUESTS ═══════════════════════════════════════════════════════════ -->
<div id="pane-requests" class="hidden">
    <?php if(empty($myRequests)):?>
    <div class="card p-12 text-center" style="color:var(--muted)"><i data-lucide="inbox" class="w-10 h-10 mx-auto mb-3 opacity-30"></i><p>No requests yet.</p></div>
    <?php else:?>
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr>
                    <th class="text-left">Book</th>
                    <th class="text-left">Requested</th>
                    <th class="text-left">Status</th>
                    <th class="text-left">Due Date</th>
                    <th class="text-left">Extensions</th>
                    <th class="text-left">Fine</th>
                    <th class="text-left">Action</th>
                </tr></thead>
                <tbody>
                <?php foreach($myRequests as $req):
                    $ov=$req['status']==='Approved'&&$req['due_date']&&!$req['returned_at']&&strtotime($req['due_date'])<time();
                    $extCount=(int)($req['extension_count']??0);
                    $canExtend=$req['status']==='Approved' && !$req['returned_at'] && $extCount < 2;
                ?>
                <tr>
                    <td>
                        <div class="font-semibold text-white"><?=htmlspecialchars($req['title'])?></div>
                        <div class="text-xs" style="color:var(--muted)"><?=htmlspecialchars($req['author'])?></div>
                    </td>
                    <td class="text-sm" style="color:var(--muted)"><?=date('d M Y',strtotime($req['requested_at']))?></td>
                    <td><span class="badge badge-<?=strtolower($req['status'])?>"><?=$req['status']?></span></td>
                    <td class="text-sm <?=$ov?'text-red-400 font-bold':''?>" style="<?=!$ov?'color:var(--muted)':''?>">
                        <?=$req['due_date']?date('d M Y',strtotime($req['due_date'])):'—'?><?php if($ov):?> ⚠️<?php endif;?>
                    </td>
                    <td>
                        <?php if($req['status']==='Approved'):?>
                        <span class="text-xs <?=$extCount>=2?'text-red-400':'text-blue-400'?> font-semibold">
                            <?=$extCount?>/2 used
                        </span>
                        <?php else:?><span style="color:var(--muted)">—</span><?php endif;?>
                    </td>
                    <td>
                        <?php if(($req['fine_amount']??0)>0):?>
                        <span class="text-red-400 font-bold">₹<?=number_format($req['fine_amount'],2)?></span>
                        <?php else:?><span style="color:var(--muted)">—</span><?php endif;?>
                    </td>
                    <td>
                        <?php if($canExtend):?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="extend_request_id" value="<?=$req['id']?>">
                            <button type="submit" class="btn-extend" title="Extend by 10 days (<?=2-$extCount?> left)">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                                Extend
                            </button>
                        </form>
                        <?php elseif($req['status']==='Approved' && !$req['returned_at']):?>
                        <button class="btn-extend" disabled title="Max extensions reached">⛔ Max</button>
                        <?php else:?><span style="color:var(--muted)">—</span><?php endif;?>
                    </td>
                </tr>
                <?php endforeach;?>
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3 text-xs" style="color:var(--muted);border-top:1px solid var(--border)">
            💡 You can extend the deadline up to <strong class="text-white">2 times</strong>, 10 extra days each time. Extensions are not allowed after the book is overdue by more than 3 days.
        </div>
    </div>
    <?php endif;?>
</div>

<!-- ══ FINE MANAGEMENT ════════════════════════════════════════════════════════ -->
<div id="pane-fines" class="hidden">
    <div class="fine-bar mb-6 flex items-center justify-between flex-wrap gap-4">
        <div>
            <div class="text-xs uppercase tracking-widest mb-1" style="color:var(--muted)">Total Fine Due</div>
            <div class="text-3xl font-bold text-red-400">₹<?=number_format($totalFineAmt,2)?></div>
            <?php if($totalFineAmt==0):?><div class="text-emerald-400 text-sm font-semibold mt-1">✅ No pending fines!</div><?php endif;?>
        </div>
        <?php if($totalFineAmt>0):?>
        <div class="flex gap-2">
            <button onclick="setPayTab('qr')"  id="ptab-qr"  class="pay-method-btn active">📷 QR Code</button>
            <button onclick="setPayTab('upi')" id="ptab-upi" class="pay-method-btn inactive">📱 UPI ID</button>
        </div>
        <?php endif;?>
    </div>
    <?php if($totalFineAmt>0):?>
    <div class="grid md:grid-cols-2 gap-6 mb-6">
        <div id="ppay-qr" class="card p-6">
            <h3 class="font-bold text-white mb-1 flex items-center gap-2"><i data-lucide="qr-code" class="w-5 h-5 text-emerald-400"></i> Scan &amp; Pay</h3>
            <p class="text-xs mb-5" style="color:var(--muted)">Open PhonePe / GPay / Paytm and scan to pay</p>
            <div class="flex justify-center mb-4">
                <?php $upiLink="upi://pay?pa=".urlencode($UPI_ID)."&pn=".urlencode($UPI_NAME)."&am=$totalFineAmt&cu=INR&tn=LibraryFine";?>
                <a href="<?=htmlspecialchars($upiLink)?>">
                    <div class="qr-wrap cursor-pointer">
                        <?php if(file_exists($QR_IMAGE)):?>
                        <img src="<?=htmlspecialchars($QR_IMAGE)?>" alt="Pay" style="width:200px;height:200px">
                        <?php else:?>
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?=urlencode($upiLink)?>" alt="Pay" style="width:200px;height:200px">
                        <?php endif;?>
                    </div>
                </a>
            </div>
            <div class="text-center text-xs mb-4" style="color:var(--muted)">Pay <strong class="text-red-400">₹<?=number_format($totalFineAmt,2)?></strong> to <strong class="text-white"><?=htmlspecialchars($UPI_NAME)?></strong><br><span style="color:var(--blue)">Tap QR on mobile to open payment app</span></div>
            <?php $fRows=array_filter($myRequests,fn($r)=>($r['fine_amount']??0)>0&&!$r['returned_at']); $fFirst=reset($fRows);?>
            <?php if($fFirst):?>
            <div style="border-top:1px solid var(--border);padding-top:14px">
                <p class="text-xs font-semibold mb-3" style="color:var(--muted)">AFTER PAYMENT — Submit proof</p>
                <form method="POST" enctype="multipart/form-data" class="space-y-3">
                    <input type="hidden" name="submit_payment" value="1">
                    <input type="hidden" name="pay_request_id" value="<?=$fFirst['id']?>">
                    <input type="hidden" name="pay_amount" value="<?=$totalFineAmt?>">
                    <input type="text" name="upi_ref" class="dark-input" placeholder="UPI Transaction ID (optional)">
                    <label class="file-input-label" for="payScreenshot"><i data-lucide="upload" class="w-4 h-4"></i> Upload Screenshot (optional)</label>
                    <input type="file" id="payScreenshot" name="pay_screenshot" accept="image/*">
                    <button type="submit" class="btn-save w-full">✅ Submit Payment for Verification</button>
                </form>
                <?php if(in_array($fFirst['id'],$pendingPayIds)):?>
                <div class="mt-3 text-center text-xs px-3 py-2 rounded-lg" style="background:rgba(88,166,255,.1);border:1px solid rgba(88,166,255,.3);color:#58a6ff">⏳ Verification pending — admin will confirm shortly</div>
                <?php endif;?>
            </div>
            <?php endif;?>
        </div>
        <div id="ppay-upi" class="card p-6 hidden">
            <h3 class="font-bold text-white mb-1 flex items-center gap-2"><i data-lucide="smartphone" class="w-5 h-5 text-blue-400"></i> Pay via UPI ID</h3>
            <p class="text-xs mb-4" style="color:var(--muted)">Copy &amp; paste in any payment app</p>
            <div class="flex items-center justify-between p-3 rounded-lg mb-4" style="background:var(--card2);border:1px solid var(--border)">
                <div><div class="text-xs mb-0.5" style="color:var(--muted)">UPI ID</div><div class="text-white font-mono font-semibold" id="upiIdText"><?=htmlspecialchars($UPI_ID)?></div></div>
                <button onclick="copyUPI()" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white" style="background:var(--blue)">Copy</button>
            </div>
            <?php $steps=["Open PhonePe, GPay or any UPI app","Tap Send Money → Pay via UPI ID","Paste UPI: <strong class='text-white'>".htmlspecialchars($UPI_ID)."</strong>","Enter amount <strong class='text-red-400'>₹".number_format($totalFineAmt,2)."</strong> and pay","Submit screenshot below to notify admin"];
            foreach($steps as $i=>$s):?><div class="flex items-start gap-3 mb-3"><div class="step-badge"><?=$i+1?></div><p class="text-sm" style="color:var(--muted)"><?=$s?></p></div><?php endforeach;?>
            <a href="<?=htmlspecialchars($upiLink)?>" class="flex items-center justify-center gap-2 mt-4 p-3 rounded-lg font-bold text-white text-sm" style="background:#238636;text-decoration:none">
                <i data-lucide="external-link" class="w-4 h-4"></i> Open UPI App — Pay ₹<?=number_format($totalFineAmt,2)?>
            </a>
        </div>
        <div class="card overflow-hidden md:col-span-2">
            <div class="px-5 py-4 border-b flex items-center gap-2" style="border-color:var(--border)"><i data-lucide="list" class="w-4 h-4 text-red-400"></i><h3 class="font-bold text-white">Fine Breakdown</h3></div>
            <div class="overflow-x-auto"><table class="w-full">
                <thead><tr><th class="text-left">Book</th><th class="text-left">Due Date</th><th class="text-left">Days Late</th><th class="text-left">Fine (₹5/day)</th><th class="text-left">Payment</th></tr></thead>
                <tbody>
                <?php foreach(array_filter($myRequests,fn($r)=>($r['fine_amount']??0)>0) as $fr):
                    $due=new DateTime($fr['due_date']); $ov=(int)$today->diff($due)->days;
                    $paid=in_array($fr['id'],$pendingPayIds);
                ?>
                <tr>
                    <td><div class="font-semibold text-white"><?=htmlspecialchars($fr['title'])?></div></td>
                    <td class="text-sm text-red-400 font-semibold"><?=date('d M Y',strtotime($fr['due_date']))?></td>
                    <td class="text-sm" style="color:var(--warn)"><?=$ov?> days</td>
                    <td class="font-bold text-red-400">₹<?=number_format($fr['fine_amount'],2)?></td>
                    <td><?php if($paid):?><span class="badge" style="background:rgba(88,166,255,.1);color:#58a6ff;border:1px solid rgba(88,166,255,.3)">⏳ Pending</span><?php else:?><span class="badge" style="background:rgba(248,81,73,.1);color:#f85149;border:1px solid rgba(248,81,73,.3)">Unpaid</span><?php endif;?></td>
                </tr>
                <?php endforeach;?>
                </tbody>
            </table></div>
            <div class="px-5 py-3 text-xs" style="color:var(--muted);border-top:1px solid var(--border)">💡 Fine rate: <strong class="text-white">₹5/day</strong>. Submit screenshot after paying. Admin will verify and clear your fine.</div>
        </div>
    </div>
    <?php else:?>
    <div class="card p-12 text-center">
        <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background:rgba(35,134,54,.2);border:1px solid rgba(63,185,80,.3)"><i data-lucide="check-circle-2" class="w-8 h-8 text-emerald-400"></i></div>
        <p class="text-white font-bold text-lg">All Clear!</p>
        <p class="text-sm mt-1" style="color:var(--muted)">No outstanding fines 🎉</p>
    </div>
    <?php endif;?>
</div>

<!-- ══ REQUEST A BOOK ═════════════════════════════════════════════════════════ -->
<div id="pane-purchase" class="hidden">
    <div class="grid md:grid-cols-2 gap-6">
        <div class="card p-6">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background:rgba(88,166,255,.1)"><i data-lucide="package-plus" class="w-5 h-5" style="color:#58a6ff"></i></div>
                <div><h3 class="font-bold text-white">Request a New Book</h3><p class="text-xs" style="color:var(--muted)">Can't find a book? Ask the library to order it!</p></div>
            </div>
            <form method="POST" class="space-y-4">
                <div><label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Book Title <span class="text-red-400">*</span></label><input type="text" name="purchase_title" class="dark-input" placeholder="e.g. Atomic Habits" required></div>
                <div><label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Author (optional)</label><input type="text" name="purchase_author" class="dark-input" placeholder="e.g. James Clear"></div>
                <div><label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Why do you need this book?</label><textarea name="purchase_reason" rows="3" class="dark-input resize-none" placeholder="e.g. Required for semester project..."></textarea></div>
                <button type="submit" class="btn-save w-full flex items-center justify-center gap-2"><i data-lucide="send" class="w-4 h-4"></i> Send Request to Admin</button>
            </form>
        </div>
        <div>
            <h3 class="font-bold text-white mb-3 flex items-center gap-2"><i data-lucide="history" class="w-4 h-4" style="color:var(--blue)"></i> My Purchase Requests</h3>
            <?php
            $prs=$pdo->prepare("SELECT * FROM book_purchase_requests WHERE user_id=? ORDER BY created_at DESC LIMIT 10");
            $prs->execute([$user_id]); $prs=$prs->fetchAll();
            ?>
            <?php if(empty($prs)):?>
            <div class="card p-6 text-center" style="color:var(--muted)"><i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-30"></i><p class="text-sm">No requests yet.</p></div>
            <?php else:?>
            <div class="space-y-3">
                <?php foreach($prs as $pr):
                    $sI=['Pending'=>'⏳','Reviewed'=>'👀','Ordered'=>'✅','Rejected'=>'❌'];
                    $sS=['Pending'=>'background:rgba(251,191,36,.12);border:1px solid rgba(251,191,36,.3);color:#fbbf24','Reviewed'=>'background:rgba(96,165,250,.12);border:1px solid rgba(96,165,250,.3);color:#60a5fa','Ordered'=>'background:rgba(52,211,153,.12);border:1px solid rgba(52,211,153,.3);color:#34d399','Rejected'=>'background:rgba(248,113,113,.12);border:1px solid rgba(248,113,113,.3);color:#f87171'];
                    $bc=$pr['status']=='Ordered'?'#34d399':($pr['status']=='Rejected'?'#f87171':($pr['status']=='Reviewed'?'#60a5fa':'#fbbf24'));
                ?>
                <div class="card p-4" style="border-left:3px solid <?=$bc?>">
                    <div class="flex items-start justify-between gap-2 flex-wrap">
                        <div style="flex:1">
                            <div class="font-semibold text-white"><?=htmlspecialchars($pr['book_title'])?></div>
                            <?php if($pr['author']):?><div class="text-xs mt-0.5" style="color:var(--muted)">by <?=htmlspecialchars($pr['author'])?></div><?php endif;?>
                            <div class="text-xs mt-1" style="color:var(--muted)">Requested <?=date('d M Y',strtotime($pr['created_at']))?></div>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 rounded-full whitespace-nowrap" style="<?=$sS[$pr['status']]??$sS['Pending']?>"><?=$sI[$pr['status']]??'⏳'?> <?=$pr['status']?></span>
                    </div>
                    <?php if($pr['reason']):?><div class="mt-2 text-xs px-3 py-2 rounded-lg" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);color:var(--muted)">Your reason: <?=htmlspecialchars($pr['reason'])?></div><?php endif;?>
                    <?php if($pr['admin_note']):?>
                    <div class="mt-2 px-3 py-2 rounded-lg" style="background:rgba(34,211,238,.08);border:1px solid rgba(34,211,238,.25)">
                        <div class="text-xs font-bold mb-1" style="color:#22d3ee">📩 Admin Response:</div>
                        <div class="text-sm" style="color:rgba(255,255,255,.85)"><?=htmlspecialchars($pr['admin_note'])?></div>
                    </div>
                    <?php else:?><div class="mt-2 text-xs px-3 py-2 rounded-lg" style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);color:var(--muted)">⏳ Waiting for admin response...</div><?php endif;?>
                </div>
                <?php endforeach;?>
            </div>
            <?php endif;?>
        </div>
    </div>
</div>

</div><!-- /container -->

<!-- AI FAB -->
<div style="position:fixed;bottom:28px;right:28px;z-index:8000">
    <a href="chat_bot.php" id="ai-fab" title="LIBRITE AI Assistant">🤖</a>
    <div id="ai-fab-label">🤖 Ask LIBRITE AI</div>
</div>

<script>
lucide.createIcons();

// ── Tab switching ──────────────────────────────────────────────────────────
function switchTab(t) {
    ['browse','requests','fines','purchase'].forEach(n => {
        document.getElementById('pane-'+n).classList.toggle('hidden', n!==t);
        const b = document.getElementById('tab-'+n);
        b.classList.toggle('tab-active', n===t);
        b.classList.toggle('tab-inactive', n!==t);
    });
}

// ── Profile panel ──────────────────────────────────────────────────────────
function openProfile() {
    document.getElementById('profilePanel').classList.add('open');
    document.getElementById('panelOverlay').classList.add('open');
    document.body.style.overflow='hidden';
    lucide.createIcons();
}
function closeProfile() {
    document.getElementById('profilePanel').classList.remove('open');
    document.getElementById('panelOverlay').classList.remove('open');
    document.body.style.overflow='';
}
function switchProfileTab(t) {
    ['view','edit'].forEach(n => {
        document.getElementById('ppane-'+n).classList.toggle('hidden', n!==t);
        const b = document.getElementById('ptab-'+n);
        b.classList.toggle('tab-active', n===t);
        b.classList.toggle('tab-inactive', n!==t);
    });
    lucide.createIcons();
}
function previewPhoto(input) {
    if (input.files && input.files[0])
        document.getElementById('fileLabel').textContent = input.files[0].name;
}

// ── Fine payment tabs ──────────────────────────────────────────────────────
function setPayTab(t) {
    ['qr','upi'].forEach(n => {
        const p = document.getElementById('ppay-'+n);
        const b = document.getElementById('ptab-'+n);
        if(p) p.classList.toggle('hidden', n!==t);
        if(b) { b.classList.toggle('active', n===t); b.classList.toggle('inactive', n!==t); }
    });
}
function copyUPI() {
    navigator.clipboard.writeText(document.getElementById('upiIdText')?.textContent||'')
        .then(()=>showToast('✅ UPI ID copied!','#238636'));
}

// ── Book filter ────────────────────────────────────────────────────────────
function filterBooks() {
    const s   = document.getElementById('searchInput').value.toLowerCase();
    const st  = document.getElementById('statusFilter').value;
    const catVal = document.getElementById('catFilter').value;
    let n = 0;
    document.querySelectorAll('#booksGrid .book-card').forEach(c => {
        let courseMatch = true;
        let catMatch = true;
        
        if (catVal.startsWith('course_')) {
            const courseName = catVal.replace('course_', '');
            courseMatch = c.dataset.course === courseName;
        } else if (catVal.startsWith('cat_')) {
            const catName = catVal.replace('cat_', '');
            catMatch = c.dataset.cat === catName;
        }

        const ok = (c.dataset.title.includes(s)||c.dataset.author.includes(s)||c.dataset.isbn.includes(s))
                && (st==='All'||c.dataset.status===st)
                && catMatch
                && courseMatch;
        c.style.display = ok ? '' : 'none';
        if(ok) n++;
    });
    document.getElementById('recordCount').innerText = `Showing ${n} books`;
}

// ── Toast ──────────────────────────────────────────────────────────────────
function showToast(msg, color='#238636') {
    const t = document.getElementById('toast');
    t.textContent=msg; t.style.background=color; t.style.color='white';
    t.classList.add('show');
    setTimeout(()=>t.classList.remove('show'), 3500);
}

// ── Modal helpers ──────────────────────────────────────────────────────────
function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow='hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow='';
}
document.querySelectorAll('.modal-bg').forEach(m => {
    m.addEventListener('click', e => { if(e.target===m) closeModal(m.id); });
});

// ── Review modal ───────────────────────────────────────────────────────────
function openReviewModal(bookId, title, author, existingRating, existingComment) {
    document.getElementById('reviewBookId').value = bookId;
    document.getElementById('reviewBookTitle').textContent = title;
    document.getElementById('reviewBookAuthor').textContent = '— ' + author;
    document.getElementById('reviewComment').value = existingComment || '';
    // Set star
    if (existingRating) {
        const r = document.querySelector(`#starSelector input[value="${existingRating}"]`);
        if (r) r.checked = true;
    } else {
        document.querySelectorAll('#starSelector input').forEach(i=>i.checked=false);
    }
    openModal('reviewModal');
}

// Star selector color feedback
document.querySelectorAll('.star-selector label').forEach(label => {
    label.addEventListener('mouseenter', function() {
        this.style.color='#fbbf24';
    });
    label.addEventListener('mouseleave', function() {
        this.style.color='';
    });
});

// ── Report modal ───────────────────────────────────────────────────────────
function openReportModal(bookId, title) {
    document.getElementById('reportBookId').value = bookId;
    document.getElementById('reportBookTitle').textContent = title;
    openModal('reportModal');
}

// ── Action modal ───────────────────────────────────────────────────────────
let currentActionBook = {};
function openActionModal(bookId, title, author, existingRating, existingComment) {
    currentActionBook = { id: bookId, title: title, author: author, rating: existingRating, comment: existingComment };
    openModal('actionModal');
}
function openActionReview() {
    openReviewModal(currentActionBook.id, currentActionBook.title, currentActionBook.author, currentActionBook.rating, currentActionBook.comment);
}
function openActionReport() {
    openReportModal(currentActionBook.id, currentActionBook.title);
}

// ── Auto open profile edit if name missing ────────────────────────────────
<?php if(empty($userData['full_name'])):?>
setTimeout(()=>{ openProfile(); switchProfileTab('edit'); }, 700);
<?php endif;?>

// ── Handle ?tab= from chatbot.php redirect ─────────────────────────────────
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('tab')) switchTab(urlParams.get('tab'));
if (urlParams.get('search')) {
    document.getElementById('searchInput').value = urlParams.get('search');
    filterBooks();
}
</script>
</body>
</html>