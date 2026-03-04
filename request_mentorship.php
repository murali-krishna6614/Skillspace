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

$mentee_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$skill_id = $input['skill_id'] ?? null;

if (!$skill_id) {
    echo json_encode(['error' => 'Skill ID missing']);
    exit();
}

// Check if request already exists
$stmt = $con->prepare("SELECT * FROM mentorship_requests WHERE mentee_id=? AND skill_id=? LIMIT 1");
$stmt->bind_param("ii", $mentee_id, $skill_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    echo json_encode(['error' => 'You already requested this skill']);
    exit();
}
$stmt->close();

// Insert request
$stmt = $con->prepare("INSERT INTO mentorship_requests (skill_id, mentee_id, status) VALUES (?, ?, 'pending')");
$stmt->bind_param("ii", $skill_id, $mentee_id);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Request sent successfully']);
} else {
    echo json_encode(['error' => 'Failed to send request']);
}
$stmt->close();
$con->close();
?>
