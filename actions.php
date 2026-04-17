<?php
require_once 'config.php';
require_once 'functions.php';

$categories = ['Elektronik','Dompet / Tas','Kunci','Dokumen','Pakaian','Perhiasan','Lainnya'];
$errors = [];
$page = $_GET['page'] ?? 'list';

// ─── LOGIN & LOGOUT ──────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    try {
        $stmt = getDB()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        if ($row && password_verify($password, $row['password'])) {
            $u = ['id'=>$row['id'],'username'=>$row['username'],'role'=>$row['role'],'name'=>$row['name'],'avatar'=>$row['avatar']];
            $_SESSION['user'] = $u;
            header('Location: index.php?page='.($u['role']==='admin'?'dashboard':'list').'&toast=login_ok'); exit;
        } else {
            $errors['login'] = 'Username atau password salah';
        }
    } catch (PDOException $e) {
        $errors['login'] = 'Koneksi database gagal: '.$e->getMessage();
    }
}

if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }

// ─── AI CHAT API ─────────────────────────────────────────────
if (isset($_GET['ai_chat'])) {
    header('Content-Type: application/json');
    $u = getUser();
    if (!$u) { echo json_encode(['error' => 'unauthenticated']); exit; }
    $message = trim($_POST['message'] ?? '');
    if (empty($message)) { echo json_encode(['error' => 'empty']); exit; }
    echo json_encode(['reply' => callAI($message)]);
    exit;
}

// ─── TAMBAH LAPORAN ─────────────────────────────────────────
if (isset($_POST['action']) && in_array($_POST['action'], ['report_lost','report_found'])) {
    $u = getUser(); if (!$u) { header('Location: index.php'); exit; }
    $type        = $_POST['action']==='report_lost' ? 'lost' : 'found';
    $title       = trim($_POST['title']       ?? '');
    $location    = trim($_POST['location']    ?? '');
    $date        = trim($_POST['date']        ?? '');
    $description = trim($_POST['description'] ?? '');
    $category    = trim($_POST['category']    ?? '');
    $photo       = null;
    
    if (!$title || strlen($title)<3) $errors['title'] = 'Judul wajib diisi (min 3 karakter)';
    if (!$location || strlen($location)<3) $errors['location'] = 'Lokasi wajib diisi (min 3 karakter)';
    if (!$date) $errors['date'] = 'Tanggal wajib diisi'; elseif (strtotime($date) > time()) $errors['date'] = 'Tidak boleh masa depan';
    if (!$description || strlen($description)<10) $errors['description'] = 'Deskripsi wajib diisi (min 10 karakter)';
    
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['photo']['size'] > 5*1024*1024) $errors['photo'] = 'Foto max 5MB';
        else {
            $imgData = base64_encode(file_get_contents($_FILES['photo']['tmp_name']));
            $mime    = mime_content_type($_FILES['photo']['tmp_name']);
            $photo   = "data:{$mime};base64,{$imgData}";
        }
    }
    
    if (empty($errors)) {
        $stmt = getDB()->prepare('INSERT INTO reports (type,title,category,location,date,description,photo,status,reporter,user_id) VALUES (?,?,?,?,?,?,?,\'open\',?,?)');
        $stmt->execute([$type,$title,$category,$location,$date,$description,$photo,$u['name'],$u['id']]);
        header('Location: index.php?page=history&toast=report_ok'); exit;
    }
}

// ─── HAPUS LAPORAN & UBAH STATUS ─────────────────────────────
if (isset($_POST['action']) && in_array($_POST['action'], ['delete_report', 'change_status'])) {
    $u = getUser();
    if ($u) {
        $rid = (int)$_POST['report_id'];
        if ($_POST['action'] === 'delete_report') {
            $sql = $u['role'] === 'admin' ? 'DELETE FROM reports WHERE id=?' : 'DELETE FROM reports WHERE id=? AND user_id=?';
            $params = $u['role'] === 'admin' ? [$rid] : [$rid, $u['id']];
            getDB()->prepare($sql)->execute($params);
            header('Location: index.php?page='.$page.'&toast=delete_ok'); exit;
        } else {
            $ns = $_POST['new_status'];
            $sql = $u['role'] === 'admin' ? 'UPDATE reports SET status=? WHERE id=?' : 'UPDATE reports SET status=? WHERE id=? AND user_id=?';
            $params = $u['role'] === 'admin' ? [$ns, $rid] : [$ns, $rid, $u['id']];
            getDB()->prepare($sql)->execute($params);
            header('Location: index.php?page='.$page.'&toast=status_ok'); exit;
        }
    }
}

