<?php
$host=getenv('DB_HOST') ?: 'localhost';
$dbname=getenv('DB_NAME') ?: 'driveplan';
$user=getenv('DB_USER') ?: 'root';
$pass=getenv('DB_PASS') ?: '';
$dsn="mysql:host={$host};dbname={$dbname};charset=utf8mb4";
return new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
