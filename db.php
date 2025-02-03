<?php

$host = "localhost";
$username = "root";
$password = "";
$dbname = "sudoku_game";

// Create a DSN string for a normal MySQL connection without charset
$conn = "mysql:host=$host;dbname=$dbname";

try {
    // Create a PDO instance with the connection details
    $db = new PDO($conn, $username, $password);

    // Uncomment the following lines if you want to test the connection
    // $stmt = $db->query("SELECT VERSION()");
    // print ($stmt->fetch()[0]);

    // echo "Connection successful!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
