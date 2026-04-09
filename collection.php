<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LIBRITE - Our Collections</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top left, #0f172a, #020617);
            min-height: 100vh;
            color: #f8fafc;
        }

        .glass-card {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .glass-card:hover {
            transform: translateY(-5px);
            background: rgba(30, 41, 59, 0.8);
            border-color: rgba(56, 189, 248, 0.5);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
        }

        .gradient-text {
            background: linear-gradient(to right, #38bdf8, #818cf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .book-badge {
            background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #020617;
        }
        ::-webkit-scrollbar-thumb {
            background: #1e293b;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #334155;
        }
    </style>
</head>
<body class="p-4 md:p-8">

    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <header class="text-center mb-12">
            <h1 class="text-4xl md:text-5xl font-bold gradient-text mb-4">Our Collections</h1>
            <p class="text-slate-400 max-w-2xl mx-auto">Explore our diverse range of academic resources, competitive exam materials, and literature curated for Edurite College.</p>
        </header>

        <!-- Main Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-16">
            
            <!-- Academic Card -->
            <div class="glass-card p-6 rounded-2xl">
                <div class="w-12 h-12 bg-sky-500/20 rounded-lg flex items-center justify-center mb-4 text-sky-400">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M8 7h6"/><path d="M8 11h8"/></svg>
                </div>
                <h3 class="text-xl font-semibold mb-2">Academic & Textbooks</h3>
                <p class="text-slate-400 text-sm leading-relaxed">Comprehensive resources for Management and Commerce studies, tailored to the university curriculum.</p>
            </div>

            <!-- Competitive Exams -->
            <div class="glass-card p-6 rounded-2xl">
                <div class="w-12 h-12 bg-indigo-500/20 rounded-lg flex items-center justify-center mb-4 text-indigo-400">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                </div>
                <h3 class="text-xl font-semibold mb-2">Competitive Exams</h3>
                <p class="text-slate-400 text-sm leading-relaxed">Specialized materials for UPSC, KPSC, banking, and entrance exams with updated modules.</p>
            </div>

            <!-- Journals -->
            <div class="glass-card p-6 rounded-2xl">
                <div class="w-12 h-12 bg-emerald-500/20 rounded-lg flex items-center justify-center mb-4 text-emerald-400">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6Z"/></svg>
                </div>
                <h3 class="text-xl font-semibold mb-2">Journals & Periodicals</h3>
                <p class="text-slate-400 text-sm leading-relaxed">National and international subscriptions for the latest research and industry trends.</p>
            </div>

            <!-- Digital Archives -->
            <div class="glass-card p-6 rounded-2xl">
                <div class="w-12 h-12 bg-purple-500/20 rounded-lg flex items-center justify-center mb-4 text-purple-400">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>
                </div>
                <h3 class="text-xl font-semibold mb-2">Digital Archives</h3>
                <p class="text-slate-400 text-sm leading-relaxed">Access to e-books, past question papers, and project reports in high-definition digital format.</p>
            </div>

            <!-- Regional Literature -->
            <div class="glass-card p-6 rounded-2xl">
                <div class="w-12 h-12 bg-amber-500/20 rounded-lg flex items-center justify-center mb-4 text-amber-400">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19 7-7 3 3-7 7-3-3Z"/><path d="m18 13-1.5-7.5L2 2l3.5 14.5L13 18l5-5Z"/><path d="m2 2 5 5"/><path d="m8.5 8.5 7 7"/></svg>
                </div>
                <h3 class="text-xl font-semibold mb-2">Regional Literature</h3>
                <p class="text-slate-400 text-sm leading-relaxed">Extensive collection of Kannada and English novels, poetry, and classical literature.</p>
            </div>

            <!-- Reference Section -->
            <div class="glass-card p-6 rounded-2xl">
                <div class="w-12 h-12 bg-rose-500/20 rounded-lg flex items-center justify-center mb-4 text-rose-400">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                </div>
                <h3 class="text-xl font-semibold mb-2">Reference Section</h3>
                <p class="text-slate-400 text-sm leading-relaxed">Encyclopedias, dictionaries, and yearbooks for deep-dive research and fact-checking.</p>
            </div>

        </div>

        <!-- Single Book Information Section -->
        <section class="mt-20">
            <h2 class="text-2xl font-bold mb-8 flex items-center gap-2">
                <span class="w-8 h-1 bg-sky-500 rounded-full"></span>
                Featured Book Information
            </h2>
            
            <!-- Book Info Card -->
            <div class="glass-card flex flex-col md:flex-row rounded-3xl overflow-hidden max-w-3xl mx-auto shadow-2xl group">
                <!-- Book Cover Side -->
                <div class="w-full md:w-2/5 bg-slate-800 relative flex items-center justify-center p-8 overflow-hidden">
                    <!-- Decorative Background -->
                    <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-sky-500 via-transparent to-transparent"></div>
                    
                    <!-- SVG Book Mockup -->
                    <div class="relative w-48 h-64 shadow-[20px_20px_50px_rgba(0,0,0,0.5)] transition-transform group-hover:scale-105 duration-500">
                        <svg viewBox="0 0 100 130" class="w-full h-full drop-shadow-2xl">
                            <rect width="100" height="130" rx="4" fill="#1e293b" />
                            <rect x="5" y="0" width="10" height="130" fill="#0f172a" opacity="0.3" />
                            <text x="50" y="45" font-family="serif" font-size="8" fill="#38bdf8" text-anchor="middle" font-weight="bold">HORRORS
</text>
                            <text x="50" y="55" font-family="serif" font-size="6" fill="#94a3b8" text-anchor="middle">NEXT
DOOoorrrr...</text>
                            <line x1="30" y1="70" x2="70" y2="70" stroke="#38bdf8" stroke-width="1" />
                            <text x="50" y="110" font-family="sans-serif" font-size="4" fill="#64748b" text-anchor="middle">Rabindranath</text>
                        </svg>
                    </div>
                </div>

                <!-- Book Details Side -->
                <div class="w-full md:w-3/5 p-8 flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-start mb-4">
                            <span class="text-xs font-bold uppercase tracking-widest text-sky-400 book-badge px-3 py-1 rounded-full text-white">New Arrival</span>
                            <span class="text-slate-500 text-xs">ISBN: 978-3-16-148410-0</span>
                        </div>
                        <h3 class="text-3xl font-bold mb-2">HORRORS<br>NEXT<br>DOOoorrrr...</h3>
                        <p class="text-indigo-400 font-medium mb-4">written by : Rabintranath tagore<br>Translated by:Prasun roy </p>
                        
                        <div class="space-y-3 mb-6">
                            <div class="flex items-center text-sm text-slate-400">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                Published: Jan 2026
                            </div>
                            <div class="flex items-center text-sm text-slate-400">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5s3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                Pages: 452
                            </div>
                        </div>

                        <p class="text-slate-400 text-sm mb-6 line-clamp-3">
                            A foundational text exploring the evolution of management theory into the digital age. This edition focuses on remote team dynamics and sustainable commerce practices.
                        </p>
                    </div>

                    <div class="flex gap-3">
                        <a href="user_login.php">
    <button class="flex-1 bg-sky-600 hover:bg-sky-500 text-white font-semibold py-3 px-6 rounded-xl transition-colors flex items-center justify-center gap-2">
        
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path d="M12 4v16m8-8H4"></path>
        </svg>
        
        Borrow Book
    </button>
</a>
                        <button class="w-12 h-12 glass-card flex items-center justify-center rounded-xl text-slate-300 hover:text-rose-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer / Back Action -->
        <footer class="mt-20 mb-12 text-center">
            <button onclick="window.history.back()" class="inline-flex items-center gap-2 text-slate-400 hover:text-sky-400 transition-colors py-2 px-4 rounded-full border border-slate-700 hover:border-sky-500">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                Back to Home
            </button>
        </footer>
    </div>

</body>
</html>