<?php
session_start();
// Tento soubor rezervuje produkty
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pujcovna_db";

// Vytvoření připojení k databázi
$conn = new mysqli($servername, $username, $password, $dbname);

// Kontrola připojení
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Získání POST parametrů z AJAX požadavku
$item_id = $_POST['item_id'];
$hour_id = $_POST['hour_id'];
$day_id = $_POST['day_id'];
$quantity = $_POST['quantity'];
$user_id = $_SESSION['user_id']; // Předpokládáme, že je uživatel přihlášen a má ID v session

// Zkontrolování, zda jsou všechny požadované hodnoty dostupné
if (!$item_id || !$hour_id || !$quantity || !$day_id) {
    echo "Neplatné údaje pro rezervaci.";
    exit();
}

// Získání maximální dostupnosti položky z tabulky items
$itemSql = "SELECT quantity FROM items WHERE id = ?";
$stmt = $conn->prepare($itemSql);
$stmt->bind_param("i", $item_id);
$stmt->execute();
$stmt->bind_result($total_quantity);
$stmt->fetch();
$stmt->close();

// Získání aktuální rezervace pro daný den a hodinu
$availabilitySql = "SELECT SUM(quantity) as reserved_quantity FROM reservations WHERE item_id = ? AND day_id = ? AND hour_id = ?";
$stmt = $conn->prepare($availabilitySql);
$stmt->bind_param("iii", $item_id, $day_id, $hour_id);
$stmt->execute();
$stmt->bind_result($reserved_quantity);
$stmt->fetch();
$stmt->close();

// Vypočítání dostupných položek
$available_quantity = $total_quantity - $reserved_quantity;

// Kontrola, zda je dostatečný počet položek k dispozici
if ($available_quantity < $quantity) {
    echo "Nedostatečná dostupnost položek. K dispozici je pouze " . $available_quantity . " kusů.";
    exit();
}

// Vložení rezervace do databázee
$reserveSql = "INSERT INTO reservations (id, user_id, item_id, quantity, reserved_at, hour_id, day_id) VALUES (null, ?, ?, ?, NOW(), ?, ?)";
$stmt = $conn->prepare($reserveSql);
$stmt->bind_param("iiiii", $user_id, $item_id, $quantity, $hour_id, $day_id);

if ($stmt->execute()) {
    echo "Rezervace byla úspěšně provedena!";
} else {
    echo "Chyba při provádění rezervace: " . $conn->error;
}

$stmt->close();
$conn->close();