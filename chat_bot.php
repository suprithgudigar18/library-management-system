<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id'])) { header("Location: user_login.php"); exit(); }

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];

$userData = $pdo->prepare("SELECT * FROM users WHERE id=?");
$userData->execute([$user_id]);
$userData = $userData->fetch();

$books = $pdo->query("
    SELECT MIN(id) AS id, title, author, isbn,
           SUM(copies) AS copies,
           MAX(status) AS status,
           category, genre, shelf, description
    FROM books
    GROUP BY LOWER(TRIM(title)), LOWER(TRIM(author))
    ORDER BY title ASC
")->fetchAll();

$myReqStmt = $pdo->prepare("
    SELECT b.category FROM book_requests br
    JOIN books b ON b.id = br.book_id
    WHERE br.user_id = ? ORDER BY br.requested_at DESC LIMIT 10
");
$myReqStmt->execute([$user_id]);
$lastCats = array_unique(array_column($myReqStmt->fetchAll(), 'category'));

$totalAvail    = array_sum(array_column(array_filter($books, fn($b)=>$b['status']==='Available'), 'copies'));
$totalBorrowed = count(array_filter($books, fn($b)=>$b['status']==='Borrowed'));
$cats          = array_unique(array_column($books, 'category'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LIBRITE AI — Book Assistant</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root {
    --bg: #050810;
    --surface: #0c1120;
    --surface2: #121929;
    --surface3: #1a2436;
    --border: rgba(255,255,255,0.07);
    --border2: rgba(255,255,255,0.12);
    --accent: #4f8ef7;
    --accent2: #7c5cf6;
    --green: #34d399;
    --red: #f87171;
    --amber: #fbbf24;
    --text: #e2e8f0;
    --muted: #64748b;
    --muted2: #94a3b8;
}
* { margin:0; padding:0; box-sizing:border-box; }

body {
    background: var(--bg);
    color: var(--text);
    font-family: 'Outfit', sans-serif;
    height: 100vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background-image:
        radial-gradient(ellipse 80% 50% at 20% -10%, rgba(79,142,247,0.08) 0%, transparent 60%),
        radial-gradient(ellipse 60% 40% at 80% 110%, rgba(124,92,246,0.07) 0%, transparent 60%);
}

/* ── HEADER ── */
.header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    height: 64px;
    border-bottom: 1px solid var(--border);
    background: rgba(12,17,32,0.9);
    backdrop-filter: blur(20px);
    flex-shrink: 0;
    position: relative;
    z-index: 10;
}
.header-left { display: flex; align-items: center; gap: 14px; }

.ai-orb {
    width: 42px; height: 42px;
    border-radius: 50%;
    position: relative;
    flex-shrink: 0;
}
.ai-orb::before {
    content: '';
    position: absolute; inset: 0;
    border-radius: 50%;
    background: linear-gradient(135deg, #4f8ef7, #7c5cf6);
    animation: orbPulse 3s ease-in-out infinite;
}
.ai-orb-icon {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; z-index: 1;
}
@keyframes orbPulse {
    0%,100% { box-shadow: 0 0 0 0 rgba(79,142,247,0.4), 0 0 20px rgba(79,142,247,0.15); }
    50% { box-shadow: 0 0 0 6px rgba(79,142,247,0), 0 0 30px rgba(79,142,247,0.25); }
}

.header-brand { }
.brand-name { font-size: 16px; font-weight: 700; color: white; letter-spacing: -0.3px; }
.brand-status {
    font-size: 11px; color: var(--green);
    display: flex; align-items: center; gap: 5px; margin-top: 1px;
}
.status-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: var(--green);
    animation: blink 2s ease-in-out infinite;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.4} }

.header-right { display: flex; align-items: center; gap: 10px; }

.stat-pill {
    display: flex; align-items: center; gap: 6px;
    padding: 5px 12px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 20px;
    font-size: 12px; color: var(--muted2); font-weight: 500;
}
.stat-pill strong { color: white; }

.back-btn {
    display: flex; align-items: center; gap: 6px;
    padding: 7px 14px; border-radius: 10px;
    background: var(--surface2); border: 1px solid var(--border2);
    color: var(--muted2); text-decoration: none; font-size: 13px; font-weight: 500;
    transition: all .2s; font-family: 'Outfit', sans-serif;
}
.back-btn:hover { border-color: var(--accent); color: var(--accent); background: rgba(79,142,247,0.08); }

/* ── CHAT AREA ── */
.chat-wrap {
    flex: 1;
    overflow-y: auto;
    padding: 24px 20px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    scrollbar-width: thin;
    scrollbar-color: var(--surface3) transparent;
}
.chat-wrap::-webkit-scrollbar { width: 4px; }
.chat-wrap::-webkit-scrollbar-thumb { background: var(--surface3); border-radius: 4px; }

/* ── MESSAGES ── */
.msg-row { display: flex; gap: 12px; align-items: flex-end; max-width: 780px; }
.msg-row.user { flex-direction: row-reverse; margin-left: auto; }

.msg-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; flex-shrink: 0;
}
.msg-avatar.bot {
    background: linear-gradient(135deg, #4f8ef7, #7c5cf6);
    color: white; font-size: 16px;
}
.msg-avatar.user {
    background: var(--surface3);
    border: 1px solid var(--border2);
    color: var(--accent);
    font-size: 13px; font-weight: 700;
}

.msg-content { flex: 1; min-width: 0; }

.msg-bubble {
    padding: 12px 16px;
    border-radius: 16px;
    font-size: 14px; line-height: 1.65;
    white-space: pre-line;
}
.msg-bubble.bot {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 4px 16px 16px 16px;
    color: var(--text);
}
.msg-bubble.user {
    background: linear-gradient(135deg, rgba(79,142,247,0.15), rgba(124,92,246,0.12));
    border: 1px solid rgba(79,142,247,0.2);
    border-radius: 16px 4px 16px 16px;
    color: var(--text);
    text-align: right;
    margin-left: auto;
    max-width: 480px;
}
.msg-time {
    font-size: 11px; color: var(--muted);
    margin-top: 5px; padding: 0 4px;
    font-family: 'JetBrains Mono', monospace;
}
.msg-row.user .msg-time { text-align: right; }

/* ── BOOK CARDS ── */
.books-grid {
    display: flex; flex-direction: column; gap: 10px;
    padding-left: 46px; margin-top: 4px;
}
.book-card {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 14px;
    display: flex; gap: 14px; align-items: flex-start;
    cursor: pointer;
    transition: all .2s;
    max-width: 520px;
    position: relative; overflow: hidden;
}
.book-card::before {
    content: '';
    position: absolute; left: 0; top: 0; bottom: 0;
    width: 3px;
    background: linear-gradient(180deg, var(--accent), var(--accent2));
    border-radius: 3px 0 0 3px;
    opacity: 0; transition: opacity .2s;
}
.book-card:hover {
    border-color: rgba(79,142,247,0.3);
    background: var(--surface3);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.3);
}
.book-card:hover::before { opacity: 1; }

.book-thumb {
    width: 52px; height: 72px;
    border-radius: 8px; object-fit: cover;
    background: var(--surface3); flex-shrink: 0;
    border: 1px solid var(--border);
}
.book-info { flex: 1; min-width: 0; }
.book-title { font-size: 14px; font-weight: 600; color: white; line-height: 1.3; margin-bottom: 3px; }
.book-author { font-size: 12px; color: var(--muted2); margin-bottom: 8px; }
.book-pills { display: flex; gap: 5px; flex-wrap: wrap; margin-bottom: 7px; }
.pill {
    font-size: 11px; padding: 2px 9px; border-radius: 20px;
    font-weight: 600; letter-spacing: 0.2px;
}
.pill-cat { background: rgba(79,142,247,0.12); color: #7eb3ff; border: 1px solid rgba(79,142,247,0.2); }
.pill-avail { background: rgba(52,211,153,0.1); color: #6ee7b7; border: 1px solid rgba(52,211,153,0.2); }
.pill-none { background: rgba(248,113,113,0.1); color: #fca5a5; border: 1px solid rgba(248,113,113,0.2); }
.pill-shelf { background: rgba(255,255,255,0.05); color: var(--muted2); border: 1px solid var(--border); }
.pill-external { background: rgba(251,191,36,0.1); color: #fcd34d; border: 1px solid rgba(251,191,36,0.2); }
.book-desc {
    font-size: 12px; color: var(--muted);
    line-height: 1.5;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.book-cta {
    display: inline-flex; align-items: center; gap: 4px;
    margin-top: 8px; font-size: 11px; font-weight: 600; color: var(--accent);
}

/* ── EXTERNAL BOOK CARD ── */
.ext-badge {
    position: absolute; top: 10px; right: 10px;
    font-size: 10px; padding: 2px 7px;
    background: rgba(251,191,36,0.1); color: #fcd34d;
    border: 1px solid rgba(251,191,36,0.2); border-radius: 10px;
    font-weight: 600; letter-spacing: 0.3px;
}

/* ── TYPING ── */
.typing-row { display: flex; gap: 12px; align-items: flex-end; }
.typing-bubble {
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: 4px 16px 16px 16px; padding: 14px 18px;
    display: flex; align-items: center; gap: 5px;
}
.typing-bubble span {
    width: 7px; height: 7px; border-radius: 50%;
    background: var(--muted);
    animation: tdot .9s ease-in-out infinite;
}
.typing-bubble span:nth-child(2) { animation-delay: .18s; }
.typing-bubble span:nth-child(3) { animation-delay: .36s; }
@keyframes tdot {
    0%,100% { transform: translateY(0); opacity: .4; }
    50% { transform: translateY(-5px); opacity: 1; }
}

/* ── QUICK CHIPS ── */
.chips-row {
    padding: 12px 20px 4px;
    display: flex; gap: 7px; flex-wrap: wrap;
    border-top: 1px solid var(--border);
    flex-shrink: 0;
}
.chip {
    padding: 6px 14px; border-radius: 20px;
    background: var(--surface2); border: 1px solid var(--border);
    color: var(--muted2); font-size: 12px; font-weight: 500;
    cursor: pointer; transition: all .2s; white-space: nowrap;
    font-family: 'Outfit', sans-serif;
}
.chip:hover { background: rgba(79,142,247,0.1); border-color: rgba(79,142,247,0.4); color: #7eb3ff; }
.chip.active { background: rgba(79,142,247,0.1); border-color: rgba(79,142,247,0.4); color: #7eb3ff; }

/* ── INPUT BAR ── */
.input-bar {
    padding: 12px 20px 16px;
    display: flex; gap: 10px; align-items: center;
    background: rgba(12,17,32,0.95); backdrop-filter: blur(20px);
    border-top: 1px solid var(--border); flex-shrink: 0;
}
.chat-input {
    flex: 1;
    background: var(--surface2);
    border: 1px solid var(--border2);
    border-radius: 14px;
    color: white; padding: 12px 18px;
    font-size: 14px; font-family: 'Outfit', sans-serif;
    outline: none; transition: border-color .2s;
    line-height: 1.5;
}
.chat-input:focus { border-color: rgba(79,142,247,0.5); background: var(--surface3); }
.chat-input::placeholder { color: var(--muted); }

.send-btn {
    width: 46px; height: 46px;
    border-radius: 14px; border: none;
    background: linear-gradient(135deg, #4f8ef7, #7c5cf6);
    color: white; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; transition: all .2s;
    box-shadow: 0 4px 16px rgba(79,142,247,0.3);
}
.send-btn:hover { transform: scale(1.06); box-shadow: 0 6px 22px rgba(79,142,247,0.45); }
.send-btn:active { transform: scale(0.96); }

/* Welcome banner */
.welcome-banner {
    background: linear-gradient(135deg, rgba(79,142,247,0.08), rgba(124,92,246,0.06));
    border: 1px solid rgba(79,142,247,0.15);
    border-radius: 18px; padding: 20px 22px; max-width: 520px;
}
.welcome-greeting { font-size: 18px; font-weight: 700; color: white; margin-bottom: 6px; }
.welcome-sub { font-size: 13px; color: var(--muted2); line-height: 1.6; margin-bottom: 14px; }
.welcome-caps {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 8px;
}
.cap-item {
    display: flex; align-items: center; gap: 8px;
    background: rgba(255,255,255,0.04); border: 1px solid var(--border);
    border-radius: 10px; padding: 8px 11px;
    font-size: 12px; color: var(--muted2);
}
.cap-icon { font-size: 15px; }

/* Mobile */
@media(max-width:600px){
    .stat-pill { display: none; }
    .books-grid { padding-left: 0; }
    .book-card { max-width: 100%; }
    .msg-bubble.user { max-width: 100%; }
}
</style>
</head>
<body>

<!-- HEADER -->
<div class="header">
    <div class="header-left">
        <div class="ai-orb">
            <div class="ai-orb-icon">🤖</div>
        </div>
        <div class="header-brand">
            <div class="brand-name">LIBRITE AI</div>
            <div class="brand-status">
                <span class="status-dot"></span>
                Online · <?= count($books) ?> books indexed
            </div>
        </div>
    </div>
    <div class="header-right">
        <div class="stat-pill">📚 <strong><?= count($books) ?></strong> titles</div>
        <div class="stat-pill" style="color:#6ee7b7"><strong style="color:#6ee7b7"><?= $totalAvail ?></strong> available</div>
        <a href="user_dashboard.php" class="back-btn">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Dashboard
        </a>
    </div>
</div>

<!-- CHAT AREA -->
<div class="chat-wrap" id="chatArea">
    <div class="msg-row">
        <div class="msg-avatar bot">🤖</div>
        <div class="msg-content">
            <div class="welcome-banner">
                <div class="welcome-greeting">Hello, <?= htmlspecialchars($userData['full_name'] ?: $username) ?>! 👋</div>
                <div class="welcome-sub">I'm LIBRITE AI — your intelligent library assistant. I have full knowledge of our <?= count($books) ?> books, and I can also look up <em>any book in the world</em> via the web.</div>
                <div class="welcome-caps">
                    <div class="cap-item"><span class="cap-icon">🔍</span> Search & describe books</div>
                    <div class="cap-item"><span class="cap-icon">✨</span> Personalised picks</div>
                    <div class="cap-item"><span class="cap-icon">🌐</span> Any book, worldwide</div>
                    <div class="cap-item"><span class="cap-icon">💰</span> Fine & borrow rules</div>
                </div>
            </div>
            <div class="msg-time">Just now</div>
        </div>
    </div>
</div>

<!-- QUICK CHIPS -->
<div class="chips-row" id="chipsRow">
    <span class="chip" onclick="sendQuick('Suggest books for me')">✨ Suggest for me</span>
    <span class="chip" onclick="sendQuick('Show Fiction books available')">📖 Fiction</span>
    <span class="chip" onclick="sendQuick('Show Academic books')">🎓 Academic</span>
    <span class="chip" onclick="sendQuick('Explain fine system')">💰 Fines</span>
    <span class="chip" onclick="sendQuick('Library statistics')">📊 Stats</span>
    <span class="chip" onclick="sendQuick('Popular books')">🔥 Popular</span>
    <span class="chip" onclick="sendQuick('How do I borrow a book?')">📋 Borrow rules</span>
</div>

<!-- INPUT BAR -->
<div class="input-bar">
    <input type="text" class="chat-input" id="chatInput"
           placeholder="Ask about any book, get suggestions, check rules… (Enter to send)"
           onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault();sendMsg();}">
    <button class="send-btn" onclick="sendMsg()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="22" y1="2" x2="11" y2="13"></line>
            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
        </svg>
    </button>
</div>

<script>
const BOOKS     = <?= json_encode(array_map(fn($b) => [
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
const USER_CATS = <?= json_encode($lastCats) ?>;
const USER_NAME = "<?= htmlspecialchars($userData['full_name'] ?: $username) ?>";
const USER_INIT = "<?= strtoupper(substr($userData['full_name'] ?: $username, 0, 1)) ?>";

/* ── Utilities ── */
const now = () => new Date().toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'});
function esc(s){ const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
function scroll(){ const a=document.getElementById('chatArea'); a.scrollTop=a.scrollHeight; }

/* ── Quick chip ── */
function sendQuick(msg){
    document.getElementById('chatInput').value = msg;
    sendMsg();
}

/* ── Send message ── */
function sendMsg(){
    const inp = document.getElementById('chatInput');
    const msg = inp.value.trim();
    if(!msg) return;
    inp.value = '';
    appendUser(msg);
    const typing = appendTyping();
    setTimeout(async()=>{
        try{
            const reply = await getReply(msg);
            typing.remove();
            if(typeof reply === 'string'){
                appendBot(reply);
            } else {
                appendBot(reply.text);
                if(reply.books && reply.books.length)  appendBooks(reply.books, false);
                if(reply.extBooks && reply.extBooks.length) appendBooks(reply.extBooks, true);
            }
        } catch(e){
            typing.remove();
            appendBot("Hmm, something went wrong on my end 🛠️\nPlease try again in a moment.");
            console.error(e);
        }
    }, 500 + Math.random()*400);
}

/* ── DOM helpers ── */
function appendUser(text){
    const area = document.getElementById('chatArea');
    const el = document.createElement('div');
    el.className = 'msg-row user';
    el.innerHTML = `
        <div class="msg-avatar user">${USER_INIT}</div>
        <div class="msg-content">
            <div class="msg-bubble user">${esc(text)}</div>
            <div class="msg-time">${now()}</div>
        </div>`;
    area.appendChild(el); scroll();
}

function appendBot(text){
    const area = document.getElementById('chatArea');
    const el = document.createElement('div');
    el.className = 'msg-row';
    el.innerHTML = `
        <div class="msg-avatar bot">🤖</div>
        <div class="msg-content">
            <div class="msg-bubble bot">${esc(text)}</div>
            <div class="msg-time">${now()}</div>
        </div>`;
    area.appendChild(el); scroll();
}

function appendTyping(){
    const area = document.getElementById('chatArea');
    const el = document.createElement('div');
    el.className = 'typing-row';
    el.innerHTML = `
        <div class="msg-avatar bot">🤖</div>
        <div class="typing-bubble"><span></span><span></span><span></span></div>`;
    area.appendChild(el); scroll();
    return el;
}

function appendBooks(books, isExternal){
    const area = document.getElementById('chatArea');
    const grid = document.createElement('div');
    grid.className = 'books-grid';
    books.forEach(b => {
        const avail    = b.copies > 0 && b.status === 'Available';
        const isbnClean= b.isbn ? b.isbn.replace(/[^0-9Xx]/g,'') : '';
        const seed     = ((b.id||Math.floor(Math.random()*999)) * 37) % 1000;
        const fallback = `https://picsum.photos/seed/lib${seed}/100/140`;
        const cover    = isbnClean
            ? `https://covers.openlibrary.org/b/isbn/${isbnClean}-M.jpg?default=false`
            : fallback;
        const desc     = b.description
            ? b.description.substring(0,130) + (b.description.length > 130 ? '…' : '')
            : 'No description available.';

        const card = document.createElement('div');
        card.className = 'book-card';
        card.innerHTML = `
            ${isExternal ? '<span class="ext-badge">🌐 Web Result</span>' : ''}
            <img class="book-thumb" src="${cover}"
                 onerror="if(this.src!=='${fallback}')this.src='${fallback}'"
                 alt="${esc(b.title)}">
            <div class="book-info">
                <div class="book-title">${esc(b.title)}</div>
                <div class="book-author">by ${esc(b.author)}</div>
                <div class="book-pills">
                    ${b.category ? `<span class="pill pill-cat">${esc(b.category)}</span>` : ''}
                    ${isExternal
                        ? `<span class="pill pill-external">Not in library</span>`
                        : `<span class="pill ${avail?'pill-avail':'pill-none'}">${avail ? b.copies+' cop'+(b.copies===1?'y':'ies')+' available' : 'All borrowed'}</span>`}
                    ${b.shelf && !isExternal ? `<span class="pill pill-shelf">📍 ${esc(b.shelf)}</span>` : ''}
                </div>
                <div class="book-desc">${esc(desc)}</div>
                ${!isExternal ? `<div class="book-cta">Search in library →</div>` : `<div class="book-cta" style="color:var(--amber)">Request acquisition →</div>`}
            </div>`;
        if(!isExternal){
            card.onclick = () => window.location.href = `user_dashboard.php?search=${encodeURIComponent(b.title)}`;
        }
        grid.appendChild(card);
    });
    area.appendChild(grid); scroll();
}

/* ═══════════════════════════════════════════════════════
   AI REPLY ENGINE
═══════════════════════════════════════════════════════ */

/* Fuzzy local search */
function localSearch(query){
    const q = query.toLowerCase();
    const words = q.split(/\s+/).filter(w=>w.length>2);
    return BOOKS.filter(b=>{
        const hay = (b.title+' '+b.author+' '+b.category+' '+b.genre).toLowerCase();
        return words.some(w=>hay.includes(w)) || hay.includes(q);
    });
}

/* Google Books API */
async function fetchGoogleBooks(query, maxResults=4){
    const res  = await fetch(`https://www.googleapis.com/books/v1/volumes?q=${encodeURIComponent(query)}&maxResults=${maxResults}&langRestrict=en`);
    const data = await res.json();
    if(!data.items) return [];
    return data.items.map(item=>{
        const v = item.volumeInfo;
        return {
            id: Math.floor(Math.random()*100000),
            title:       v.title || 'Unknown Title',
            author:      v.authors ? v.authors.join(', ') : 'Unknown Author',
            category:    v.categories ? v.categories[0] : '',
            genre:       '',
            status:      'Unavailable',
            copies:      0,
            shelf:       '',
            description: v.description || '',
            isbn:        v.industryIdentifiers ? (v.industryIdentifiers.find(i=>i.type==='ISBN_13')||v.industryIdentifiers[0]||{}).identifier||'' : '',
        };
    });
}

/* Main intent handler */
async function getReply(msg){
    const q = msg.toLowerCase().trim();

    /* ── Describe / Info about a book ── */
    if(/\b(describe|tell me about|what is|about the book|summary of|synopsis|who wrote|info on|details about|review)\b/.test(q)){
        let searchTerm = q
            .replace(/\b(describe|tell me about|what is|about the book|summary of|synopsis of|synopsis|who wrote|info on|details about|review of|review|the book|can you|please|me)\b/gi,'')
            .replace(/\?/g,'').trim();

        if(!searchTerm || searchTerm.length < 2) return "Which book would you like me to describe? You can also just type the book name directly!";

        // 1. Exact local match
        const localMatches = localSearch(searchTerm);
        if(localMatches.length > 0){
            const book = localMatches[0];
            if(book.description && book.description.length > 30){
                return {
                    text: `📖 Found "${book.title}" in our library!\n\n${book.description}`,
                    books: [book]
                };
            }
        }

        // 2. Google Books for description
        try{
            const gBooks = await fetchGoogleBooks(searchTerm, 1);
            if(gBooks.length > 0){
                const gb = gBooks[0];
                const desc = gb.description
                    ? gb.description.substring(0, 600) + (gb.description.length > 600 ? '…' : '')
                    : 'No description found online either.';

                if(localMatches.length > 0){
                    // We have local book but no description — enrich from Google
                    return {
                        text: `📖 "${localMatches[0].title}" — here's what we know:\n\n${desc}`,
                        books: [localMatches[0]]
                    };
                } else {
                    // Not in our library at all — show web result
                    gb.description = desc;
                    return {
                        text: `🌐 "${gb.title}" isn't in our library yet, but here's what I found online:\n\n${desc}\n\n💡 You can ask the admin to add this book!`,
                        extBooks: [gb]
                    };
                }
            }
        } catch(e){ console.error(e); }

        if(localMatches.length > 0){
            return {
                text: `I found "${localMatches[0].title}" in our library, but couldn't fetch more details online right now.`,
                books: localMatches.slice(0,3)
            };
        }
        return `I couldn't find "${searchTerm}" in our library or online. Double-check the spelling, or try a partial title!`;
    }

    /* ── Generic book name (no describe keyword) — smart detect ── */
    if(!/(suggest|recommend|fine|borrow|rule|stat|how many|total|popular|genre|categor|show|list|author|by\s+\w|shelf|hi|hello|hey|thank|help)/i.test(q) && q.length > 4 && q.split(' ').length <= 8){
        const localMatches = localSearch(q);
        if(localMatches.length > 0){
            const book = localMatches[0];
            const desc = book.description && book.description.length > 20 ? book.description : null;
            if(desc) return { text: `📖 Found "${book.title}" in our library!\n\n${desc}`, books: [book] };
            // Try Google Books for description
            try{
                const gBooks = await fetchGoogleBooks(q, 1);
                if(gBooks.length && gBooks[0].description){
                    const gDesc = gBooks[0].description.substring(0,500)+'…';
                    return { text: `📖 "${book.title}" — here's what I found:\n\n${gDesc}`, books: [book] };
                }
            } catch(e){}
            return { text: `📚 Found "${book.title}" in our library!`, books: localMatches.slice(0,3) };
        }
        // Try online search
        try{
            const gBooks = await fetchGoogleBooks(q, 3);
            if(gBooks.length){
                return {
                    text: `🌐 "${q}" isn't in our library. Here are some results from the web:\n\nYou can request the admin to add these books!`,
                    extBooks: gBooks
                };
            }
        } catch(e){}
    }

    /* ── Suggest / Recommend ── */
    if(/\b(suggest|recommend|what should i read|good book|what to read|reading list|pick for me)\b/.test(q)){
        const avail = BOOKS.filter(b => b.copies > 0 && b.status === 'Available');
        if(USER_CATS.length > 0){
            const matched = avail.filter(b => USER_CATS.includes(b.category));
            const pool    = matched.length >= 2 ? matched : avail;
            const picks   = pool.sort(()=>Math.random()-.5).slice(0,4);
            return {
                text: `Based on your reading history in ${USER_CATS.slice(0,2).join(' & ')}, here are my top picks ✨`,
                books: picks
            };
        }
        const picks = avail.sort(()=>Math.random()-.5).slice(0,4);
        return picks.length
            ? { text:`Here are some great books available right now ✨`, books: picks }
            : `No books available at the moment — check back soon!`;
    }

    /* ── Search by author ── */
    if(/\b(by |written by|author|books? of)\b/.test(q)){
        const match = q.match(/(?:by|written by|of)\s+([a-z\s'.\-]+)/i);
        const authorSearch = match ? match[1].replace(/\?/,'').trim() : '';
        if(authorSearch.length > 2){
            const found = BOOKS.filter(b => b.author.toLowerCase().includes(authorSearch.toLowerCase()));
            if(found.length) return { text:`Found ${found.length} book(s) by "${authorSearch}":`, books: found.slice(0,5) };
            // Try online
            try{
                const gBooks = await fetchGoogleBooks(`author:${authorSearch}`, 4);
                if(gBooks.length) return {
                    text: `No books by "${authorSearch}" in our library, but here are some online results 🌐`,
                    extBooks: gBooks
                };
            } catch(e){}
            return `No books by "${authorSearch}" found — try partial name or check spelling.`;
        }
        return `Who's the author? Try: "books by George Orwell" or "books by Amish Tripathi"`;
    }

    /* ── Genre / Category ── */
    const genreMap = {
        'fiction':['Fiction'],'non-fiction':['Non-Fiction'],'nonfiction':['Non-Fiction'],
        'academic':['Academic'],'reference':['Reference'],'science':['Science','Academic'],
        'horror':['Horror'],'classic':['Fiction'],'novel':['Fiction'],
        'mystery':['Fiction'],'biography':['Non-Fiction'],'history':['Non-Fiction','Academic'],
        'children':['Fiction'],'self help':['Non-Fiction'],'self-help':['Non-Fiction'],
    };
    for(const [key,cats] of Object.entries(genreMap)){
        if(q.includes(key)){
            const avail = BOOKS.filter(b=>cats.includes(b.category)&&b.copies>0).slice(0,5);
            const all   = BOOKS.filter(b=>cats.includes(b.category));
            return avail.length
                ? { text:`📚 ${cats[0]} books (${avail.length} of ${all.length} available):`, books: avail }
                : `No ${cats[0]} books available right now. ${all.length} titles exist but all are borrowed.`;
        }
    }

    /* ── Show all genres ── */
    if(/\b(genre|categor|what types?|show all|list all)\b/.test(q)){
        const cats = [...new Set(BOOKS.map(b=>b.category).filter(Boolean))];
        const lines = cats.map(c=>{
            const a = BOOKS.filter(b=>b.category===c&&b.copies>0).length;
            const t = BOOKS.filter(b=>b.category===c).length;
            return `• ${c}: ${t} titles (${a} available)`;
        });
        return `📂 Library Genres:\n\n${lines.join('\n')}\n\nSay "show me [genre]" to browse any of these!`;
    }

    /* ── Fine system ── */
    if(/\b(fine|penalty|overdue|late fee|charge|payment)\b/.test(q)){
        return `💰 Fine System — LIBRITE Library

📅 Loan period: 4 days from approval
⚠️  Fine starts: Day 1 after due date
💸 Rate: ₹10 per day overdue

Example breakdown:
  • 1 day late   →  ₹10
  • 5 days late  →  ₹50
  • 10 days late →  ₹100

💳 How to pay:
  Dashboard → Fine tab → Scan QR / use UPI ID
  → Submit payment screenshot for admin verification.`;
    }

    /* ── Borrow rules ── */
    if(/\b(borrow|loan|how long|rule|process|request|return)\b/.test(q)){
        return `📋 How to Borrow a Book

1️⃣  Browse books in the Dashboard
2️⃣  Click "Request Book" on available titles
3️⃣  Admin reviews & approves (usually within a day)
4️⃣  You get notified when approved
5️⃣  Collect from the library counter

⏱  Loan period: 4 days
🔁  Return on time to avoid fines
⚠️  Fine: ₹10/day if overdue`;
    }

    /* ── Library stats ── */
    if(/\b(how many|total|stat|count|overview|numbers?)\b/.test(q)){
        const avail   = BOOKS.filter(b=>b.copies>0&&b.status==='Available').length;
        const totalCp = BOOKS.reduce((s,b)=>s+b.copies,0);
        const catList = [...new Set(BOOKS.map(b=>b.category).filter(Boolean))];
        return `📊 LIBRITE Library Stats

📚 Unique titles:    ${BOOKS.length}
📦 Total copies:     ${totalCp}
✅ Available titles: ${avail}
❌ Unavailable:      ${BOOKS.length - avail}
📂 Genres:           ${catList.length} (${catList.join(', ')})`;
    }

    /* ── Popular / Trending ── */
    if(/\b(popular|trending|best|top|featured|recommended)\b/.test(q)){
        const picks = BOOKS.filter(b=>b.copies>0&&b.status==='Available').sort(()=>Math.random()-.5).slice(0,4);
        return picks.length
            ? { text:`🔥 Popular picks from our library right now:`, books: picks }
            : `No books available at the moment!`;
    }

    /* ── Shelf ── */
    if(/\b(shelf|rack|section|aisle)\b/.test(q)){
        const m = q.match(/shelf\s+([a-z0-9\-]+)/i);
        if(m){
            const sh = m[1].toUpperCase();
            const found = BOOKS.filter(b=>b.shelf&&b.shelf.toUpperCase().includes(sh));
            return found.length
                ? { text:`Books on Shelf ${sh}:`, books: found.slice(0,5) }
                : `No books found on shelf "${sh}".`;
        }
        return `Which shelf? Try: "books on shelf A-01"`;
    }

    /* ── ISBN lookup ── */
    if(/isbn|\d{9,13}/.test(q)){
        const m = q.match(/\d{9,13}/);
        if(m){
            const found = BOOKS.filter(b=>b.isbn&&b.isbn.replace(/-/g,'').includes(m[0]));
            if(found.length) return { text:`Found by ISBN ${m[0]}:`, books: found };
            // Try Google Books
            try{
                const gBooks = await fetchGoogleBooks(`isbn:${m[0]}`, 1);
                if(gBooks.length) return { text:`ISBN ${m[0]} not in our library. Found online:`, extBooks: gBooks };
            } catch(e){}
            return `No book with ISBN "${m[0]}" found.`;
        }
    }

    /* ── Greetings ── */
    if(/^(hi|hello|hey|hii|namaste|howdy|good (morning|evening|afternoon))\b/.test(q)){
        return `Hello ${USER_NAME}! 👋 Great to see you here.

I know all ${BOOKS.length} books in LIBRITE — and I can look up any book in the world too! Try:
  📖 "Describe 1984 by George Orwell"
  ✨ "Suggest books for me"
  📂 "Show Fiction books"
  👤 "Books by Khaled Hosseini"
  🌐 "Tell me about Atomic Habits" (even if it's not in our library!)`;
    }

    /* ── Thanks ── */
    if(/\b(thank|thanks|great|awesome|perfect|excellent|good bot)\b/.test(q)){
        return `You're welcome, ${USER_NAME}! 😊 Happy reading! 📚`;
    }

    /* ── Help ── */
    if(/\b(help|what can you|commands|options)\b/.test(q)){
        return `Here's what I can do:\n\n📖 Describe any book (even if not in library)\n✨ Suggest personalised picks\n👤 Search by author name\n📂 Browse by genre/category\n📍 Find books by shelf number\n🔢 Look up by ISBN\n💰 Fine & penalty info\n📋 Borrowing rules & process\n📊 Library statistics\n\nJust type naturally — I'll understand!`;
    }

    /* ── Fallback — try Google Books as last resort ── */
    try{
        const gBooks = await fetchGoogleBooks(q, 3);
        if(gBooks.length && q.split(' ').length <= 6){
            return {
                text: `I found some books related to "${msg}" 🌐\n(These may not be in our library yet)`,
                extBooks: gBooks
            };
        }
    } catch(e){}

    return `I didn't quite catch that 🤔\n\nTry asking:\n• "Describe [any book name]"\n• "Suggest books for me"\n• "Books by [author]"\n• "Show Fiction books"\n• "Fine system"\n• "How to borrow"\n\nOr just type any book name and I'll search our library and the web!`;
}

// Focus input on load
document.getElementById('chatInput').focus();
</script>
</body>
</html>