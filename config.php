<?php
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$db = getenv('DB_NAME') ?: 'gestion_notes_inptic';

$conn = mysqli_connect($host, $user, $pass, $db, (int) $port);

if (!$conn) {
    die("La connexion a échoué : " . mysqli_connect_error());
}
?>