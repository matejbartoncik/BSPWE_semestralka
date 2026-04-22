<?php
declare(strict_types=1);

session_start();

const HOSTINGS_REGISTRY_PATH = '/srv/www/.hostings.json';
const WEB_ROOT_PATH = '/srv/www';
const ADMIN_PASSWORD = 'admin123';

function load_hostings(): array
{
    if (!file_exists(HOSTINGS_REGISTRY_PATH)) {
        return [];
    }

    $content = file_get_contents(HOSTINGS_REGISTRY_PATH);
    if ($content === false || trim($content) === '') {
        return [];
    }

    $hostings = json_decode($content, true);

    return is_array($hostings) ? $hostings : [];
}

function set_flash(string $type, string $message, array $details = []): void
{
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message,
        'details' => $details,
    ];
}

function pull_flash(): ?array
{
    $flash = $_SESSION['flash_message'] ?? null;
    unset($_SESSION['flash_message']);

    return is_array($flash) ? $flash : null;
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function sanitize_customer_name(string $value): string
{
    $normalized = strtolower(trim($value));
    $normalized = preg_replace('/[^a-z0-9_-]+/', '', $normalized) ?? '';
    $normalized = trim($normalized, '_-');

    return substr($normalized, 0, 24);
}

function build_base_url(?string $port = null): string
{
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $hostWithoutPort = preg_replace('/:\d+$/', '', $host) ?: 'localhost';

    if ($port === null) {
        return sprintf('%s://%s', $scheme, $host);
    }

    return sprintf('%s://%s:%s', $scheme, $hostWithoutPort, $port);
}

function build_customer_url(string $customer): string
{
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $port = (string) ($_SERVER['SERVER_PORT'] ?? '');
    $defaultPort = $isHttps ? '443' : '80';
    $portSuffix = ($port !== '' && $port !== $defaultPort) ? ':' . $port : '';

    return sprintf('%s://%s.localhost%s/', $scheme, rawurlencode($customer), $portSuffix);
}

function build_phpmyadmin_url(): string
{
    return build_base_url('8081') . '/';
}

function customer_public_path(string $customer): string
{
    return WEB_ROOT_PATH . '/' . $customer . '/public';
}

function normalize_file_upload(array $fileInput): array
{
    if (is_array($fileInput['name'] ?? null)) {
        return $fileInput;
    }

    return [
        'name' => [$fileInput['name'] ?? ''],
        'tmp_name' => [$fileInput['tmp_name'] ?? ''],
        'error' => [$fileInput['error'] ?? UPLOAD_ERR_NO_FILE],
        'full_path' => [$fileInput['full_path'] ?? ($fileInput['name'] ?? '')],
    ];
}

function sanitize_relative_upload_path(string $path): ?string
{
    $path = str_replace('\\', '/', trim($path));
    $path = preg_replace('#/+#', '/', $path) ?? $path;
    $path = ltrim($path, '/');

    if ($path === '' || str_contains($path, "\0")) {
        return null;
    }

    $segments = [];

    foreach (explode('/', $path) as $segment) {
        $segment = trim($segment);

        if ($segment === '' || $segment === '.' || $segment === '..') {
            return null;
        }

        $cleanSegment = preg_replace('/[^A-Za-z0-9._-]+/', '_', $segment) ?? '';
        if ($cleanSegment === '') {
            return null;
        }

        $segments[] = $cleanSegment;
    }

    return implode('/', $segments);
}

function strip_shared_root_folder(array $paths): array
{
    if ($paths === []) {
        return [];
    }

    $firstSegments = [];
    foreach ($paths as $path) {
        $parts = explode('/', $path);
        if (count($parts) < 2) {
            return $paths;
        }
        $firstSegments[] = $parts[0];
    }

    if (count(array_unique($firstSegments)) !== 1) {
        return $paths;
    }

    $stripped = [];
    foreach ($paths as $path) {
        $parts = explode('/', $path);
        array_shift($parts);
        $stripped[] = implode('/', $parts);
    }

    return $stripped;
}

function empty_directory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
}

