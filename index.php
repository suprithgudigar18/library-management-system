<?php
include "db_connect.php";
// Optional: You can add PHP logic here later if needed
// For now, this file just outputs static HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LIBRITE - Edurite College</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Inter:wght@300;400;600&display=swap');
        
        :root {
            --primary-accent: #22d3ee; 
            --deep-onyx: #02040a;
            --glass: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body, html {
            width: 100%; height: 100%; overflow: hidden;
            background-color: var(--deep-onyx);
            font-family: 'Inter', sans-serif;
            color: white;
            font-style: italic;
        }

        .stage {
            width: 100vw; height: 100vh;
            position: relative; overflow: hidden;
            perspective: 2000px;
        }

        /* --- PARTICLES --- */
        .books-container {
            position: absolute; inset: 0; z-index: 2; pointer-events: none;
        }

        .falling-book {
            position: absolute; right: -150px;
            color: var(--primary-accent);
            text-shadow: 0 0 10px var(--primary-accent);
            filter: blur(var(--b-blur, 2px));
            opacity: 0;
            animation: bookDrift var(--b-speed) linear infinite;
        }

        @keyframes bookDrift {
            0% { transform: translate(0,0) rotate(0deg); opacity: 0; }
            15% { opacity: 0.9; }
            85% { opacity: 0.9; }
            100% { transform: translate(-120vw, var(--b-y-drift)) rotate(var(--b-rot)); opacity: 0; }
        }

        .vignette {
            position: absolute; inset: 0;
            background: radial-gradient(circle at center, transparent 20%, rgba(0,0,0,0.8) 100%);
            backdrop-filter: blur(var(--blur-amt, 1px));
            z-index: 4; pointer-events: none;
        }

        .grain {
            position: absolute; inset: 0; z-index: 100; opacity: 0.03; pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0%200%20200%20200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.7' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        /* --- UI --- */
        .ui-overlay {
            position: absolute; inset: 0; z-index: 10;
            display: flex; flex-direction: column;
            justify-content: center; align-items: center;
            text-align: center; padding: 40px; pointer-events: none;
        }

        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            padding: 3rem; border-radius: 24px;
            max-width: 600px; transform: translateZ(100px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            pointer-events: auto;
        }

        h1 {
            font-family: 'Playfair Display', serif; font-size: 3.5rem;
            margin-bottom: 1rem; letter-spacing: -1px;
            background: linear-gradient(to bottom, #fff, #999);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }

        #marketing-text {
            font-family: 'Playfair Display', serif; font-size: 1.3rem;
            color: rgba(255, 255, 255, 0.8); margin-bottom: 2rem;
            min-height: 3.2em; transition: opacity 0.5s ease;
        }
        .fade-out { opacity: 0; }

        .btn {
            padding: 12px 30px; border-radius: 50px;
            text-decoration: none; font-weight: 600; font-size: 0.9rem;
            text-transform: uppercase; letter-spacing: 1px;
            cursor: pointer; transition: all 0.3s ease;
            border: none; outline: none;
        }

        .btn-primary {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--primary-accent);
            color: var(--primary-accent);
        }

        .btn-primary:hover {
            background: var(--primary-accent); color: #000;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        /* --- MODAL --- */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.85);
            backdrop-filter: blur(10px); z-index: 200;
            display: flex; justify-content: center; align-items: center;
            opacity: 0; pointer-events: none; transition: opacity 0.3s ease;
        }
        .modal-overlay.active { opacity: 1; pointer-events: auto; }
        
        .modal-content {
            background: #111; border: 1px solid rgba(255,255,255,0.1);
            padding: 40px; border-radius: 15px; width: 90%; max-width: 500px;
            text-align: center; transform: scale(0.9); transition: transform 0.3s ease;
            max-height: 80vh; overflow-y: auto; font-style: normal;
        }
        .modal-overlay.active .modal-content { transform: scale(1); }
        
        .close-modal {
            margin-top: 25px; display: inline-block; cursor: pointer;
            color: var(--primary-accent); text-decoration: underline;
        }
        #modalTitle {
            font-family: 'Playfair Display', serif; margin-bottom: 15px;
            color: var(--primary-accent); font-size: 1.8rem;
        }
        #modalBody { color: #ddd; text-align: left; line-height: 1.6; }
        #modalBody ul { padding-left: 20px; }
        #modalBody li { padding: 5px 0; border-bottom: 1px solid rgba(255,255,255,0.1); }
        #modalBody p { margin-bottom: 10px; }
        
        /* Checkbox Style */
        .term-checkbox {
            margin-top: 15px;
            display: flex; align-items: center; gap: 10px;
            font-size: 0.9rem; color: var(--primary-accent);
        }
        .term-checkbox input { accent-color: var(--primary-accent); transform: scale(1.2); cursor: pointer; }

        /* --- HEADER/FOOTER --- */
        header, footer {
            position: absolute; left: 0; width: 100%; z-index: 50;
            display: flex; justify-content: space-between; align-items: center;
            padding: 2rem 3rem; pointer-events: auto;
        }
        header { top: 0; } 
        footer { bottom: 0; font-size: 0.8rem; color: rgba(255, 255, 255, 0.5); background: linear-gradient(to top, rgba(0,0,0,0.8), transparent); }

        .brand {
            font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 700;
            color: white; text-decoration: none; display: flex; align-items: center; gap: 15px; font-style: normal;
        }
        .logo-img {
            height: 60px; width: 60px; object-fit: cover; border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.1); background: rgba(255,255,255,0.05);
        }
        nav a, .footer-links a {
            color: rgba(255, 255, 255, 0.7); text-decoration: none; margin-left: 2rem;
            text-transform: uppercase; font-size: 0.85rem; cursor: pointer; transition: color 0.3s;
        }
        nav a:hover, .footer-links a:hover, .socials i:hover { color: var(--primary-accent); }
        .footer-content { display: flex; gap: 2rem; align-items: center; }
        .socials i { font-size: 1.1rem; margin-left: 1.5rem; color: rgba(255,255,255,0.5); transition: color 0.3s; }

        @media (max-width: 768px) {
            header, footer { padding: 1.5rem; flex-direction: column; gap: 1rem; }
            nav { display: none; }
            h1 { font-size: 2.5rem; }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="stage" id="viewport">
        <!-- Admin Bar Removed -->

        <header>
            <a href="#" class="brand">
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='48' fill='%23111' stroke='rgba(255,255,255,0.2)' stroke-width='2'/%3E%3Cpath d='M30 35 L30 75 L50 85 L70 75 L70 35 L50 45 Z' fill='none' stroke='%2322d3ee' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M50 45 L50 85' stroke='%2322d3ee' stroke-width='3' stroke-linecap='round'/%3E%3C/svg%3E" alt="Librite" class="logo-img">
                LIBRITE
            </a>
            <nav>
              <a href="admin_login.php">Admin</a>
              <a onclick="window.location.href='collection.php'">Collections</a>
              <a onclick="openModal('services')">Services</a>
              <a onclick="openModal('contact')">
                  <i class="fas fa-headset"></i> <!-- Text removed -->
              </a>
           </nav>        
       </header>

        <div class="books-container" id="books"></div>
        <div class="vignette" id="bokeh"></div>
        <div class="grain"></div>

        <div class="ui-overlay">
            <div class="glass-card">
                <h1>LIBRARY AUTOMATION</h1>
                <p id="marketing-text">"A room without books is like a body without a soul."</p>
               <div class="cta-group">
                 <a href="user_login.php" class="btn btn-primary">User Login</a>
               </div>            
            </div>
        </div>

        <footer style="justify-content: flex-end;">
            <div class="footer-content">
                <div class="footer-links">
                    <a onclick="openModal('privacy')">Privacy Policy</a>
                    <a onclick="openModal('terms')">Terms & Conditions</a>
                </div>
                <div class="socials">
                    <a href="https://www.linkedin.com/company/edurite-college-of-administration-&-management-studies-shimoga./?lipi=urn%3Ali%3Apage%3Ad_flagship3_search_srp_schools%3B4bLaW%2FSZRu2CjpzLaeJbjw%3D%3D" target="_blank">
                        <i class="fab fa-linkedin"></i>
                    </a>
                    <a href="https://www.instagram.com/edurite_college/" target="_blank"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </footer>
        
        <div class="modal-overlay" id="demoModal">
            <div class="modal-content">
                <h2 id="modalTitle">Access Granted</h2>
                <div id="modalBody"><p>Welcome to the Athena Archive dashboard demo.</p></div>
                <span class="close-modal" id="closeModal"><i class="fas fa-arrow-left"></i> Back</span>
            </div>
        </div>
    </div>

    <script>
        const bokeh = document.getElementById('bokeh');
        const booksContainer = document.getElementById('books');

        // Books Animation
        const bookIcons = ['fa-book', 'fa-book-open', 'fa-journal-whills', 'fa-scroll', 'fa-book-medical'];
        function createBook() {
            const book = document.createElement('i');
            book.className = `fas ${bookIcons[Math.floor(Math.random()*bookIcons.length)]} falling-book`;
            const speed = Math.random() * (15 - 8) + 8;
            
            // Standard styles
            book.style.fontSize = `${Math.random() * 1.4 + 0.8}rem`;
            book.style.top = `${Math.random() * 80 + 10}%`;

            // CSS Variables (Must use setProperty)
            book.style.setProperty('--b-speed', `${speed}s`);
            book.style.setProperty('--b-blur', `${Math.random()*4}px`);
            book.style.setProperty('--b-y-drift', `${(Math.random()-0.5)*300}px`);
            book.style.setProperty('--b-rot', `${(Math.random()-0.5)*360}deg`);

            booksContainer.appendChild(book);
            setTimeout(() => book.remove(), (speed + 2) * 1000);
        }
        
        // Initialize and loop
        for(let i=0; i<12; i++) createBook();
        setInterval(createBook, 1200);

        // Vignette effect
        window.addEventListener('mousemove', e => {
            const dist = Math.hypot(0.5 - e.clientX/innerWidth, 0.5 - e.clientY/innerHeight);
            bokeh.style.setProperty('--blur-amt', `${dist * 5}px`);
        });

        // Modals Data
        const modal = document.getElementById('demoModal');
        const modalContent = {
            collections: { 
                title: "Our Collections", 
                body: `
                    <ul>
                        <li><strong>Academic & Textbooks:</strong> Comprehensive resources for Management and Commerce studies.</li>
                        <li><strong>Competitive Exams:</strong> Specialized materials for UPSC, KPSC, banking, and entrance exams.</li>
                        <li><strong>Journals & Periodicals:</strong> National and international subscriptions for latest research.</li>
                        <li><strong>Digital Archives:</strong> Access to e-books, past question papers, and project reports.</li>
                        <li><strong>Regional Literature:</strong> Extensive collection of Kannada and English novels and literature.</li>
                        <li><strong>Reference Section:</strong> Encyclopedias, dictionaries, and yearbooks.</li>
                    </ul>` 
            },
            services: { 
                title: "Library Services", 
                body: `
                    <ul>
                        <li><strong>OPAC (Online Public Access Catalog):</strong> Search books from anywhere on campus.</li>
                        <li><strong>Book Reservation:</strong> Reserve books online and pick them up at the counter.</li>
                        <li><strong>Reading Room:</strong> Quiet, air-conditioned space with Wi-Fi access.</li>
                        <li><strong>Inter-Library Loan:</strong> Request books from partner institutions.</li>
                        <li><strong>Reprography:</strong> Photocopying and printing services available for students.</li>
                        <li><strong>Digital Library:</strong> Dedicated computers for accessing e-resources.</li>
                    </ul>` 
            },
            contact: { 
                title: "Contact Us", 
                body: `
                    <div style="text-align:center; margin-bottom:15px;">
                        <i class="fas fa-headset" style="font-size:3rem; color:var(--primary-accent); margin-bottom:10px;"></i>
                    </div>
                    <p><strong>Edurite College of Management Studies</strong></p>
                    <p>alkola,backside of vikasa school, Shivamogga, Karnataka 577203</p>
                    <br>
                    <p><i class="fas fa-phone"></i> +91 97310 62072 </p>
                    <p><i class="fas fa-envelope"></i> eduritecollageofmanagementstudies@gmail.com</p>
                    <p><i class="fas fa-clock"></i> Mon-Sat: 9:00 AM - 5:00 PM</p>
                    ` 
            },
            terms: { 
                title: "Terms & Conditions", 
                body: `
                    <p>By using the library facilities, you agree to the following:</p>
                    <ul>
                        <li>Books must be returned within <b>4 days </b>from borrowed.</li>
                        <li>A fine of <b>₹10 per day will be charged</b> for after the duedate .</li>
                        <li>Lost or <b>damaged books must be replaced or paid</b> for (current market price + processing fee).</li>
                        <li>Silence must be maintained in the reading room at all times.</li>
                        <li>Mobile phones must be kept on silent mode.</li>
                        <li>collage id card is mandatory.</li>
                    </ul>
                    <div class="term-checkbox">
                        <input type="checkbox" id="acceptTerms">
                        <label for="acceptTerms">I have read and agree to the Terms & Conditions</label>
                    </div>
                    ` 
            },
            privacy: {
                title: "Privacy Policy",
                body: `
                    <p>At Edurite College Library, we value your privacy.</p>
                    <ul>
                        <li><strong>Data Collection:</strong> We collect your name, student ID, and contact details for library management purposes only.</li>
                        <li><strong>Usage:</strong> Your data is used to track book loans, reservations, and overdue notifications.</li>
                        <li><strong>Security:</strong> We implement standard security measures to protect your personal information.</li>
                        <li><strong>Sharing:</strong> We do not share your data with third parties unless required by law.</li>
                        <li><strong>Access:</strong> You have the right to review and correct your personal information held by us.</li>
                    </ul>
                `
            }
        };

        window.openModal = type => {
            const c = modalContent[type];
            if(c) {
                document.getElementById('modalTitle').textContent = c.title;
                document.getElementById('modalBody').innerHTML = c.body;
                modal.classList.add('active');
            }
        };
        const closeM = () => modal.classList.remove('active');
        
        
        document.getElementById('closeModal')?.addEventListener('click', closeM);
        modal.addEventListener('click', e => e.target === modal && closeM());

        // Text Rotator
        const phrases = [
            "\"Reading is dreaming with open eyes.\"",
            "\"Books are a uniquely portable magic.\"",
            "\"There is no friend as loyal as a book.\"",
            "\"Today a reader, tomorrow a leader.\"",
            "\"So many books, so little time.\""
        ];
        let pool = [...phrases];
        const textEl = document.getElementById('marketing-text');

        setInterval(() => {
            textEl.classList.add('fade-out');
            setTimeout(() => {
                if (!pool.length) pool = [...phrases];
                const idx = Math.floor(Math.random() * pool.length);
                textEl.textContent = pool.splice(idx, 1)[0];
                textEl.classList.remove('fade-out');
            }, 500);
        }, 5000);
    </script>
</body>
</html>