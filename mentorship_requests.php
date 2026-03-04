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

$servername = "sql105.infinityfree.com";
$username = "if0_39907321";
$password = "SkillSpace4";
$database = "if0_39907321_student";

$con = new mysqli($servername, $username, $password, $database);
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

$mentor_id = $_SESSION['user_id'];

// Fetch mentorship requests for mentor's skills
$sql = "SELECT mr.id AS request_id, u.fullname AS mentee_name, u.email AS mentee_email,
        s.title AS skill_name, s.id AS skill_id, mr.status
        FROM mentorship_requests mr
        INNER JOIN skills s ON mr.skill_id = s.id
        INNER JOIN users u ON mr.mentee_id = u.id
        WHERE s.mentor_id = ?
        ORDER BY mr.id DESC";

$stmt = $con->prepare($sql);
$stmt->bind_param("i", $mentor_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mentorship Requests</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
body { 
    font-family: 'Poppins', sans-serif; 
    margin:0; 
    padding:20px; 
    background: linear-gradient(135deg, #ffdd00 0%, #ff8800 40%, #ff3c00 100%);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: flex-start;
}
.container { 
    max-width: 900px; 
    width: 100%;
    background: linear-gradient(145deg, #fff8e1, #fff3cd);
    border-radius: 18px; 
    padding: 25px 35px; 
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    margin-top: 40px;
}
h1 { 
    text-align:center; 
    color:#b91c1c; 
    text-shadow: 1px 1px 3px rgba(255,255,255,0.6);
}
table { 
    width: 100%; 
    border-collapse: collapse; 
    margin-top: 20px; 
    background: #fffdf5;
    border-radius: 10px;
    overflow: hidden;
}
th, td { 
    padding: 14px; 
    text-align: center; 
    border-bottom: 1px solid #f2c94c;
    color: #222;
}
th { 
    background: #ffcc66; 
    color: #3b0d0c; 
    font-weight: 600;
}
.status { 
    font-weight: 600; 
    border-radius: 8px; 
    padding: 6px 12px; 
    display: inline-block; 
}
.pending { 
    background: #fff7ae; 
    color: #92400e; 
}
.accepted { 
    background: #c6f6d5; 
    color: #065f46; 
}
.rejected { 
    background: #fecaca; 
    color: #991b1b; 
}
.btn { 
    border:none; 
    padding:6px 12px; 
    border-radius: 8px; 
    cursor:pointer; 
    font-weight:600; 
    margin:2px;
}
.accept-btn { 
    background: #f59e0b; 
    color:white; 
}
.reject-btn { 
    background: #ef4444; 
    color:white; 
}
.btn:hover { 
    opacity:0.9; 
}
p { 
    color: #222; 
    text-align:center;
}
</style>
</head>
<body>
<div class="container">
<h1>Mentorship Requests</h1>

<?php if ($result->num_rows > 0): ?>
<table>
<thead>
<tr>
<th>Mentee Name</th>
<th>Email</th>
<th>Skill Requested</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php while($row = $result->fetch_assoc()): ?>
<tr id="request-<?php echo $row['request_id']; ?>">
<td><?php echo htmlspecialchars($row['mentee_name']); ?></td>
<td><?php echo htmlspecialchars($row['mentee_email']); ?></td>
<td><?php echo htmlspecialchars($row['skill_name']); ?></td>
<td><span class="status <?php echo strtolower($row['status']); ?>"><?php echo ucfirst($row['status']); ?></span></td>
<td>
<?php if($row['status'] === 'pending'): ?>
    <button class="btn accept-btn" onclick="updateStatus(<?php echo $row['request_id']; ?>, 'accepted', <?php echo $row['skill_id']; ?>)">Accept</button>
    <button class="btn reject-btn" onclick="updateStatus(<?php echo $row['request_id']; ?>, 'rejected', <?php echo $row['skill_id']; ?>)">Reject</button>
<?php else: ?>
    —
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
<?php else: ?>
<p>No mentorship requests yet.</p>
<?php endif; ?>
</div>

<script>
function updateStatus(requestId, action, skillId) {
    if(!confirm(`Are you sure you want to ${action} this request?`)) return;

    fetch('update_request_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `request_id=${requestId}&action=${action}`
    })
    .then(res => res.json())
    .then(data => {
        if(data.success){
            const row = document.getElementById(`request-${requestId}`);
            row.querySelector('.status').textContent = action.charAt(0).toUpperCase() + action.slice(1);
            row.querySelector('.status').className = 'status ' + action;

            const actionCell = row.lastElementChild;
            actionCell.textContent = '—';

            fetch('notify_mentee.php', {
                method: 'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:`request_id=${requestId}&status=${action}&skill_id=${skillId}`
            });

        } else {
            alert(data.error || 'Error updating request.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Network error.');
    });
}
</script>

</body>
</html>

<?php 
$stmt->close(); 
$con->close(); 
?>