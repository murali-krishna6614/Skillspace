<?php
session_start();
header('Content-Type: application/json');

$servername = "sql105.infinityfree.com";
$username = "if0_39907321";
$password = "SkillSpace4";
$database = "if0_39907321_student";

$con = new mysqli($servername, $username, $password, $database);
if ($con->connect_error) {
    echo json_encode(['error'=>'DB connection failed']);
    exit();
}

$request_id = $_POST['request_id'] ?? null;
$status = $_POST['status'] ?? null;
$skill_id = $_POST['skill_id'] ?? null;

if(!$request_id || !$status || !$skill_id){
    echo json_encode(['error'=>'Invalid input']);
    exit();
}

// Get mentee id
$stmt = $con->prepare("SELECT mentee_id FROM mentorship_requests WHERE id=? LIMIT 1");
$stmt->bind_param("i",$request_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if(!$row){
    echo json_encode(['error'=>'Request not found']);
    exit();
}

$mentee_id = $row['mentee_id'];

// Optionally, you can store notifications
$notif_stmt = $con->prepare("INSERT INTO notifications (user_id, message, skill_id, created_at) VALUES (?,?,?,NOW())");
$message = "Your mentorship request for skill ID $skill_id has been $status.";
$notif_stmt->bind_param("isi",$mentee_id,$message,$skill_id);
$notif_stmt->execute();
$notif_stmt->close();

echo json_encode(['success'=>true]);
$con->close();
?>
