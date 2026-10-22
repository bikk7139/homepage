<?php
// 홈페이지 포트폴리오 목록 (공개)
require __DIR__ . '/../inc/common.php';

$out = [];
foreach (store_read('portfolio', []) as $p) {
    if (empty($p['visible'])) continue;
    $out[] = [
        'cat'   => $p['cat'],
        'title' => $p['title'],
        'desc'  => $p['desc'],
        'link'  => $p['link'],
        'image' => $p['image'] ?: (($yid = youtube_id($p['link'])) ? "https://img.youtube.com/vi/$yid/hqdefault.jpg" : ''),
        'yt'    => youtube_id($p['link']) !== '',
    ];
}
header('Cache-Control: no-cache');
json_out($out);
