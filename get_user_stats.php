<?php
session_start();
header('Content-Type: application/json');

$servername = "sql105.infinityfree.com";
$username = "if0_39907321";
$password = "SkillSpace4";
$database = "if0_39907321_student";
$con = new mysqli($servername, $username, $password, $database);

if ($con->connect_error) {
    error_log("get_user_stats.php: Connection failed: " . $con->connect_error);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 1;

$stmt = $con->prepare("SELECT COUNT(*) as offered FROM skills_offered WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$offered = $result->fetch_assoc()['offered'];

$stmt = $con->prepare("SELECT COUNT(*) as requested FROM users WHERE id = ? AND profession IN ('learner', 'both')");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$requested = $result->fetch_assoc()['requested'];

$response = [
    'offered' => $offered,
    'requested' => $requested,
    'matches' => 0 // Placeholder for future matches
];

$stmt->close();
$con->close();
echo json_encode($response);
?>