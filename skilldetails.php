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

    $servername = "sql105.infinityfree.com";
    $username = "if0_39907321";
    $password = "SkillSpace4";
    $database = "if0_39907321_student";

    $con = new mysqli($servername, $username, $password, $database);
    if ($con->connect_error) {
        echo json_encode(['error' => 'Database connection failed']);
        exit();
    }

    $user_id = $_SESSION['user_id'] ?? 0;
    $skill_id = isset($_GET['id']) ? intval($_GET['id']) : null;
    $skill_title = isset($_GET['skill']) ? $con->real_escape_string($_GET['skill']) : null;

    if (!$skill_id && !$skill_title) {
        echo json_encode(['error' => 'Skill not specified']);
        exit();
    }

    // Fetch skill info
    if ($skill_id) {
        $sql = "SELECT s.*, u.fullname AS mentor_name_from_user, u.avatar AS mentor_avatar, u.profession AS mentor_role, u.id AS mentor_user_id
                FROM skills s
                LEFT JOIN users u ON s.mentor_id = u.id
                WHERE s.id = ? LIMIT 1";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("i", $skill_id);
    } else {
        $sql = "SELECT s.*, u.fullname AS mentor_name_from_user, u.avatar AS mentor_avatar, u.profession AS mentor_role, u.id AS mentor_user_id
                FROM skills s
                LEFT JOIN users u ON s.mentor_id = u.id
                WHERE s.title = ? LIMIT 1";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $skill_title);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        echo json_encode(['error' => 'Skill not found']);
        exit();
    }
    $skill = $res->fetch_assoc();
    $stmt->close();

    // Mentor info
    $mentor_name = $skill['mentor_name'] ?? $skill['mentor_name_from_user'] ?? 'Mentor';
    $mentor_avatar = $skill['mentor_avatar'] ?? $skill['image'] ?? 'Uploads/default-avatar.png';

    // Fetch reviews
    $reviews = [];
    $sid = $skill['id'];
    $rev_sql = "SELECT f.*, u.fullname FROM feedback f LEFT JOIN users u ON f.user_id = u.id WHERE f.skill_id = ? ORDER BY f.created_at DESC";
    $rev_stmt = $con->prepare($rev_sql);
    $rev_stmt->bind_param("i", $sid);
    $rev_stmt->execute();
    $rev_res = $rev_stmt->get_result();
    while ($r = $rev_res->fetch_assoc()) {
        $comment = htmlspecialchars($r['comment'], ENT_QUOTES, 'UTF-8');
        $reviews[] = [
            'fullname' => htmlspecialchars($r['fullname'] ?? 'User', ENT_QUOTES, 'UTF-8'),
            'rating' => intval($r['rating']),
            'comment' => $comment
        ];
    }
    $rev_stmt->close();

    // Check if current user is mentor
    $is_owner = ($user_id && $user_id == $skill['mentor_id']);

    // Check if user (mentee) has requested this skill
    $mentee_request_status = null;
    if ($user_id) {
        $rq = $con->prepare("SELECT status FROM mentorship_requests WHERE mentee_id=? AND skill_id=? LIMIT 1");
        $rq->bind_param("ii", $user_id, $skill['id']);
        $rq->execute();
        $rq_res = $rq->get_result();
        if ($rq_res->num_rows > 0) {
            $row = $rq_res->fetch_assoc();
            $mentee_request_status = strtolower($row['status']); // pending, accepted, rejected
        }
        $rq->close();
    }

    // Response
    $response = [
        'id' => intval($skill['id']),
        'title' => $skill['title'],
        'category' => $skill['category'],
        'description' => $skill['description'] ?? '',
        'rating' => floatval($skill['rating'] ?? 0),
        'mentor_name' => $skill['mentor_name'] ?? null,
        'mentor_name_from_user' => $mentor_name,
        'mentor_photo' => $mentor_avatar,
        'mentor_role' => $skill['mentor_role'] ?? '',
        'mentor_id' => intval($skill['mentor_id']),
        'is_owner' => $is_owner,
        'reviews' => $reviews,
        'mentee_request_status' => $mentee_request_status // <-- this is used in your JS
    ];

    echo json_encode($response);
    $con->close();
    exit();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>SkillSpace — Skill Details</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg1:#fffcdd;  /* light bright yellow */
  --bg2:#fff59d;  /* sunny gold */
  --bg3:#ffe082;  /* soft orange */
  --bg4:#ffecb3;  /* warm cream */
  --card1:#ffffff;     
  --card2:#fff8e1;    
  --card3:#e0f2f1;    
  --accent:#ff8c00;    
  --accent2:#ff4081;   
  --muted:#555;
  --radius:16px;
  --shadow:0 10px 25px rgba(0,0,0,0.08);
}

