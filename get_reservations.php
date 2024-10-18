<?php
session_start();
require 'db_connection.php'; // Připojení k databázi

$hour_id = $_GET['hour_id'];
$reservations = [];

// Opravený dotaz s JOIN
$query = "SELECT u.username, r.quantity 
          FROM reservations r 
          JOIN users u ON r.user_id = u.id 
          WHERE r.hour_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $hour_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $reservations[] = $row;
}

// Získejte maximální dostupnost
$maxQuantity = 5; // Změňte podle vaší logiky

// Vytvořte odpověď
$response = [
    'reservations' => $reservations,
    'maxQuantity' => $maxQuantity
];

// Odeslat odpověď jako JSON
header('Content-Type: application/json'); // Nastavení správného typu obsahu
echo json_encode($response);
?>