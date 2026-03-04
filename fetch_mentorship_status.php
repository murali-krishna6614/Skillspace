<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit();
}

$servername = "sql105.infinityfree.com";
$username = "if0_39907321";
$password = "SkillSpace4";
$database = "if0_39907321_student";

$con = new mysqli($servername, $username, $password, $database);
if ($con->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch mentorships where user is mentee or mentor
$sql = "SELECT m.id, m.skill_id, m.mentor_id, m.mentee_id, m.status,
               s.skills_offered AS skill_title,
               u1.fullname AS mentor_name,
               u2.fullname AS mentee_name
        FROM mentorships m
        LEFT JOIN users u1 ON m.mentor_id = u1.id
        LEFT JOIN users u2 ON m.mentee_id = u2.id
        LEFT JOIN users s_user ON m.mentor_id = s_user.id
        LEFT JOIN users s ON s_user.id = s.id
        WHERE m.mentee_id = ? OR m.mentor_id = ?
        ORDER BY m.id DESC";

$stmt = $con->prepare($sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$mentorships = [];

while ($row = $result->fetch_assoc()) {
    $mentorships[] = [
        'id' => $row['id'],
        'skill_title' => $row['skill_title'] ?? 'Skill',
        'mentor_name' => $row['mentor_name'] ?? 'Mentor',
        'mentee_name' => $row['mentee_name'] ?? 'Mentee',
        'status' => $row['status'] ?? 'Pending',
        'mentor_id' => $row['mentor_id'],
        'mentee_id' => $row['mentee_id']
    ];
}

echo json_encode(['mentorships' => $mentorships]);
$con->close();
?>
