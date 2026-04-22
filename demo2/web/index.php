<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$dbError = null;
$formError = null;
$entries = [];
$success = isset($_GET['ok']);

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'],
        (int) $config['port'],
        $config['name'],
        $config['charset']
    );

    $pdo = new PDO(
        $dsn,
        $config['user'],
        $config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $author = trim((string) ($_POST['author'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));

        if ($author === '' || $message === '') {
            $formError = 'Vypln jmeno i zpravu.';
        } elseif (strlen($author) > 80 || strlen($message) > 1000) {
            $formError = 'Jmeno nebo zprava jsou moc dlouhe.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO guestbook_entries (author, message) VALUES (:author, :message)'
            );
            $stmt->execute([
                ':author' => $author,
                ':message' => $message,
            ]);

            header('Location: index.php?ok=1');
            exit;
        }
    }

    $stmt = $pdo->query(
        'SELECT id, author, message, created_at FROM guestbook_entries ORDER BY id DESC LIMIT 50'
    );
    $entries = $stmt->fetchAll();
} catch (Throwable $exception) {
    $dbError = 'DB chyba: zkontroluj config.php a import schema.sql v phpMyAdmin.';
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>demo2 - PHP + DB</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="page">
        <header class="hero card">
            <p class="tag">demo2</p>
            <h1>Jednoduchy PHP web s databazi</h1>
            <p>
                Tohle demo uklada zpravy do MySQL/MariaDB tabulky <b>guestbook_entries</b>.
                DDL najdes v souboru <b>schema.sql</b>.
            </p>
        </header>

        <?php if ($dbError !== null): ?>
            <section class="card alert alert-error">
                <strong><?= h($dbError) ?></strong>
            </section>
        <?php endif; ?>

        <?php if ($success): ?>
            <section class="card alert alert-success">
                <strong>Zprava byla ulozena do databaze.</strong>
            </section>
        <?php endif; ?>

        <?php if ($formError !== null): ?>
            <section class="card alert alert-error">
                <strong><?= h($formError) ?></strong>
            </section>
        <?php endif; ?>

        <section class="grid">
            <article class="card">
                <h2>Nova zprava</h2>
                <form method="post" class="form">
                    <label>
                        Jmeno
                        <input type="text" name="author" maxlength="80" required>
                    </label>
                    <label>
                        Zprava
                        <textarea name="message" rows="6" maxlength="1000" required></textarea>
                    </label>
                    <button type="submit">Ulozit do DB</button>
                </form>
            </article>

            <article class="card">
                <h2>DB nastaveni</h2>
                <ul class="meta">
                    <li><span>Host:</span> <b><?= h((string) $config['host']) ?></b></li>
                    <li><span>Port:</span> <b><?= h((string) $config['port']) ?></b></li>
                    <li><span>Databaze:</span> <b><?= h((string) $config['name']) ?></b></li>
                    <li><span>Uzivatel:</span> <b><?= h((string) $config['user']) ?></b></li>
                </ul>
                <p class="hint">
                    Pri produkcnim pouziti uprav <b>config.php</b> nebo pouzij env promenne.
                </p>
            </article>
        </section>

        <section class="card table-card">
            <h2>Posledni zpravy (<?= h((string) count($entries)) ?>)</h2>

            <?php if ($entries === []): ?>
                <p>Zatim tu nejsou zadne zaznamy.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Jmeno</th>
                                <th>Zprava</th>
                                <th>Vytvoreno</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $entry): ?>
                                <tr>
                                    <td><?= h((string) $entry['id']) ?></td>
                                    <td><?= h((string) $entry['author']) ?></td>
                                    <td><?= nl2br(h((string) $entry['message'])) ?></td>
                                    <td><?= h((string) $entry['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
