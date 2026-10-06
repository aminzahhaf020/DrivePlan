<?php 
namespace App; 

class Csrf { 

 // Maakt een beveiligingstoken aan en slaat deze op in de sessie.
 public static function token(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; } 

 // Zet het beveiligingstoken als verborgen veld in een formulier.
 public static function field(): string { return '<input type="hidden" name="csrf" value="'.htmlspecialchars(self::token(),ENT_QUOTES,'UTF-8').'">'; } 

 // Controleert of het meegestuurde token klopt.
 // Als het niet klopt, wordt de aanvraag gestopt.
 public static function verify(): void { if(!isset($_POST['csrf']) || !hash_equals(self::token(),(string)$_POST['csrf'])) throw new \RuntimeException('De aanvraag kon niet veilig worden verwerkt. Probeer opnieuw.'); } 

} //“CSRF controleert of een formulier echt vanuit mijn eigen website is verstuurd.”