function upload_site_files(array $fileInput, string $destination, bool $clearExisting = false): int
{
    $fileInput = normalize_file_upload($fileInput);
    $uploads = [];

    foreach ($fileInput['name'] as $index => $name) {
        $error = $fileInput['error'][$index] ?? UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Nahravani souboru selhalo.');
        }

        $relativePath = sanitize_relative_upload_path((string) ($fileInput['full_path'][$index] ?? $name));
        if ($relativePath === null) {
            throw new RuntimeException('Jeden z nahravanych souboru ma neplatnou cestu.');
        }

        $uploads[] = [
            'tmp_name' => $fileInput['tmp_name'][$index],
            'relative_path' => $relativePath,
        ];
    }

    if ($uploads === []) {
        throw new RuntimeException('Nevybral(a) jsi zadne soubory.');
    }

    $normalizedPaths = strip_shared_root_folder(array_column($uploads, 'relative_path'));
    foreach ($normalizedPaths as $index => $relativePath) {
        $uploads[$index]['relative_path'] = $relativePath;
    }

    if (!is_dir($destination)) {
        mkdir($destination, 0777, true);
    }

    if ($clearExisting) {
        empty_directory($destination);
    }

    $storedFiles = 0;

    foreach ($uploads as $upload) {
        $targetPath = $destination . '/' . $upload['relative_path'];
        $targetDir = dirname($targetPath);

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        if (!move_uploaded_file($upload['tmp_name'], $targetPath)) {
            throw new RuntimeException('Nepodarilo se ulozit vsechny soubory.');
        }

        $storedFiles++;
    }

    return $storedFiles;
}

function list_customer_files(string $directory, int $limit = 120): array
{
    if (!is_dir($directory)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }

        $relativePath = str_replace('\\', '/', substr($item->getPathname(), strlen(rtrim($directory, '/\\')) + 1));

        $files[] = [
            'path' => $relativePath,
            'size' => $item->getSize(),
            'modified' => date('d.m.Y H:i', $item->getMTime()),
        ];

        if (count($files) >= $limit) {
            break;
        }
    }

    usort($files, static fn(array $left, array $right): int => strcmp($left['path'], $right['path']));

    return $files;
}

function format_bytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $size = (float) $bytes;
    $unitIndex = 0;

    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size /= 1024;
        $unitIndex++;
    }

    return number_format($size, $unitIndex === 0 ? 0 : 1, ',', ' ') . ' ' . $units[$unitIndex];
}

if (isset($_GET['logout'])) {
    $target = $_GET['logout'];

    if ($target === 'admin') {
        unset($_SESSION['admin_logged_in']);
    } elseif ($target === 'customer') {
        unset($_SESSION['customer_name']);
    } else {
        session_destroy();
    }

    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'admin_login') {
        if (($_POST['password'] ?? '') === ADMIN_PASSWORD) {
            unset($_SESSION['customer_name']);
            $_SESSION['admin_logged_in'] = true;
            set_flash('success', 'Admin přihlášení proběhlo úspěšně.');
        } else {
            set_flash('error', 'Spatne admin heslo.');
        }

        header('Location: index.php');
        exit;
    }

    if ($action === 'customer_login') {
        $hostings = load_hostings();
        $portalUser = sanitize_customer_name($_POST['portal_user'] ?? '');
        $password = $_POST['password'] ?? '';
        $matchedCustomer = null;

        foreach ($hostings as $customer => $hosting) {
            if (($hosting['portal_user'] ?? '') === $portalUser && password_verify($password, $hosting['portal_password_hash'] ?? '')) {
                $matchedCustomer = $customer;
                break;
            }
        }

        if ($matchedCustomer !== null) {
            unset($_SESSION['admin_logged_in']);
            $_SESSION['customer_name'] = $matchedCustomer;
            set_flash('success', 'Přihlášení do zákaznického portálu proběhlo úspěšně.');
        } else {
            set_flash('error', 'Neplatne zakaznicke prihlasovaci udaje.');
        }

        header('Location: index.php');
        exit;
    }
}

$hostings = load_hostings();
$isAdminLoggedIn = (bool) ($_SESSION['admin_logged_in'] ?? false);
$currentCustomer = $_SESSION['customer_name'] ?? null;
$customerHosting = null;

