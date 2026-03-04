<?php
session_start();

// Restore session from cookies if missing (keeps compatibility with setups that set cookies)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['user_id'])) {
    $_SESSION['user_id']   = $_COOKIE['user_id'];
    if (isset($_COOKIE['user_name'])) $_SESSION['user_name'] = $_COOKIE['user_name'];
    if (isset($_COOKIE['role'])) $_SESSION['role'] = $_COOKIE['role'];
}

if (!isset($_SESSION['user_id'])) {
    echo "Invalid access - missing user ID.";
    exit();
}
$current_user = intval($_SESSION['user_id']);

$servername = "sql105.infinityfree.com";
$username = "if0_39907321";
$password = "SkillSpace4";
$database = "if0_39907321_student";

$con = new mysqli($servername, $username, $password, $database);
if ($con->connect_error) die("DB connection failed: " . $con->connect_error);

// ----------------- helpers -----------------
function linkify_meeting_html($text) {
    $text = htmlspecialchars($text);
    $text = nl2br($text);
    $text = preg_replace('#(https?://[^\s<]+)#i', '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>', $text);
    $text = preg_replace_callback('#<a href="(https?://[^\s<]+)"[^>]*>[^<]*</a>#i', function($m){
        $u = $m[1];
        if (preg_match('#(meet\.google\.com|zoom\.us|teams\.microsoft\.com|webex\.com)#i', $u)) {
            return '<a href="'. $u .'" target="_blank" rel="noopener noreferrer" class="meeting-link">Join meeting</a>';
        }
        return '<a href="'. $u .'" target="_blank" rel="noopener noreferrer">'. $u .'</a>';
    }, $text);
    return $text;
}

/**
 * Given the database connection and a mentorship_id, return a canonical mentorship id for that pair.
 * If no canonical found, returns the original mentorship_id.
 *
 * This finds a mentorship row that has the same mentor and mentee in either order and returns its id.
 */
function get_canonical_mentorship_id($con, $mentorship_id) {
    // get mentor/mentee for the passed id
    $stmt = $con->prepare("SELECT mentor_id, mentee_id FROM mentorships WHERE id = ? LIMIT 1");
    if (!$stmt) return $mentorship_id;
    $stmt->bind_param("i", $mentorship_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return $mentorship_id;

    $a = intval($row['mentor_id']);
    $b = intval($row['mentee_id']);

    // find a mentorship row for the same pair (either ordering) - prefer lowest id
    $stmt2 = $con->prepare("SELECT id FROM mentorships WHERE (mentor_id = ? AND mentee_id = ?) OR (mentor_id = ? AND mentee_id = ?) ORDER BY id ASC LIMIT 1");
    if (!$stmt2) return $mentorship_id;
    $stmt2->bind_param("iiii", $a, $b, $b, $a);
    $stmt2->execute();
    $res = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();
    if ($res && intval($res['id']) > 0) {
        return intval($res['id']);
    }
    return $mentorship_id;
}

// ----------------- ensure uploads dir -----------------
$uploads_dir = __DIR__ . '/Uploads/';
if (!is_dir($uploads_dir)) @mkdir($uploads_dir, 0777, true);

// ----------------- action route -----------------
$action = $_REQUEST['action'] ?? null;

// ----------------- SEND message (text or file) -----------------
if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $mentorship_id = intval($_POST['mentorship_id'] ?? 0);
    $reply_to = intval($_POST['reply_to'] ?? 0);
    $type = 'text';
    $message_value = trim($_POST['message'] ?? '');

    // Normalize mentorship id to canonical so messages go to shared thread
    if ($mentorship_id) {
        $canon = get_canonical_mentorship_id($con, $mentorship_id);
        if ($canon) $mentorship_id = $canon;
    }

    if (!empty($_FILES['file']['name'])) {
        $orig = basename($_FILES['file']['name']);
        $safe = time() . '_' . preg_replace('/[^A-Za-z0-9_\.\-]/', '_', $orig);
        $target = $uploads_dir . $safe;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
            $webpath = 'Uploads/' . $safe;
            $ext = strtolower(pathinfo($safe, PATHINFO_EXTENSION));
            $type = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'image' : 'file';
            $message_value = $webpath;
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Upload failed']);
            exit;
        }
    }

    if ($mentorship_id && $message_value !== '') {
        $stmt = $con->prepare("INSERT INTO chats (mentorship_id, sender_id, message, type, status, reply_to) VALUES (?, ?, ?, ?, 'sent', ?)");
        $stmt->bind_param("iissi", $mentorship_id, $current_user, $message_value, $type, $reply_to);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    }
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

