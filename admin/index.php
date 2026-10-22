<?php
// M3 MEDIA 관리자 페이지
require __DIR__ . '/../inc/common.php';
admin_session();

$view  = $_GET['v'] ?? 'inquiry';
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

function go(string $url, string $msg = ''): void {
    if ($msg !== '') $_SESSION['flash'] = $msg;
    header('Location: ' . $url);
    exit;
}

// ---------------- 로그인 / 로그아웃 ----------------
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    go('./');
}

if (!is_admin()) {
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $fails = store_read('loginfail', []);
        $ip = client_ip();
        $now = time();
        $rec = $fails[$ip] ?? ['n' => 0, 't' => 0];
        if ($rec['n'] >= 5 && $now - $rec['t'] < 600) {
            $err = '로그인 시도가 너무 많습니다. 10분 후 다시 시도해주세요.';
        } else {
            $cfg = admin_config();
            $ok = ($_POST['id'] ?? '') === ($cfg['id'] ?? '')
                && password_verify((string)($_POST['pw'] ?? ''), $cfg['hash'] ?? '');
            if ($ok) {
                unset($fails[$ip]);
                store_write('loginfail', $fails);
                session_regenerate_id(true);
                $_SESSION['admin'] = $cfg['id'];
                go('./');
            }
            $rec = ['n' => ($now - $rec['t'] < 600 ? $rec['n'] : 0) + 1, 't' => $now];
            $fails[$ip] = $rec;
            store_write('loginfail', $fails);
            $err = '아이디 또는 비밀번호가 올바르지 않습니다.';
        }
    }
    layout_head('관리자 로그인'); ?>
    <div class="login">
      <form method="post" class="card">
        <div class="brand"><span class="logo-mark">M3</span><b>M3 MEDIA 관리자</b></div>
        <?php if ($err): ?><p class="alert err"><?= h($err) ?></p><?php endif; ?>
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <label>아이디<input name="id" autocomplete="username" required autofocus></label>
        <label>비밀번호<input name="pw" type="password" autocomplete="current-password" required></label>
        <button class="btn">로그인</button>
        <a class="back" href="/">← 홈페이지로</a>
      </form>
    </div>
    <?php layout_foot();
    exit;
}

