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
if(!isset($_SESSION['user_id'])){
    header("Location: dashboard.php");
    exit();
}

$servername = "sql105.infinityfree.com";
$username = "if0_39907321";
$password = "SkillSpace4";
$database = "if0_39907321_student";

$con = new mysqli($servername, $username, $password, $database);
if($con->connect_error){
    die("Database connection failed: " . $con->connect_error);
}

$user_id = $_SESSION['user_id'];

$stmt = $con->prepare("SELECT skills, fullname FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

$requested_skills = array_map('trim', explode(',', $user['skills'] ?? ''));

$matches = [];
if(count($requested_skills) > 0){
    $regex = implode('|', $requested_skills);
    $sql = "SELECT s.*, u.avatar, u.id AS user_id FROM skills s 
            JOIN users u ON s.mentor_id = u.id 
            WHERE u.id != ? AND s.title REGEXP ? 
            ORDER BY s.rating DESC, s.created_at DESC";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("is", $user_id, $regex);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()){
        $fid = $row['id'];
        $feedQ = $con->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total FROM feedback WHERE skill_id=?");
        $feedQ->bind_param("i", $fid);
        $feedQ->execute();
        $feed = $feedQ->get_result()->fetch_assoc();
        $row['avg_rating'] = $feed['avg_rating'] ? round($feed['avg_rating'],1) : 0;
        $row['total_feedback'] = $feed['total'] ?? 0;
        $feedQ->close();
        $row['feedbacks'] = [];
        $matches[] = $row;
    }
    $stmt->close();
}
$con->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SkillSpace ⚡ — Skill Connections</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
/* ---------- Page & Body ---------- */
body {
    background: linear-gradient(135deg, #ffcc00, #ff8800, #ff3b3b);
    font-family: 'Poppins', sans-serif;
    min-height: 100vh;
    color: #222;
}

/* ---------- Navbar ---------- */
.navbar {
    background: linear-gradient(90deg, #2193b0, #6dd5ed) !important; /* Blue gradient navbar */
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    padding: 0.3rem 1rem;
    height: 55px;
}
.navbar-brand {
    font-weight: 700;
    font-size: 1.3rem;
    color: #fff !important;
}
.navbar .nav-link {
    color: #fff !important;
    font-weight: 500;
    margin-left: 10px;
    padding: 0.3rem 0.6rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}
.navbar .nav-link:hover { 
    background: rgba(255,255,255,0.2);
    transform: scale(1.05);
}

/* ---------- Skill Card ---------- */
.card-match {
    transition: transform 0.3s, box-shadow 0.3s;
    border-radius: 20px;
    background: linear-gradient(145deg, #d0e6f7, #a0c4e8); /* Updated card background color */
    position: relative;
    padding-top: 70px;
    overflow: visible;
}
.card-match:hover {
    transform: translateY(-7px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.35);
}
.avatar-circle {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    position: absolute;
    top: -60px;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid #fff;
    box-shadow: 0 5px 20px rgba(0,0,0,0.25);
    background-color: #fff;
}
.card-body { text-align: center; padding-top: 25px; }
.rating { margin-bottom: 8px; }
.badge-category {
    background: #2193b0; color: #fff; margin: 3px; /* Badge color adjusted to match card */
}
.feedback-box {
    background: linear-gradient(135deg, #f0f8ff, #d0e6f7); /* Card inside feedback box color updated */
    border-radius: 12px; padding: 12px; margin-top: 10px;
    font-size: 0.9rem;
}
.feedback-box small { color: #333; }

/* ---------- Heading ---------- */
h2 {
    font-weight: 700;
    color: #fff;
    text-shadow: 1px 1px 6px rgba(0,0,0,0.3);
}

/* ---------- Responsive Navbar Toggle ---------- */
.navbar-toggler {
    border-color: #fff;
}
.navbar-toggler-icon {
    background-image: url("data:image/svg+xml;charset=utf8,%3Csvg viewBox='0 0 30 30' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath stroke='rgba%28255, 255, 255, 1%29' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 7h22M4 15h22M4 23h22'/%3E%3C/svg%3E");
}
</style>
</head>
<body>

<!-- ---------- Navbar ---------- -->
<nav class="navbar navbar-expand-lg shadow-sm sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">⚡ SkillSpace</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
      aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav align-items-center">
        <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="browseskills.php"><i class="bi bi-search me-1"></i> Browse Skills</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- ---------- Page Content ---------- -->
<div class="container py-5 text-center">
    <h2 class="mb-4">Skill Connections</h2>

    <div class="row g-4 mt-4">
        <?php if(count($matches) > 0): ?>
            <?php foreach($matches as $match): ?>
                <div class="col-md-4">
                    <div class="card card-match h-100" data-user-id="<?= htmlspecialchars($match['user_id']); ?>" data-skill-id="<?= htmlspecialchars($match['id']); ?>">
                        <img src="<?= $match['avatar'] ?? 'Uploads/default-avatar.png'; ?>" class="avatar-circle">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($match['mentor_name']); ?></h5>
                            <p class="card-text"><strong>Skill:</strong> <?= htmlspecialchars($match['title']); ?></p>
                            <p class="card-text"><strong>Category:</strong> 
                                <span class="badge badge-category"><?= htmlspecialchars($match['category']); ?></span>
                            </p>

                            <div class="rating mb-2"></div>

                            <div class="feedback-box" id="feedback-<?= htmlspecialchars($match['id']); ?>">
                                <p>Loading latest feedback...</p>
                            </div>

                            <p class="request-note mt-2">To request this skill, visit <a href="browseskills.php">Browse Skills →</a></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info text-center">No matches found for your requested skills.</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function escapeHtml(unsafe) {
    return unsafe
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

document.addEventListener('DOMContentLoaded', () => {
    const cards = document.querySelectorAll('.card-match');
    cards.forEach(card => {
        const skillId = card.getAttribute('data-skill-id');
        if (skillId) {
            fetch(`connect.php?action=fetch_latest_feedback_by_skill&skill_id=${skillId}`)
                .then(res => res.json())
                .then(data => {
                    const feedbackDiv = document.getElementById(`feedback-${skillId}`);
                    const ratingDiv = card.querySelector('.rating');
                    if (feedbackDiv && ratingDiv) {
                        if (data.rating && data.created_at) {
                            const ratingDisplay = `⭐ ${data.rating}/5`;
                            const commentDisplay = data.comment && data.comment.trim() ? `: ${escapeHtml(data.comment.trim())}` : ': No comment provided';
                            feedbackDiv.innerHTML = `<p><strong>${ratingDisplay}${commentDisplay}</strong></p><small>by ${escapeHtml(data.giver_name || 'Anonymous')}</small>`;
                            ratingDiv.innerHTML = `<p><strong>Average Rating:</strong> ⭐ ${data.avg_rating || data.rating}/5</p>`;
                        } else {
                            feedbackDiv.innerHTML = `<p>No feedback available</p>`;
                            ratingDiv.innerHTML = `<p><strong>Average Rating:</strong> ⭐ 0/5</p>`;
                        }
                    }
                }).catch(() => {
                    const feedbackDiv = document.getElementById(`feedback-${skillId}`);
                    const ratingDiv = card.querySelector('.rating');
                    if (feedbackDiv && ratingDiv) {
                        feedbackDiv.innerHTML = `<p>No feedback available</p>`;
                        ratingDiv.innerHTML = `<p><strong>Average Rating:</strong> ⭐ 0/5</p>`;
                    }
                });
        }
    });
});
</script>
</body>
</html>