<?php
// 공통 설정 / 저장소 / 인증 헬퍼
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('DATA_DIR', ROOT . '/data');
define('UPLOAD_DIR', ROOT . '/uploads');

date_default_timezone_set('Asia/Seoul');

// 데이터 파일은 .php 로 저장하고 첫 줄에 exit 를 넣어 웹에서 직접 열람되지 않도록 함
const GUARD = "<?php exit; ?>\n";

function store_path(string $name): string { return DATA_DIR . '/' . $name . '.php'; }

function store_read(string $name, $default = []) {
    $f = store_path($name);
    if (!is_file($f)) return $default;
    $raw = file_get_contents($f);
    $json = substr($raw, strlen(GUARD));
    $v = json_decode($json, true);
    return $v === null ? $default : $v;
}

function store_write(string $name, $data): void {
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
    $f = store_path($name);
    $tmp = $f . '.tmp';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) throw new RuntimeException('저장 실패: ' . json_last_error_msg());
    file_put_contents($tmp, GUARD . $json, LOCK_EX);
    rename($tmp, $f);
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function json_out($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? ''; }

// ---------- 관리자 세션 ----------
function admin_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('m3adm');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
        'path'     => '/',
    ]);
    session_start();
}

function is_admin(): bool { admin_session(); return !empty($_SESSION['admin']); }

function csrf_token(): string {
    admin_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function csrf_check(): void {
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('잘못된 요청입니다. 페이지를 새로고침 후 다시 시도해주세요.');
    }
}

function admin_config(): array { return store_read('config', []); }

function youtube_id(string $url): string {
    if (preg_match('~(?:youtu\.be/|v=|/shorts/|/embed/)([A-Za-z0-9_-]{11})~', $url, $m)) return $m[1];
    return '';
}
