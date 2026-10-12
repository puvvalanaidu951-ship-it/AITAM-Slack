<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('college_connect_session');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__.'/../config.php';
require_once __DIR__.'/functions.php';