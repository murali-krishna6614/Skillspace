<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ob_start(); // Start output buffering

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = trim($_POST['fname']);
    $email = trim($_POST['email']);
    $pwd = trim($_POST['pwd']);
    $cpwd = trim($_POST['cpwd']);
    $profession = trim($_POST['profession']);

    if ($pwd != $cpwd) {
        error_log("reg.php: Passwords do not match for email $email");
        die("Passwords do not match. Please go back and try again.");
    }

    $servername = "sql105.infinityfree.com";
    $username = "if0_39907321";
    $password = "SkillSpace4";
    $database = "if0_39907321_student";
    $con = new mysqli($servername, $username, $password, $database);

    if ($con->connect_error) {
        error_log("reg.php: Connection failed: " . $con->connect_error);
        die("Connection failed: " . $con->connect_error);
    }

    // Sanitize and check for duplicate email
    $email = mysqli_real_escape_string($con, $email);
    $check_email = $con->query("SELECT id FROM users WHERE email='$email'");
    if ($check_email->num_rows > 0) {
        error_log("reg.php: Email $email already exists");
        die("Email already registered. Please use a different email.");
    }

    // Escape inputs
    $fname = mysqli_real_escape_string($con, $fname);
    $profession = mysqli_real_escape_string($con, $profession);

    // Insert user data (without hashing password)
    $sql = "INSERT INTO users (fullname, email, password, profession) VALUES ('$fname', '$email', '$pwd', '$profession')";
    if ($con->query($sql) === TRUE) {
        error_log("reg.php: User registered: $email");
        ob_end_clean(); // Clear any output before redirect
        header("Location: index.php");
        exit();
    } else {
        error_log("reg.php: Error inserting record: " . $con->error);
        echo "Error inserting record: " . $con->error;
    }

    $con->close();
    ob_end_flush(); // Flush output buffer
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>SkillSpace - Register</title>
  <style>
    /* 🌌 Animated Polygon Network Background */
    body {
      margin: 0;
      font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      background: #0f2027;
      background: radial-gradient(circle at 20% 20%, #283e51, #0f2027);
      overflow: hidden;
      position: relative;
    }

    canvas#polygon-bg {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: 0;
    }

    /* ⚡ Container with left image section + register form */
    .main-wrapper {
      display: flex;
      justify-content: center;
      align-items: stretch;
      width: 850px;
      height: 520px;
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(12px) saturate(160%);
      -webkit-backdrop-filter: blur(12px) saturate(160%);
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.25);
      position: relative;
      z-index: 2;
    }

    /* 📸 Left-side image box */
    .image-box {
      flex: 1;
      background: url('skillspace1.png') center/cover no-repeat;
      position: relative;
    }

    .image-box::after {
      content: "";
      position: absolute;
      inset: 0;
      background: rgba(0, 0, 0, 0.25);
    }

    /* 💎 Glass Registration Box */
    .register-container {
      flex: 1;
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(25px) saturate(200%);
      -webkit-backdrop-filter: blur(25px) saturate(200%);
      padding: 40px 35px;
      text-align: center;
      position: relative;
      z-index: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
      border-left: 1px solid rgba(255,255,255,0.2);
    }

    .register-container h2 {
      margin-bottom: 25px;
      color: #f1f1f1;
      font-weight: 700;
      letter-spacing: 0.5px;
    }

    /* ✨ Input Styling */
    .register-container input,
    .register-container select {
      width: 100%;
      padding: 12px;
      margin: 10px 0;
      border: none;
      border-radius: 10px;
      font-size: 1em;
      outline: none;
      background: rgba(255, 255, 255, 0.85);
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
      transition: all 0.3s ease;
    }

    .register-container input:focus,
    .register-container select:focus {
      box-shadow: 0 0 8px rgba(102, 126, 234, 0.8);
      transform: scale(1.02);
    }

    /* 🔘 Button Styling */
    .register-container button {
      width: 100%;
      padding: 12px;
      margin-top: 15px;
      background: linear-gradient(90deg, #36d1dc, #5b86e5);
      border: none;
      color: white;
      font-size: 1em;
      font-weight: bold;
      border-radius: 25px;
      cursor: pointer;
      transition: all 0.4s ease;
      box-shadow: 0 4px 15px rgba(91, 134, 229, 0.5);
    }

    .register-container button:hover {
      background: linear-gradient(90deg, #5b86e5, #36d1dc);
      box-shadow: 0 8px 20px rgba(91, 134, 229, 0.7);
      transform: translateY(-2px);
    }

    /* 🌸 Link Styling */
    .register-container a {
      display: block;
      margin-top: 15px;
      text-decoration: none;
      font-size: 0.9em;
      color: #f1f1f1;
      transition: color 0.3s, transform 0.3s;
    }

    .register-container a:hover {
      color: #9ec5ff;
      transform: scale(1.05);
    }

    @media (max-width: 768px) {
      .main-wrapper {
        flex-direction: column;
        width: 90%;
        height: auto;
      }
      .image-box {
        height: 200px;
      }
    }
  </style>
</head>
<body>
  <canvas id="polygon-bg"></canvas>

  <div class="main-wrapper">
    <div class="image-box"></div>
    <div class="register-container">
      <h2>Create Your Account</h2>
      <form method="post" action="reg.php" novalidate>
        <input type="text" name="fname" placeholder="Full Name" required />
        <input type="email" name="email" placeholder="Email" required />
        <input type="password" name="pwd" placeholder="Password" required />
        <input type="password" name="cpwd" placeholder="Confirm Password" required />
        <select name="profession" required>
          <option value="">Select profession</option>
          <option value="learner">Learner</option>
          <option value="mentor">Mentor</option>
          <option value="both">Both</option>
        </select>
        <button type="submit">Register</button>
      </form>
      <a href="index.php">Already have an account? Login</a>
    </div>
  </div>

  <!-- 🎨 Polygon Animation Script -->
  <script>
    const canvas = document.getElementById("polygon-bg");
    const ctx = canvas.getContext("2d");
    let width, height, points;

    function resize() {
      width = canvas.width = window.innerWidth;
      height = canvas.height = window.innerHeight;
      points = Array.from({length: 70}, () => ({
        x: Math.random() * width,
        y: Math.random() * height,
        vx: (Math.random() - 0.5) * 0.6,
        vy: (Math.random() - 0.5) * 0.6
      }));
    }

    window.addEventListener("resize", resize);
    resize();

    function draw() {
      ctx.clearRect(0, 0, width, height);
      ctx.fillStyle = "rgba(255,255,255,0.3)";
      for (const p of points) {
        p.x += p.vx;
        p.y += p.vy;
        if (p.x < 0 || p.x > width) p.vx *= -1;
        if (p.y < 0 || p.y > height) p.vy *= -1;
        ctx.beginPath();
        ctx.arc(p.x, p.y, 2, 0, Math.PI * 2);
        ctx.fill();
      }

      for (let i = 0; i < points.length; i++) {
        for (let j = i + 1; j < points.length; j++) {
          const dx = points[i].x - points[j].x;
          const dy = points[i].y - points[j].y;
          const dist = Math.sqrt(dx * dx + dy * dy);
          if (dist < 130) {
            ctx.strokeStyle = `rgba(255,255,255,${0.15 - dist / 1300})`;
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(points[i].x, points[i].y);
            ctx.lineTo(points[j].x, points[j].y);
            ctx.stroke();
          }
        }
      }
      requestAnimationFrame(draw);
    }
    draw();
  </script>
</body>
</html>
