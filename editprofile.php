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

// Check if the request is a form submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if(!isset($_SESSION['user_id'])){
        echo json_encode(['error'=>'User not logged in']);
        exit();
    }

    $servername = "sql105.infinityfree.com";
    $username = "if0_39907321";
    $password = "SkillSpace4";
    $database = "if0_39907321_student";

    $con = new mysqli($servername,$username,$password,$database);
    if($con->connect_error){
        echo json_encode(['error'=>'Database connection failed']);
        exit();
    }

    $user_id = $_SESSION['user_id'];

    // Get form data
    $fullname = trim($_POST['name'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $skill_offered = trim($_POST['skillsOffered'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $skill_requested = trim($_POST['skillsRequested'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $skill_description = trim($_POST['skillDescription'] ?? '');

    // Handle file uploads
    $profilePic = $_FILES['profilePic'] ?? null;
    $skillImage = $_FILES['skillImage'] ?? null;

    // === PROFILE PIC UPLOAD ===
    $avatar_path = null;
    if($profilePic && $profilePic['tmp_name']){
        $ext = pathinfo($profilePic['name'], PATHINFO_EXTENSION);
        $new_name = 'avatar_'.$user_id.'_'.time().'.'.$ext;
        $upload_dir = 'Uploads/';
        if(!is_dir($upload_dir)) mkdir($upload_dir,0755,true);
        $target = $upload_dir.$new_name;

        if(move_uploaded_file($profilePic['tmp_name'], $target)){
            $avatar_path = $target;
        } else {
            echo json_encode(['error'=>'Failed to upload profile picture']);
            exit();
        }
    }

    // === SKILL IMAGE UPLOAD ===
    $skill_image_path = null;
    if($skillImage && $skillImage['tmp_name']){
        $ext = pathinfo($skillImage['name'], PATHINFO_EXTENSION);
        $new_name = 'skill_'.$user_id.'_'.time().'.'.$ext;
        $upload_dir = 'Uploads/';
        if(!is_dir($upload_dir)) mkdir($upload_dir,0755,true);
        $target = $upload_dir.$new_name;

        if(move_uploaded_file($skillImage['tmp_name'], $target)){
            $skill_image_path = $target;
        } else {
            echo json_encode(['error'=>'Failed to upload skill image']);
            exit();
        }
    }

    // === UPDATE USERS TABLE ===
    $sql = "UPDATE users SET fullname=?, city=?, bio=?, skills_offered=?, skills=?, avatar=? WHERE id=?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("ssssssi", $fullname, $city, $bio, $skill_offered, $skill_requested, $avatar_path, $user_id);

    if(!$stmt->execute()){
        echo json_encode(['error'=>'Failed to update user profile']);
        exit();
    }
    $stmt->close();

    // === UPDATE SKILLS TABLE ===
    // Instead of deleting all skills, we will keep your previous behavior (delete then insert only latest)
    // This ensures the single-skill UI you had remains compatible.

    // Delete existing skills for this mentor (same as before)
    $delete_query = "DELETE FROM skills WHERE mentor_id = ?";
    $stmt = $con->prepare($delete_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    // Insert latest skill offered (with description and mentor_name)
    if(!empty($skill_offered)){
        $insert_query = "INSERT INTO skills (title, category, mentor_id, mentor_name, description, image, rating, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, 0, NOW())";
        $stmt = $con->prepare($insert_query);
        // bind: title (s), category (s), mentor_id (i), mentor_name (s), description (s), image (s)
        $stmt->bind_param("ssisss", $skill_offered, $category, $user_id, $fullname, $skill_description, $skill_image_path);
        $stmt->execute();
        $stmt->close();
    }

    // === UPDATE SESSION ===
    $_SESSION['fullname'] = $fullname;
    if($avatar_path) $_SESSION['avatar'] = $avatar_path;

    // === RETURN SUCCESS ===
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully!',
        'avatar' => $avatar_path ?? $_SESSION['avatar']
    ]);

    $con->close();
    exit();
}
?>
<!DOCTYPE html> 
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>SkillSpace — Edit Profile</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root {
  --bg: linear-gradient(135deg, #1e3a8a, #0f172a, #0d9488);
  --accent: #6366f1;
  --accent2:#06b6d4;
  --muted: #64748b;
}
body {
  margin:0;
  font-family:'Inter',system-ui,sans-serif;
  background:var(--bg);
  display:flex;
  align-items:center;
  justify-content:center;
  min-height:100vh;
}
.panel {
  width:90%;
  max-width:1100px;
  height:700px;
  display:grid;
  grid-template-columns:1fr 1fr;
  border-radius:20px;
  overflow:hidden;
  box-shadow:0 20px 50px rgba(0,0,0,0.3);
  background:white;
  position:relative;
  z-index:1;
}
@media(max-width:900px){
  .panel{grid-template-columns:1fr;height:auto}
  .image-box{display:none}
}
.image-box {
  background:url("skillspace2.png") center/cover no-repeat;
  position:relative;
}
.form-box {
  padding:40px;
  display:flex;
  flex-direction:column;
  justify-content:flex-start;
  overflow-y:auto;
}
h2 {
  font-size:2rem;
  color:#0f172a;
  margin:0 0 4px;
  font-weight:700;
}
.small {
  color:var(--muted);
  margin-bottom:18px;
  display:block;
  font-size:0.95rem;
}
label {
  font-weight:600;
  color:#334155;
  display:block;
  margin-bottom:4px;
}
input, textarea {
  width:100%;
  padding:14px 16px;
  border-radius:12px;
  border:1px solid #cbd5e1;
  font-size:1rem;
  background:#f8fafc;
  margin-bottom:20px;
  transition:border .2s ease, box-shadow .2s ease;
}
textarea {
  resize:vertical;
  min-height:120px;
}
.btn-gradient {
  padding:14px 18px;
  border-radius:12px;
  background:linear-gradient(90deg,var(--accent2),var(--accent));
  color:white;
  border:none;
  cursor:pointer;
  font-weight:700;
  font-size:1.1rem;
  transition:transform .2s ease, box-shadow .2s ease;
}
.btn-gradient:hover {
  transform:translateY(-2px);
  box-shadow:0 8px 20px rgba(0,0,0,0.15);
}
</style>
</head>
<body>
<div class="panel">
  <div class="image-box"></div>
  <div class="form-box">
    <h2>Edit Profile</h2>
    <span class="small">Update your information and skills</span>
    <form id="editForm" enctype="multipart/form-data">
      <div><label>Full Name</label><input type="text" id="name" name="name" required></div>
      <div><label>City</label><input type="text" id="city" name="city"></div>

      <!-- Skill title -->
      <div><label>Skill Offered</label><input type="text" id="skillsOffered" name="skillsOffered"></div>

      <!-- Category -->
      <div><label>Category</label><input type="text" id="category" name="category"></div>

      <!-- Skill description - NEW FIELD -->
      <div><label>Skill Description</label><textarea id="skillDescription" name="skillDescription" placeholder="Describe what you teach, prerequisites and format (e.g., 1:1 mentoring, 4 sessions)"></textarea></div>

      <div>
        <label>Skill Image</label>
        <input type="file" id="skillImage" name="skillImage" accept="image/*">
        <small class="small">Image shown in Browse Skills & Skill Details</small>
      </div>
      <div><label>Skill Requested</label><input type="text" id="skillsRequested" name="skillsRequested"></div>
      <div><label>Bio</label><textarea rows="3" id="bio" name="bio"></textarea></div>
      <div>
        <label>Profile Picture</label>
        <input type="file" id="profilePic" name="profilePic" accept="image/*">
        <small class="small">PNG/JPG up to 2MB</small>
      </div>
      <button class="btn-gradient" type="submit">💾 Save Changes</button>
    </form>
  </div>
</div>

<script>
// Populate form with current profile data
window.addEventListener('DOMContentLoaded', () => {
  fetch('profile.php?t=' + new Date().getTime())
    .then(res => res.json())
    .then(data => {
      if(data.error) return console.error(data.error);
      document.getElementById('name').value = data.fullname || '';
      document.getElementById('city').value = data.city || '';
      // Older apps had skills_offered as list; handle both
      document.getElementById('skillsOffered').value = data.skills_offered?.[0]?.title || data.skills_offered || '';
      document.getElementById('category').value = data.skills_offered?.[0]?.category || '';
      document.getElementById('skillDescription').value = data.skills_offered?.[0]?.description || '';
      document.getElementById('skillsRequested').value = data.skills_requested?.[0] || '';
      document.getElementById('bio').value = data.bio || '';
    })
    .catch(err => console.error('Profile fetch error:', err));
});

// Handle form submission
document.getElementById('editForm').addEventListener('submit', function(e){
  e.preventDefault();
  const formData = new FormData(this);

  // include the new skillDescription value
  fetch('editprofile.php', { method:'POST', body: formData })
    .then(res => res.json())
    .then(data => {
      if(data.success){
        alert("✅ Profile updated successfully!");
        // Update dashboard avatar immediately if exists
        const dashAvatar = document.getElementById('user-avatar');
        if(dashAvatar && data.avatar) dashAvatar.src = data.avatar;
        window.location.href = `profile.php?t=${new Date().getTime()}`;
      } else {
        alert("❌ Update failed: " + (data.error || "Unknown error"));
      }
    })
    .catch(err => {
      console.error('Error submitting form:', err);
      alert("🚫 Error submitting form: " + err.message);
    });
});
</script>
</body>
</html>