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

    // Ensure user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'User not logged in']);
        exit();
    }

    $servername = "sql105.infinityfree.com";
    $username = "if0_39907321";
    $password = "SkillSpace4";
    $database = "if0_39907321_student";

    // Create connection
    $con = new mysqli($servername, $username, $password, $database);
    if ($con->connect_error) {
        echo json_encode(['error' => 'Database connection failed']);
        exit();
    }

    $user_id = $_SESSION['user_id'];

    // Fetch user info
    $stmt = $con->prepare("SELECT fullname, avatar FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();

    if (!$user_data) {
        echo json_encode(['error' => 'User data not found']);
        exit();
    }

    // Count skills offered by the user
    $stmt = $con->prepare("SELECT COUNT(*) AS count FROM users WHERE id = ? AND skills_offered IS NOT NULL AND skills_offered != ''");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $offered_count = $result->fetch_assoc()['count'] ?? 0;
    $stmt->close();

    // Count skill requests made by the user
    $stmt = $con->prepare("SELECT COUNT(*) AS count FROM users WHERE id = ? AND skills IS NOT NULL AND skills != ''");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $requested_count = $result->fetch_assoc()['count'] ?? 0;
    $stmt->close();

    // Matches count — accepted mentorships where user is mentor or mentee
    $stmt = $con->prepare("
        SELECT COUNT(*) AS count 
        FROM mentorships 
        WHERE status = 'accepted' AND (mentor_id = ? OR mentee_id = ?)
    ");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $matches_count = $result->fetch_assoc()['count'] ?? 0;
    $stmt->close();

    // Recommended skills: latest 5 skills offered by other users
    $recommended_skills = [];
    $sql = "SELECT id, fullname AS mentor_name, skills_offered AS title 
            FROM users 
            WHERE id != ? 
            AND skills_offered IS NOT NULL 
            AND skills_offered != '' 
            ORDER BY created_at DESC 
            LIMIT 5";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recommended_skills[] = [
            'title' => $row['title'],
            'mentor_name' => $row['mentor_name'],
            'mentor_id' => $row['id'], // useful for opening chat
            'rating' => '4.8' // placeholder rating
        ];
    }
    $stmt->close();

    // Prepare final JSON response
    $response = [
        'fullname' => $user_data['fullname'] ?? 'User',
        'avatar' => $user_data['avatar'] ?? 'default-avatar.png',
        'offered_count' => $offered_count,
        'requested_count' => $requested_count,
        'matches_count' => $matches_count,
        'recommended_skills' => $recommended_skills
    ];

    echo json_encode($response);
    $con->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>SkillSpace — Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <script src="https://kit.fontawesome.com/4e5b2b5b2d.js" crossorigin="anonymous"></script>
  <style>
    :root {
      --accent: #7c3aed;
      --muted: #6b7280;
      --radius: 14px;
      --sidebar: #7b1e3f; /* Sidebar color */
      --sidebar-text: #fefefe; /* Sidebar text */
      --scroll-color: #fefefe; /* Scrolling text color */
      --avatar-border: #fcd34d; /* Avatar outline color as per page style */
      --maxw: 1200px;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Poppins', sans-serif;
      color: #111827;
      display: flex;
      min-height: 100vh;
      position: relative;
      overflow: hidden;
      background: linear-gradient(135deg, #ff4e50, #f9d423);
    }

    .particle {
      position: absolute;
      width: 20px;
      height: 20px;
      background: rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      animation: float 12s linear infinite;
    }
    @keyframes float {
      0% { transform: translateY(0) translateX(0) scale(0.8); opacity: 0.7; }
      50% { transform: translateY(-50vh) translateX(30vw) scale(1.2); opacity: 0.3; }
      100% { transform: translateY(-100vh) translateX(0) scale(0.8); opacity: 0.7; }
    }

    /* Sidebar */
    .sidebar {
      width: 240px;
      background: var(--sidebar);
      color: var(--sidebar-text);
      display: flex;
      flex-direction: column;
      padding: 24px 16px;
      z-index: 1;
      position: relative;
    }
    .sidebar h2 { color: #fff; margin-bottom: 30px; font-size: 1.6rem; text-align: center; }
    .sidebar a {
      text-decoration: none;
      color: var(--sidebar-text);
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px 14px;
      border-radius: 10px;
      margin-bottom: 10px;
      transition: .3s;
    }
    .sidebar a:hover { background: #991f4f; color: #fff; }

    /* Main Section */
    .main {
      flex: 1;
      padding: 30px;
      max-width: var(--maxw);
      margin: 0 auto;
      z-index: 1;
      position: relative;
    }
    .header { text-align: center; margin-bottom: 40px; }

    .scrolling-text {
      display: inline-block;
      white-space: nowrap;
      overflow: hidden;
      animation: scrollText 8s linear infinite;
      font-weight: 700;
      color: var(--scroll-color);
    }
    @keyframes scrollText {
      0% { transform: translateX(100%); }
      100% { transform: translateX(-100%); }
    }
    .header h1 { font-size: 2rem; margin-bottom: 20px; }

    /* User Section */
    .user {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 12px;
      font-weight: 600;
      margin-bottom: 30px;
    }
    .user img {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      object-fit: cover;
      border: 5px solid var(--avatar-border); /* Page-defined border color */
      cursor: pointer;
      box-shadow: 0 8px 16px rgba(124, 58, 174, 0.25);
      transition: transform 0.3s, box-shadow 0.3s;
    }
    .user img:hover {
      transform: scale(1.05);
      box-shadow: 0 12px 24px rgba(124, 58, 174, 0.35);
    }

    /* Stats Section */
    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 20px;
      margin-bottom: 40px;
    }
    .stat {
      border-radius: var(--radius);
      padding: 20px;
      text-align: center;
      color: #1e293b;
      background: linear-gradient(135deg, #ffffffcc, #ffffffaa);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255, 255, 255, 0.3);
      transition: transform 0.3s;
    }
    .stat:hover { transform: translateY(-3px); }
    .stat:nth-child(2) { background: linear-gradient(135deg, #d1fae5cc, #ecfdf5aa); }
    .stat:nth-child(3) { background: linear-gradient(135deg, #d0ebffcc, #e0f2feaa); }
    .stat strong { font-size: 1.5rem; display: block; color: #4c1d95; }

    /* Recommended Section */
    .section { margin-top: 40px; }
    .section h2 { margin-bottom: 14px; color: #1e293b; text-align: center; }

    .cards {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 20px;
    }
    .card {
      border-radius: var(--radius);
      padding: 18px;
      color: #1e293b;
      background: linear-gradient(135deg, #ffffffcc, #ffffffaa);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
      transition: .3s;
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255, 255, 255, 0.3);
    }
    .card:nth-child(2) { background: linear-gradient(135deg, #f5e3ffcc, #ede9feaa); }
    .card:nth-child(3) { background: linear-gradient(135deg, #d1fae5cc, #ecfdf5aa); }
    .card:hover { transform: translateY(-4px); }
    .card strong { display: block; margin-bottom: 6px; font-size: 1.1rem; }
    .card .small { color: var(--muted); font-size: 0.9rem; }

    /* Buttons */
    .btn {
      background: var(--accent);
      color: white;
      border: none;
      border-radius: 8px;
      padding: 8px 12px;
      margin-top: 10px;
      cursor: pointer;
      font-weight: 600;
      transition: .3s;
      box-shadow: 0 4px 12px rgba(124, 58, 174, 0.3);
    }
    .btn:hover { background: #6d28d9; }

    @media (max-width: 1024px) {
      .stats { grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); }
      .cards { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
    }
    @media (max-width: 800px) {
      .sidebar { display: none; }
      .main { padding: 20px; }
    }
  </style>
</head>
<body>

<div class="particle" style="top: 90%; left: 10%; animation-duration: 10s;"></div>
<div class="particle" style="top: 100%; left: 50%; animation-duration: 12s;"></div>
<div class="particle" style="top: 95%; left: 80%; animation-duration: 14s;"></div>
<div class="particle" style="top: 85%; left: 30%; animation-duration: 16s;"></div>
<div class="particle" style="top: 92%; left: 70%; animation-duration: 11s;"></div>

<nav class="sidebar">
  <h2>⚡ SkillSpace</h2>
  <a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
  <a href="browseskills.php"><i class="fa-solid fa-magnifying-glass"></i> Browse Skills</a>
  <a href="skillsync.php"><i class="fa-solid fa-handshake"></i> Matches</a>
  <a href="profile.php"><i class="fa-solid fa-user"></i> Profile</a>
  <a href="mentorship_requests.php"><i class="fa-solid fa-user-check"></i> Mentorship Requests</a>
  <a href="my_mentorships.php"><i class="fa-solid fa-user-graduate"></i> My Mentorships</a>
  <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</nav>

<main class="main">
  <header class="header">
    <h1 class="scrolling-text">Welcome <span id="welcome-name">User</span> 👋</h1>
    <div class="user">
      <img id="user-avatar" src="default-avatar.png" alt="user" onclick="goToProfile()">
      <span id="user-name">User</span>
    </div>
  </header>

  <section class="stats">
    <div class="stat"><strong id="offeredCount">0</strong><div class="small">Skills Offered</div></div>
    <div class="stat"><strong id="requestedCount">0</strong><div class="small">Requests Open</div></div>
    <div class="stat"><strong id="matchesCount">0</strong><div class="small">Matches</div></div>
  </section>

  <section class="section">
    <h2>Recommended for You</h2>
    <div class="cards" id="recommended-skills"></div>
  </section>
</main>

<script>
fetch('dashboard.php?api=1')
  .then(res => res.json())
  .then(data => {
    if (data.error) return console.error(data.error);

    document.getElementById('welcome-name').textContent = data.fullname;
    document.getElementById('user-name').textContent = data.fullname;
    document.getElementById('user-avatar').src = data.avatar || 'default-avatar.png';
    document.getElementById('offeredCount').textContent = data.offered_count;
    document.getElementById('requestedCount').textContent = data.requested_count;
    document.getElementById('matchesCount').textContent = data.matches_count;

    const skillsContainer = document.getElementById('recommended-skills');
    skillsContainer.innerHTML = '';
    data.recommended_skills.forEach(skill => {
      const card = document.createElement('div');
      card.className = 'card';
      card.innerHTML = `
        <strong>${skill.title}</strong>
        <div class="small">by ${skill.mentor_name}</div>
        <button class="btn" onclick="location.href='skilldetails.php?skill=${encodeURIComponent(skill.title)}'">View</button>
      `;
      skillsContainer.appendChild(card);
    });
  })
  .catch(err => console.error(err));

function goToProfile() {
  window.location.href = "profile.php";
}
</script>

</body>
</html>