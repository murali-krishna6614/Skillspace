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
// Check if the request is for JSON data (from the fetch call)
if (isset($_GET['api']) && $_GET['api'] == '1') {
    header('Content-Type: application/json; charset=utf-8');

    $servername = "sql105.infinityfree.com";
    $username = "if0_39907321";
    $password = "SkillSpace4";
    $database = "if0_39907321_student";

    $con = new mysqli($servername, $username, $password, $database);
    if($con->connect_error){
        echo json_encode([]);
        exit();
    }

    $search = isset($_GET['search']) ? $con->real_escape_string($_GET['search']) : '';
    $category = isset($_GET['category']) ? $con->real_escape_string($_GET['category']) : 'All';

    $sql = "SELECT s.*, u.fullname AS mentor_name 
            FROM skills s 
            LEFT JOIN users u ON s.mentor_id = u.id
            WHERE (s.title LIKE '%$search%' OR s.category LIKE '%$search%')";

    if($category != 'All'){
        $sql .= " AND s.category='$category'";
    }

    $sql .= " ORDER BY s.created_at DESC";

    $res = $con->query($sql);
    $skills = [];

    if($res->num_rows > 0){
        while($row = $res->fetch_assoc()){
            $skills[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'category' => $row['category'],
                'mentor_name' => $row['mentor_name'] ?? 'Mentor',
                'image' => $row['image']
            ];
        }
    }

    echo json_encode($skills);
    $con->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>SkillSpace — Browse Skills</title>
<style>
:root{
  --bg:linear-gradient(135deg,#36d1dc,#5b86e5);
  --card:#fff;
  --accent:#0ea5e9;
  --muted:#6b7280;
  --radius:14px;
  --maxw:1200px
}
*{box-sizing:border-box}
body{margin:0;font-family:Inter,system-ui,Roboto;color:#071129;background:var(--bg)}
.container{max-width:var(--maxw);margin:20px auto;padding:18px}
.header{display:flex;justify-content:space-between;align-items:center;color:#fff}
.header nav a{color:#fff;text-decoration:none;margin-left:12px;position:relative;font-weight:500}
.header nav a::after{content:'';position:absolute;width:0%;height:2px;left:0;bottom:-3px;background:#fff;transition:width 0.3s}
.header nav a:hover::after{width:100%}
.searchbar{background:var(--card);padding:12px;border-radius:12px;margin-top:18px;display:flex;gap:12px;align-items:center}
.searchbar input, .searchbar select{padding:10px;border-radius:10px;border:1px solid #eef2ff}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;margin-top:18px}
.card{background:var(--card);padding:16px;border-radius:12px;box-shadow:0 8px 18px rgba(16,24,40,0.06)}
.skill-img{height:120px;border-radius:10px;overflow:hidden;background:#f8fafc}
.skill-img img{width:100%;height:100%;object-fit:cover}
.title{font-weight:800;color:#0b1220;margin:8px 0}
.meta{color:var(--muted);font-size:.95rem}
.btn{padding:8px 12px;border-radius:10px;background:var(--accent);color:white;border:0;cursor:pointer;transition:0.2s}
.btn:hover{background:#0571b0}
</style>
</head>
<body>
<div class="container">
  <header class="header">
    <div style="font-weight:800;font-size:1.2rem;color:#fff">Browse Skills</div>
    <nav>
      <a href="dashboard.php">Dashboard</a>
    </nav>
  </header>

  <div class="searchbar">
    <input id="searchInput" type="text" placeholder="Search skills..." style="flex:1">
    <select id="categorySelect">
      <option value="All">All Categories</option>
      <option value="Technology">Technology</option>
      <option value="Design">Design</option>
      <option value="Music">Music</option>
      <option value="Business">Business</option>
      <option value="Photography">Photography</option>
      <option value="User">User</option>
    </select>
    <button class="btn" onclick="loadSkills()">Search</button>
  </div>

  <main id="skillsGrid" class="grid" aria-live="polite"></main>
</div>

<script>
function loadSkills(){
    const searchValue = document.getElementById('searchInput').value.toLowerCase();
    const categoryValue = document.getElementById('categorySelect').value;

    fetch('browseskills.php?api=1&search=' + encodeURIComponent(searchValue) + '&category=' + encodeURIComponent(categoryValue))
    .then(res => res.json())
    .then(data => {
        const grid = document.getElementById('skillsGrid'); 
        grid.innerHTML = '';
        if(data.length === 0){
            grid.innerHTML='<p style="color:white;font-weight:600;">No skills found.</p>';
            return;
        }
        data.forEach(skill => {
            const card = document.createElement('article'); 
            card.className='card';
            card.innerHTML=`
            <div class="skill-img"><img src="${skill.image || 'default-skill.png'}" alt="${skill.title}"></div>
            <div class="title">${skill.title}</div>
            <div class="meta">Category: ${skill.category}</div>
            <div class="meta">Mentor: ${skill.mentor_name || 'Unknown'}</div>
            <div style="margin-top:8px">
              <button class="btn" onclick="location.href='skilldetails.php?skill_id=${skill.id}'">View Details</button>
            </div>`;
            grid.appendChild(card);
        });
    })
    .catch(err => {
        console.error('Error loading skills:', err);
        document.getElementById('skillsGrid').innerHTML='<p style="color:white;">Error loading skills.</p>';
    });
}

window.addEventListener('DOMContentLoaded', loadSkills);
</script>
</body>
</html>