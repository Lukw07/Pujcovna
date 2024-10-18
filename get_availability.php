<?php
// Připojení k databázi
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pujcovna_db";

$conn = new mysqli($servername, $username, $password, $dbname);

// Zkontrolujte připojení
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Funkce pro získání dostupnosti položky
function getAvailability($conn, $itemId, $hourId, $dayId) {
    $availabilitySql = "
        SELECT COALESCE(SUM(quantity), 0) AS total_quantity
        FROM reservations
        WHERE item_id = ? AND hour_id = ? AND day_id = ?
    ";
    $stmt = $conn->prepare($availabilitySql);
    $stmt->bind_param("iii", $itemId, $hourId, $dayId);
    $stmt->execute();
    $availabilityResult = $stmt->get_result();
    return $availabilityResult->fetch_assoc()['total_quantity'];
}

// Funkce pro získání celkového množství položek
function getTotalItems($conn, $itemId) {
    $totalItemsSql = "SELECT quantity FROM items WHERE id = ?";
    $stmtTotal = $conn->prepare($totalItemsSql);
    $stmtTotal->bind_param("i", $itemId);
    $stmtTotal->execute();
    $totalItemsResult = $stmtTotal->get_result();
    return $totalItemsResult->fetch_assoc()['quantity'];
}

if (isset($_GET['item_id'])) {
    $itemId = intval($_GET['item_id']); // Převod na integer pro bezpečnost

    // Dotaz na získání dnů z tabulky days
    $daysSql = "SELECT id, day_name FROM days";
    $daysResult = $conn->query($daysSql);

    // Dotaz na získání hodin z tabulky hours
    $hoursSql = "
        SELECT id, 
               TIME_FORMAT(Start, '%H:%i') AS hour_start, 
               TIME_FORMAT(End, '%H:%i') AS hour_end 
        FROM hours
    ";
    $hoursResult = $conn->query($hoursSql);

    // Vytvoření prázdné tabulky
    echo "<table border='1'>";

    // První řádek tabulky s hodinami
    echo "<tr><th>Den</th>";
    while ($hour = $hoursResult->fetch_assoc()) {
        echo "<th>" . htmlspecialchars($hour['hour_start']) . " - " . htmlspecialchars($hour['hour_end']) . "</th>";
    }
    echo "</tr>";

    // Získání dnešního data a nastavení na pondělí
    $today = new DateTime();
    $today->modify('this week'); // Nastaví na začátek týdne (pondělí)
    $daysArray = [];
    
    // Získání dnů pro tabulku
    while ($day = $daysResult->fetch_assoc()) {
        $daysArray[] = $day; // Uložení dnů do pole
    }

    // Iterace přes dny a vytvoření řádků
    foreach ($daysArray as $day) {
        echo "<tr>";
        // Získání data pro aktuální den
        $date = clone $today; // Vytvoření kopie pro manipulaci
        $date->modify('+' . (array_search($day['day_name'], array_column($daysArray, 'day_name'))) . ' days');
        $formattedDate = $date->format('j.n.'); // Formátování data (bez roku)

        echo "<td>" . htmlspecialchars($day['day_name']) . " " . $formattedDate . "</td>";

        // Iterace přes jednotlivé hodiny
        $hoursResult->data_seek(0);  // Vrátíme se na začátek výsledku hodin
        while ($hour = $hoursResult->fetch_assoc()) {
            // Získání dostupnosti a celkového množství
            $totalItems = getTotalItems($conn, $itemId);
            $totalQuantity = getAvailability($conn, $itemId, $hour['id'], $day['id']);
            $availableQuantity = $totalItems - $totalQuantity;

            // Barevná indikace podle dostupnosti
            if ($availableQuantity == $totalItems) {
                $class = "available";  // Zelená
            } elseif ($availableQuantity > $totalItems * 0.25) {
                $class = "partial";  // Oranžová
            } else {
                $class = "unavailable";  // Červená
            }

            echo "<td class='$class' data-hour-id='" . htmlspecialchars($hour['id']) . "'>" . htmlspecialchars($availableQuantity) . "</td>";
        }

        echo "</tr>";
    }

    echo "</table>";
} else {
    echo "<p>Nebylo vybráno žádné ID položky.</p>";
}

$conn->close();
?>