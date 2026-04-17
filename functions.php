<?php
require_once 'config.php';

// ─── KONEKSI DATABASE ────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

// ─── AI CHAT FUNCTION ────────────────────────────────────────
function callAI($userMessage) {
    return callAIChat([['role'=>'user','content'=>$userMessage]]);
}

function callAIChat(array $conversation, string $userName = 'Pengguna') {
    $provider = AI_PROVIDER;
    $systemPrompt = "Kamu adalah asisten virtual ramah bernama \"Admin AI\" untuk sistem Lost & Found PT Sinergi Nusantara Integrasi. "
        . "Tugasmu membantu pengguna yang kehilangan atau menemukan barang. "
        . "Saat ini kamu sedang berbicara dengan {$userName}. "
        . "Jawab dalam bahasa Indonesia yang sopan, singkat, dan jelas.";

    if ($provider === 'openai') {
        $url = 'https://api.openai.com/v1/chat/completions';
        $headers = ['Authorization: Bearer ' . OPENAI_API_KEY, 'Content-Type: application/json'];
        $messages = array_merge([['role' => 'system', 'content' => $systemPrompt]], $conversation);
        $data = ['model' => 'gpt-4o-mini', 'messages' => $messages, 'max_tokens' => 500];
    } elseif ($provider === 'grok') {
        $url = 'https://api.x.ai/v1/chat/completions';
        $headers = ['Authorization: Bearer ' . GROK_API_KEY, 'Content-Type: application/json'];
        $messages = array_merge([['role' => 'system', 'content' => $systemPrompt]], $conversation);
        $data = ['model' => 'grok-beta', 'messages' => $messages, 'max_tokens' => 500];
    } else { 
        $url = OLLAMA_URL . '/api/chat';
        $headers = ['Content-Type: application/json'];
        $data = ['model' => 'llama3', 'messages' => array_merge([['role' => 'system', 'content' => $systemPrompt]], $conversation), 'stream' => false];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return "Maaf, saya sedang tidak dapat dihubungi. Silakan coba beberapa saat lagi.";
    $json = json_decode($result, true);
    return ($provider === 'openai' || $provider === 'grok') ? ($json['choices'][0]['message']['content'] ?? 'Maaf, tidak ada respons.') : ($json['message']['content'] ?? 'Maaf, tidak ada respons.');
}

// ─── HELPER FUNCTIONS ────────────────────────────────────────
function fmtDate($d){$m=['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'Mei','06'=>'Jun','07'=>'Jul','08'=>'Agu','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Des'];[$y,$mo,$dy]=explode('-',$d);return "$dy {$m[$mo]} $y";}
function timeAgo($ts){$diff=(time()-strtotime($ts))/60;if($diff<60)return (int)$diff.' mnt lalu';$h=(int)($diff/60);if($h<24)return $h.' jam lalu';return (int)($h/24).' hari lalu';}
function stLabel($s){return['open'=>'Aktif','matched'=>'Sudah Ditemukan','closed'=>'Selesai'][$s]??$s;}
function stClass($s){return['open'=>'st-open','matched'=>'st-matched','closed'=>'st-closed'][$s]??'';}
function tpLabel($t){return $t==='lost'?'Hilang':'Ditemukan';}
function tpClass($t){return $t==='lost'?'tp-lost':'tp-found';}
function h($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
function getUser(){return $_SESSION['user']??null;}

// ─── DATA FETCHING ───────────────────────────────────────────
function fetchReports(string $search, string $fType, string $fStatus, string $fFrom, string $fTo): array {
    $sql    = 'SELECT * FROM reports WHERE 1=1';
    $params = [];
    if ($fType   !== 'all') { $sql .= ' AND type=?';   $params[] = $fType; }
    if ($fStatus !== 'all') { $sql .= ' AND status=?'; $params[] = $fStatus; }
    if ($search) {
        $sql .= ' AND (title LIKE ? OR location LIKE ? OR category LIKE ? OR reporter LIKE ?)';
        $like = '%'.$search.'%';
        array_push($params, $like, $like, $like, $like);
    }
    if ($fFrom) { $sql .= ' AND date >= ?'; $params[] = $fFrom; }
    if ($fTo)   { $sql .= ' AND date <= ?'; $params[] = $fTo; }
    $sql .= ' ORDER BY created_at DESC';
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return array_map(function($r){ $r['userId'] = $r['user_id']; $r['createdAt'] = $r['created_at']; return $r; }, $stmt->fetchAll());
}

function fetchAllReports(): array {
    $stmt = getDB()->query('SELECT * FROM reports ORDER BY created_at DESC');
    return array_map(function($r){ $r['userId'] = $r['user_id']; $r['createdAt'] = $r['created_at']; return $r; }, $stmt->fetchAll());
}

// ─── UI RENDERERS ────────────────────────────────────────────
function renderFilters($search,$fType,$fStatus,$fFrom,$fTo,$page,$filtered){
    $has=$search||$fType!=='all'||$fStatus!=='all'||$fFrom||$fTo;
    ob_start();?>
    <form method="get" class="flt">
      <input type="hidden" name="page" value="<?=h($page)?>">
      <input class="fi fs" type="text" name="search" placeholder="🔍  Cari judul, lokasi, kategori..." value="<?=h($search)?>">
      <select name="type" class="fi">
        <option value="all" <?=$fType==='all'?'selected':''?>>Semua Tipe</option>
        <option value="lost" <?=$fType==='lost'?'selected':''?>>🔴 Hilang</option>
        <option value="found" <?=$fType==='found'?'selected':''?>>🟢 Ditemukan</option>
      </select>
      <select name="status" class="fi">
        <option value="all" <?=$fStatus==='all'?'selected':''?>>Semua Status</option>
        <option value="open" <?=$fStatus==='open'?'selected':''?>>⏳ Aktif</option>
        <option value="matched" <?=$fStatus==='matched'?'selected':''?>>🎯 Sudah Ditemukan</option>
        <option value="closed" <?=$fStatus==='closed'?'selected':''?>>✅ Selesai</option>
      </select>
      <input type="date" name="from" class="fi" value="<?=h($fFrom)?>" title="Dari tanggal">
      <input type="date" name="to" class="fi" value="<?=h($fTo)?>" title="Sampai tanggal">
      <button type="submit" class="btn-pr" style="padding:9px 18px;font-size:12px;">Cari</button>
      <?php if($has):?><a href="?page=<?=h($page)?>"><button type="button" class="btn-sc" style="padding:9px 14px;font-size:12px;">✕ Reset</button></a><?php endif;?>
      <span class="rc-count"><?=count($filtered)?> laporan</span>
    </form>
    <?php return ob_get_clean();
}

function renderReports($reps,$user,$page){
    if($page !== 'dashboard') $reps = array_values(array_filter($reps, fn($r) => $r['status'] !== 'closed'));
    else $reps = array_values($reps);
    if(empty($reps)) return '<div class="empty"><div class="empty-ico">📭</div><p>Tidak ada laporan yang ditemukan</p></div>';
    ob_start();
    echo '<div class="rg">';
    foreach($reps as $r):
        $canEdit=$user&&($user['role']==='admin'||$r['userId']===$user['id']);
        $isMatched=$r['status']==='matched';
        $isClosed=$r['status']==='closed';
        $accentClass=$isMatched?'matched-accent':($r['type']==='lost'?'lost-accent':'found-accent');
    ?>
    <div class="rc-card<?=$isMatched?' is-matched':''?><?=$isClosed?' is-closed':''?>">
      <div class="card-accent <?=$accentClass?>"></div>
      <div class="rc-hd">
        <div class="rc-bg">
          <span class="badge <?=tpClass($r['type'])?>"><?=tpLabel($r['type'])?></span>
          <span class="badge <?=stClass($r['status'])?>"><?=$isMatched?'🎯 ':''?><?=stLabel($r['status'])?></span>
          <span class="badge" style="background:var(--bg);color:var(--txt3);border:1px solid var(--brd);font-weight:600;"><?=h($r['category'])?></span>
        </div>
        <span class="rc-tm"><?=timeAgo($r['createdAt'])?></span>
      </div>

      <?php if($isMatched):?>
      <div class="matched-ribbon">
        <span class="matched-ribbon-icon">🎯</span>
        Pemilik / penemu sudah berhasil diidentifikasi!
      </div>
      <?php endif;?>

      <div class="rc-bd">
        <div class="rc-tit"><?=h($r['title'])?></div>
        <div class="rc-meta">
          <div class="rc-mr"><span class="rc-mr-ico">📍</span><?=h($r['location'])?></div>
          <div class="rc-mr"><span class="rc-mr-ico">📅</span><?=fmtDate($r['date'])?></div>
          <div class="rc-mr"><span class="rc-mr-ico">👤</span><?=h($r['reporter'])?></div>
        </div>
        <button class="toggle-btn" onclick="var d=document.getElementById('det<?=$r['id']?>');var open=d.style.display==='block';d.style.display=open?'none':'block';this.textContent=open?'Lihat Detail ▾':'Sembunyikan ▴'">
          Lihat Detail ▾
        </button>
      </div>

      <div id="det<?=$r['id']?>" class="rc-dt">
        <?php if($r['photo']):?>
        <img src="<?=$r['photo']?>" class="rc-photo" alt="Foto barang">
        <?php endif;?>
        <div class="rc-desc"><?=nl2br(h($r['description']))?></div>
        <?php if($canEdit):?>
        <div class="sa">
          <span class="sa-lbl">Ubah Status:</span>
          <?php if($r['status']!=='matched'):?>
          <form method="post" style="display:inline">
            <input type="hidden" name="action" value="change_status">
            <input type="hidden" name="report_id" value="<?=$r['id']?>">
            <input type="hidden" name="new_status" value="matched">
            <button type="submit" class="bs bm">🎯 Sudah Ditemukan</button>
          </form>
          <?php endif;?>
          <?php if($r['status']!=='closed'):?>
          <form method="post" style="display:inline" onsubmit="return confirm('Laporan ini akan ditandai selesai dan hilang dari daftar. Lanjutkan?')">
            <input type="hidden" name="action" value="change_status">
            <input type="hidden" name="report_id" value="<?=$r['id']?>">
            <input type="hidden" name="new_status" value="closed">
            <button type="submit" class="bs bc">✅ Selesaikan</button>
          </form>
          <?php endif;?>
          <?php if($r['status']==='closed'):?>
          <form method="post" style="display:inline" onsubmit="return confirm('Aktifkan kembali laporan ini?')">
            <input type="hidden" name="action" value="change_status">
            <input type="hidden" name="report_id" value="<?=$r['id']?>">
            <input type="hidden" name="new_status" value="open">
            <button type="submit" class="bs bo">↺ Aktifkan Kembali</button>
          </form>
          <form method="post" style="display:inline" onsubmit="return confirm('Hapus laporan ini secara permanen?')">
            <input type="hidden" name="action" value="delete_report">
            <input type="hidden" name="report_id" value="<?=$r['id']?>">
            <button type="submit" class="bs" style="border-color:var(--lost-lter);color:var(--lost);background:var(--lost-lt);">🗑 Hapus</button>
          </form>
          <?php endif;?>
        </div>
        <?php endif;?>
      </div>
    </div>
    <?php
    endforeach;
    echo '</div>';
    return ob_get_clean();
}