// ----------------- DELETE message -----------------
// mode = 'me' (delete for me) OR 'everyone' (delete for everyone)
if ($action === 'delete' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $mode = $_POST['mode'] ?? 'me';

    // fetch message sender
    $stmt = $con->prepare("SELECT sender_id, deleted_by FROM chats WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if (!$res) {
        echo json_encode(['error' => 'Message not found']);
        exit;
    }

    $sender_id = intval($res['sender_id']);
    $deleted_by = $res['deleted_by'] ?? 'none';

    if ($mode === 'everyone') {
        // Replace text with standard notice (keeps row)
        $con->query("UPDATE chats SET message='This message was deleted', type='text' WHERE id=$id");
        echo json_encode(['ok' => true]);
        exit;
    } else {
        // Delete for me: mark deleted_by accordingly (we use your deleted_by enum values none,sender,both)
        // If current user is sender -> set deleted_by='sender'. If other user -> set 'both' if sender already 'sender'
        if ($current_user === $sender_id) {
            // mark as deleted by sender
            $con->query("UPDATE chats SET deleted_by='sender' WHERE id=$id");
        } else {
            // current user is receiver; if sender already 'sender' then set 'both' else set 'both' to hide
            if ($deleted_by === 'sender') {
                $con->query("UPDATE chats SET deleted_by='both' WHERE id=$id");
            } else {
                $con->query("UPDATE chats SET deleted_by='both' WHERE id=$id");
            }
        }
        echo json_encode(['ok' => true]);
        exit;
    }
}

// ----------------- PIN message -----------------
if ($action === 'pin' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $con->query("UPDATE chats SET is_pinned = IF(is_pinned=1,0,1) WHERE id=$id");
    echo json_encode(['ok'=>true]);
    exit;
}

