<?php
session_start();
include 'db_connection.php'; // Soubor pro připojení k databázi

// Generování CSRF tokenu pouze při prvním načtení stránky
if ($_SERVER["REQUEST_METHOD"] == "GET" && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Kontrola CSRF tokenu
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Neplatný CSRF token.");
    }

    // Vyhledání uživatele v databázi
    $sql = "SELECT * FROM Users WHERE Username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        // Ověření hesla
        if (hash("sha256", $password) == $user['PasswordHash']) {
            // Uložení uživatelského ID a uživatelského jména do session
            $_SESSION['user_id'] = $user['UserID'];
            $_SESSION['username'] = $user['Username']; // Přidáno uložení uživatelského jména do session
            header("Location: /pujcovna/main.php");
            exit();
        } else {
            // Špatné heslo
            $_SESSION['notification'] = [
                'type' => 'error',
                'message' => 'Špatné heslo.'
            ];
        }
    } else {
        // Uživatel nenalezen
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Uživatel nenalezen.'
        ];
    }

    // Přesměrování po odeslání formuláře, aby se zabránilo opakovanému odeslání
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Přihlášení do systému</title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="background">
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
    <form method="POST" action="login.php">
    <h3>Přihlášení do rezervačního systému</h3>

    <div id="notification" class="notification">
        <?php
        if (isset($_SESSION['notification'])) {
            echo '<div class="notification ' . $_SESSION['notification']['type'] . '">' . htmlspecialchars($_SESSION['notification']['message']) . '</div>';
            unset($_SESSION['notification']);
        }
        ?>
    </div>

    <label for="username">Uživatelské jméno</label>
    <input type="text" placeholder="Jméno" id="username" name="username" required>

    <label for="password">Heslo</label>
    <input type="password" placeholder="Heslo" id="password" name="password" required>

    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

    <button type="submit" onclick="show()">Přihlásit se</button>
</form>

<script>
    function show() {
        var notification = document.getElementsByClassName('notification')[0]; // Přístup k prvnímu elementu
        notification.style.display = 'block'; // Zajistíme, že element je viditelný
        notification.style.opacity = '1'; // Změníme opacity na 1 pro zobrazení
    }
</script>
</body>
</html>