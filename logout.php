<?php
require_once __DIR__.'/includes/app.php';
clear_remember_login($pdo);
$_SESSION=[];
if(ini_get('session.use_cookies')){
    $p=session_get_cookie_params();
    setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);
}
session_destroy();
header('Location: /login.php');
