<?php

namespace App;

use PDO;
use DateTime;

class LessonService {

 // Maakt verbinding met de database beschikbaar binnen deze class.
 public function __construct(private PDO $db){}

 // Controleert of de ingevoerde start- en eindtijd geldig zijn.
 // De eindtijd moet na de starttijd liggen en de les mag niet in het verleden zijn.
 private function dates(string $start,string $end): array {
     try{
         $s=new DateTime($start);
         $e=new DateTime($end);
     }catch(\Throwable){
         throw new \RuntimeException('Vul een geldige datum en tijd in.');
     }

     if($s >= $e)
         throw new \RuntimeException('De eindtijd moet na de starttijd liggen.');

     if($s < new DateTime('-5 minutes'))
         throw new \RuntimeException('Je kunt geen les in het verleden plannen.');

     return [$s->format('Y-m-d H:i:s'),$e->format('Y-m-d H:i:s')];
 }

 // Controleert of een instructeur op dit tijdstip al een andere les heeft.
 // Zo voorkomen we dat een instructeur twee lessen tegelijk krijgt.
 public function hasOverlap(int $instructor,string $start,string $end,?int $ignore=null): bool {
     $sql="SELECT COUNT(*) FROM lessons WHERE instructor_id=? AND status IN ('confirmed','completed') AND start_at < ? AND end_at > ?";
     $a=[$instructor,$end,$start];

     if($ignore){
         $sql.=' AND id<>?';
         $a[]=$ignore;
     }

     $s=$this->db->prepare($sql);
     $s->execute($a);

     return (int)$s->fetchColumn()>0;
 }

 // Controleert of de instructeur beschikbaar is op het gekozen tijdstip.
 // Er wordt ook gecontroleerd of het tijdstip niet geblokkeerd is.
 public function isAvailable(int $instructor,string $start,string $end): bool {
     $s=$this->db->prepare("SELECT COUNT(*) FROM availability WHERE instructor_id=? AND type='available' AND start_at<=? AND end_at>=?");
     $s->execute([$instructor,$start,$end]);
     $available=(int)$s->fetchColumn()>0;

     $s=$this->db->prepare("SELECT COUNT(*) FROM availability WHERE instructor_id=? AND type='blocked' AND start_at < ? AND end_at > ?");
     $s->execute([$instructor,$end,$start]);

     return $available && (int)$s->fetchColumn()===0;
 }

 // Hiermee kan een leerling een rijles aanvragen.
 // Er wordt eerst gekeken naar lestegoed, beschikbaarheid en dubbele boekingen.
 public function request(int $student,int $instructor,string $start,string $end): int {
     [$start,$end]=$this->dates($start,$end);

     $s=$this->db->prepare('SELECT remaining_minutes FROM lesson_credits WHERE user_id=?');
     $s->execute([$student]);
     $credit=(int)$s->fetchColumn();

     $mins=(int)((strtotime($end)-strtotime($start))/60);

     if($credit<$mins)
         throw new \RuntimeException('Je hebt niet genoeg lestegoed voor deze les.');

     if(!$this->isAvailable($instructor,$start,$end))
         throw new \RuntimeException('Dit tijdstip valt niet binnen de beschikbaarheid van de instructeur.');

     if($this->hasOverlap($instructor,$start,$end))
         throw new \RuntimeException('Dit tijdstip is niet meer beschikbaar.');

     $s=$this->db->prepare("INSERT INTO lessons(student_id,instructor_id,start_at,end_at,status) VALUES(?,?,?,?, 'requested')");
     $s->execute([$student,$instructor,$start,$end]);

     return (int)$this->db->lastInsertId();
 }

 // Hiermee kan de instructeur een aangevraagde les bevestigen.
 // Voor het bevestigen wordt nog een keer gecontroleerd of het tijdstip beschikbaar is.
 public function confirm(int $id,int $instructor): void {
     $s=$this->db->prepare('SELECT * FROM lessons WHERE id=? AND instructor_id=?');
     $s->execute([$id,$instructor]);
     $l=$s->fetch();

     if(!$l)
         throw new \RuntimeException('Les niet gevonden.');

     if($this->hasOverlap($instructor,$l['start_at'],$l['end_at'],$id))
         throw new \RuntimeException('Deze les overlapt met een andere les.');

     if(!$this->isAvailable($instructor,$l['start_at'],$l['end_at']))
         throw new \RuntimeException('Dit tijdstip is niet beschikbaar.');

     $this->db->prepare("UPDATE lessons SET status='confirmed' WHERE id=?")->execute([$id]);
 }

 // Hiermee kan de instructeur een rijles naar een ander tijdstip verplaatsen.
 // Het nieuwe tijdstip wordt opnieuw gecontroleerd op beschikbaarheid en overlap.
 public function move(int $id,int $instructor,string $start,string $end): void {
     [$start,$end]=$this->dates($start,$end);

     if(!$this->isAvailable($instructor,$start,$end))
         throw new \RuntimeException('Het nieuwe tijdstip is niet beschikbaar.');

     if($this->hasOverlap($instructor,$start,$end,$id))
         throw new \RuntimeException('Het nieuwe tijdstip overlapt met een andere les.');

     $s=$this->db->prepare("UPDATE lessons SET start_at=?,end_at=?,status='confirmed' WHERE id=? AND instructor_id=?");
     $s->execute([$start,$end,$id,$instructor]);

     if(!$s->rowCount())
         throw new \RuntimeException('Les niet gevonden.');
 }

 // Hiermee kan de instructeur de locaties en status van een les aanpassen.
 // Wanneer een les voor het eerst op 'completed' wordt gezet, wordt het lestegoed afgetrokken.
 public function updateDetails(int $id,int $instructor,string $startLocation,string $endLocation,string $status): void {
     if(mb_strlen($startLocation)>255||mb_strlen($endLocation)>255)
         throw new \RuntimeException('De locatie is te lang.');

     $allowed=['confirmed','completed','cancelled'];

     if(!in_array($status,$allowed,true))
         throw new \RuntimeException('Ongeldige lesstatus.');

     $this->db->beginTransaction();

     try{
         $s=$this->db->prepare('SELECT * FROM lessons WHERE id=? AND instructor_id=? FOR UPDATE');
         $s->execute([$id,$instructor]);
         $l=$s->fetch();

         if(!$l)
             throw new \RuntimeException('Les niet gevonden.');

         $old=$l['status'];

         $this->db->prepare('UPDATE lessons SET start_location=?,end_location=?,status=? WHERE id=?')->execute([$startLocation,$endLocation,$status,$id]);

         // Trek alleen lestegoed af wanneer de les voor het eerst wordt afgerond.
         // Hierdoor wordt het lestegoed niet meerdere keren voor dezelfde les afgetrokken.
         if($status==='completed' && $old!=='completed'){
             $mins=(int)((strtotime($l['end_at'])-strtotime($l['start_at']))/60);

             $c=$this->db->prepare('UPDATE lesson_credits SET remaining_minutes=GREATEST(0,remaining_minutes-?) WHERE user_id=?');
             $c->execute([$mins,$l['student_id']]);
         }

         $this->db->commit();

     }catch(\Throwable $e){
         $this->db->rollBack();
         throw $e;
     }
 }
} //LessonService regelt de logica van de rijlessen. Het controleert bijvoorbeeld of een instructeur beschikbaar is, 
// voorkomt dubbele lessen, controleert het 
// lestegoed en laat een instructeur een les bevestigen of verplaatsen.”