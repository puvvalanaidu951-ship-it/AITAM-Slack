<?php
require_once __DIR__.'/includes/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){redirect('index.php');}
verify_csrf();
$_SESSION=[];
if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',(bool)$p['secure'],(bool)$p['httponly']);}
session_destroy();session_start();session_regenerate_id(true);$_SESSION['flash']=['type'=>'success','message'=>'You have been logged out.'];header('Location: index.php');exit;
