<?php
namespace App;
class Auth {
 public static function user(): ?array { return $_SESSION['user']??null; }
 public static function login(array $u): void { session_regenerate_id(true); $_SESSION['user']=['id'=>(int)$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role']]; }
 public static function logout(): void { $_SESSION=[]; if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);} session_destroy(); }
 public static function requireLogin(): void { if(!self::user()){header('Location: /?page=login');exit;} }
 public static function requireRole(string $r): void { self::requireLogin(); if((self::user()['role']??'')!==$r){http_response_code(403); throw new \RuntimeException('Je hebt geen toegang tot deze actie.');} }
}
