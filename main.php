<?php
session_start();

if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

// Zakázání cachování
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");

// Připojení k databázi
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pujcovna_db";
// Vytvoření připojení
$conn = new mysqli($servername, $username, $password, $dbname);

// Zkontrolování připojení
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Dotaz na získání hodin z tabulky hours
$hoursSql = "SELECT id, TIME_FORMAT(Start, '%H:%i') AS hour_start, TIME_FORMAT(End, '%H:%i') AS hour_end FROM hours";
$hoursResult = $conn->query($hoursSql);

// Zkontroluj, zda dotaz vrátil výsledky
if (!$hoursResult) {
    die("Chyba dotazu na hodiny: " . $conn->error);
}

// Dotaz na získání dnů z tabulky days
$daysSql = "SELECT id, day_name FROM days";
$daysResult = $conn->query($daysSql);

// Zkontroluj, zda dotaz vrátil výsledky
if (!$daysResult) {
    die("Chyba dotazu na dny: " . $conn->error);
}

// Dotaz na získání položek (items)
$itemsSql = "SELECT id, name FROM items";
$itemsResult = $conn->query($itemsSql);

// Zkontroluj, zda dotaz vrátil výsledky
if (!$itemsResult) {
    die("Chyba dotazu na položku: " . $conn->error);
}

