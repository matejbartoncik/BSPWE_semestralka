<?php
session_start();

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

$loggedIn = $_SESSION['admin_logged_in'] ?? false;

if (isset($_POST['login'])) {
    if ($_POST['password'] === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
        header("Location: index.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="cs">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | Hosting</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="container">
        <h1>🚀 Hosting Admin</h1>

        <?php if (!$loggedIn): ?>
            <div class="card">
                <h2>Přihlášení</h2>
                <form method="post">
                    <input type="password" name="password" placeholder="Zadejte heslo" required>
                    <button type="submit" name="login">Vstoupit</button>
                </form>
            </div>
        <?php else: ?>
            <div class="card">
                <h2>Vytvořit nový hosting</h2>
                <p>Zadejte název pro novou složku, databázi a FTP.</p>
                <form action="create_hosting.php" method="post">
                    <input type="text" name="customer_name" placeholder="Např. alfa, beta, web1" required>
                    <button type="submit" class="btn-create">Vytvořit hosting</button>
                </form>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    ✅ Hosting pro <b><?= htmlspecialchars($_GET['success']) ?></b> byl úspěšně vytvořen!
                </div>
            <?php endif; ?>

            <div class="footer">
                <a href="?logout=1" class="btn-logout">Odhlásit se</a>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>