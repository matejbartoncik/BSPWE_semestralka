<?php
declare(strict_types=1);

session_start();

const HOSTINGS_REGISTRY_PATH = '/srv/www/.hostings.json';
const WEB_ROOT_PATH = '/srv/www';
const DEFAULT_DB_HOST = 'db';
const DEFAULT_DB_ROOT_PASSWORD = 'change_me_root';

function set_flash(string $type, string $message, array $details = []): void
{
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message,
        'details' => $details,
    ];
}

function sanitize_customer_name(string $value): string
{
    $normalized = strtolower(trim($value));
    $normalized = preg_replace('/[^a-z0-9_-]+/', '', $normalized) ?? '';
    $normalized = trim($normalized, '_-');

    return substr($normalized, 0, 24);
}

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

function save_hostings(array $hostings): void
{
    if (!is_dir(WEB_ROOT_PATH)) {
        mkdir(WEB_ROOT_PATH, 0777, true);
    }

    ksort($hostings);

    file_put_contents(
        HOSTINGS_REGISTRY_PATH,
        json_encode($hostings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function customer_root_path(string $customer): string
{
    return WEB_ROOT_PATH . '/' . $customer;
}

function customer_public_path(string $customer): string
{
    return customer_root_path($customer) . '/public';
}

function build_base_url(?string $port = null): string
{
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
    $hostWithoutPort = preg_replace('/:\d+$/', '', $host) ?: 'localhost';

    if ($port === null) {
        return sprintf('%s://%s', $scheme, $host);
    }

    return sprintf('%s://%s:%s', $scheme, $hostWithoutPort, $port);
}

function build_customer_url(string $customer): string
{
    return build_base_url() . '/~' . rawurlencode($customer) . '/';
}

function build_phpmyadmin_url(): string
{
    return build_base_url('8081') . '/';
}

function remove_directory_tree(string $path): void
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

    rmdir($path);
}

if (!($_SESSION['admin_logged_in'] ?? false)) {
    http_response_code(403);
    exit('Nepovoleny pristup.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$customer = sanitize_customer_name($_POST['customer_name'] ?? '');

if ($customer === '') {
    set_flash('error', 'Vypln platny nazev hostingu. Pouzij mala pismena, cisla, pomlcky nebo podtrzitka.');
    header('Location: index.php');
    exit;
}

$hostings = load_hostings();

if (isset($hostings[$customer]) || is_dir(customer_root_path($customer))) {
    set_flash('error', 'Hosting s timto nazvem uz existuje.');
    header('Location: index.php');
    exit;
}

$publicPath = customer_public_path($customer);
$customerRoot = customer_root_path($customer);
$dbHost = getenv('MYSQL_HOST') ?: DEFAULT_DB_HOST;
$dbRootPassword = getenv('MYSQL_ROOT_PASSWORD') ?: DEFAULT_DB_ROOT_PASSWORD;
$dbName = 'cust' . $customer . 'db';
$dbUser = 'cust' . $customer;
$dbPassword = bin2hex(random_bytes(8));
$portalUser = $customer;
$portalPassword = bin2hex(random_bytes(6));
$folderReady = false;
$databaseReady = false;

try {
    mkdir($publicPath, 0777, true);
    $folderReady = true;

    $welcomeContent = "<?php echo 'Vitejte na hostingu zakaznika: " . $customer . "'; ?>";
    file_put_contents($publicPath . '/index.php', $welcomeContent);

    $pdo = new PDO(
        "mysql:host={$dbHost};charset=utf8mb4",
        'root',
        $dbRootPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]
    );

    $pdo->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("CREATE USER '$dbUser'@'%' IDENTIFIED BY '$dbPassword'");
    $pdo->exec("GRANT ALL PRIVILEGES ON `$dbName`.* TO '$dbUser'@'%'");
    $pdo->exec('FLUSH PRIVILEGES');
    $databaseReady = true;

    $hostings[$customer] = [
        'customer' => $customer,
        'portal_user' => $portalUser,
        'portal_password_hash' => password_hash($portalPassword, PASSWORD_DEFAULT),
        'db_host' => $dbHost,
        'db_name' => $dbName,
        'db_user' => $dbUser,
        'db_password' => $dbPassword,
        'created_at' => date(DATE_ATOM),
    ];

    save_hostings($hostings);

    $credentials = [
        '[HOSTING]',
        'Nazev: ' . $customer,
        'Web URL: ' . build_customer_url($customer),
        'Portal login: ' . $portalUser,
        'Portal heslo: ' . $portalPassword,
        'Public slozka: ' . $publicPath,
        '',
        '[DATABASE]',
        'Host: ' . $dbHost,
        'Databaze: ' . $dbName,
        'Uzivatel: ' . $dbUser,
        'Heslo: ' . $dbPassword,
        'phpMyAdmin: ' . build_phpmyadmin_url(),
    ];

    file_put_contents($customerRoot . '/hosting_credentials.txt', implode(PHP_EOL, $credentials));

    set_flash('success', 'Hosting byl uspesne vytvoren.', [
        'created' => [
            'Hosting' => $customer,
            'Web URL' => build_customer_url($customer),
            'Portal login' => $portalUser,
            'Portal heslo' => $portalPassword,
            'DB host' => $dbHost,
            'DB name' => $dbName,
            'DB user' => $dbUser,
            'DB heslo' => $dbPassword,
        ],
    ]);
} catch (Throwable $exception) {
    if ($databaseReady) {
        try {
            $pdo ??= new PDO(
                "mysql:host={$dbHost};charset=utf8mb4",
                'root',
                $dbRootPassword,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]
            );
            $pdo->exec("DROP DATABASE IF EXISTS `$dbName`");
            $pdo->exec("DROP USER IF EXISTS '$dbUser'@'%'");
            $pdo->exec('FLUSH PRIVILEGES');
        } catch (Throwable $rollbackException) {
        }
    }

    if ($folderReady) {
        remove_directory_tree($customerRoot);
    }

    set_flash('error', 'Vytvoreni hostingu selhalo: ' . $exception->getMessage());
}

header('Location: index.php');
exit;
