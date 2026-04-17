<?php
require_once 'actions.php';

$user    = getUser();
$search  = trim($_GET['search'] ?? '');
$fType   = $_GET['type']   ?? 'all';
$fStatus = $_GET['status'] ?? 'all';
$fFrom   = $_GET['from']   ?? '';
$fTo     = $_GET['to']     ?? '';

try {
    $filtered = fetchReports($search, $fType, $fStatus, $fFrom, $fTo);
    $reports  = fetchAllReports();
} catch (PDOException $e) {
    die('<pre style="font-family:monospace;padding:2em;color:#c00"><b>❌ Koneksi Database Gagal</b><br>Error: '.$e->getMessage().'<br>Pastikan database sudah disetup.</pre>');
}

$total    = count($reports);
$tLost    = count(array_filter($reports, fn($r) => $r['type']==='lost'));
$tFound   = count(array_filter($reports, fn($r) => $r['type']==='found'));
$tOpen    = count(array_filter($reports, fn($r) => $r['status']==='open'));
$tMatched = count(array_filter($reports, fn($r) => $r['status']==='matched'));
$tClosed  = count(array_filter($reports, fn($r) => $r['status']==='closed'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SNI Lost &amp; Found</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Bricolage+Grotesque:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php if(!$user): ?>
<div class="lw">
  <div class="lb">
    <div class="ll"><img src="<?=$LOGO_URI?>" alt="SNI Logo"></div>
    <p class="ls">Sistem Manajemen Barang Hilang &amp; Ditemukan<br>PT Sinergi Nusantara Integrasi</p>
    <div class="da">
      <div class="dt">Akun Demo — Klik untuk mengisi</div>
      <div class="dbs">
        <button class="db" onclick="fill('admin','admin123')">👑 Admin</button>
        <button class="db" onclick="fill('budi','budi123')">👤 Budi</button>
        <button class="db" onclick="fill('siti','siti123')">👤 Siti</button>
      </div>
    </div>
    <?php if(!empty($errors['login'])):?>
    <div class="le">⚠ <?=h($errors['login'])?></div>
    <?php endif;?>
    <form method="post">
      <input type="hidden" name="action" value="login">
      <div class="fld" style="margin-bottom:14px;">
        <label class="flb">Username</label>
        <input id="usr" name="username" class="fin" placeholder="Masukkan username" value="<?=h($_POST['username']??'')?>">
      </div>
      <div class="fld" style="margin-bottom:22px;">
        <label class="flb">Password</label>
        <div class="pw">
          <input id="pwd" name="password" type="password" class="fin" placeholder="Masukkan password">
          <button type="button" class="pe" onclick="var f=document.getElementById('pwd');f.type=f.type==='password'?'text':'password'">👁</button>
        </div>
      </div>
      <button type="submit" class="btn-pr" style="width:100%;font-size:14px;padding:13px;">Masuk ke Sistem →</button>
    </form>
  </div>
</div>
<script>function fill(u,p){document.getElementById('usr').value=u;document.getElementById('pwd').value=p;}</script>

<?php else: ?>
<div class="app">
  <aside class="sidebar">
    <div class="s-logo"><img src="<?=$LOGO_URI?>" alt="SNI"></div>
    <nav class="s-nav">
      <?php if($user['role']==='admin'): ?>
      <div class="nav-sec">Menu Utama</div>
      <a href="?page=dashboard" class="nav-i <?=$page==='dashboard'?'active':''?>"><span class="nico">📊</span> Dashboard</a>
      <?php endif; ?>
      <a href="?page=list" class="nav-i <?=$page==='list'?'active':''?>"><span class="nico">📋</span> Semua Laporan</a>
      <div class="nav-sec">Buat Laporan</div>
      <a href="?page=report_lost" class="nav-i <?=$page==='report_lost'?'active':''?>"><span class="nico">🔴</span> Barang Hilang</a>
      <a href="?page=report_found" class="nav-i <?=$page==='report_found'?'active':''?>"><span class="nico">🟢</span> Barang Ditemukan</a>
      <div class="nav-sec">Akun Saya</div>
      <a href="?page=history" class="nav-i <?=$page==='history'?'active':''?>"><span class="nico">📁</span> Riwayat Saya</a>
      <?php if($user['role']==='admin'): ?>
      <div class="nav-sec">Komunikasi</div>
      <a href="#" class="nav-i" onclick="chatToggle();return false;" style="position:relative;">
        <span class="nico">💬</span> Chat Pengguna
        <span id="sidebar-badge" style="display:none;margin-left:auto;background:var(--accent);color:#fff;font-size:10px;font-weight:800;min-width:18px;height:18px;border-radius:9px;align-items:center;justify-content:center;padding:0 4px;"></span>
      </a>
      <?php endif; ?>
    </nav>
    <div class="s-foot">
      <div class="u-info">
        <div class="av"><?=h($user['avatar'])?></div>
        <div><div class="u-name"><?=h($user['name'])?></div><div class="u-role"><?=$user['role']==='admin'?'Administrator':'Pengguna'?></div></div>
      </div>
      <a href="?logout=1"><button class="btn-out">⎋ Keluar dari Akun</button></a>
      <div class="copy">© 2026 PT Sinergi<br>Nusantara Integrasi</div>
    </div>
  </aside>

  <main class="main">
    <div class="tbar">
      <div class="tbar-brand"><div class="tbar-dot"></div>SNI Lost &amp; Found</div>
      <div class="tbar-r"><?=h(['dashboard'=>'📊 Dashboard','list'=>'📋 Semua Laporan','report_lost'=>'🔴 Laporan Hilang','report_found'=>'🟢 Laporan Ditemukan','history'=>'📁 Riwayat Saya','ai_chat'=>'🤖 AI Assistant'][$page]??'')?></div>
    </div>

    <div class="cnt">
    <?php if($page === 'dashboard' && $user['role']==='admin'): ?>
    <div class="ph"><h2>📊 Dashboard Admin</h2></div>
    <div class="sg">
      <div class="sc c-total"><span class="sc-ico">📦</span><div class="sv"><?=$total?></div><div class="sl">Total Laporan</div></div>
      <div class="sc c-lost"><span class="sc-ico">🔴</span><div class="sv"><?=$tLost?></div><div class="sl">Barang Hilang</div></div>
      <div class="sc c-found"><span class="sc-ico">🟢</span><div class="sv"><?=$tFound?></div><div class="sl">Barang Ditemukan</div></div>
      <div class="sc c-open"><span class="sc-ico">⏳</span><div class="sv"><?=$tOpen?></div><div class="sl">Aktif</div></div>
      <div class="sc c-matched"><span class="sc-ico">🎯</span><div class="sv"><?=$tMatched?></div><div class="sl">Sudah Ditemukan</div></div>
      <div class="sc c-closed"><span class="sc-ico">✅</span><div class="sv"><?=$tClosed?></div><div class="sl">Selesai</div></div>
    </div>
    <div class="ph" style="margin-top:8px;"><h2 style="font-size:17px;">📋 Laporan Terbaru</h2></div>
    <?php echo renderReports(array_slice($reports, 0, 10), $user, $page); ?>

    <?php elseif($page==='list'): ?>
    <?php $lOpen=count(array_filter($reports,fn($r)=>$r['type']==='lost'&&$r['status']==='open')); $fOpen=count(array_filter($reports,fn($r)=>$r['type']==='found'&&$r['status']==='open')); ?>
    <div class="hero">
      <div class="hero-txt"><h2>📋 Papan Laporan</h2><p>PT Sinergi Nusantara Integrasi — Sistem Lost &amp; Found</p></div>
      <div class="hs">
        <div class="hst"><div class="hsv"><?=$lOpen?></div><div class="hsl">Hilang Aktif</div></div>
        <div class="hst"><div class="hsv"><?=$fOpen?></div><div class="hsl">Temuan Aktif</div></div>
      </div>
    </div>
    <?=renderFilters($search,$fType,$fStatus,$fFrom,$fTo,$page,$filtered)?>
    <?=renderReports($filtered,$user,$page)?>

    <?php elseif($page==='history'): ?>
    <?php $myAll=array_filter($reports,fn($r)=>$r['userId']===$user['id']);$myF=array_filter($filtered,fn($r)=>$r['userId']===$user['id']);?>
    <div class="ph"><h2>📁 Riwayat Laporan Saya</h2></div>
    <div class="sg" style="max-width:600px">
      <div class="sc c-total"><span class="sc-ico">📦</span><div class="sv"><?=count($myAll)?></div><div class="sl">Total</div></div>
      <div class="sc c-open"><span class="sc-ico">⏳</span><div class="sv"><?=count(array_filter($myAll,fn($r)=>$r['status']==='open'))?></div><div class="sl">Aktif</div></div>
      <div class="sc c-matched"><span class="sc-ico">🎯</span><div class="sv"><?=count(array_filter($myAll,fn($r)=>$r['status']==='matched'))?></div><div class="sl">Sudah Ditemukan</div></div>
    </div>
    <?=renderFilters($search,$fType,$fStatus,$fFrom,$fTo,$page,$myF)?>
    <?=renderReports($myF,$user,$page)?>

    <?php elseif(in_array($page,['report_lost','report_found'])): ?>
    <?php $isL=$page==='report_lost';$ac=$isL?'var(--lost)':'var(--found)';?>
    <div class="fp">
      <div class="fhd"><h2 style="color:<?=$ac?>"><?=$isL?'🔴 Laporkan Barang Hilang':'🟢 Laporkan Barang Ditemukan'?></h2></div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="<?=$page?>">
        <div class="fg">
          <div class="fld ff">
            <label class="flb">Judul Barang <span class="req">*</span></label>
            <input name="title" class="fin" value="<?=h($_POST['title']??'')?>">
          </div>
          <div class="fld">
            <label class="flb">Kategori</label>
            <select name="category" class="fin">
              <?php foreach($categories as $c):?><option value="<?=h($c)?>"><?=h($c)?></option><?php endforeach;?>
            </select>
          </div>
          <div class="fld">
            <label class="flb">Tanggal Kejadian <span class="req">*</span></label>
            <input type="date" name="date" class="fin" max="<?=date('Y-m-d')?>" value="<?=h($_POST['date']??'')?>">
          </div>
          <div class="fld ff">
            <label class="flb">Lokasi <span class="req">*</span></label>
            <input name="location" class="fin" value="<?=h($_POST['location']??'')?>">
          </div>
          <div class="fld ff">
            <label class="flb">Deskripsi Detail <span class="req">*</span></label>
            <textarea name="description" class="fin fta"><?=h($_POST['description']??'')?></textarea>
          </div>
          <div class="fld ff">
            <label class="flb">Foto Barang (opsional, maks 5MB)</label>
            <div class="pu" onclick="document.getElementById('pi').click()">📷 Klik untuk upload foto<input id="pi" type="file" name="photo" accept="image/*" style="display:none" onchange="var r=new FileReader();r.onload=function(e){var i=document.getElementById('pp');i.src=e.target.result;i.style.display='block'};r.readAsDataURL(this.files[0])"></div>
            <img id="pp" style="display:none;width:100%;max-height:200px;object-fit:cover;border-radius:10px;margin-top:10px;">
          </div>
        </div>
        <div class="fa"><button type="submit" class="btn-pr" style="background:<?=$ac?>">Kirim Laporan →</button></div>
      </form>
    </div>
    <?php endif;?>
    </div>
  </main>
</div>

<button id="chat-fab" title="Chat dengan Admin" onclick="chatToggle()">💬<span class="fab-badge" id="fab-badge">0</span></button>
<div id="chat-win">
  <div class="chat-header" id="chat-header">
    <button class="chat-back" id="chat-back" onclick="chatBack()" style="display:none">←</button>
    <div class="chat-header-av" id="chat-header-av">💬</div>
    <div class="chat-header-info">
      <div class="chat-header-title" id="chat-header-title"><?=$user['role']==='admin'?'Chat Pengguna':'Chat Admin'?><span class="chat-24">24 jam</span></div>
      <div class="chat-header-sub" id="chat-header-sub"><?=$user['role']==='admin'?'Pilih pengguna untuk membalas':(CHAT_REPLY_MODE==='ai_auto'?'🤖 Dijawab otomatis oleh AI':'● Online — siap membantu')?></div>
    </div>
    <button class="chat-header-close" onclick="chatClose()">✕</button>
  </div>
  <?php if($user['role']==='admin'): ?>
  <div id="chat-rooms-panel" style="flex:1;display:flex;flex-direction:column;overflow:hidden;"><div class="chat-rooms" id="chat-rooms-list"><div class="chat-rooms-empty">⏳ Memuat...</div></div></div>
  <div id="chat-msgs-panel" style="flex:1;display:none;flex-direction:column;overflow:hidden;">
  <?php else: ?>
  <div id="chat-msgs-panel" style="flex:1;display:flex;flex-direction:column;overflow:hidden;">
  <?php endif; ?>
    <div class="chat-msgs" id="chat-msgs"></div>
    <div class="chat-typing" id="chat-typing" style="display:none;">🤖 Mengetik...</div>
    <div class="chat-input-row">
      <textarea id="chat-textarea" placeholder="Ketik pesan..." rows="1" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();chatSend();}" oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,80)+'px'"></textarea>
      <button class="chat-send-btn" onclick="chatSend()">➤</button>
    </div>
  </div>
</div>

<script>
  window.APP_CONFIG = {
    isAdmin: <?= $user['role'] === 'admin' ? 'true' : 'false' ?>,
    myId: <?= (int)$user['id'] ?>,
    myName: <?= json_encode($user['name']) ?>,
    myAvatar: <?= json_encode($user['avatar']) ?>,
    chatMode: <?= json_encode(CHAT_REPLY_MODE) ?>
  };
</script>
<script src="assets/js/app.js"></script>
<?php endif;?>

<?php
$tMap=['login_ok'=>'✓ Login berhasil!','report_ok'=>'✓ Laporan dikirim!','status_ok'=>'✓ Status diubah!','delete_ok'=>'🗑 Laporan dihapus.'];
if(isset($_GET['toast'])&&isset($tMap[$_GET['toast']])): ?>
<div class="toast ts" id="toast"><?=h($tMap[$_GET['toast']])?></div>
<script>setTimeout(()=>{var t=document.getElementById('toast');if(t){t.style.opacity='0';t.style.transform='translateY(10px)';t.style.transition='.3s';setTimeout(()=>t.remove(),350)}},3500)</script>
<?php endif;?>

</body>
</html>