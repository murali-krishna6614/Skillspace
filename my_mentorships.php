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
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$servername = "sql105.infinityfree.com";
$username = "if0_39907321";
$password = "SkillSpace4";
$database = "if0_39907321_student";

$con = new mysqli($servername, $username, $password, $database);
if ($con->connect_error) {
    die("Database connection failed: " . $con->connect_error);
}

// Fetch mentorships for the logged-in mentee
$sql = "
    SELECT 
        mr.id AS request_id,
        s.id AS skill_id,
        s.title AS skill_name,
        s.category,
        s.image,
        u.fullname AS mentor_name,
        u.email AS mentor_email,
        mr.status AS request_status,
        s.mentor_id
    FROM mentorship_requests mr
    INNER JOIN skills s ON mr.skill_id = s.id
    INNER JOIN users u ON s.mentor_id = u.id
    WHERE mr.mentee_id = ?
    ORDER BY mr.id DESC
";

$stmt = $con->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Mentorships — SkillSpace</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
/* === BRIGHT, VIBRANT BACKGROUND ADDED === */
body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #00c6ff, #0072ff, #00ffdd);
    background-attachment: fixed;
    background-size: cover;
    color: #222;
    margin: 0;
    padding: 0;
}

/* === EXISTING STYLES (UNCHANGED) === */
header {
    background: #007bff;
    color: white;
    text-align: center;
    padding: 15px 0;
    font-size: 22px;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

table {
    width: 90%;
    margin: 30px auto;
    border-collapse: collapse;
    background: #ffffff;
    box-shadow: 0 6px 15px rgba(0,0,0,0.1);
    border-radius: 12px;
    overflow: hidden;
}
th, td {
    padding: 15px;
    text-align: center;
    border-bottom: 1px solid #eee;
}
th {
    background: #007bff;
    color: white;
    font-weight: 600;
}
.status {
    padding: 6px 12px;
    border-radius: 6px;
    font-weight: 600;
}
.pending { background: #fff3cd; color: #856404; }
.accepted { background: #d4edda; color: #155724; }
.rejected { background: #f8d7da; color: #721c24; }
.chat-btn {
    background: #007bff;
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 7px 14px;
    cursor: pointer;
    font-size: 14px;
    transition: 0.2s;
}
.chat-btn:hover { background: #0056b3; }
.no-data {
    text-align: center;
    padding: 40px;
    color: #fff;
    font-size: 16px;
    font-weight: 500;
}
</style>
</head>
<body>
<header>My Mentorships</header>

<?php if ($result->num_rows > 0): ?>
<table>
    <thead>
        <tr>
            <th>Skill</th>
            <th>Category</th>
            <th>Mentor Name</th>
            <th>Email</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr id="request-<?php echo $row['request_id']; ?>">
                <td><?php echo htmlspecialchars($row['skill_name']); ?></td>
                <td><?php echo htmlspecialchars($row['category']); ?></td>
                <td><?php echo htmlspecialchars($row['mentor_name']); ?></td>
                <td><?php echo htmlspecialchars($row['mentor_email']); ?></td>
                <td>
                    <span class="status <?php echo strtolower($row['request_status']); ?>">
                        <?php echo ucfirst($row['request_status']); ?>
                    </span>
                </td>
                <td>
                    <?php
                    // Fetch the actual mentorship_id from mentorships table
                    $mentorshipQuery = $con->prepare("SELECT id FROM mentorships WHERE mentor_id = ? AND mentee_id = ? AND skill_id = ?");
                    $mentorshipQuery->bind_param("iii", $row['mentor_id'], $user_id, $row['skill_id']);
                    $mentorshipQuery->execute();
                    $mentorshipResult = $mentorshipQuery->get_result();
                    $mentorshipRow = $mentorshipResult->fetch_assoc();
                    $mentorship_id = $mentorshipRow ? $mentorshipRow['id'] : 0;
                    $mentorshipQuery->close();
                    ?>

                    <?php if ($row['request_status'] === 'accepted' && $mentorship_id > 0): ?>
                        <button class="chat-btn" onclick="goToChat(<?php echo $mentorship_id; ?>)">Chat</button>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<?php else: ?>
    <div class="no-data">You have no mentorships yet.</div>
<?php endif; ?>

<script>
function goToChat(mentorshipId){
    window.location.href = `connect.php?mentorship_id=${mentorshipId}`;
}

// Optional: auto-refresh to update statuses every 10 seconds
setInterval(() => {
    fetch('fetch_mentorship_status.php')
    .then(res=>res.json())
    .then(data=>{
        data.forEach(req=>{
            const row = document.getElementById('request-'+req.request_id);
            if(row){
                const statusCell = row.querySelector('.status');
                statusCell.textContent = req.status.charAt(0).toUpperCase() + req.status.slice(1);
                statusCell.className = 'status ' + req.status;

                const actionCell = row.lastElementChild;
                if(req.status === 'accepted' && req.mentorship_id > 0){
                    actionCell.innerHTML = `<button class="chat-btn" onclick="goToChat(${req.mentorship_id})">Chat</button>`;
                } else {
                    actionCell.textContent = '—';
                }
            }
        });
    });
}, 10000);
</script>

</body>
</html>

<?php
$stmt->close();
$con->close();
?>