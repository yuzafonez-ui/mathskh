<?php
$host = 'localhost';
$dbname = 'mathskh_db';
$username = 'root'; // Change to your database username
$password = '';     // Change to your database password

try {
    // Connect to MySQL
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Set PDO error mode to exception for better debugging
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}
?>
