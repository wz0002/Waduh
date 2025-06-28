<?php
$host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'bouquet_shop';

$conn = new mysqli($host, $db_user, $db_pass, $db_name);

if ($conn) {
    $conn->set_charset('utf8mb4');
}

if ($conn->connect_error) {
    die("Maaf, situs sedang mengalami gangguan teknis. Silakan coba lagi nanti.");
}