// Funkce pro získání data pro aktuální týdenní den (Po-Pá)
function getDateForDay($dayIndex) {
    $currentDate = new DateTime();
    $currentDate->modify("next " . $dayIndex);
    return $currentDate->format('Y-m-d');
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezervace</title>
    <link rel="stylesheet" href=" main.css">
    <style>
        .calendar {
            max-width: 600px;
            margin: 0 auto;
            font-family: Arial, sans-serif;
        }

        .day {
            margin-bottom: 20px;
        }

        .date {
            font-weight: bold;
            margin-bottom: 10px;
        }

        .hour {
            padding: 10px;
            border: 1px solid #ddd;
            margin-bottom: 5px;
            position: relative;
            cursor: pointer;
        }

        .hour:hover {
            background-color: #f0f0f0;
        }

        .hour::after {
            content: attr(data-details);
            position: absolute;
            left: 110%;
            top: 50%;
            transform: translateY(-50%);
            background-color: white;
            padding: 5px;
            border: 1px solid #ddd;
            display: none;
            width: 200px;
            box-shadow: 0px 0px 10px rgba(0,0,0,0.1);
        }

        .hour:hover::after {
            display: block;
        }

        .highlight-current-hour {
            background-color: #ffeb3b;
        }

        .reservation-details {
            position: absolute;
            background-color: #fff;
            border: 1px solid #ddd;
            padding: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1000;
            display: none;
        }

        .loader {
            border: 16px solid #f3f3f3;
            border-top: 16px solid #3498db;
            border-radius: 50%;
            width: 120px;
            height: 120px;
            animation: spin 2s linear infinite;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .reservation-dialog {
            position: fixed;
            background-color: #fff;
            border: 1px solid #ddd;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1000;
            display: none;
            max-width: 300px;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
        }
    </style>
</head>
<body>
<header>
    <img src="z-kamenick-dn-ii-high-resolution-logo-transparent.png" alt="Logo">
    <div class="username"><?php echo htmlspecialchars($_SESSION["username"]); ?></div>
<div class="search">
    <label for="item">Vyberte položku:</label>
    <select id="item">
        <option value="" disabled selected>Vyberte položku</option>
        <?php while ($item = $itemsResult->fetch_assoc()): ?>
            <option value="<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['name']); ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <a href="logout.php" class="logout-button">Odhlásit se</a>
</header>


    <!-- Loader pro načítání -->
    <div id="loader" class="loader" style="display: none;"></div>

    <h2>Dostupnost</h2>
    <div id="availability">
        <!-- Sem se bude načítat tabulka dostupnosti pomocí AJAXu -->
    </div>
</div>

<div id="reservation-details" class="reservation-details"></div>
<div id="reservation-dialog" class="reservation-dialog"></div>

<script>
   document.addEventListener('DOMContentLoaded', function() {
    setInterval(function() {
        console.log("1");
        var xhr = new XMLHttpRequest();
        var itemId = document.getElementById('item').value; 
        xhr.open('GET', 'get_availability.php?item_id=' + itemId, true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                var response = xhr.responseText;
                var availabilityDiv = document.getElementById('availability');
                availabilityDiv.innerHTML = response;

                highlightCurrentHour(availabilityDiv); // Highlight immediately after loading

                var rows = availabilityDiv.getElementsByTagName('tr');
                for (let i2 = 1; i2 < rows.length; i2++) {
                    const dayId = i2;
                    var rows2 = rows[i2].getElementsByTagName('td');
                    for (let i = 1; i < rows2.length; i++) {
                        const hourId = rows2[i].getAttribute("data-hour-id");

                        rows2[i].addEventListener('mouseover', function(event) {
                            if (hourId) {
                                showReservationDetails(hourId, event);
                            }
                        });

                        rows2[i].addEventListener('mouseout', function() {
                            hideReservationDetails();
                        });

                        rows2[i].addEventListener('click', function() {
                            if (hourId && itemId) {
                                showReservationDialog(itemId, dayId, hourId);
                            }
                        });
                    }
                }
            } else {
                document.getElementById('availability').innerHTML = '<p>Chyba při načítání dostupnosti.</p>';
            }
            hideLoader();
        };
        xhr.onerror = function() {
            document.getElementById('availability').innerHTML = '<p>Chyba při načítání dostupnosti.</p>';
        };
        xhr.send();
    }, 10000);  // každých 10s(10000m)
});

document.getElementById('item').addEventListener('change', function() {
    var itemId = this.value;
    if (!itemId) return;

    showLoader();

    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'get_availability.php?item_id=' + itemId, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            var response = xhr.responseText;
            var availabilityDiv = document.getElementById('availability');
            availabilityDiv.innerHTML = response;

            highlightCurrentHour(availabilityDiv); // Zvýraznit hned po načtení

            var rows = availabilityDiv.getElementsByTagName('tr');
            for (var i2 = 1; i2 < rows.length; i2++){
                const dayId = i2;
                var rows2 = rows[i2].getElementsByTagName('td');
                for (var i = 1; i < rows2.length; i++){
                    const hourId = rows2[i].getAttribute("data-hour-id");
                    rows2[i].addEventListener('mouseover', function(event) {
                        if (hourId) {
                            showReservationDetails(hourId, event);
                        }
                    });

                    rows2[i].addEventListener('mouseout', function() {
                        hideReservationDetails();
                    });
                    rows2[i].addEventListener('click', function() {
                        if (hourId && itemId) {
                            showReservationDialog(itemId, dayId, hourId);
                        }
                    });
                }
            }
        } else {
            document.getElementById('availability').innerHTML = '<p>Chyba při načítání dostupnosti.</p>';
        }
        hideLoader();
    };
    xhr.send();
});

// Zvýraznění aktuální hodiny
function highlightCurrentHour(availabilityDiv) {
    var rows = availabilityDiv.getElementsByTagName('tr');
    var currentTime = new Date();
    var currentHours = currentTime.getHours();
    var currentMinutes = currentTime.getMinutes();
    var currentTimeInMinutes = currentHours * 60 + currentMinutes;

    for (var i = 1; i < rows[0].getElementsByTagName('th').length; i++) {
        var startHour, endHour;

        switch(i) {
            case 1: startHour = '7:55'; endHour = '8:40'; break;
            case 2: startHour = '8:50'; endHour = '9:35'; break;
            case 3: startHour = '9:55'; endHour = '10:40'; break;
            case 4: startHour = '10:50'; endHour = '11:35'; break;
            case 5: startHour = '11:45'; endHour = '12:30'; break;
            case 6: startHour = '12:40'; endHour = '13:25'; break;
            case 7: startHour = '13:30'; endHour = '14:15'; break;
            case 8: startHour = '14:20'; endHour = '15:05'; break;
        }
        if (!startHour || !endHour) continue;

        var [startHourStr, startMinutesStr] = startHour.split(':');
        var [endHourStr, endMinutesStr] = endHour.split(':');

        var startTimeInMinutes = parseInt(startHourStr) * 60 + parseInt(startMinutesStr);
        var endTimeInMinutes = parseInt(endHourStr) * 60 + parseInt(endMinutesStr);

        if (currentTimeInMinutes >= startTimeInMinutes && currentTimeInMinutes < endTimeInMinutes) {
            rows[0].getElementsByTagName('th')[i].classList.add('highlight-current-hour');
        } else {
            rows[0].getElementsByTagName('th')[i].classList.remove('highlight-current-hour');
        }
    }
} 

