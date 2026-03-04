<?php
session_start(); // Added to enable session for cookies code

// Cookies code to restore session or redirect to index.php
if(!isset($_SESSION['user_id']) && isset($_COOKIE['user_id'])) {
    $_SESSION['user_id']   = $_COOKIE['user_id'];
    $_SESSION['user_name'] = $_COOKIE['user_name'];
    $_SESSION['role']      = $_COOKIE['role'];
}

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Prevent caching and back navigation to login page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Additional back navigation prevention if user logs out and presses back
if (basename($_SERVER['PHP_SELF']) !== 'index.php' && !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>SkillSpace — Learn • Share • Grow</title>
<style>
:root{
  --bg1: linear-gradient(135deg,#0ea5e9 0%, #6366f1 50%, #a855f7 100%);
  --glass: rgba(255,255,255,0.12);
  --card: rgba(255,255,255,0.95);
  --accent:#06b6d4;
  --muted:#6b7280;
  --radius:16px;
  --maxw:1200px;
}
*{box-sizing:border-box}
html,body{
  height:100%;
  margin:0;
  font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial;
  background:var(--bg1);
  color:#071129;
}
.container{
  max-width:var(--maxw);
  margin:0 auto;
  padding:28px;
}
.header{
  position:sticky;
  top:0;
  z-index:999;
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  padding:18px 40px;
  background:var(--glass);
  border-radius:20px;
  border:1px solid rgba(255,255,255,0.08);
  backdrop-filter:blur(20px);
  box-shadow:0 4px 20px rgba(0,0,0,0.1);
  margin-bottom:30px;
}
.brand{
  font-weight:800;
  color:#fff;
  display:flex;
  align-items:center;
  gap:10px;
  font-size:1.3rem;
  letter-spacing:0.5px;
}
.nav{
  display:flex;
  gap:32px;
}
.nav a{
  color:rgba(255,255,255,0.95);
  text-decoration:none;
  font-weight:600;
  font-size:1.05rem;
  position:relative;
  padding-bottom:4px;
  transition:color .3s ease;
}
.nav a::after{
  content:"";
  position:absolute;
  left:0;
  bottom:0;
  width:0;
  height:2px;
  background:#fff;
  transition:width .3s ease;
  border-radius:2px;
}
.nav a:hover{
  color:#fff;
}
.nav a:hover::after{
  width:100%;
}
.hero{
  display:grid;
  grid-template-columns:1fr 480px;
  gap:28px;
  align-items:center;
  margin-top:26px
}
@media(max-width:980px){
  .hero{grid-template-columns:1fr;}
}
.card{
  background:var(--card);
  padding:22px;
  border-radius:var(--radius);
  box-shadow:0 12px 30px rgba(16,24,40,0.12);
}
.hero-left h1{
  font-size:2.4rem;
  margin:0 0 10px
}
.lead{
  color:var(--muted);
  margin-bottom:18px
}
.cta{
  display:flex;
  gap:12px;
  flex-wrap:wrap
}
.btn{
  padding:12px 18px;
  border-radius:12px;
  border:0;
  cursor:pointer;
  font-weight:700;
  transition:transform .2s ease, box-shadow .2s ease;
}
.btn:hover{
  transform:translateY(-2px);
  box-shadow:0 6px 14px rgba(0,0,0,0.15);
}
.btn-primary{
  background:linear-gradient(90deg,var(--accent),#7c3aed);
  color:white
}
.btn-ghost{
  background:transparent;
  border:1px solid rgba(12,18,30,0.06);
  color:#0b1220
}
.hero-right{
  display:flex;
  flex-direction:column;
  gap:12px
}
.stat-row{
  display:flex;
  gap:12px;
  justify-content:space-between
}
.stat{
  background:linear-gradient(180deg,#fff,#f3f4f6);
  padding:12px;
  border-radius:12px;
  text-align:center;
  flex:1
}
.features{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:12px;
  margin-top:18px
}
@media(max-width:980px){
  .features{grid-template-columns:repeat(2,1fr)}
}
.feature{
  background:linear-gradient(180deg,#ffffffcc,#ffffffaa);
  padding:12px;
  border-radius:12px;
  text-align:center
}
.hero-visual{
  width:100%;
  height:320px;
  border-radius:12px;
  overflow:hidden;
  border:1px solid rgba(255,255,255,0.18)
}
.hero-visual img{
  width:100%;
  height:100%;
  object-fit:cover;
  display:block
}
</style>
<script>
// Disable back button navigation after login
window.history.pushState(null, "", window.location.href);
window.onpopstate = function () {
    window.history.pushState(null, "", window.location.href);
};

// Also prevent navigation from cached pages (after logout)
window.onload = function () {
  if (performance.navigation.type === 2) {
    window.location.replace("index.php");
  }
};
</script>
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="brand">⚡ <span>SkillSpace</span></div>
      <nav class="nav">
        <a href="landing.php">Home</a>
        <a href="browseskills.php">Browse</a>
        <a href="dashboard.php">Dashboard</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
      </nav>
    </header>
    <section class="hero">
      <div class="hero-left">
        <div class="card">
          <h1>Exchange skills with people nearby — learn fast, teach freely</h1>
          <p class="lead">SkillSpace is a peer-to-peer skill exchange: offer what you know, request what you want to learn, and connect with matched learners/mentors.</p>
          <div class="cta">
            <button class="btn btn-primary" onclick="location.href='browseskills.php'">Explore Skills</button>
            <button class="btn btn-ghost" onclick="location.href='dashboard.php'">Open Dashboard</button>
          </div>
          <div class="features" aria-hidden="true">
            <div class="feature"><strong>Smart Matching</strong><br/>Find the best partners</div>
            <div class="feature"><strong>Built-in Chat</strong><br/>Coordinate sessions</div>
            <div class="feature"><strong>Ratings</strong><br/>Trust & quality</div>
          </div>
        </div>
      </div>
      <aside class="hero-right">
        <div class="card hero-visual">
          <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?q=80&w=1400&auto=format&fit=crop" alt="Skill exchange hero">
        </div>
        <div class="card stat-row">
          <div class="stat">
            <strong><?php include 'get_learners.php'; ?></strong>
            <div style="font-size:.85rem;color:var(--muted)">Active learners</div>
          </div>
          <div class="stat">
            <strong><?php include 'get_skills.php'; ?></strong>
            <div style="font-size:.85rem;color:var(--muted)">Skill offers</div>
          </div>
        </div>
      </aside>
    </section>
  </div>
  <footer style="
    text-align: center;
    margin-top: 80px;
    padding: 20px 0;
    color: #e5e7eb;
    font-size: 0.9rem;
  ">
    © 2025 SkillSpace Peer-to-peer skill exchange
  </footer>
</body>
</html>