// ---------------- 처리 (POST) ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';

    if ($act === 'inq_status' || $act === 'inq_memo' || $act === 'inq_delete') {
        $list = store_read('inquiries', []);
        foreach ($list as $i => $q) {
            if ($q['id'] !== ($_POST['id'] ?? '')) continue;
            if ($act === 'inq_status') $list[$i]['status'] = $q['status'] === 'done' ? 'new' : 'done';
            if ($act === 'inq_memo')   $list[$i]['memo'] = mb_substr(trim((string)$_POST['memo']), 0, 2000);
            if ($act === 'inq_delete') unset($list[$i]);
        }
        store_write('inquiries', array_values($list));
        go('./?v=inquiry', '저장되었습니다.');
    }

    if ($act === 'pf_save') {
        $list = store_read('portfolio', []);
        $id = $_POST['id'] ?? '';
        $item = [
            'id'      => $id ?: bin2hex(random_bytes(6)),
            'cat'     => mb_substr(trim((string)$_POST['cat']), 0, 30),
            'title'   => mb_substr(trim((string)$_POST['title']), 0, 100),
            'desc'    => mb_substr(trim((string)$_POST['desc']), 0, 200),
            'link'    => mb_substr(trim((string)$_POST['link']), 0, 300),
            'visible' => !empty($_POST['visible']),
            'image'   => '',
        ];
        if ($item['link'] !== '' && !preg_match('~^https?://~i', $item['link'])) $item['link'] = 'https://' . $item['link'];
        foreach ($list as $p) if ($p['id'] === $id) $item['image'] = $p['image'];
        if (!empty($_POST['del_image'])) $item['image'] = '';

        // 썸네일 업로드
        if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            $info = @getimagesize($_FILES['image']['tmp_name']);
            if (!$info || !isset($types[$info['mime']])) go('./?v=portfolio', '이미지 파일(JPG, PNG, WEBP, GIF)만 올릴 수 있습니다.');
            if ($_FILES['image']['size'] > 5 * 1024 * 1024) go('./?v=portfolio', '이미지는 5MB 이하만 가능합니다.');
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            $fn = date('Ymd') . '_' . bin2hex(random_bytes(5)) . '.' . $types[$info['mime']];
            move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . '/' . $fn);
            $item['image'] = '/uploads/' . $fn;
        }

        if ($item['title'] === '') go('./?v=portfolio', '제목을 입력해주세요.');
        $found = false;
        foreach ($list as $i => $p) if ($p['id'] === $item['id']) { $list[$i] = $item; $found = true; }
        if (!$found) $list[] = $item;
        store_write('portfolio', $list);
        go('./?v=portfolio', '포트폴리오가 저장되었습니다.');
    }

    if ($act === 'pf_delete' || $act === 'pf_move') {
        $list = store_read('portfolio', []);
        foreach ($list as $i => $p) {
            if ($p['id'] !== ($_POST['id'] ?? '')) continue;
            if ($act === 'pf_delete') { array_splice($list, $i, 1); break; }
            $j = $i + ($_POST['dir'] === 'up' ? -1 : 1);
            if (isset($list[$j])) [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
            break;
        }
        store_write('portfolio', array_values($list));
        go('./?v=portfolio', $act === 'pf_delete' ? '삭제되었습니다.' : '');
    }

    if ($act === 'password') {
        $cfg = admin_config();
        if (!password_verify((string)$_POST['cur'], $cfg['hash'])) go('./?v=settings', '현재 비밀번호가 올바르지 않습니다.');
        if (strlen((string)$_POST['new']) < 8) go('./?v=settings', '새 비밀번호는 8자 이상이어야 합니다.');
        if ($_POST['new'] !== $_POST['new2']) go('./?v=settings', '새 비밀번호가 서로 일치하지 않습니다.');
        $cfg['hash'] = password_hash((string)$_POST['new'], PASSWORD_DEFAULT);
        $newId = trim((string)($_POST['id'] ?? ''));
        if ($newId !== '') { $cfg['id'] = $newId; $_SESSION['admin'] = $newId; }
        store_write('config', $cfg);
        go('./?v=settings', '계정 정보가 변경되었습니다.');
    }
    go('./');
}

// ---------------- 화면 ----------------
$inquiries = store_read('inquiries', []);
$newCount  = count(array_filter($inquiries, fn($q) => $q['status'] === 'new'));
$portfolio = store_read('portfolio', []);

layout_head('관리자'); ?>
<header class="top">
  <a class="brand" href="./"><span class="logo-mark">M3</span><b>M3 MEDIA 관리자</b></a>
  <nav>
    <a href="?v=inquiry" class="<?= $view === 'inquiry' ? 'on' : '' ?>">상담문의<?php if ($newCount): ?> <i><?= $newCount ?></i><?php endif; ?></a>
    <a href="?v=portfolio" class="<?= $view === 'portfolio' || $view === 'pf_edit' ? 'on' : '' ?>">포트폴리오</a>
    <a href="?v=settings" class="<?= $view === 'settings' ? 'on' : '' ?>">계정설정</a>
  </nav>
  <div class="util"><a href="/" target="_blank">홈페이지 ↗</a><a href="?logout=1">로그아웃</a></div>
</header>
<main>
<?php if ($flash): ?><p class="alert"><?= h($flash) ?></p><?php endif; ?>