// ----------------- SUBMIT FEEDBACK -----------------
if ($action === 'submit_feedback' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accept both JSON body and form-data just in case front-end varies
    if (empty($_POST) && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $_POST = array_merge($_POST, $json);
        }
    }

    $mentorship_id = intval($_POST['mentorship_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    $session_id = intval($_POST['session_id'] ?? 0);

    // Allow empty comments? Your UI requires non-empty; keep that validation
    if (!$mentorship_id || $rating < 1 || $rating > 5 || $comment === '' || $comment === '0') {
        echo json_encode(['error' => 'Invalid input: Rating (1-5) and non-empty comment are required']);
        exit;
    }

    // try to get skill_id from mentorships table (assumes column skill_id exists)
    $skill_id = null;
    $q = $con->prepare("SELECT skill_id FROM mentorships WHERE id=? LIMIT 1");
    $q->bind_param("i", $mentorship_id);
    $q->execute();
    $qr = $q->get_result()->fetch_assoc();
    if ($qr) $skill_id = intval($qr['skill_id']);

    // Check if feedback exists for this user and session, update if it does, insert if it doesn't
    $check_stmt = $con->prepare("SELECT id FROM feedback WHERE user_id = ? AND session_id = ? LIMIT 1");
    $check_stmt->bind_param("ii", $current_user, $session_id);
    $check_stmt->execute();
    $check_res = $check_stmt->get_result();

    if ($check_res->num_rows > 0) {
        // Update existing feedback
        $feedback_id = intval($check_res->fetch_assoc()['id']);
        // CORRECT bind types: skill_id (int), rating (int), comment (string), id (int) => "iisi"
        $stmt = $con->prepare("UPDATE feedback SET skill_id = ?, rating = ?, comment = ?, created_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("iisi", $skill_id, $rating, $comment, $feedback_id);
    } else {
        // Insert new feedback
        $stmt = $con->prepare("INSERT INTO feedback (skill_id, user_id, rating, comment, created_at, session_id) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, ?)");
        $sid = $skill_id ? $skill_id : 0;
        $stmt->bind_param("iiisi", $sid, $current_user, $rating, $comment, $session_id);
    }

    if ($stmt->execute()) {
        // Return the saved/updated row so client can update UI immediately if needed
        if (isset($feedback_id) && $feedback_id) {
            $id_to_fetch = $feedback_id;
        } else {
            $id_to_fetch = $con->insert_id;
        }

        $fetch = $con->prepare("SELECT f.id, f.skill_id, f.user_id, f.rating, f.comment, f.created_at, f.session_id, u.fullname AS giver_name FROM feedback f LEFT JOIN users u ON f.user_id = u.id WHERE f.id = ? LIMIT 1");
        $fetch->bind_param("i", $id_to_fetch);
        $fetch->execute();
        $saved = $fetch->get_result()->fetch_assoc();
        $fetch->close();

        echo json_encode(['ok'=>true, 'feedback' => $saved]);
    } else {
        echo json_encode(['error'=>'Could not save feedback']);
    }
    exit;
}

// ----------------- FETCH messages -----------------
if ($action === 'fetch_messages' && isset($_GET['mentorship_id'])) {
    $mentorship_id = intval($_GET['mentorship_id']);

    // Normalize mentorship id to canonical so receiver fetches the same thread
    if ($mentorship_id) {
        $canon = get_canonical_mentorship_id($con, $mentorship_id);
        if ($canon) $mentorship_id = $canon;
    }

    // Mark other user's messages delivered
    $upd = $con->prepare("UPDATE chats SET status='delivered' WHERE mentorship_id=? AND sender_id!=? AND (status IS NULL OR status!='delivered')");
    $upd->bind_param("ii", $mentorship_id, $current_user);
    @$upd->execute();

    $q = $con->prepare("SELECT c.*, u.fullname AS sender_name, u.avatar AS sender_avatar 
                        FROM chats c JOIN users u ON c.sender_id=u.id 
                        WHERE c.mentorship_id=? ORDER BY c.is_pinned DESC, c.created_at ASC");
    $q->bind_param("i", $mentorship_id);
    $q->execute();
    $res = $q->get_result();
    $out = [];
    while ($m = $res->fetch_assoc()) {
        // Hide messages that were marked deleted for this user (deleted_by)
        $hide_for_current = false;
        if (isset($m['deleted_by'])) {
            if ($m['deleted_by'] === 'sender' && $m['sender_id'] == $current_user) $hide_for_current = true;
            if ($m['deleted_by'] === 'both') $hide_for_current = true;
        }
        if ($hide_for_current) continue;

        $display_html = "";
        if ($m['type'] === 'image') {
            $display_html = '<img src="'.htmlspecialchars($m['message']).'" class="img-thumb">';
        } elseif ($m['type'] === 'file') {
            $display_html = '<a href="'.htmlspecialchars($m['message']).'" target="_blank" class="file-link">📄 '.htmlspecialchars(basename($m['message'])).'</a>';
        } else {
            $display_html = linkify_meeting_html($m['message']);
        }
        $ticks = '';
        if ($m['sender_id'] == $current_user) {
            $ticks = ($m['status'] === 'delivered') ? '✔✔' : '✔';
        }
        $out[] = [
            'id' => intval($m['id']),
            'sender_id' => intval($m['sender_id']),
            'sender_name' => $m['sender_name'],
            'sender_avatar' => $m['sender_avatar'],
            'html' => $display_html,
            'created_at' => $m['created_at'],
            'ticks' => $ticks,
            'is_pinned' => $m['is_pinned'] ?? 0,
            'message' => $m['message'],
            'reply_to' => $m['reply_to'] ?? null
        ];
    }
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

// ----------------- FETCH latest feedback (for external pages) -----------------
if ($action === 'fetch_latest_feedback' && isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    $stmt = $con->prepare("SELECT skill_id, rating, comment, created_at FROM feedback WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    header('Content-Type: application/json');
    echo json_encode($res ? $res : []);
    exit;
}

// ----------------- FETCH latest feedback by skill (for skillsync.php) -----------------
if ($action === 'fetch_latest_feedback_by_skill' && isset($_GET['skill_id'])) {
    $skill_id = intval($_GET['skill_id']);
    $stmt = $con->prepare("SELECT f.rating, f.comment, f.created_at, u.fullname AS giver_name, 
                           (SELECT AVG(rating) FROM feedback WHERE skill_id = ?) AS avg_rating 
                           FROM feedback f 
                           LEFT JOIN users u ON f.user_id = u.id 
                           WHERE f.skill_id = ? 
                           ORDER BY f.created_at DESC LIMIT 1");
    $stmt->bind_param("ii", $skill_id, $skill_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    header('Content-Type: application/json');
    echo json_encode($res ? $res : []);
    exit;
}

// ----------------- FETCH feedback status (for debugging feedback issues) -----------------
if ($action === 'fetch_feedback_status' && isset($_GET['mentorship_id'])) {
    $mentorship_id = intval($_GET['mentorship_id']);
    $stmt = $con->prepare("SELECT f.id, f.rating, f.comment, f.created_at, u.fullname AS giver_name 
                           FROM feedback f 
                           LEFT JOIN users u ON f.user_id = u.id 
                           WHERE f.session_id = ? AND f.user_id = ? 
                           ORDER BY f.created_at DESC LIMIT 1");
    $stmt->bind_param("ii", $mentorship_id, $current_user);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    header('Content-Type: application/json');
    echo json_encode($res ? ['exists' => true, 'rating' => $res['rating'], 'comment' => $res['comment'], 'created_at' => $res['created_at'], 'giver_name' => $res['giver_name']] : ['exists' => false]);
    exit;
}

// ----------------- Typing indicator -----------------
if ($action === 'typing' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $mentorship_id = intval($_POST['mentorship_id'] ?? 0);

    // Normalize mentorship id
    if ($mentorship_id) {
        $canon = get_canonical_mentorship_id($con, $mentorship_id);
        if ($canon) $mentorship_id = $canon;
    }

    $typing = intval($_POST['typing'] ?? 0);
    $file = __DIR__ . "/typing_{$mentorship_id}.txt";
    if ($typing) file_put_contents($file, $current_user);
    else @unlink($file);
    echo json_encode(['ok'=>true]);
    exit;
}
if ($action === 'typing_status' && isset($_GET['mentorship_id'])) {
    $mentorship_id = intval($_GET['mentorship_id']);

    // Normalize mentorship id
    if ($mentorship_id) {
        $canon = get_canonical_mentorship_id($con, $mentorship_id);
        if ($canon) $mentorship_id = $canon;
    }

    $file = __DIR__ . "/typing_{$mentorship_id}.txt";
    $val = @file_get_contents($file);
    header('Content-Type: application/json');
    echo json_encode(['typing'=>($val && intval($val) !== $current_user)]);
    exit;
}

// ----------------- Heartbeat system -----------------
if ($action === 'heartbeat' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents(__DIR__ . "/online_{$current_user}.txt", time());
    echo json_encode(['ok'=>true]);
    exit;
}
if ($action === 'heartbeat_probe' && isset($_GET['user_id'])) {
    $uid = intval($_GET['user_id']);
    $file = __DIR__ . "/online_{$uid}.txt";
    $online = false;
    if (file_exists($file)) {
        $ts = intval(file_get_contents($file));
        $online = (time() - $ts) <= 30;
    }
    echo json_encode(['online'=>$online]);
    exit;
}

// ----------------- MAIN PAGE (render UI) -----------------
if (!isset($_GET['mentorship_id'])) {
    echo "Invalid access - missing mentorship ID.";
    exit();
}
$mentorship_id = intval($_GET['mentorship_id']);

// Normalize mentorship id to canonical for UI - if different, redirect to canonical ID
$canonical = get_canonical_mentorship_id($con, $mentorship_id);
if ($canonical && $canonical !== $mentorship_id) {
    // Redirect to canonical mentorship_id so both users load the same thread
    $redirect = htmlspecialchars("connect.php?mentorship_id=" . $canonical);
    header("Location: $redirect");
    exit();
}
$mentorship_id = $canonical;

$check = $con->prepare("SELECT mentor_id, mentee_id FROM mentorships WHERE id=? LIMIT 1");
$check->bind_param("i", $mentorship_id);
$check->execute();
$cr = $check->get_result();
if ($cr->num_rows === 0) exit("Mentorship not found.");

$mi = $cr->fetch_assoc();
$mentor_id = intval($mi['mentor_id']);
$mentee_id = intval($mi['mentee_id']);
if ($current_user !== $mentor_id && $current_user !== $mentee_id) exit("Access denied.");

$other_user_id = ($current_user === $mentor_id) ? $mentee_id : $mentor_id;
$uq = $con->prepare("SELECT fullname, avatar FROM users WHERE id=? LIMIT 1");
$uq->bind_param("i", $other_user_id);
$uq->execute();
$ou = $uq->get_result()->fetch_assoc();
$other_name = $ou['fullname'] ?? 'User';
$other_avatar = $ou['avatar'] ?? '';

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SkillSpace Chat</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg1:#c3ecff;
  --bg2:#b993d6;
  --card:#ffffff;
  --accent1:#5b8def;
  --accent2:#7b4be6;
  --muted:#67707d;
}
*{box-sizing:border-box}
html,body{height:100%;margin:0;font-family:"Poppins",sans-serif;background:linear-gradient(135deg,var(--bg1),var(--bg2));}
.app{max-width:1200px;margin:18px auto;height:calc(100vh - 36px);display:grid;grid-template-columns:300px 1fr;gap:16px;padding:14px}
.sidebar{background:var(--card);border-radius:14px;padding:18px;box-shadow:0 8px 24px rgba(16,24,40,0.12);display:flex;flex-direction:column;align-items:center;justify-content:space-between}
.avatar{width:80px;height:80px;border-radius:50%;object-fit:cover;box-shadow:0 6px 18px rgba(34,43,69,0.08)}
.name{font-weight:700;font-size:18px}
.status{font-size:13px;color:var(--muted)}
.skill-img {
  width: 300px;
  height: 550px;
  object-fit: cover;
  object-position: center;
  border-radius: 10px;
  display: block;
  margin-top: 10px;
    margin-bottom : 0px;
}
.chat-panel{background:var(--card);border-radius:14px;display:flex;flex-direction:column;box-shadow:0 8px 24px rgba(16,24,40,0.12);overflow:hidden}
.header{display:flex;gap:14px;align-items:center;padding:16px 20px;border-bottom:1px solid #f1f5f9;background:linear-gradient(90deg,var(--accent1),var(--accent2));color:white}
.header .avatar{width:64px;height:64px;border:3px solid rgba(255,255,255,0.2)}
.header .meta{display:flex;flex-direction:column}
.header .meta .who{font-weight:700;font-size:18px}
.header .meta .small{opacity:0.95;font-size:13px}
.header .actions{margin-left:auto;display:flex;gap:8px;align-items:center}
.messages{flex:1;overflow:auto;padding:16px;display:flex;flex-direction:column;gap:12px;background:linear-gradient(180deg,#f8fbff,#f3f6ff);position:relative}
.msg{max-width:66%;padding:12px 14px;border-radius:12px;word-break:break-word;position:relative}
.msg.me{align-self:flex-end;background:linear-gradient(180deg,#e6ffdd,#c9f6bd);color:#062017}
.msg.other{align-self:flex-start;background:white;color:#0b1220}
.msg .meta{display:block;font-size:12px;color:var(--muted);margin-top:6px}
.toolsBtn{position:absolute;top:6px;right:4px;display:none;background:transparent;border:0;cursor:pointer;font-size:16px;color:#333;padding:2px 6px;border-radius:4px}
.msg:hover .toolsBtn{display:block}
.toolsBtn:hover{background:#eee}
.menu{position:absolute;background:#fff;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,0.12);padding:6px;border:1px solid #eee;z-index:999;display:none;min-width:160px}
.menu button{display:block;width:100%;padding:8px;border:0;background:transparent;text-align:left;cursor:pointer;border-radius:6px}
.menu button:hover{background:#f0f0f0}
.img-thumb{max-width:280px;border-radius:10px;display:block}
.file-link{background:#fff3f8;padding:6px 10px;border-radius:8px;color:#7b4be6;text-decoration:none;display:inline-block}
.meeting-link{background:#e8f0ff;padding:6px 10px;border-radius:8px;color:#2357ff;text-decoration:none;display:inline-block;font-weight:600}
.controls{display:flex;gap:10px;align-items:center;padding:12px 16px;border-top:1px solid #eef3ff;background:transparent}
.input{flex:1;display:flex;gap:10px;align-items:center;background:white;padding:8px;border-radius:28px;border:1px solid #e6eefb}
.input input{flex:1;border:0;outline:none;padding:8px 10px;border-radius:20px;font-size:15px}
.preview{display:flex;align-items:center;gap:8px;padding:6px 10px;border-radius:8px;background:#fff;border:1px solid #eee;margin-right:10px}
.preview img{max-height:60px;border-radius:8px}
.btn{background:linear-gradient(90deg,var(--accent1),var(--accent2));color:white;border:0;padding:10px 14px;border-radius:12px;cursor:pointer;display:inline-block}
.typing{font-style:italic;color:var(--muted);padding-left:16px}
.reply-preview{padding:8px 10px;background:#f9fbff;border-left:4px solid var(--accent2);margin:10px 0;border-radius:6px}
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,0.4);display:none;align-items:center;justify-content:center;z-index:1000}
.modal{background:#fff;padding:18px;border-radius:10px;min-width:320px;max-width:540px;box-shadow:0 8px 32px rgba(0,0,0,0.18)}
.stars{display:flex;gap:6px;margin:8px 0}
.star{font-size:22px;cursor:pointer;opacity:0.5}
.star.active{color:#ffb400;opacity:1}
@media(max-width:900px){.app{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="app">
  <div class="sidebar">
    <div style="text-align:center;padding:20px;">
      <?php if ($other_avatar): ?>
        <img src="<?=htmlspecialchars($other_avatar)?>" class="avatar">
      <?php else: ?>
        <div class="avatar" style="display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--accent1),var(--accent2));color:white;font-weight:700;">
          <?=strtoupper(substr($other_name,0,1))?>
        </div>
      <?php endif; ?>
      <div class="name"><?=htmlspecialchars($other_name)?></div>
      <div id="onlineStatus" class="status">Checking status...</div>
      <img src="skillspace4.png" alt="Skill exchange" class="skill-img">
    </div>
  </div>

  <div class="chat-panel">
    <div class="header">
      <img src="<?=htmlspecialchars($other_avatar)?>" class="avatar">
      <div class="meta">
        <div class="who"><?=htmlspecialchars($other_name)?></div>
        <div class="small">Mentorship chat</div>
      </div>
      <div class="header actions">
        <button id="giveFeedbackBtn" class="btn">Give Feedback</button>
      </div>
    </div>

    <div id="messages" class="messages"></div>

    <div id="replyPreview" class="reply-preview" style="display:none"></div>

    <div class="controls">
      <div class="input">
        <label for="file" style="cursor:pointer;padding:6px">📎</label>
        <input type="file" id="file" style="display:none">
        <div id="filePreview" class="preview" style="display:none"></div>
        <input type="text" id="message" placeholder="Type message..." autocomplete="off">
      </div>
      <button id="sendBtn" class="btn">Send</button>
    </div>
  </div>
</div>

<!-- menu element reused -->
<div id="contextMenu" class="menu"></div>

<!-- Feedback modal -->
<div id="fbBackdrop" class="modal-backdrop">
  <div class="modal">
    <h3>Give feedback</h3>
    <div>
      <div class="stars" id="stars">
        <span class="star" data-value="1">&#9733;</span>
        <span class="star" data-value="2">&#9733;</span>
        <span class="star" data-value="3">&#9733;</span>
        <span class="star" data-value="4">&#9733;</span>
        <span class="star" data-value="5">&#9733;</span>
      </div>
      <textarea id="fbComment" rows="4" style="width:100%;border:1px solid #eee;padding:8px;border-radius:6px" placeholder="Write your review..."></textarea>
      <div style="text-align:right;margin-top:10px">
        <button id="fbCancel" class="btn" style="background:#ddd;color:#222;margin-right:8px">Cancel</button>
        <button id="fbSubmit" class="btn">Submit</button>
      </div>
    </div>
  </div>
</div>

<script>
const currentUser = <?= $current_user ?>;
const mentorshipId = <?= $mentorship_id ?>;
const otherUserId = <?= $other_user_id ?>;
const messagesEl = document.getElementById('messages');
const messageInput = document.getElementById('message');
const fileInput = document.getElementById('file');
const filePreview = document.getElementById('filePreview');
const replyPreview = document.getElementById('replyPreview');
const contextMenu = document.getElementById('contextMenu');
let replyTo = 0;
let longPressTimer = null;
let menuOpenForId = 0;

// show file preview before sending
fileInput.addEventListener('change', () => {
  filePreview.style.display = 'none';
  filePreview.innerHTML = '';
  const f = fileInput.files[0];
  if (!f) return;
  if (f.type && f.type.startsWith('image/')) {
    const img = document.createElement('img');
    img.src = URL.createObjectURL(f);
    img.style.maxHeight = '60px';
    filePreview.appendChild(img);
  } else {
    filePreview.textContent = f.name;
  }
  filePreview.style.display = 'flex';
});

// open context menu for a message
function openMenuFor(event, msgId, msgText, senderId) {
  event.stopPropagation();
  menuOpenForId = msgId;
  contextMenu.innerHTML = '';
  const optReply = document.createElement('button');
  optReply.textContent = '↩️ Reply';
  optReply.onclick = (e) => { e.stopPropagation(); startReply(msgId, msgText); closeMenu(); };

  const optDeleteMe = document.createElement('button');
  optDeleteMe.textContent = '🗑️ Delete for me';
  optDeleteMe.onclick = (e) => { e.stopPropagation(); deleteMsg(msgId, 'me'); closeMenu(); };

  const optDeleteEveryone = document.createElement('button');
  optDeleteEveryone.textContent = '❌ Delete for everyone';
  optDeleteEveryone.onclick = (e) => { e.stopPropagation();
    if (confirm('Delete this message for everyone?')) { deleteMsg(msgId, 'everyone'); }
    closeMenu();
  };

  contextMenu.appendChild(optReply);
  contextMenu.appendChild(optDeleteMe);
  if (senderId == currentUser) contextMenu.appendChild(optDeleteEveryone);

  // position menu below the message within chat panel
  const btn = event.target;
  const rect = btn.getBoundingClientRect();
  const messagesRect = messagesEl.getBoundingClientRect();
  let top = rect.bottom - messagesRect.top + 4;
  let left = rect.left - messagesRect.left;
  if (top + contextMenu.offsetHeight > messagesRect.height) {
    top = rect.top - messagesRect.top - contextMenu.offsetHeight - 4;
  }
  if (left + contextMenu.offsetWidth > messagesRect.width) {
    left = rect.right - messagesRect.left - contextMenu.offsetWidth;
  }
  contextMenu.style.top = top + 'px';
  contextMenu.style.left = left + 'px';
  contextMenu.style.display = 'block';
}

// close menu
function closeMenu() {
  contextMenu.style.display = 'none';
  menuOpenForId = 0;
}

document.addEventListener('click', (e) => {
  if (!contextMenu.contains(e.target)) closeMenu();
});

// long press support for mobile (open menu)
function attachLongPress(el, id, msgText, senderId) {
  el.addEventListener('touchstart', function(e) {
    longPressTimer = setTimeout(() => {
      const toolsBtn = el.querySelector('.toolsBtn');
      if (toolsBtn) {
        const rect = toolsBtn.getBoundingClientRect();
        openMenuFor({ target: toolsBtn, clientX: rect.left, clientY: rect.bottom }, id, msgText, senderId);
      }
    }, 700);
  });
  el.addEventListener('touchend', function(e) {
    if (longPressTimer) clearTimeout(longPressTimer);
  });
  el.addEventListener('touchmove', function(e) {
    if (longPressTimer) clearTimeout(longPressTimer);
  });
}

// start reply action
function startReply(id, msgText) {
  replyTo = id;
  replyPreview.style.display = 'block';
  replyPreview.innerHTML = '<strong>Replying to:</strong> ' + (msgText ? msgText.substring(0,200) : '');
  messageInput.focus();
}

// fetch and render messages
async function loadMessages(){
  try {
    const res = await fetch(`connect.php?action=fetch_messages&mentorship_id=${mentorshipId}`);
    const data = await res.json();
    messagesEl.innerHTML = '';
    for (const m of data) {
      const d = document.createElement('div');
      d.className = 'msg ' + (m.sender_id === currentUser ? 'me' : 'other');
      let replyHtml = '';
      if (m.reply_to) {
        let replyMsgText = '';
        const found = data.find(x => x.id === m.reply_to);
        if (found) replyMsgText = (found.message || '').toString().substring(0,120);
        replyHtml = `<div class="reply-preview">Reply to: ${escapeHtml(replyMsgText)}</div><div class="content">${m.html}</div>`;
      } else {
        replyHtml = `<div class="content">${m.html}</div>`;
      }
      d.innerHTML = replyHtml + `<div class="meta">${escapeHtml(m.sender_name)} • ${new Date(m.created_at).toLocaleTimeString()} <span style="color:#34b7f1;margin-left:6px">${m.ticks}</span></div>`;
      const btn = document.createElement('button');
      btn.className = 'toolsBtn';
      btn.innerText = '⋮';
      btn.title = 'Actions';
      btn.onclick = (e) => openMenuFor(e, m.id, (m.message || ''), m.sender_id);
      d.appendChild(btn);
      attachLongPress(d, m.id, (m.message || ''), m.sender_id);
      messagesEl.appendChild(d);
    }
    messagesEl.scrollTop = messagesEl.scrollHeight;
  } catch (err) {
    console.error(err);
  }
}

// send
document.getElementById('sendBtn').addEventListener('click', async () => {
  const fd = new FormData();
  fd.append('action','send');
  fd.append('mentorship_id', mentorshipId);
  fd.append('reply_to', replyTo);
  if (fileInput.files[0]) {
    fd.append('file', fileInput.files[0]);
  } else {
    fd.append('message', messageInput.value.trim());
  }
  try {
    const res = await fetch('connect.php?action=send', { method:'POST', body: fd });
    const j = await res.json();
    if (j.success) {
      messageInput.value = '';
      fileInput.value = '';
      filePreview.style.display = 'none';
      filePreview.innerHTML = '';
      replyTo = 0;
      replyPreview.style.display = 'none';
      await loadMessages();
    } else {
      alert(j.error || 'Send failed');
    }
  } catch (e) {
    console.error(e);
    alert('Network error sending message');
  }
});

// delete
async function deleteMsg(id, mode='me') {
  const res = await fetch('connect.php?action=delete', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: `id=${encodeURIComponent(id)}&mode=${encodeURIComponent(mode)}`
  });
  const j = await res.json();
  if (j.ok) loadMessages();
  else alert(j.error || 'Delete failed');
}

// pin
async function pinMsg(id) {
  await fetch('connect.php?action=pin', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: `id=${encodeURIComponent(id)}`
  });
  loadMessages();
}

// small helper
function escapeHtml(s) {
  if (!s) return '';
  return String(s).replace(/[&<>"']/g, function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];});
}

// online status check
async function checkOnline(){
  try {
    const r = await fetch(`connect.php?action=heartbeat_probe&user_id=${otherUserId}`);
    const j = await r.json();
    document.getElementById('onlineStatus').textContent = j.online ? 'Online' : 'Offline';
  } catch(e){
    // quietly ignore
  }
}

// typing indicator notify
let typingTimer = null;
messageInput.addEventListener('input', ()=>{  
  fetch('connect.php?action=typing', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`mentorship_id=${mentorshipId}&typing=${messageInput.value.trim() ? 1 : 0}`});
  if (typingTimer) clearTimeout(typingTimer);
  typingTimer = setTimeout(()=> fetch('connect.php?action=typing', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`mentorship_id=${mentorshipId}&typing=0`}), 2500);
});

// feedback modal wiring
const fbBackdrop = document.getElementById('fbBackdrop');
const giveFeedbackBtn = document.getElementById('giveFeedbackBtn');
const stars = document.querySelectorAll('.star');
let selectedRating = 0;
giveFeedbackBtn.addEventListener('click', () => {
  fbBackdrop.style.display = 'flex';
});
document.getElementById('fbCancel').addEventListener('click', () => {
  fbBackdrop.style.display = 'none';
});
stars.forEach(s => {
  s.addEventListener('click', ()=> {
    selectedRating = parseInt(s.dataset.value);
    stars.forEach(x => x.classList.toggle('active', parseInt(x.dataset.value) <= selectedRating));
  });
});
document.getElementById('fbSubmit').addEventListener('click', async () => {
  const comment = document.getElementById('fbComment').value.trim();
  if (selectedRating < 1) { alert('Please choose star rating'); return; }
  if (!comment || comment === '0') { alert('Please provide a valid comment'); return; }
  const fd = new FormData();
  fd.append('action','submit_feedback');
  fd.append('mentorship_id', mentorshipId);
  fd.append('rating', selectedRating);
  fd.append('comment', comment);
  fd.append('session_id', mentorshipId);
  try {
    const res = await fetch('connect.php?action=submit_feedback', {method:'POST', body: fd});
    const j = await res.json();
    if (j.ok) {
      // show success and immediately refresh areas that may depend on feedback
      alert('Thank you for the feedback');
      fbBackdrop.style.display = 'none';
      selectedRating = 0;
      stars.forEach(x => x.classList.remove('active'));
      document.getElementById('fbComment').value = '';

      // Refresh messages and optionally request updated skill feedback from server
      await loadMessages();

      // If the calling page (skillsync/skilldetails) needs latest feedback it can call the endpoints:
      // connect.php?action=fetch_latest_feedback_by_skill&skill_id=...
      // Here we attempt to update the parent windows or refresh if those pages are open in same app (best-effort)
      try {
        // optional: attempt to notify other scripts by dispatching a custom event
        const ev = new CustomEvent('feedbackSaved', { detail: j.feedback || {} });
        window.dispatchEvent(ev);
      } catch(e){}
    } else {
      alert(j.error || 'Feedback not saved');
    }
  } catch (e) {
    console.error(e);
    alert('Network error while saving feedback');
  }
});

// polling
setInterval(loadMessages, 3000);
setInterval(checkOnline, 5000);
loadMessages();
checkOnline();

</script>
</body>
</html>
