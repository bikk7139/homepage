<?php
// 홈페이지 상담 신청 접수
require __DIR__ . '/../inc/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'msg' => 'POST only'], 405);

// 스팸 방지: 숨김 필드가 채워져 있으면 봇으로 간주
if (!empty($_POST['website'])) json_out(['ok' => true]);

$name = trim((string)($_POST['name'] ?? ''));
$tel  = trim((string)($_POST['tel'] ?? ''));
$type = trim((string)($_POST['type'] ?? ''));
$msg  = trim((string)($_POST['msg'] ?? ''));

if ($name === '' || $tel === '') json_out(['ok' => false, 'msg' => '병원명과 연락처를 입력해주세요.'], 422);
if (mb_strlen($name) > 100 || mb_strlen($tel) > 40 || mb_strlen($type) > 50 || mb_strlen($msg) > 3000)
    json_out(['ok' => false, 'msg' => '입력 내용이 너무 깁니다.'], 422);

// 같은 IP 에서 30초 이내 재전송 차단
$rl = store_read('ratelimit', []);
$ip = client_ip();
$now = time();
if (isset($rl[$ip]) && $now - $rl[$ip] < 30) json_out(['ok' => false, 'msg' => '잠시 후 다시 시도해주세요.'], 429);
$rl = array_filter($rl, fn($t) => $now - $t < 3600);
$rl[$ip] = $now;
store_write('ratelimit', $rl);

$list = store_read('inquiries', []);
array_unshift($list, [
    'id'      => bin2hex(random_bytes(6)),
    'name'    => $name,
    'tel'     => $tel,
    'type'    => $type,
    'msg'     => $msg,
    'ip'      => $ip,
    'created' => date('Y-m-d H:i'),
    'status'  => 'new', // new | done
    'memo'    => '',
]);
store_write('inquiries', $list);

json_out(['ok' => true, 'msg' => '상담 신청이 접수되었습니다. 빠르게 연락드리겠습니다.']);
