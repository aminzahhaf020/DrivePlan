# DrivePlan – Rijschool RoadWise

DrivePlan is de KT1-W3 realisatie van de eerder gemaakte planning en het ontwerp.

## Gerealiseerde functies
- FE-01: inloggen als leerling of instructeur + rolcontrole
- FE-02: accountgegevens en contactvoorkeur aanpassen
- FE-03: beschikbare momenten van instructeurs bekijken
- FE-04: rijles aanvragen
- FE-05: instructeur beheert beschikbaarheid en blokkades
- FE-06: instructeur bevestigt of verplaatst een les
- FE-07: start- en eindlocatie opslaan
- FE-08: overlap van bevestigde lessen voorkomen
- FE-09: lestegoed tonen en bij afronding afboeken
- FE-10: succes- en foutmeldingen bij acties

## Techniek en beveiliging
PHP 8.1+, MySQL/MariaDB, PDO prepared statements, password_hash/password_verify, server-side rolcontrole, CSRF-token, server-side validatie en HTML escaping.

## Installatie lokaal/PLESK
1. Maak een lege MySQL/MariaDB database.
2. Importeer `database/schema.sql`.
3. Importeer daarna `database/demo_data.sql` voor testaccounts.
4. Stel DB_HOST, DB_NAME, DB_USER en DB_PASS in als environment variables, of pas voor je eigen omgeving `config/database.php` aan. Zet echte wachtwoorden nooit in GitHub.
5. Stel de document root van het domein/subdomein in op de map `public`.
6. Open de website in de browser.

## Demoaccounts
- Leerling: `leerling@driveplan.test`
- Instructeur: `instructeur@driveplan.test`
- Wachtwoord voor beide: `DrivePlan123!`

## Korte demoflow
1. Log in als instructeur en voeg beschikbaarheid toe.
2. Log uit en log in als leerling.
3. Bekijk lestegoed en beschikbare momenten en vraag een les aan.
4. Log in als instructeur, bevestig/verplaats de aanvraag en vul locaties in.
5. Zet de les na uitvoering op `completed`; het lestegoed wordt dan één keer verminderd.
6. Probeer een overlappende les of een pagina van de verkeerde rol om foutafhandeling/rechten te tonen.

## GitHub
Gebruik één centrale repository. Commit verspreid over de projectperiode. Markeer de definitieve versie bijvoorbeeld met tag `v1.0-kt1-w3`.

## Belangrijk
Controleer deze code zelf, test alle functies in jouw PLESK-omgeving en zorg dat je iedere regel/keuze kunt uitleggen tijdens de beoordeling.