body{
  margin:0;
  font-family:Poppins,Inter,system-ui;
  background:linear-gradient(135deg,var(--bg1),var(--bg2),var(--bg3),var(--bg4));
  background-size:400% 400%;
  animation:gradientShift 20s ease infinite;
  color:#0b1220;
}

@keyframes gradientShift{
  0%{background-position:0% 50%}
  50%{background-position:100% 50%}
  100%{background-position:0% 50%}
}

nav{
  display:flex;
  justify-content:space-between;
  align-items:center;
  padding:16px 32px;
  background: linear-gradient(90deg,#ffb74d,#ff8a65);
  border-radius:0 0 20px 20px;
  box-shadow:0 6px 18px rgba(0,0,0,0.12);
}

nav a{
  color:#fff;
  text-decoration:none;
  font-weight:600;
  margin-left:12px;
  padding:8px 14px;
  border-radius:12px;
  transition:0.3s;
}

nav a:hover{
  background: rgba(255,255,255,0.2);
}

.wrap{
  max-width:1100px;
  margin:28px auto;
  padding:20px;
}

.grid{
  display:grid;
  grid-template-columns:1fr 360px;
  gap:22px;
  align-items:start;
}

@media(max-width:900px){
  .grid{grid-template-columns:1fr}
}

.hero{
  background:linear-gradient(135deg,#fff59d,#ffd54f);
  color:#111;
  padding:28px;
  border-radius:var(--radius);
  box-shadow:var(--shadow);
  display:flex;
  flex-direction:column;
  gap:10px;
}

.hero h1{margin:0;font-size:1.8rem}

.meta{opacity:0.95;font-weight:500}

.btn{
  background:#fff;
  color:#111;
  padding:10px 14px;
  border-radius:12px;
  border:0;
  font-weight:700;
  cursor:pointer;
}

.card{
  padding:20px;
  border-radius:16px;
  box-shadow:var(--shadow);
  background:linear-gradient(145deg,#ffffff,#fffde7);
}

.card:nth-of-type(2){
  background:linear-gradient(145deg,#fff8e1,#ffe0b2);
}

.card.reviews{
  background:linear-gradient(145deg,#e0f7fa,#b2ebf2);
}

.mentor{
  display:flex;
  gap:14px;
  align-items:center;
  padding:16px;
  border-radius:16px;
  background: linear-gradient(135deg, #ffecb3, #ffd54f);
  box-shadow: 0 8px 20px rgba(0,0,0,0.12);
}

.mentor img{
  width:84px;
  height:84px;
  border-radius:50%;
  object-fit:cover;
  border:4px solid #fff;
  box-shadow:var(--shadow);
}

.reviews .review{
  padding:12px;
  border-radius:10px;
  margin-bottom:10px;
  background:#ffffffb0;
}

.small{color:var(--muted);font-size:0.95rem}

.status{margin-top:10px;padding:10px;border-radius:10px}

.request-btn{
  background:linear-gradient(90deg,#ff8c00,#ff4081);
  color:white;
  border:0;
  padding:12px 16px;
  border-radius:12px;
  font-weight:700;
  cursor:pointer;
}
</style>
</head>
<body>
<nav>
  <div><a href="browseskills.html">← Back to Browse</a></div>
  <div><a href="dashboard.php">Dashboard</a></div>
</nav>
<div class="wrap">
  <div class="grid">
    <div>
      <div id="hero" class="hero">
        <h1 id="title">Loading...</h1>
        <div class="meta" id="category">—</div>
        <div id="desc" class="small">Loading description…</div>
        <div style="margin-top:12px">
          <button id="requestBtn" class="request-btn">Request this Skill</button>
          <div id="requestStatus" class="status" style="display:none"></div>
        </div>
      </div>
      <div style="margin-top:18px" id="detailsCard" class="card">
        <h3>About this Skill</h3>
        <p id="fullDescription" class="small">Loading…</p>
      </div>
      <div style="margin-top:18px" class="card reviews" id="reviewsCard">
        <h3>Reviews & Ratings</h3>
        <div id="reviewsList"><p class="small">Loading reviews…</p></div>
      </div>
    </div>
    <aside>
      <div class="card">
        <h3>Mentor</h3>
        <div class="mentor" id="mentorBox">
          <img id="mentorPhoto" src="Uploads/default-avatar.png" alt="Mentor">
          <div>
            <div id="mentorName" style="font-weight:700">Loading...</div>
            <div id="mentorCity" class="small" style="margin-top:6px"></div>
          </div>
        </div>
      </div>
    </aside>
  </div>
</div>

<script>
const qp = new URLSearchParams(location.search);
const skillId = qp.get('id') || qp.get('skill_id');
const skillTitleParam = qp.get('skill');
const fetchUrl = skillId ? `skilldetails.php?api=1&id=${encodeURIComponent(skillId)}` : skillTitleParam ? `skilldetails.php?api=1&skill=${encodeURIComponent(skillTitleParam)}` : null;

if(!fetchUrl){
  document.getElementById('title').textContent = 'Skill not specified';
  document.getElementById('desc').textContent = 'No skill id provided in URL.';
} else {
  fetch(fetchUrl)
  .then(r => r.json())
  .then(data => {
    if(data.error){
      document.getElementById('title').textContent = 'Skill not found';
      document.getElementById('desc').textContent = data.error;
      document.getElementById('requestBtn').style.display = 'none';
      return;
    }

    document.getElementById('title').textContent = data.title;
    document.getElementById('category').textContent = data.category?.includes('Category') ? data.category : 'Category: ' + data.category;
    document.getElementById('desc').textContent = '';
    document.getElementById('fullDescription').textContent = data.description || 'No description provided.';
    document.getElementById('mentorName').textContent = data.mentor_name_from_user || 'Mentor';
    document.getElementById('mentorPhoto').src = data.mentor_photo || 'Uploads/default-avatar.png';
    document.getElementById('mentorCity').textContent = data.mentor_city || '';

    const reviewsList = document.getElementById('reviewsList');
    reviewsList.innerHTML = '';
    if(data.reviews && data.reviews.length){
      data.reviews.forEach(r=>{
        const div = document.createElement('div');
        div.className = 'review';
        div.innerHTML = `<strong>${r.fullname}</strong><p class="small">${r.comment}</p>`;
        reviewsList.appendChild(div);
      });
    } else {
      reviewsList.innerHTML = '<p class="small">No reviews yet.</p>';
    }

    const btn = document.getElementById('requestBtn');
    const st = document.getElementById('requestStatus');

    function refreshStatus(){
      fetch(`skilldetails.php?api=1&id=${data.id}`)
        .then(r=>r.json())
        .then(d=>{
          if(d.mentee_request_status){
            btn.style.display='none';
            st.style.display='block';
            if(d.mentee_request_status==='pending'){
              st.style.background = '#fef9c3';
              st.style.color = '#b45309';
              st.textContent = 'Your request is pending';
            } else if(d.mentee_request_status==='accepted'){
              st.style.background = '#d1fae5';
              st.style.color = '#065f46';
              st.textContent = 'Your request has been accepted ✅';
            } else if(d.mentee_request_status==='rejected'){
              st.style.background = '#fee2e2';
              st.style.color = '#7f1d1d';
              st.textContent = 'Your request was rejected ❌';
            }
          }
        });
    }

    if(data.is_owner){
      btn.style.display='none';
      st.style.display='block';
      st.style.background = '#ecfeff';
      st.style.color = '#0369a1';
      st.textContent = 'This skill is offered by you';
    } else if(data.mentee_request_status){
      refreshStatus();
    } else {
      btn.style.display='inline-block';
      st.style.display='none';
      btn.addEventListener('click', ()=>{
        btn.disabled = true;
        fetch('request_mentorship.php',{
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body:JSON.stringify({ skill_id: data.id })
        }).then(r=>r.json()).then(res=>{
          btn.disabled=false;
          st.style.display='block';
          if(res.success){
            st.style.background='#ecfccb';
            st.style.color='#365314';
            st.textContent='Request sent — awaiting mentor confirmation';
            btn.style.display='none';
            const poll = setInterval(()=>{
              fetch(`skilldetails.php?api=1&id=${data.id}`)
                .then(r=>r.json())
                .then(d=>{
                  if(d.mentee_request_status && d.mentee_request_status!=='pending'){
                    refreshStatus();
                    clearInterval(poll);
                  }
                });
            },5000);
          } else {
            st.style.background='#fee2e2';
            st.style.color='#7f1d1d';
            st.textContent=res.error || 'Error sending request';
          }
        }).catch(err=>{
          btn.disabled=false;
          st.style.display='block';
          st.style.background='#fee2e2';
          st.style.color='#7f1d1d';
          st.textContent='Network error';
        })
      });
    }
  })
  .catch(err=>{
    console.error(err);
    document.getElementById('title').textContent = 'Error loading skill';
  });
}
</script>
</body>
</html>