<?php
namespace App;
class Csrf {
 public static function token(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
 public static function field(): string { return '<input type="hidden" name="csrf" value="'.htmlspecialchars(self::token(),ENT_QUOTES,'UTF-8').'">'; }
 public static function verify(): void { if(!isset($_POST['csrf']) || !hash_equals(self::token(),(string)$_POST['csrf'])) throw new \RuntimeException('De aanvraag kon niet veilig worden verwerkt. Probeer opnieuw.'); }
}