<?php if ($view === 'inquiry'): ?>
  <h1>상담문의 <small>전체 <?= count($inquiries) ?>건 · 미처리 <?= $newCount ?>건</small></h1>
  <?php if (!$inquiries): ?><p class="empty">아직 접수된 상담 신청이 없습니다.</p><?php endif; ?>
  <div class="inq-list">
  <?php foreach ($inquiries as $q): ?>
    <article class="card inq <?= $q['status'] ?>">
      <div class="inq-head">
        <span class="badge <?= $q['status'] ?>"><?= $q['status'] === 'done' ? '처리완료' : '신규' ?></span>
        <b><?= h($q['name']) ?></b>
        <a href="tel:<?= h($q['tel']) ?>"><?= h($q['tel']) ?></a>
        <span class="type"><?= h($q['type']) ?></span>
        <time><?= h($q['created']) ?></time>
      </div>
      <?php if ($q['msg'] !== ''): ?><p class="msg"><?= nl2br(h($q['msg'])) ?></p><?php endif; ?>
      <div class="inq-actions">
        <form method="post" class="memo">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="inq_memo"><input type="hidden" name="id" value="<?= h($q['id']) ?>">
          <input name="memo" value="<?= h($q['memo']) ?>" placeholder="관리자 메모 (상담 결과 등)"><button class="btn sm line">메모 저장</button>
        </form>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="inq_status"><input type="hidden" name="id" value="<?= h($q['id']) ?>">
          <button class="btn sm"><?= $q['status'] === 'done' ? '신규로 되돌리기' : '처리완료' ?></button>
        </form>
        <form method="post" onsubmit="return confirm('이 문의를 삭제할까요?')">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="inq_delete"><input type="hidden" name="id" value="<?= h($q['id']) ?>">
          <button class="btn sm danger">삭제</button>
        </form>
      </div>
    </article>
  <?php endforeach; ?>
  </div>

<?php elseif ($view === 'portfolio'): ?>
  <h1>포트폴리오 <small><?= count($portfolio) ?>개</small> <a class="btn sm" href="?v=pf_edit">+ 새 항목</a></h1>
  <p class="hint">등록된 항목이 없으면 홈페이지에는 기본 예시 카드가 표시됩니다. 유튜브 링크를 넣으면 썸네일이 자동으로 표시됩니다.</p>
  <?php if (!$portfolio): ?><p class="empty">등록된 포트폴리오가 없습니다.</p><?php endif; ?>
  <div class="pf-grid">
  <?php foreach ($portfolio as $p):
      $img = $p['image'] ?: (($y = youtube_id($p['link'])) ? "https://img.youtube.com/vi/$y/hqdefault.jpg" : ''); ?>
    <article class="card pf <?= $p['visible'] ? '' : 'hidden' ?>">
      <div class="thumb"<?php if ($img): ?> style="background-image:url('<?= h($img) ?>')"<?php endif; ?>><?php if (!$p['visible']): ?><span>숨김</span><?php endif; ?></div>
      <div class="body">
        <span class="cat"><?= h($p['cat']) ?></span>
        <h3><?= h($p['title']) ?></h3>
        <p><?= h($p['desc']) ?></p>
      </div>
      <div class="pf-actions">
        <?php foreach (['up' => '←', 'down' => '→'] as $d => $label): ?>
        <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="pf_move"><input type="hidden" name="dir" value="<?= $d ?>"><input type="hidden" name="id" value="<?= h($p['id']) ?>"><button class="btn sm line" title="순서 이동"><?= $label ?></button></form>
        <?php endforeach; ?>
        <a class="btn sm" href="?v=pf_edit&id=<?= h($p['id']) ?>">수정</a>
        <form method="post" onsubmit="return confirm('삭제할까요?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="pf_delete"><input type="hidden" name="id" value="<?= h($p['id']) ?>"><button class="btn sm danger">삭제</button></form>
      </div>
    </article>
  <?php endforeach; ?>
  </div>

