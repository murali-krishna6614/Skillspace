<?php
session_start();
header('Content-Type: application/json');

// Ensure user logged in
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

// Inputs
$request_id = $_POST['request_id'] ?? null;
$action = $_POST['action'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$request_id || !$action) {
    echo json_encode(['error' => 'Invalid input']);
    exit();
}

// Validate action
if (!in_array($action, ['accepted','rejected'])) {
    echo json_encode(['error' => 'Invalid action']);
    exit();
}

// Verify mentor owns this request
$stmt = $con->prepare("SELECT mr.mentee_id, s.mentor_id, mr.skill_id 
                       FROM mentorship_requests mr 
                       INNER JOIN skills s ON mr.skill_id = s.id
                       WHERE mr.id = ? LIMIT 1");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if(!$row){
    echo json_encode(['error'=>'Request not found']);
    exit();
}
if($row['mentor_id'] != $user_id){
    echo json_encode(['error'=>'Unauthorized']);
    exit();
}

// Update status
$update = $con->prepare("UPDATE mentorship_requests SET status=? WHERE id=?");
$update->bind_param("si",$action,$request_id);
if($update->execute()){

    // If accepted, create entry in mentorships table if not exists
    if($action==='accepted'){
        $check = $con->prepare("SELECT id FROM mentorships WHERE mentor_id=? AND mentee_id=? AND skill_id=?");
        $check->bind_param("iii",$user_id,$row['mentee_id'],$row['skill_id']);
        $check->execute();
        $check_res = $check->get_result();
        if($check_res->num_rows===0){
            $insert = $con->prepare("INSERT INTO mentorships (mentor_id, mentee_id, skill_id, status, created_at) VALUES (?,?,?, 'accepted', NOW())");
            $insert->bind_param("iii",$user_id,$row['mentee_id'],$row['skill_id']);
            $insert->execute();
            $insert->close();
        }
        $check->close();
    }

    echo json_encode(['success'=>true,'status'=>$action]);
}else{
    echo json_encode(['error'=>'Failed to update status']);
}
$update->close();
$con->close();
?>
