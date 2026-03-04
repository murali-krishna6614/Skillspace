<?php
$servername = "sql105.infinityfree.com";
$username = "if0_39907321";
$password = "SkillSpace4";
$database = "if0_39907321_student";
$con = new mysqli($servername, $username, $password, $database);

if ($con->connect_error) {
    error_log("get_skills.php: Connection failed: " . $con->connect_error);
    echo "N/A";
} else {
    // Placeholder: Count non-NULL skills; update if a skills table exists
    $sql = "SELECT COUNT(DISTINCT skills) as count FROM users WHERE skills IS NOT NULL";
    $result = $con->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        $count = $row['count'];
        echo $count >= 1000 ? number_format($count / 1000, 1) . 'k+' : $count;
    } else {
        echo "N/A";
    }
    $con->close();
}
?>