<?php elseif ($view === 'pf_edit'):
    $p = ['id' => '', 'cat' => '병원 유튜브', 'title' => '', 'desc' => '', 'link' => '', 'image' => '', 'visible' => true];
    foreach ($portfolio as $x) if ($x['id'] === ($_GET['id'] ?? '')) $p = $x; ?>
  <h1><?= $p['id'] ? '포트폴리오 수정' : '새 포트폴리오' ?></h1>
  <form method="post" enctype="multipart/form-data" class="card form">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="pf_save"><input type="hidden" name="id" value="<?= h($p['id']) ?>">
    <label>분류
      <input name="cat" value="<?= h($p['cat']) ?>" list="cats">
      <datalist id="cats"><option>병원 유튜브</option><option>바이럴 마케팅</option><option>전광판 광고</option><option>영상 프로덕션</option></datalist>
    </label>
    <label>제목 *<input name="title" value="<?= h($p['title']) ?>" required></label>
    <label>설명<input name="desc" value="<?= h($p['desc']) ?>" placeholder="예: 서면 OO치과 · 2026"></label>
    <label>링크 (유튜브 / 블로그 등)<input name="link" value="<?= h($p['link']) ?>" placeholder="https://youtu.be/..."></label>
    <label>썸네일 이미지 <small>(비워두면 유튜브 썸네일 자동 사용)</small><input type="file" name="image" accept="image/*"></label>
    <?php if ($p['image']): ?><div class="cur-img"><img src="<?= h($p['image']) ?>" alt=""><label class="chk"><input type="checkbox" name="del_image" value="1"> 현재 이미지 삭제</label></div><?php endif; ?>
    <label class="chk"><input type="checkbox" name="visible" value="1" <?= $p['visible'] ? 'checked' : '' ?>> 홈페이지에 표시</label>
    <div class="row"><button class="btn">저장</button><a class="btn line" href="?v=portfolio">취소</a></div>
  </form>

<?php elseif ($view === 'settings'): ?>
  <h1>계정설정</h1>
  <form method="post" class="card form">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="password">
    <label>아이디<input name="id" value="<?= h($_SESSION['admin']) ?>" autocomplete="username"></label>
    <label>현재 비밀번호<input name="cur" type="password" required autocomplete="current-password"></label>
    <label>새 비밀번호 (8자 이상)<input name="new" type="password" required minlength="8" autocomplete="new-password"></label>
    <label>새 비밀번호 확인<input name="new2" type="password" required minlength="8" autocomplete="new-password"></label>
    <div class="row"><button class="btn">변경하기</button></div>
  </form>
<?php endif; ?>
</main>
<?php layout_foot();