function showReservationDetails(hourId, event, id) {
    var detailsDiv = document.getElementById('reservation-details');
    detailsDiv.innerHTML = 'Načítání rezervací...'; // Zobrazte zprávu o načítání
    detailsDiv.style.top = event.clientY + 'px';
    detailsDiv.style.left = event.clientX + 'px';
    detailsDiv.style.display = 'block';

    // Získejte rezervace a aktualizujte zobrazení
    getReservationsForHour(hourId, detailsDiv);
}

function hideReservationDetails() {
    document.getElementById('reservation-details').style.display = 'none';
}

function showReservationDialog(itemId, dayId, hourId, maxQuantity) {
    var dialogDiv = document.getElementById('reservation-dialog');
    dialogDiv.innerHTML = '<p>Rezervace pro položku ' + itemId + ' v hodině ' + hourId + ' ve dni ' + dayId + '</p>';
    dialogDiv.innerHTML += '<label for="quantity">Počet položek:</label>';
    dialogDiv.innerHTML += '<input type="number" id="quantity" value="1" min="1" max="' + maxQuantity + '">'; // max podle dostupnosti
    dialogDiv.innerHTML += '<button onclick="reserve(' + itemId + ', ' + dayId + ', ' + hourId + ')">Rezervovat</button>';
    dialogDiv.innerHTML += '<button onclick="hideReservationDialog()">Zavřít</button>'; // Přidáno tlačítko pro zavření dialogu
    dialogDiv.style.display = 'block';
}

function hideReservationDialog() {
    document.getElementById('reservation-dialog').style.display = 'none';
}

function reserve(itemId, dayId, hourId) {
    var quantity = document.getElementById('quantity').value;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'reserve_item.php', true);
    xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            alert('Rezervace úspěšná!');
            hideReservationDialog(); // Skryjte dialog po úspěšné rezervaci
            document.getElementById('item').dispatchEvent(new Event('change')); // Aktualizuj dostupnost
        } else {
            alert('Chyba při rezervaci.');
        }
    };
    xhr.send('item_id=' + itemId + '&day_id=' + dayId + '&hour_id=' + hourId + '&quantity=' + quantity);
}

function getReservationsForHour(hourId, detailsDiv) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'get_reservations.php?hour_id=' + hourId, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            var response = JSON.parse(xhr.responseText);
            displayReservations(response.reservations, detailsDiv, response.maxQuantity);
        } else {
            console.error('Chyba při načítání rezervací.');
            detailsDiv.innerHTML = 'Nebyly nalezeny žádné rezervace.';
        }
    };
    xhr.send();
}

function displayReservations(reservations, detailsDiv, maxQuantity) {
    detailsDiv.innerHTML = 'Rezervace pro danou hodinu:<br>';
    let totalQuantity = 0; // Sčítáme rezervace
    reservations.forEach(function(reservation) {
        detailsDiv.innerHTML += 'Uživatel: ' + reservation.username + ', Počet: ' + reservation.quantity + '<br>';
        totalQuantity += reservation.quantity;
    });

    // Nastavte maximální dostupnost na základě celkového počtu
    showReservationDialog(itemId, dayId, hourId, maxQuantity - totalQuantity); // Zavolejte dialog s maximem
}

function showLoader() {
    document.getElementById('loader').style.display = 'block';
}

function hideLoader() {
    document.getElementById('loader').style.display = 'none';
}
</script>

</body>
</html>