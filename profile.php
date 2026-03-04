<?php
session_start(); // Enable session handling
if(!isset($_SESSION['user_id']) && isset($_COOKIE['user_id'])) {
    $_SESSION['user_id']   = $_COOKIE['user_id'];
    $_SESSION['user_name'] = $_COOKIE['user_name'];
    $_SESSION['role']      = $_COOKIE['role'];
}
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>
<?php 
session_start();

// Check if the request is for JSON data (from the fetch call)
if (isset($_GET['api']) && $_GET['api'] == '1') {
    header('Content-Type: application/json');

    if(!isset($_SESSION['user_id'])){
        echo json_encode(['error' => 'User not logged in']);
        exit();
    }

    $servername = "sql105.infinityfree.com";
    $username = "if0_39907321";
    $password = "SkillSpace4";
    $database = "if0_39907321_student";

    $con = new mysqli($servername, $username, $password, $database);
    if($con->connect_error){
        echo json_encode(['error' => 'Database connection failed']);
        exit();
    }

    $user_id = $_SESSION['user_id'];

    // ✅ Fetch user info
    $stmt = $con->prepare("SELECT fullname, avatar, bio, city, skills_offered, skills FROM users WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();

    // ✅ Fetch skills offered from skills table
    $stmt2 = $con->prepare("SELECT id, title, category, image FROM skills WHERE mentor_id=?");
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();

    $skills_offered = [];
    $skill_ids = [];
    while($row = $res2->fetch_assoc()){
        $skills_offered[] = [
            'title' => $row['title'],
            'category' => $row['category'],
            'image' => $row['image']
        ];
        $skill_ids[] = $row['id'];
    }
    $stmt2->close();

    // ✅ Fetch feedback for the user's offered skills
    $feedbacks = [];
    if (!empty($skill_ids)) {
        $ids_placeholder = implode(',', array_fill(0, count($skill_ids), '?'));
        $types = str_repeat('i', count($skill_ids));
        $sql = "SELECT f.rating, f.comment, f.created_at, s.title AS skill_name, u.fullname AS reviewer_name 
                FROM feedback f
                JOIN skills s ON f.skill_id = s.id
                JOIN users u ON f.user_id = u.id
                WHERE f.skill_id IN ($ids_placeholder)
                ORDER BY f.created_at DESC";
        $stmt3 = $con->prepare($sql);
        $stmt3->bind_param($types, ...$skill_ids);
        $stmt3->execute();
        $res3 = $stmt3->get_result();
        while($row = $res3->fetch_assoc()){
            $feedbacks[] = $row;
        }
        $stmt3->close();
    }

    // ✅ Build unified response
    $response = [
        'fullname' => $user['fullname'] ?? 'User',
        'city' => $user['city'] ?? '',
        'bio' => $user['bio'] ?? '',
        'avatar' => $user['avatar'] ?? 'default-avatar.png',
        'skill_offered' => $skills_offered,
        'skill_requested' => $user['skills'] ?? '',
        'feedbacks' => $feedbacks
    ];

    echo json_encode($response);
    $con->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>SkillSpace — Profile</title>
<style>
:root{
  --accent:#007bff;
  --accent2:#00b4d8;
  --muted:#1f2937;
  --radius:16px;
  --maxw:1100px;
  --avatar-border:#00b4d8;
}

/* Reset and Body */
*{box-sizing:border-box;margin:0;padding:0}
body{
  font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto;
  background:linear-gradient(135deg,#fff7ad,#ffa9f9,#a1c4fd,#c2ffd8);
  background-size:400% 400%;
  animation:glowMove 10s ease infinite;
  min-height:100vh;
  color:#000;
  transition:background 5s ease, box-shadow 5s ease;
}
@keyframes glowMove{
  0%{background-position:0% 50%}
  50%{background-position:100% 50%}
  100%{background-position:0% 50%}
}
body:hover{
  background:linear-gradient(135deg,#f9d423,#ff4e50,#00c9ff,#92fe9d);
  background-size:400% 400%;
  animation:glowMove 20s ease infinite;
  box-shadow:0 0 60px rgba(255,255,255,0.5) inset;
}

/* Container */
.container{max-width:var(--maxw);margin:24px auto;padding:18px}

/* Navbar */
.header{
  display:flex;
  justify-content:space-between;
  align-items:center;
  padding:20px 40px;
  border-radius:20px;
  background:linear-gradient(90deg,var(--accent),var(--accent2));
  box-shadow:0 8px 28px rgba(0,0,0,0.18);
}
.brand{font-weight:800;color:#fff;font-size:1.5rem;letter-spacing:0.5px}
.nav{display:flex;gap:32px}
.nav a{text-decoration:none;color:#fff;font-weight:600;font-size:1.05rem;position:relative}
.nav a::after{content:"";position:absolute;left:0;bottom:-4px;height:2px;width:0;background:#fff;border-radius:2px;transition:width .3s ease}
.nav a:hover::after{width:100%}

/* Layout */
.grid{display:grid;grid-template-columns:280px 1fr;gap:24px;margin-top:26px}
@media(max-width:880px){.grid{grid-template-columns:1fr}}

/* Card */
.card{
  background:linear-gradient(145deg,rgba(255,255,255,0.9),rgba(240,248,255,0.9));
  padding:24px;
  border-radius:var(--radius);
  box-shadow:0 10px 25px rgba(0,0,0,0.15);
  backdrop-filter:blur(12px);
  border:1px solid rgba(255,255,255,0.6);
  color:#000;
  transition:transform 0.2s ease, box-shadow 0.2s ease;
}
.card:hover{
  transform:translateY(-4px);
  box-shadow:0 0 35px 10px rgba(255,255,255,0.5), 0 16px 36px rgba(0,0,0,0.2);
}

/* Avatar Box */
.avatar-box{text-align:center;}
.avatar{
  width:180px;height:180px;
  border-radius:50%;
  object-fit:cover;
  border:5px solid var(--avatar-border);
  box-shadow:0 10px 28px rgba(0,0,0,0.15);
  padding:4px;
  background:#fff;
  transition:transform .3s,box-shadow .3s;
}
.avatar:hover{transform:scale(1.05);box-shadow:0 14px 28px rgba(0,0,0,0.25);}

h2{margin:14px 0 4px;font-size:1.6rem;color:#000}
.small{color:var(--muted);font-size:0.95rem}

/* Button */
.btn{
  margin-top:14px;
  padding:12px 18px;
  border-radius:12px;
  background:linear-gradient(90deg,var(--accent),var(--accent2));
  color:white;
  border:0;
  cursor:pointer;
  font-weight:700;
  transition:transform .2s ease,box-shadow .2s ease
}
.btn:hover{transform:translateY(-2px);box-shadow:0 6px 14px rgba(0,0,0,0.2)}

/* Skills */
.skills{margin-top:10px;display:flex;gap:10px;flex-wrap:wrap}
.skill-chip{
  display:flex;
  align-items:center;
  gap:5px;
  background:linear-gradient(135deg,var(--accent),var(--accent2));
  border-radius:999px;
  font-weight:700;
  padding:8px 12px;
  color:#fff;
  transition:transform .2s ease, box-shadow .2s ease;
}
.skill-chip img{width:50px;height:50px;border-radius:8px;object-fit:cover;}
.skill-chip:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.15)}

.section-title{margin-top:20px;font-size:1.3rem;color:#000}
.card p{line-height:1.6;color:#000}
</style>
</head>
<body>
<div class="container">
  <header class="header">
    <div class="brand">⚡ SkillSpace</div>
    <nav class="nav">
      <a href="dashboard.php">Dashboard</a>
    </nav>
  </header>

  <main class="grid">
    <aside class="card avatar-box">
      <img class="avatar" id="profileAvatar" src="default-avatar.png" alt="Profile Picture">
      <h2 id="profileName">Name</h2>
      <button class="btn" onclick="location.href='editprofile.php'">Edit Profile</button>
    </aside>

    <section class="card">
      <h3 class="section-title">About</h3>
      <p id="profileBio">Your bio...</p>

      <h3 class="section-title">Skill Offered</h3>
      <div class="skills" id="skillsOfferedContainer"></div>

      <h3 class="section-title">Skill Requested</h3>
      <div class="skills" id="skillsRequestedContainer"></div>
    </section>
  </main>
</div>

<script>
async function loadProfile() {
  try {
    const res = await fetch('profile.php?api=1&t=' + new Date().getTime());
    const data = await res.json();

    if (data.error) {
      console.error('Error:', data.error);
      return;
    }

    document.getElementById('profileName').textContent = data.fullname || 'Name';
    document.getElementById('profileBio').textContent = data.bio || 'Your bio...';
    document.getElementById('profileAvatar').src = data.avatar || 'default-avatar.png';

    const offeredContainer = document.getElementById('skillsOfferedContainer');
    offeredContainer.innerHTML = '';
    if (data.skill_offered && data.skill_offered.length) {
      data.skill_offered.forEach(skill => {
        const div = document.createElement('div');
        div.className = 'skill-chip';
        div.textContent = skill.title;
        if (skill.image) {
          const img = document.createElement('img');
          img.src = skill.image;
          img.alt = skill.title;
          div.prepend(img);
        }
        offeredContainer.appendChild(div);
      });
    } else {
      offeredContainer.innerHTML = '<p style="color:#000;">No skills offered yet.</p>';
    }

    const requestedContainer = document.getElementById('skillsRequestedContainer');
    requestedContainer.innerHTML = '';
    if (data.skill_requested) {
      const requestedSkills = data.skill_requested.split(',').map(s => s.trim()).filter(s => s);
      requestedSkills.forEach(skill => {
        const span = document.createElement('span');
        span.className = 'skill-chip';
        span.textContent = skill;
        requestedContainer.appendChild(span);
      });
    } else {
      requestedContainer.innerHTML = '<p style="color:#000;">No skills requested yet.</p>';
    }

  } catch (err) {
    console.error('Error loading profile:', err);
  }
}

window.addEventListener('DOMContentLoaded', loadProfile);
</script>
</body>
</html>