// ---------------- 레이아웃 ----------------
function layout_head(string $title): void { ?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= h($title) ?> | M3 MEDIA</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
<style>
:root{--primary:#0f8a6c;--primary-dk:#0a5f4b;--ink:#121816;--ink-2:#4a5552;--line:#e3e8e6;--bg-soft:#f4f7f6;--danger:#d64545}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Pretendard,-apple-system,"Malgun Gothic",sans-serif;color:var(--ink);background:var(--bg-soft);line-height:1.6;word-break:keep-all}
a{color:inherit;text-decoration:none}
.logo-mark{width:36px;height:36px;border-radius:10px;background:var(--primary);display:grid;place-items:center;font-weight:900;color:#fff;font-size:15px}
.brand{display:flex;align-items:center;gap:10px;font-size:17px}
.btn{display:inline-flex;align-items:center;justify-content:center;height:44px;padding:0 22px;border-radius:999px;border:0;background:var(--primary);color:#fff;font:inherit;font-weight:700;font-size:15px;cursor:pointer}
.btn:hover{background:var(--primary-dk)}
.btn.sm{height:34px;padding:0 14px;font-size:13px}
.btn.line{background:#fff;color:var(--ink);border:1px solid var(--line)}
.btn.line:hover{border-color:var(--ink)}
.btn.danger{background:#fff;color:var(--danger);border:1px solid #f0caca}
.btn.danger:hover{background:var(--danger);color:#fff}
.card{background:#fff;border-radius:16px;box-shadow:0 1px 0 var(--line),0 6px 20px rgba(0,0,0,.04)}
.alert{background:#e7f6f1;color:var(--primary-dk);padding:12px 16px;border-radius:10px;margin-bottom:20px;font-size:14px;font-weight:600}
.alert.err{background:#fdeeee;color:var(--danger)}
input,textarea,select{width:100%;height:44px;border:1px solid var(--line);border-radius:10px;padding:0 14px;font:inherit;font-size:15px;background:var(--bg-soft);margin-top:6px}
input:focus{outline:2px solid var(--primary);outline-offset:-1px;background:#fff}
input[type=file]{padding:9px 14px}
label{display:block;font-size:13px;font-weight:700;color:var(--ink-2);margin-bottom:16px}
label small{font-weight:400}
.chk{display:flex;align-items:center;gap:8px;font-size:14px;color:var(--ink)}
.chk input{width:18px;height:18px;margin:0}
/* login */
.login{min-height:100vh;display:grid;place-items:center;padding:20px}
.login .card{width:100%;max-width:380px;padding:36px 32px}
.login .brand{margin-bottom:26px}
.login .btn{width:100%;margin-top:6px}
.login .back{display:block;text-align:center;margin-top:18px;font-size:14px;color:#8a9592}
/* layout */
.top{position:sticky;top:0;z-index:10;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:30px;padding:0 28px;height:68px}
.top nav{display:flex;gap:4px;flex:1}
.top nav a{padding:8px 16px;border-radius:999px;font-weight:600;color:var(--ink-2)}
.top nav a.on{background:var(--bg-soft);color:var(--primary)}
.top nav i{font-style:normal;background:var(--danger);color:#fff;font-size:11px;padding:1px 7px;border-radius:999px;margin-left:4px;vertical-align:2px}
.top .util{display:flex;gap:18px;font-size:14px;color:var(--ink-2)}
main{max-width:1100px;margin:0 auto;padding:36px 28px 80px}
h1{font-size:26px;font-weight:800;margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
h1 small{font-size:14px;font-weight:500;color:#8a9592}
h1 .btn{margin-left:auto}
.hint{font-size:14px;color:#8a9592;margin:-10px 0 20px}
.empty{padding:60px 0;text-align:center;color:#8a9592}
/* inquiries */
.inq-list{display:grid;gap:14px}
.inq{padding:20px 24px}
.inq.done{opacity:.7}
.inq-head{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.inq-head b{font-size:17px}
.inq-head a{color:var(--primary);font-weight:700}
.inq-head .type{font-size:13px;background:var(--bg-soft);padding:3px 10px;border-radius:999px}
.inq-head time{margin-left:auto;font-size:13px;color:#8a9592}
.badge{font-size:12px;font-weight:700;padding:3px 10px;border-radius:999px;background:#fdeeee;color:var(--danger)}
.badge.done{background:#e7f6f1;color:var(--primary)}
.msg{margin-top:12px;padding:14px 16px;background:var(--bg-soft);border-radius:10px;font-size:15px;white-space:normal}
.inq-actions{display:flex;gap:8px;margin-top:14px;flex-wrap:wrap}
.inq-actions .memo{flex:1;display:flex;gap:8px;min-width:260px}
.inq-actions .memo input{height:34px;margin:0;font-size:14px}
/* portfolio */
.pf-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:18px}
.pf{overflow:hidden;display:flex;flex-direction:column}
.pf.hidden{opacity:.55}
.pf .thumb{aspect-ratio:16/10;background:linear-gradient(135deg,#0f8a6c,#0b1412) center/cover;display:grid;place-items:center}
.pf .thumb span{background:rgba(0,0,0,.6);color:#fff;font-size:13px;padding:4px 12px;border-radius:999px}
.pf .body{padding:16px 18px;flex:1}
.pf .cat{font-size:12px;font-weight:700;color:var(--primary)}
.pf h3{font-size:16px;margin:4px 0}
.pf p{font-size:13px;color:#8a9592}
.pf-actions{display:flex;gap:6px;padding:0 18px 18px;flex-wrap:wrap}
.form{padding:30px;max-width:640px}
.form .row{display:flex;gap:10px;margin-top:8px}
.cur-img{display:flex;align-items:center;gap:16px;margin:-6px 0 16px}
.cur-img img{width:160px;border-radius:8px}
.cur-img .chk{margin:0}
@media (max-width:760px){
  .top{flex-wrap:wrap;height:auto;padding:12px 16px;gap:10px}
  .top nav{order:3;flex-basis:100%;overflow-x:auto}
  .top .util{margin-left:auto}
  main{padding:24px 16px 60px}
  .inq{padding:18px}
  .inq-head time{margin-left:0;flex-basis:100%}
  .form{padding:22px 18px}
}
</style>
</head>
<body>
<?php }

function layout_foot(): void { ?>
</body>
</html>
<?php }