// ─── CHAT APP API ────────────────────────────────────────────
if (isset($_GET['chat_api'])) {
    header('Content-Type: application/json');
    $u = getUser();
    if (!$u) { echo json_encode(['error'=>'unauthenticated']); exit; }
    $db = getDB();
    $action = $_GET['chat_api'];

    if ($action === 'send' && isset($_POST['msg'])) {
        $msg = trim($_POST['msg']);
        if (strlen($msg) === 0 || strlen($msg) > 2000) { echo json_encode(['error'=>'invalid']); exit; }
        if ($u['role'] === 'admin') {
            $roomUserId = (int)($_POST['room_user_id'] ?? 0);
            if (!$roomUserId) { echo json_encode(['error'=>'no room']); exit; }
            $db->prepare('INSERT INTO chat_messages (sender_id,receiver_id,room_user_id,message) VALUES (?,?,?,?)')->execute([$u['id'], $roomUserId, $roomUserId, $msg]);
        } else {
            $admin = $db->query('SELECT id FROM users WHERE role="admin" LIMIT 1')->fetch();
            if (!$admin) { echo json_encode(['error'=>'no admin']); exit; }
            $db->prepare('INSERT INTO chat_messages (sender_id,receiver_id,room_user_id,message) VALUES (?,?,?,?)')->execute([$u['id'], $admin['id'], $u['id'], $msg]);

            if (in_array(CHAT_REPLY_MODE, ['ai_auto', 'hybrid'])) {
                $histStmt = $db->prepare('SELECT m.message, u.role sender_role FROM chat_messages m JOIN users u ON u.id = m.sender_id WHERE m.room_user_id = ? ORDER BY m.created_at DESC LIMIT 10');
                $histStmt->execute([$u['id']]);
                $history = array_reverse($histStmt->fetchAll());
                $conversation = [];
                foreach ($history as $h) {
                    $role = ($h['sender_role'] === 'user') ? 'user' : 'assistant';
                    $conversation[] = ['role' => $role, 'content' => $h['message']];
                }
                $aiReply = callAIChat($conversation, $u['name']);
                $db->prepare('INSERT INTO chat_messages (sender_id,receiver_id,room_user_id,message,is_read) VALUES (?,?,?,?,1)')->execute([$admin['id'], $u['id'], $u['id'], $aiReply]);
            }
        }
        echo json_encode(['ok'=>true, 'id'=>$db->lastInsertId()]); exit;
    }

    if ($action === 'fetch') {
        $since = (int)($_GET['since'] ?? 0);
        $roomUserId = $u['role'] === 'admin' ? (int)($_GET['room_user_id'] ?? 0) : $u['id'];
        if (!$roomUserId && $u['role'] === 'admin') { echo json_encode(['msgs'=>[], 'unread'=>[]]); exit; }
        
        $db->prepare('UPDATE chat_messages SET is_read=1 WHERE room_user_id=? AND receiver_id=? AND is_read=0')->execute([$roomUserId, $u['id']]);
        $stmt = $db->prepare('SELECT m.*,u.name sender_name,u.avatar sender_avatar,u.role sender_role FROM chat_messages m JOIN users u ON u.id=m.sender_id WHERE m.room_user_id=? AND m.id>? ORDER BY m.created_at ASC');
        $stmt->execute([$roomUserId, $since]);
        $msgs = $stmt->fetchAll();

        if ($u['role'] === 'admin') {
            $rows = $db->prepare('SELECT room_user_id, COUNT(*) cnt FROM chat_messages WHERE receiver_id=? AND is_read=0 GROUP BY room_user_id');
            $rows->execute([$u['id']]);
            $unread = []; foreach ($rows->fetchAll() as $r) $unread[$r['room_user_id']] = (int)$r['cnt'];
        } else {
            $row = $db->prepare('SELECT COUNT(*) cnt FROM chat_messages WHERE room_user_id=? AND receiver_id=? AND is_read=0');
            $row->execute([$u['id'], $u['id']]);
            $unread = (int)$row->fetch()['cnt'];
        }
        echo json_encode(['msgs'=>$msgs, 'unread'=>$unread]); exit;
    }

    if ($action === 'rooms' && $u['role'] === 'admin') {
        $stmt = $db->query('SELECT u.id, u.name, u.avatar, MAX(m.created_at) last_msg_at, LEFT(( SELECT message FROM chat_messages WHERE room_user_id=u.id ORDER BY created_at DESC LIMIT 1 ),60) last_msg, (SELECT COUNT(*) FROM chat_messages WHERE room_user_id=u.id AND receiver_id='.$u['id'].' AND is_read=0) unread FROM users u JOIN chat_messages m ON m.room_user_id = u.id WHERE u.role = "user" GROUP BY u.id ORDER BY last_msg_at DESC');
        echo json_encode(['rooms' => $stmt->fetchAll()]); exit;
    }
}