if ($currentCustomer !== null) {
    $customerHosting = $hostings[$currentCustomer] ?? null;

    if ($customerHosting === null) {
        unset($_SESSION['customer_name']);
        set_flash('error', 'Zakaznicky ucet uz neexistuje.');
        header('Location: index.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'customer_upload') {
    if ($customerHosting === null) {
        set_flash('error', 'Nejsi prihlaseny jako zakaznik.');
        header('Location: index.php');
        exit;
    }

    try {
        $uploadedCount = upload_site_files(
            $_FILES['site_files'] ?? [],
            customer_public_path($customerHosting['customer']),
            isset($_POST['clear_public'])
        );

        set_flash('success', 'Na hosting bylo nahráno ' . $uploadedCount . ' souborů.');
    } catch (Throwable $exception) {
        set_flash('error', $exception->getMessage());
    }

    header('Location: index.php');
    exit;
}

$flash = pull_flash();
$files = $customerHosting !== null ? list_customer_files(customer_public_path($customerHosting['customer'])) : [];

uasort($hostings, static fn(array $left, array $right): int => strcmp($left['customer'], $right['customer']));
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hosting panel</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header class="hero">
            <div>
                <h1>Hosting panel</h1>
                <p>Admin vytvoří hosting a zákazník si pak nahraje svůj web jen do své složky a vidí jen svou databázi.</p>
            </div>

            <?php if ($isAdminLoggedIn): ?>
                <a href="?logout=admin" class="btn-logout">Odhlásit admina</a>
            <?php elseif ($customerHosting !== null): ?>
                <a href="?logout=customer" class="btn-logout">Odhlásit zákazníka</a>
            <?php endif; ?>
        </header>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?= escape($flash['type']) ?>">
                <strong><?= escape($flash['message']) ?></strong>

                <?php if (!empty($flash['details']['created']) && is_array($flash['details']['created'])): ?>
                    <div class="credentials">
                        <?php foreach ($flash['details']['created'] as $label => $value): ?>
                            <div class="credential-box">
                                <span><?= escape($label) ?></span>
                                <b><?= escape((string) $value) ?></b>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="note">Tohle jsou vygenerované údaje pro zákazníka. Po refreshi už se znovu neukážou.</p>
                <?php endif; ?>

                <?php if (!empty($flash['details']['warnings']) && is_array($flash['details']['warnings'])): ?>
                    <ul class="warning-list">
                        <?php foreach ($flash['details']['warnings'] as $warning): ?>
                            <li><?= escape((string) $warning) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!$isAdminLoggedIn && $customerHosting === null): ?>
            <div class="grid two-columns">
                <section class="card">
                    <h2>Admin přihlášení</h2>
                    <p>Admin přes tuhle část založí nový hosting a vygeneruje údaje pro zákazníka.</p>

                    <form method="post" class="stack-form">
                        <input type="hidden" name="action" value="admin_login">
                        <input type="password" name="password" placeholder="Admin heslo" required>
                        <button type="submit">Vstoupit jako admin</button>
                    </form>
                </section>

                <section class="card">
                    <h2>Zákaznický portál</h2>
                    <p>Zákazník vidí jen vlastní hosting, vlastní soubory a vlastní DB údaje.</p>

                    <form method="post" class="stack-form">
                        <input type="hidden" name="action" value="customer_login">
                        <input type="text" name="portal_user" placeholder="Login hostingu" required>
                        <input type="password" name="password" placeholder="Heslo zákazníka" required>
                        <button type="submit" class="btn-secondary">Přihlásit se jako zákazník</button>
                    </form>
                </section>
            </div>
        <?php elseif ($isAdminLoggedIn): ?>
            <div class="grid admin-layout">
                <section class="card large-card">
                    <h2>Vytvořit nový hosting</h2>
                    <p>Vznikne složka, databáze a přihlášení do zákaznického portálu.</p>

                    <form action="create_hosting.php" method="post" class="stack-form">
                        <input type="text" name="customer_name" placeholder="např. alfa, beta, web1" required>
                        <button type="submit" class="btn-create">Vytvořit hosting</button>
                    </form>
                </section>

                <section class="card">
                    <h2>Existující hostingy</h2>

                    <?php if ($hostings === []): ?>
                        <p>Zatím tu nejsou žádné hostingy.</p>
                    <?php else: ?>
                        <div class="hosting-list">
                            <?php foreach ($hostings as $hosting): ?>
                                <div class="hosting-item">
                                    <b><?= escape($hosting['customer']) ?></b>
                                    <span>Portál login: <?= escape($hosting['portal_user']) ?></span>
                                    <span>DB: <?= escape($hosting['db_name']) ?></span>
                                    <div class="hosting-actions">
                                        <a href="<?= escape(build_customer_url($hosting['customer'])) ?>" target="_blank" rel="noreferrer">Otevřít web</a>
                                        <form action="delete_hosting.php" method="post" class="inline-form" onsubmit="return confirm('Opravdu chces trvale smazat hosting?');">
                                            <input type="hidden" name="customer_name" value="<?= escape($hosting['customer']) ?>">
                                            <button type="submit" class="btn-danger">Smazat hosting</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        <?php elseif ($customerHosting !== null): ?>
            <div class="grid customer-layout">
                <section class="card large-card">
                    <h2>Můj hosting: <?= escape($customerHosting['customer']) ?></h2>
                    <p>Odsud můžeš nahrát svůj web do vlastní složky a zobrazit si přístupy k databázi.</p>

                    <div class="info-grid">
                        <div class="info-box">
                            <span>Web URL</span>
                            <b><a href="<?= escape(build_customer_url($customerHosting['customer'])) ?>" target="_blank" rel="noreferrer"><?= escape(build_customer_url($customerHosting['customer'])) ?></a></b>
                        </div>
                        <div class="info-box">
                            <span>Public složka</span>
                            <b><?= escape(customer_public_path($customerHosting['customer'])) ?></b>
                        </div>
                        <div class="info-box">
                            <span>DB host</span>
                            <b><?= escape($customerHosting['db_host']) ?></b>
                        </div>
                        <div class="info-box">
                            <span>DB name</span>
                            <b><?= escape($customerHosting['db_name']) ?></b>
                        </div>
                        <div class="info-box">
                            <span>DB user</span>
                            <b><?= escape($customerHosting['db_user']) ?></b>
                        </div>
                        <div class="info-box">
                            <span>DB heslo</span>
                            <b><?= escape($customerHosting['db_password']) ?></b>
                        </div>
                        <div class="info-box">
                            <span>FTP host</span>
                            <b><?= escape($customerHosting['ftp_host'] ?? 'localhost') ?></b>
                        </div>
                        <div class="info-box">
                            <span>FTP port</span>
                            <b><?= escape((string) ($customerHosting['ftp_port'] ?? 2121)) ?></b>
                        </div>
                        <div class="info-box">
                            <span>FTP login</span>
                            <b><?= escape($customerHosting['ftp_user'] ?? $customerHosting['customer']) ?></b>
                        </div>
                        <div class="info-box">
                            <span>FTP heslo</span>
                            <b><?= escape($customerHosting['ftp_password'] ?? '') ?></b>
                        </div>
                        <div class="info-box">
                            <span>FTP public root</span>
                            <b><?= escape($customerHosting['ftp_home'] ?? customer_public_path($customerHosting['customer'])) ?></b>
                        </div>
                    </div>
                </section>

                <section class="card">
                    <h2>Nahrát web</h2>
                    <p>Vyber soubory nebo celou složku webu. Nahraje se jen do tvého vlastního `public` adresáře.</p>

                    <form method="post" enctype="multipart/form-data" class="stack-form">
                        <input type="hidden" name="action" value="customer_upload">
                        <input type="file" name="site_files[]" multiple webkitdirectory directory required>
                        <label class="checkbox-row">
                            <input type="checkbox" name="clear_public" value="1">
                            <span>Před nahráním smazat aktuální obsah public</span>
                        </label>
                        <button type="submit">Nahrát soubory</button>
                    </form>
                </section>

                <section class="card">
                    <h2>Správa DB</h2>
                    <p>Nejjednodušší varianta je použít phpMyAdmin přes vlastní DB účet, takže uvidíš jen svoji databázi.</p>
                    <a class="db-link" href="<?= escape(build_phpmyadmin_url()) ?>" target="_blank" rel="noreferrer">Otevřít phpMyAdmin</a>
                </section>
            </div>

            <section class="card files-card">
                <h2>Nahrané soubory</h2>

                <?php if ($files === []): ?>
                    <p>V public složce zatím nejsou žádné soubory.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Soubor</th>
                                    <th>Velikost</th>
                                    <th>Upraveno</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($files as $file): ?>
                                    <tr>
                                        <td><?= escape($file['path']) ?></td>
                                        <td><?= escape(format_bytes((int) $file['size'])) ?></td>
                                        <td><?= escape($file['modified']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</body>
</html>
