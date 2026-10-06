<?php

// Databasegegevens voor de verbinding.
// Eerst wordt gekeken naar environment variables.
// Als die niet bestaan, worden de standaard lokale gegevens gebruikt.
$host=getenv('DB_HOST') ?: 'localhost';
$dbname=getenv('DB_NAME') ?: 'driveplan';
$user=getenv('DB_USER') ?: 'root';
$pass=getenv('DB_PASS') ?: '';

// Maakt de MySQL-verbinding met de juiste database en UTF-8 tekenset.
$dsn="mysql:host={$host};dbname={$dbname};charset=utf8mb4";

// Maakt met PDO de verbinding met de database.
// Fouten worden als exceptions gegeven en resultaten worden als arrays opgehaald.
return new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);

//Dit bestand maakt de verbinding tussen DrivePlan en de MySQL-database. Hier staan de gegevens die PHP nodig heeft om de database te kunnen gebruiken.”
//PDO gebruik ik om vanuit PHP veilig met de database te werken en SQL-query's uit te voeren