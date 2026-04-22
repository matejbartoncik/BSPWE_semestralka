<?php
declare(strict_types=1);

$quotes = [
    'Maly krok kazdy den udela velky rozdil.',
    'Jednoduchost je superpower moderniho webu.',
    'Dobre navrzene demo rekne vic nez dlouhy popis.',
    'Kod je nejlepsi, kdyz je citelny i po mesici.',
];

$quote = $quotes[array_rand($quotes)];
$today = date('d.m.Y');
$time = date('H:i');
$year = date('Y');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo PHP Web</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="bg-shape bg-shape-a"></div>
    <div class="bg-shape bg-shape-b"></div>

    <main class="page">
        <section class="hero card">
            <p class="eyebrow">Demo projekt</p>
            <h1>Velice jednoduchy PHP web</h1>
            <p class="lead">
                Tohle je zakladni ukazka stranky s PHP a CSS. Vypisuje aktualni datum, cas a nahodnou hlasku.
            </p>

            <div class="quick-info">
                <div class="pill">
                    <span>Dnes</span>
                    <strong><?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
                <div class="pill">
                    <span>Cas serveru</span>
                    <strong><?= htmlspecialchars($time, ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
                <div class="pill">
                    <span>PHP</span>
                    <strong><?= htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
            </div>
        </section>

        <section class="grid">
            <article class="card">
                <h2>Co to umi</h2>
                <ul>
                    <li>Jednoduchy layout s modernim vzhledem</li>
                    <li>Responzivni rozlozeni pro mobil i desktop</li>
                    <li>Zakladni dynamika pomoci PHP</li>
                </ul>
            </article>

            <article class="card quote-card">
                <h2>Nahodna hlaska</h2>
                <blockquote>
                    "<?= htmlspecialchars($quote, ENT_QUOTES, 'UTF-8') ?>"
                </blockquote>
            </article>
        </section>

        <footer class="footer">
            <p>Demo web | <?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?></p>
        </footer>
    </main>
</body>
</html>
