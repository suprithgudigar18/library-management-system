<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id'])) { header("Location: user_login.php"); exit(); }

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];

$userData = $pdo->prepare("SELECT * FROM users WHERE id=?");
$userData->execute([$user_id]);
$userData = $userData->fetch();

// Fetch all books for AI knowledge base
$books = $pdo->query("
    SELECT MIN(id) AS id, title, author, isbn,
           SUM(copies) AS copies,
           MAX(status) AS status,
           category, genre, shelf, description
    FROM books
    GROUP BY LOWER(TRIM(title)), LOWER(TRIM(author))
    ORDER BY title ASC
")->fetchAll();

// Last requested categories for personalised suggestions
$myReqStmt = $pdo->prepare("
    SELECT b.category FROM book_requests br
    JOIN books b ON b.id = br.book_id
    WHERE br.user_id = ? ORDER BY br.requested_at DESC LIMIT 10
");
$myReqStmt->execute([$user_id]);
$lastCats = array_unique(array_column($myReqStmt->fetchAll(), 'category'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LIBRITE AI — Book Assistant</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --bg:#02040a; --card:#0d1117; --card2:#161b22; --border:#30363d;
    --accent:#238636; --muted:#8b949e; --main:#c9d1d9;
    --blue:#58a6ff; --red:#f85149; --warn:#e3b341;
}
* { margin:0; padding:0; box-sizing:border-box; }
body { background:var(--bg); color:var(--main); font-family:'Space Grotesk',sans-serif;
       height:100vh; display:flex; flex-direction:column; overflow:hidden; }

/* ── Header ── */
.ai-header {
    background:linear-gradient(135deg,#0d2818,#0d1526);
    border-bottom:1px solid var(--border);
    padding:14px 20px;
    display:flex; align-items:center; justify-content:space-between;
    flex-shrink:0;
}
.ai-header-left { display:flex; align-items:center; gap:14px; }
.ai-avatar-big {
    width:48px; height:48px; border-radius:50%;
    background:linear-gradient(135deg,#238636,#58a6ff);
    display:flex; align-items:center; justify-content:center;
    font-size:1.5rem; flex-shrink:0;
    box-shadow:0 0 20px rgba(35,134,54,.4);
    animation:pulse 2.5s ease-in-out infinite;
}
@keyframes pulse { 0%,100%{box-shadow:0 0 20px rgba(35,134,54,.4)} 50%{box-shadow:0 0 30px rgba(35,134,54,.65)} }
.ai-header-title { font-size:1.1rem; font-weight:700; color:white; }
.ai-header-sub { font-size:.75rem; color:#3fb950; display:flex; align-items:center; gap:5px; }
.ai-header-sub::before { content:''; width:7px; height:7px; border-radius:50%; background:#3fb950; display:inline-block; }
.back-link {
    display:flex; align-items:center; gap:6px;
    padding:8px 16px; border-radius:10px;
    background:rgba(255,255,255,.05); border:1px solid var(--border);
    color:var(--muted); text-decoration:none; font-size:.82rem; font-weight:600;
    transition:all .2s;
}
.back-link:hover { border-color:var(--accent); color:var(--accent); }

/* ── Stats bar ── */
.stats-bar {
    background:rgba(255,255,255,.02); border-bottom:1px solid var(--border);
    padding:10px 20px; display:flex; gap:24px; flex-shrink:0; overflow-x:auto;
}
.stat-item { display:flex; align-items:center; gap:7px; white-space:nowrap; }
.stat-item .val { font-weight:700; color:white; font-size:.9rem; }
.stat-item .lbl { font-size:.72rem; color:var(--muted); }

/* ── Chat area ── */
.chat-area {
    flex:1; overflow-y:auto; padding:20px;
    display:flex; flex-direction:column; gap:12px;
    scrollbar-width:thin; scrollbar-color:var(--border) transparent;
}
.chat-area::-webkit-scrollbar { width:5px; }
.chat-area::-webkit-scrollbar-thumb { background:var(--border); border-radius:3px; }

/* Messages */
.msg-row { display:flex; align-items:flex-end; gap:10px; }
.msg-row.user { flex-direction:row-reverse; }
.msg-avatar {
    width:32px; height:32px; border-radius:50%; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    font-size:.85rem; font-weight:700;
}
.msg-avatar.bot { background:linear-gradient(135deg,#238636,#58a6ff); }
.msg-avatar.user { background:rgba(88,166,255,.2); border:1px solid rgba(88,166,255,.3); color:#58a6ff; }

.msg-bubble {
    max-width:72%; padding:11px 15px; border-radius:16px;
    font-size:.875rem; line-height:1.6; white-space:pre-line;
}
.msg-bubble.bot {
    background:var(--card2); border:1px solid var(--border);
    border-radius:4px 16px 16px 16px; color:var(--main);
}
.msg-bubble.user {
    background:rgba(35,134,54,.15); border:1px solid rgba(63,185,80,.25);
    border-radius:16px 16px 4px 16px; color:#c9d1d9; text-align:right;
}
.msg-time { font-size:.65rem; color:var(--muted); margin-top:4px;
    text-align:right; }

/* Book result cards */
.book-result {
    background:rgba(88,166,255,.05); border:1px solid rgba(88,166,255,.18);
    border-radius:12px; padding:12px 14px; margin-top:8px;
    display:flex; gap:12px; align-items:flex-start;
    cursor:pointer; transition:all .2s; max-width:72%; margin-left:42px;
}
.book-result:hover { background:rgba(88,166,255,.1); border-color:rgba(88,166,255,.35); transform:translateY(-2px); }
.book-cover-thumb {
    width:48px; height:65px; border-radius:6px;
    object-fit:cover; flex-shrink:0; background:var(--card2);
}
.book-result-info { flex:1; min-width:0; }
.book-result-title { font-weight:700; color:white; font-size:.875rem; line-height:1.3; margin-bottom:3px; }
.book-result-author { font-size:.75rem; color:var(--muted); margin-bottom:5px; }
.book-result-tags { display:flex; gap:5px; flex-wrap:wrap; margin-bottom:5px; }
.tag { font-size:.65rem; padding:2px 7px; border-radius:10px; font-weight:600; }
.tag-cat  { background:rgba(88,166,255,.1);  color:#58a6ff; }
.tag-avail{ background:rgba(63,185,80,.1);   color:#3fb950; }
.tag-none { background:rgba(248,81,73,.1);   color:#f85149; }
.book-result-desc { font-size:.72rem; color:rgba(255,255,255,.45); line-height:1.45;
    display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.book-result-action {
    display:inline-flex; align-items:center; gap:4px; margin-top:6px;
    font-size:.7rem; color:var(--accent); font-weight:600;
}

/* Typing indicator */
.typing-row { display:flex; align-items:flex-end; gap:10px; }
.typing-bubble {
    background:var(--card2); border:1px solid var(--border);
    border-radius:4px 16px 16px 16px; padding:12px 16px;
    display:flex; align-items:center; gap:5px;
}
.typing-bubble span {
    width:7px; height:7px; border-radius:50%; background:var(--muted);
    animation:tdot .8s infinite;
}
.typing-bubble span:nth-child(2){ animation-delay:.15s; }
.typing-bubble span:nth-child(3){ animation-delay:.3s; }
@keyframes tdot{0%,100%{transform:translateY(0);opacity:.5}50%{transform:translateY(-5px);opacity:1}}

/* ── Quick chips section ── */
.quick-section {
    padding:10px 20px 0; border-top:1px solid rgba(255,255,255,.04);
    display:flex; gap:8px; flex-wrap:wrap; flex-shrink:0;
}
.quick-chip {
    padding:6px 13px; border-radius:20px;
    background:rgba(255,255,255,.04); border:1px solid var(--border);
    color:var(--muted); font-size:.76rem; font-weight:600;
    cursor:pointer; transition:all .2s; white-space:nowrap;
}
.quick-chip:hover { background:rgba(88,166,255,.1); border-color:#58a6ff; color:#58a6ff; }

/* ── Input bar ── */
.input-bar {
    padding:14px 20px; border-top:1px solid var(--border);
    display:flex; gap:10px; align-items:center; flex-shrink:0;
    background:var(--card);
}
.chat-input {
    flex:1; background:rgba(255,255,255,.06); border:1px solid var(--border);
    border-radius:12px; color:white; padding:11px 16px; font-size:.9rem;
    outline:none; font-family:'Space Grotesk',sans-serif; transition:border-color .2s;
}
.chat-input:focus { border-color:var(--accent); }
.chat-input::placeholder { color:var(--muted); }
.send-btn {
    width:44px; height:44px; border-radius:12px; border:none;
    background:linear-gradient(135deg,#238636,#1a7f37);
    color:white; cursor:pointer; display:flex; align-items:center;
    justify-content:center; flex-shrink:0; transition:all .2s;
    box-shadow:0 4px 14px rgba(35,134,54,.35);
}
.send-btn:hover { background:linear-gradient(135deg,#2ea043,#238636);
    box-shadow:0 6px 20px rgba(35,134,54,.5); transform:scale(1.05); }
.send-btn:active { transform:scale(.97); }

/* ── Suggested queries panel ── */
.suggestions-panel {
    position:fixed; bottom:80px; right:20px; width:260px;
    background:var(--card); border:1px solid var(--border); border-radius:14px;
    padding:12px; box-shadow:0 10px 40px rgba(0,0,0,.6);
    display:none;
}
.suggestions-panel.open { display:block; animation:slideUp .25s ease; }
@keyframes slideUp { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
.suggestion-item {
    padding:8px 10px; border-radius:8px; font-size:.78rem; color:var(--muted);
    cursor:pointer; transition:all .15s; display:flex; align-items:center; gap:8px;
}
.suggestion-item:hover { background:rgba(255,255,255,.06); color:white; }

@media(max-width:600px) {
    .book-result { max-width:95%; margin-left:10px; }
    .msg-bubble { max-width:88%; }
    .stats-bar { gap:16px; }
}
</style>
</head>
<body>

<!-- Header -->
<div class="ai-header">
    <div class="ai-header-left">
        <div class="ai-avatar-big">🤖</div>
        <div>
            <div class="ai-header-title">LIBRITE AI Assistant</div>
            <div class="ai-header-sub">Online · <?= count($books) ?> books in knowledge base</div>
        </div>
    </div>
    <a href="user_dashboard.php" class="back-link">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back to Dashboard
    </a>
</div>

<!-- Stats bar -->
<?php
$totalAvail   = array_sum(array_column(array_filter($books, fn($b)=>$b['status']==='Available'), 'copies'));
$totalBorrowed= count(array_filter($books, fn($b)=>$b['status']==='Borrowed'));
$cats         = array_unique(array_column($books, 'category'));
?>
<div class="stats-bar">
    <div class="stat-item"><span class="val"><?= count($books) ?></span><span class="lbl">Total Titles</span></div>
    <div class="stat-item"><span class="val" style="color:#3fb950"><?= $totalAvail ?></span><span class="lbl">Available Copies</span></div>
    <div class="stat-item"><span class="val" style="color:#f85149"><?= $totalBorrowed ?></span><span class="lbl">Borrowed</span></div>
    <div class="stat-item"><span class="val" style="color:#58a6ff"><?= count($cats) ?></span><span class="lbl">Genres</span></div>
    <?php if(!empty($lastCats)): ?>
    <div class="stat-item"><span class="val" style="color:#e3b341"><?= htmlspecialchars(implode(', ', array_slice($lastCats,0,2))) ?></span><span class="lbl">Your interests</span></div>
    <?php endif; ?>
</div>

<!-- Chat area -->
<div class="chat-area" id="chatArea">
    <!-- Welcome message -->
    <div class="msg-row">
        <div class="msg-avatar bot">🤖</div>
        <div>
            <div class="msg-bubble bot">👋 Hello, <strong style="color:white"><?= htmlspecialchars($userData['full_name']?:$username) ?></strong>! I'm LIBRITE AI.

I have knowledge of all <?= count($books) ?> books in your library. I can:

📖 Describe any book in detail
✨ Suggest books based on your interests
🔍 Search by author, genre, or title
💰 Explain fines and borrowing rules
📊 Give library stats

What would you like to explore today?</div>
            <div class="msg-time">Just now</div>
        </div>
    </div>
</div>

<!-- Quick chips -->
<div class="quick-section" id="quickSection">
    <span class="quick-chip" onclick="sendQuick('Suggest books for me')">✨ Suggest for me</span>
    <span class="quick-chip" onclick="sendQuick('What Fiction books are available?')">📖 Fiction</span>
    <span class="quick-chip" onclick="sendQuick('Show Academic books')">🎓 Academic</span>
    <span class="quick-chip" onclick="sendQuick('Explain the fine system')">💰 Fine rules</span>
    <span class="quick-chip" onclick="sendQuick('How many books are available?')">📊 Library stats</span>
    <span class="quick-chip" onclick="sendQuick('Popular books')">🔥 Popular picks</span>
</div>

<!-- Input bar -->
<div class="input-bar">
    <input type="text" class="chat-input" id="chatInput"
           placeholder="Ask about books, fines, rules... (Press Enter to send)"
           onkeydown="if(event.key==='Enter')sendMsg()">
    <button class="send-btn" onclick="sendMsg()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="22" y1="2" x2="11" y2="13"></line>
            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
        </svg>
    </button>
</div>

<script>
const BOOKS = <?= json_encode(array_map(fn($b) => [
    'id'          => (int)$b['id'],
    'title'       => $b['title'],
    'author'      => $b['author'],
    'category'    => $b['category'] ?? '',
    'genre'       => $b['genre']    ?? '',
    'status'      => $b['status'],
    'copies'      => (int)$b['copies'],
    'shelf'       => $b['shelf']    ?? '',
    'description' => $b['description'] ?? '',
    'isbn'        => $b['isbn']     ?? '',
], $books)) ?>;

const USER_CATS  = <?= json_encode($lastCats) ?>;
const USER_NAME  = "<?= htmlspecialchars($userData['full_name']?:$username) ?>";
const USER_INIT  = "<?= strtoupper(substr($userData['full_name']?:$username, 0, 1)) ?>";

function now() {
    return new Date().toLocaleTimeString('en-IN', {hour:'2-digit',minute:'2-digit'});
}

function sendQuick(msg) {
    document.getElementById('chatInput').value = msg;
    sendMsg();
    document.getElementById('quickSection').style.display = 'none';
}

function sendMsg() {
    const input = document.getElementById('chatInput');
    const msg   = input.value.trim();
    if (!msg) return;
    input.value = '';
    addUserMsg(msg);
    const typing = addTyping();
    setTimeout(() => {
        typing.remove();
        const reply = getReply(msg);
        if (typeof reply === 'string') {
            addBotMsg(reply);
        } else {
            addBotMsg(reply.text);
            (reply.books || []).forEach(b => addBookCard(b));
        }
    }, 600 + Math.random() * 500);
}

function addUserMsg(text) {
    const area = document.getElementById('chatArea');
    area.innerHTML += `
    <div class="msg-row user">
        <div class="msg-avatar user">${USER_INIT}</div>
        <div>
            <div class="msg-bubble user">${escHtml(text)}</div>
            <div class="msg-time" style="text-align:left">${now()}</div>
        </div>
    </div>`;
    area.scrollTop = area.scrollHeight;
}

function addBotMsg(text) {
    const area = document.getElementById('chatArea');
    const el = document.createElement('div');
    el.className = 'msg-row';
    el.innerHTML = `
        <div class="msg-avatar bot">🤖</div>
        <div>
            <div class="msg-bubble bot" style="white-space:pre-line">${escHtml(text)}</div>
            <div class="msg-time">${now()}</div>
        </div>`;
    area.appendChild(el);
    area.scrollTop = area.scrollHeight;
}

function addTyping() {
    const area = document.getElementById('chatArea');
    const el = document.createElement('div');
    el.className = 'typing-row';
    el.innerHTML = `
        <div class="msg-avatar bot">🤖</div>
        <div class="typing-bubble"><span></span><span></span><span></span></div>`;
    area.appendChild(el);
    area.scrollTop = area.scrollHeight;
    return el;
}

function addBookCard(book) {
    const area  = document.getElementById('chatArea');
    const seed  = (book.id * 37) % 1000;
    const avail = book.copies > 0 && book.status === 'Available';
    const desc  = book.description ? book.description.substring(0, 120) + (book.description.length > 120 ? '…' : '') : 'No description available.';
    const el = document.createElement('div');
    el.className = 'book-result';
    el.innerHTML = `
        <img class="book-cover-thumb"
             src="https://picsum.photos/seed/libbook${seed}/100/140"
             onerror="this.style.background='#1f2937';this.style.display='flex'"
             alt="${escHtml(book.title)}">
        <div class="book-result-info">
            <div class="book-result-title">${escHtml(book.title)}</div>
            <div class="book-result-author">by ${escHtml(book.author)}</div>
            <div class="book-result-tags">
                ${book.category ? `<span class="tag tag-cat">${escHtml(book.category)}</span>` : ''}
                <span class="tag ${avail ? 'tag-avail' : 'tag-none'}">${avail ? book.copies + ' cop' + (book.copies===1?'y':'ies') + ' available' : 'Unavailable'}</span>
                ${book.shelf ? `<span class="tag" style="background:rgba(255,255,255,.05);color:#8b949e">📍 ${escHtml(book.shelf)}</span>` : ''}
            </div>
            <div class="book-result-desc">${escHtml(desc)}</div>
            <div class="book-result-action">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                Search in library →
            </div>
        </div>`;
    el.onclick = () => window.location.href = `user_dashboard.php?search=${encodeURIComponent(book.title)}`;
    area.appendChild(el);
    area.scrollTop = area.scrollHeight;
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

// ── AI reply engine ────────────────────────────────────────────────────────────
function getReply(msg) {
    const q = msg.toLowerCase().trim();

    // ── Describe / About a specific book ──────────────────────────────────────
    if (q.includes('describe') || q.includes('tell me about') || q.includes('what is') ||
        q.includes('about the book') || q.includes('summary of') || q.includes('synopsis')) {
        const exact = BOOKS.filter(b => q.includes(b.title.toLowerCase()));
        if (exact.length > 0) {
            const b = exact[0];
            const desc = b.description || 'No description is available for this book in our records.';
            return {
                text: `📖 Here's everything about "${b.title}":`,
                books: [b]
            };
        }
        // Partial word match
        const words = q.split(/\s+/).filter(w => w.length > 3);
        const partial = BOOKS.filter(b => words.some(w => b.title.toLowerCase().includes(w) || b.author.toLowerCase().includes(w)));
        if (partial.length > 0) {
            return { text: `I found ${partial.length} possible match(es) for your query:`, books: partial.slice(0,4) };
        }
        return `I couldn't find that specific book. Could you tell me the exact title?\n\nOr try: "Show me Fiction books" to browse by genre.`;
    }

    // ── Suggest / Recommend ───────────────────────────────────────────────────
    if (q.includes('suggest') || q.includes('recommend') || q.includes('what should') ||
        q.includes('good book') || q.includes('what to read')) {
        const avail = BOOKS.filter(b => b.copies > 0 && b.status === 'Available');
        if (USER_CATS.length > 0) {
            const catMatch = avail.filter(b => USER_CATS.includes(b.category));
            const pool = catMatch.length >= 2 ? catMatch : avail;
            const picks = pool.sort(() => Math.random() - .5).slice(0, 4);
            return {
                text: `Based on your reading history in ${USER_CATS.slice(0,2).join(' & ')}, here are my top picks for you ✨`,
                books: picks
            };
        }
        const picks = avail.sort(() => Math.random() - .5).slice(0, 4);
        return picks.length
            ? { text: `Here are some great books available right now ✨`, books: picks }
            : `No books are available at the moment. Check back soon!`;
    }

    // ── Search by author ──────────────────────────────────────────────────────
    if (q.includes('by ') || q.includes('written by') || q.includes('author')) {
        const byMatch = q.match(/by\s+([a-z\s]+)/i);
        const authorSearch = byMatch ? byMatch[1].trim() : '';
        if (authorSearch.length > 2) {
            const found = BOOKS.filter(b => b.author.toLowerCase().includes(authorSearch.toLowerCase()));
            if (found.length > 0) {
                return { text: `Found ${found.length} book(s) by "${authorSearch}":`, books: found.slice(0,5) };
            }
            return `No books by "${authorSearch}" found. Try a partial name or check spelling.`;
        }
        return `Who's the author you're looking for?\nTry: "books by George Orwell" or "books by Khaled Hosseini"`;
    }

    // ── Genre / Category ──────────────────────────────────────────────────────
    const genreMap = { 'fiction':['Fiction'],'non-fiction':['Non-Fiction'],'nonfiction':['Non-Fiction'],
        'academic':['Academic'],'reference':['Reference'],'science':['Science','Academic'],
        'horror':['Horror'],'classic':['Fiction'],'novel':['Fiction'] };
    for (const [key, cats] of Object.entries(genreMap)) {
        if (q.includes(key)) {
            const found = BOOKS.filter(b => cats.includes(b.category) && b.copies > 0).slice(0,5);
            const all   = BOOKS.filter(b => cats.includes(b.category));
            return found.length
                ? { text: `📚 ${cats[0]} books (${found.length} of ${all.length} available):`, books: found }
                : `No ${cats[0]} books are currently available. ${all.length} titles exist but all are borrowed.`;
        }
    }
    if (q.includes('genre') || q.includes('categor') || q.includes('what type') || q.includes('show all')) {
        const cats = [...new Set(BOOKS.map(b => b.category).filter(Boolean))];
        const catStats = cats.map(c => {
            const avail = BOOKS.filter(b => b.category === c && b.copies > 0).length;
            const total = BOOKS.filter(b => b.category === c).length;
            return `• ${c}: ${total} titles (${avail} available)`;
        });
        return `📂 Library Genres:\n\n${catStats.join('\n')}\n\nJust say "show me [genre]" to browse!`;
    }

    // ── Fine system ───────────────────────────────────────────────────────────
    if (q.includes('fine') || q.includes('penalty') || q.includes('overdue') || q.includes('late fee')) {
        return `💰 Fine System — LIBRITE Library:

📅 Loan period: 4 days from approval
⚠️ Fine starts: Day 1 after due date
💸 Rate: ₹10 per day overdue

Example breakdown:
• 1 day late  → ₹10
• 5 days late → ₹50
• 10 days late → ₹100

💳 To pay: Go to your dashboard → Fine tab → Scan QR code or use UPI ID → Submit payment screenshot for admin to verify.`;
    }

    // ── Borrowing rules ───────────────────────────────────────────────────────
    if (q.includes('borrow') || q.includes('loan') || q.includes('how long') || q.includes('rule') || q.includes('process')) {
        return `📋 How to Borrow a Book:

1️⃣ Browse books in the dashboard
2️⃣ Click "Request Book" on available titles
3️⃣ Admin reviews & approves (usually within a day)
4️⃣ You get a notification when approved
5️⃣ Collect from library counter

📅 Loan period: 4 days
🔁 Return on time to avoid fines
⚠️ Fine: ₹10/day if overdue`;
    }

    // ── Library stats ─────────────────────────────────────────────────────────
    if (q.includes('how many') || q.includes('total') || q.includes('stat') || q.includes('count')) {
        const avail   = BOOKS.filter(b => b.copies > 0 && b.status === 'Available').length;
        const totalCp = BOOKS.reduce((s, b) => s + b.copies, 0);
        const cats    = [...new Set(BOOKS.map(b => b.category).filter(Boolean))];
        return `📊 LIBRITE Library Stats:

📚 Unique titles: ${BOOKS.length}
📦 Total copies: ${totalCp}
✅ Available titles: ${avail}
❌ Unavailable: ${BOOKS.length - avail}
📂 Genres: ${cats.length} (${cats.join(', ')})`;
    }

    // ── Popular / trending ────────────────────────────────────────────────────
    if (q.includes('popular') || q.includes('trending') || q.includes('best') || q.includes('top')) {
        const picks = BOOKS.filter(b => b.copies > 0 && b.status === 'Available').sort(() => Math.random() - .5).slice(0, 4);
        return picks.length
            ? { text: `🔥 Popular picks from our library:`, books: picks }
            : `No books available right now!`;
    }

    // ── Search by shelf ───────────────────────────────────────────────────────
    if (q.includes('shelf') || q.includes('rack') || q.includes('section')) {
        const shelfMatch = q.match(/shelf\s+([a-z0-9\-]+)/i);
        if (shelfMatch) {
            const sh = shelfMatch[1].toUpperCase();
            const found = BOOKS.filter(b => b.shelf && b.shelf.toUpperCase().includes(sh));
            return found.length
                ? { text: `Books on Shelf ${sh}:`, books: found.slice(0,5) }
                : `No books found on shelf "${sh}".`;
        }
        return `Which shelf are you looking for? Try: "books on shelf A-01"`;
    }

    // ── Greeting ──────────────────────────────────────────────────────────────
    if (/^(hi|hello|hey|hii|namaste|howdy)\b/.test(q)) {
        return `Hello ${USER_NAME}! 👋 Great to see you here.

I know all ${BOOKS.length} books in LIBRITE — just ask me about any of them! Try:
• "Suggest books for me"
• "Describe 1984 by George Orwell"
• "Show me Fiction books"
• "Books by Khaled Hosseini"`;
    }

    // ── Thank you ──────────────────────────────────────────────────────────────
    if (q.includes('thank') || q.includes('thanks') || q.includes('great') || q.includes('awesome')) {
        return `You're welcome! 😊 Happy reading, ${USER_NAME}! 📚\n\nCome back anytime you need help finding your next great book!`;
    }

    // ── Specific ISBN ──────────────────────────────────────────────────────────
    if (/\d{9,13}/.test(q) || q.includes('isbn')) {
        const isbnMatch = q.match(/\d{9,13}/);
        if (isbnMatch) {
            const found = BOOKS.filter(b => b.isbn && b.isbn.replace(/-/g,'').includes(isbnMatch[0]));
            return found.length
                ? { text: `Found by ISBN:`, books: found }
                : `No book found with ISBN "${isbnMatch[0]}".`;
        }
    }

    // ── Default / Fallback ────────────────────────────────────────────────────
    return `I didn't quite understand that 🤔

Here's what I can help with:
📖 "Describe [book title]" — get full book info
✨ "Suggest books for me" — personalised picks
👤 "Books by [author name]" — author search
📂 "Show [genre] books" — browse by category
💰 "How does the fine system work?" — fine info
📊 "Library stats" — overview of our collection

What would you like to know?`;
}

// Focus input on load
document.getElementById('chatInput').focus();
</script>
</body>
</html>