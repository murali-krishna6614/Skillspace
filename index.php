<?php  
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); 
error_reporting(E_ALL);  

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uname = trim($_POST['email']);
    $pwd1 = trim($_POST['password']);
    $servername = "sql105.infinityfree.com";
    $username = "if0_39907321";
    $password = "SkillSpace4";
    $database = "if0_39907321_student";

    $con = new mysqli($servername, $username, $password, $database);
    if ($con->connect_error) {
        error_log("index.php: Connection failed: " . $con->connect_error . " at " . date('Y-m-d H:i:s'));
        die("Connection failed: " . $con->connect_error);
    }

    $uname = mysqli_real_escape_string($con, $uname);
    $pwd1 = mysqli_real_escape_string($con, $pwd1);

    $sql = "SELECT id, fullname FROM users WHERE email='$uname' AND password='$pwd1'";
    $res = $con->query($sql);

    if ($res->num_rows > 0) {
        session_start();
        $row = $res->fetch_assoc();
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['fullname'] = $row['fullname']; 
        error_log("index.php: User $uname logged in at " . date('Y-m-d H:i:s'));
        header("Location: landing.php");
        exit();
    } else {
        error_log("index.php: Invalid login for email $uname at " . date('Y-m-d H:i:s'));
        echo "<script>alert('Please enter valid username or password'); window.location.href = 'index.php';</script>";
    }

    $con->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SkillSpace - Login</title>
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(rgba(0,0,0,0.45), rgba(0,0,0,0.45)),
                  url('skillspace5.png') no-repeat center center fixed;
      background-size: cover;
      display: flex;
      justify-content: flex-start;
      align-items: flex-start;
      height: 100vh;
      color: #333;
      overflow: hidden;
      animation: bgMotion 25s ease-in-out infinite alternate;
    }

    @keyframes bgMotion {
      0% {
        background-position: center center;
        filter: brightness(100%) saturate(100%);
      }
      50% {
        background-position: center top;
        filter: brightness(110%) saturate(120%);
      }
      100% {
        background-position: center bottom;
        filter: brightness(100%) saturate(100%);
      }
    }

    .login-container {
      position: relative;
      top: 8%;
      left: 8%;
      backdrop-filter: blur(15px);
      background: rgba(255, 255, 255, 0.12);
      padding: 45px 35px;
      border-radius: 20px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
      width: 370px;
      text-align: center;
      color: white;
      border: 1px solid rgba(255, 255, 255, 0.2);
      transition: transform 0.4s ease, box-shadow 0.4s ease;
    }

    .login-container:hover {
      transform: translateY(-8px);
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5), 0 0 30px rgba(67, 206, 162, 0.5);
    }

    .login-container h2 {
      margin-bottom: 20px;
      color: #ffffff;
      text-shadow: 0 2px 10px rgba(0,0,0,0.3);
    }

    .login-container input {
      width: 100%;
      padding: 12px;
      margin: 10px 0;
      border: none;
      outline: none;
      border-radius: 8px;
      font-size: 1em;
      background: rgba(255, 255, 255, 0.85);
      color: #333;
      transition: background 0.3s, box-shadow 0.3s;
    }

    .login-container input:focus {
      background: white;
      box-shadow: 0 0 12px rgba(67, 206, 162, 0.9);
    }

    .login-container button {
      width: 100%;
      padding: 12px;
      margin-top: 15px;
      background: linear-gradient(135deg, #43cea2, #185a9d);
      border: none;
      color: white;
      font-size: 1em;
      font-weight: bold;
      border-radius: 25px;
      cursor: pointer;
      transition: transform 0.3s, box-shadow 0.3s;
    }

    .login-container button:hover {
      transform: scale(1.05);
      box-shadow: 0 5px 18px rgba(67, 206, 162, 0.6);
    }

    .login-container a {
      display: block;
      margin-top: 15px;
      text-decoration: none;
      font-size: 0.9em;
      color: #e0f7fa;
      transition: color 0.3s;
    }

    .login-container a:hover {
      color: #43cea2;
    }

    /* Responsive Enhancements */
    @media (max-width: 1200px) {
      body {
        justify-content: center;
        align-items: center;
        background-position: center center;
      }
      .login-container {
        top: 0;
        left: 0;
        width: 400px;
        padding: 40px 30px;
      }
    }

    @media (max-width: 768px) {
      body {
        justify-content: center;
        align-items: center;
        padding: 20px;
        background-size: cover;
        background-position: center;
      }
      .login-container {
        position: relative;
        top: 0;
        left: 0;
        width: 90%;
        max-width: 380px;
        padding: 35px 25px;
      }
      .login-container h2 {
        font-size: 1.6em;
      }
      .login-container input,
      .login-container button {
        font-size: 1em;
        padding: 10px;
      }
    }

    @media (max-width: 480px) {
      .login-container {
        width: 95%;
        padding: 30px 20px;
      }
      .login-container h2 {
        font-size: 1.4em;
      }
      .login-container input,
      .login-container button {
        font-size: 0.95em;
      }
      .login-container a {
        font-size: 0.85em;
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <h2>Login to SkillSpace</h2>
    <form class="login-form" id="loginForm" method="post" action="index.php" novalidate>
      <input type="email" id="email" name="email" placeholder="Email" required autocomplete="email">
      <input type="password" id="password" name="password" placeholder="Password" required autocomplete="current-password">
      <button type="submit">Login</button>
    </form>
    <a href="forgot-password.html">Forgot Password?</a>
    <a href="reg.php">Don't have an account? Register</a>
  </div>
</body>
</html>
