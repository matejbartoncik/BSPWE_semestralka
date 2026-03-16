<?php
session_start();
if (!($_SESSION['admin_logged_in'] ?? false)) {
    die("Nepovolený přístup.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['customer_name'])) {
    $customer = preg_replace('/[^a-z0-9_-]/', '', strtolower($_POST['customer_name']));
    $basePath = "/srv/www/" . $customer . "/public";

    // Inicializace proměnných pro info soubor
    $dbInfo = "Databáze nebyla vytvořena.";
    $ftpInfo = "FTP nebylo nastaveno.";

    if (!is_dir($basePath)) {
        // 1. VYTVOŘENÍ SLOŽEK
        mkdir($basePath, 0777, true);
        $welcomeContent = "<?php echo 'Vítejte na webu zákazníka: " . $customer . "'; ?>";
        file_put_contents($basePath . "/index.php", $welcomeContent);

        // 2. VYTVOŘENÍ DATABÁZE
        $dbHost = 'db';
        $dbRootPass = 'change_me_root';
        $newDbName = "cust" . $customer . "db";
        $newDbUser = "cust" . $customer;
        $newDbPass = bin2hex(random_bytes(8));

        try {
            $pdo = new PDO("mysql:host=$dbHost", "root", $dbRootPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("CREATE DATABASE `$newDbName`;");
            $pdo->exec("CREATE USER '$newDbUser'@'%' IDENTIFIED BY '$newDbPass';");
            $pdo->exec("GRANT ALL PRIVILEGES ON `$newDbName`.* TO '$newDbUser'@'%';");

            $dbInfo = "DB Name: $newDbName\nUser: $newDbUser\nPass: $newDbPass";
        } catch (PDOException $e) {
            $dbInfo = "Chyba při vytváření DB: " . $e->getMessage();
        }

        // 3. PŘÍPRAVA FTP ÚDAJŮ
        $ftpPass = bin2hex(random_bytes(6));
        $ftpInfo = "Host: localhost (port 2121)\nUser: $customer\nPass: $ftpPass";

        // ZÁPIS DO SOUBORU
        $fullInfo = "--- DATABASE ---\n$dbInfo\n\n--- FTP ---\n$ftpInfo";
        file_put_contents("/srv/www/" . $customer . "/db_credentials.txt", $fullInfo);

        // PŘESMĚROVÁNÍ (teď už proběhne bez chyb)
        header("Location: index.php?success=" . $customer);
        exit;
    } else {
        die("Chyba: Hosting již existuje.");
    }
}
