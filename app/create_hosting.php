<?php
declare(strict_types=1);

session_start();

const HOSTINGS_REGISTRY_PATH = '/srv/www/.hostings.json';
const WEB_ROOT_PATH = '/srv/www';
const DEFAULT_DB_HOST = 'db';
const DEFAULT_DB_ROOT_PASSWORD = 'change_me_root';
const FTP_CONTAINER_NAME = 'hosting_ftp';
const FTP_REAL_USER = 'www-data';

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
    ensure_directory_exists(WEB_ROOT_PATH);

    ksort($hostings);

    $written = @file_put_contents(
        HOSTINGS_REGISTRY_PATH,
        json_encode($hostings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );

    if ($written === false) {
        throw new RuntimeException('Nepodarilo se ulozit registr hostingu do ' . HOSTINGS_REGISTRY_PATH . '. Zkontroluj prava zapisu.');
    }
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
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Pro ukázku vždy použijeme jednoduchý subdoménový styl (např. customer.localhost)
    return sprintf('%s://www.%s.cz/', $scheme, rawurlencode($customer));
}

function build_phpmyadmin_url(): string
{
    return build_base_url('8081') . '/';
}

function ftp_customer_home_path(string $customer): string
{
    return '/home/' . $customer . '/public';
}

function run_local_command(string $command): string
{
    $output = [];
    $exitCode = 0;

    exec($command . ' 2>&1', $output, $exitCode);

    if ($exitCode !== 0) {
        throw new RuntimeException(trim(implode(PHP_EOL, $output)) ?: 'Příkaz selhal.');
    }

    return trim(implode(PHP_EOL, $output));
}

function create_ftp_user(string $customer, string $password): void
{
    $ftpHome = ftp_customer_home_path($customer);
    // Vytvoření textového záznamu ve sdíleném volume (bez parametru -m, který zapisuje jinam)
    $command = sprintf(
        "printf '%%s\\n%%s\\n' %s %s | pure-pw useradd %s -f /etc/pure-ftpd/passwd/pureftpd.passwd -u %s -d %s",
        escapeshellarg($password),
        escapeshellarg($password),
        escapeshellarg($customer),
        escapeshellarg(FTP_REAL_USER),
        escapeshellarg($ftpHome)
    );

    run_local_command($command);
    
    // Následná ruční kompilace do binární PDB databáze, na kterou teď FTP kontejner kouká
    run_local_command('pure-pw mkdb /etc/pure-ftpd/passwd/pureftpd.pdb -f /etc/pure-ftpd/passwd/pureftpd.passwd');
}

function delete_ftp_user(string $customer): void
{
    $command = sprintf(
        'pure-pw userdel %s -f /etc/pure-ftpd/passwd/pureftpd.passwd',
        escapeshellarg($customer)
    );

    run_local_command($command);
    run_local_command('pure-pw mkdb /etc/pure-ftpd/passwd/pureftpd.pdb -f /etc/pure-ftpd/passwd/pureftpd.passwd');
}

function ensure_directory_exists(string $path): void
{
    if (is_dir($path)) {
        return;
    }

    if (!@mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Nepodarilo se vytvorit slozku ' . $path . '. Zkontroluj prava zapisu.');
    }
}

function write_file_strict(string $path, string $content): void
{
    $written = @file_put_contents($path, $content);

    if ($written === false) {
        throw new RuntimeException('Nepodarilo se zapsat soubor ' . $path . '. Zkontroluj prava zapisu.');
    }
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

function generate_memorable_password(): string
{
    $words = ['jablko', 'banan', 'kocka', 'pejsek', 'tygr', 'medved', 'slon', 'auto', 'vlak', 'kolo', 'strom', 'kniha'];
    return $words[array_rand($words)] . random_int(10, 99);
}

$publicPath = customer_public_path($customer);
$customerRoot = customer_root_path($customer);
$dbHost = getenv('MYSQL_HOST') ?: DEFAULT_DB_HOST;
$dbRootPassword = getenv('MYSQL_ROOT_PASSWORD') ?: DEFAULT_DB_ROOT_PASSWORD;    
$dbName = 'cust' . $customer . 'db';
$dbUser = 'cust' . $customer;
$dbPassword = generate_memorable_password();
$portalUser = $customer;
$portalPassword = generate_memorable_password();
$ftpUser = $customer;
$ftpPassword = generate_memorable_password();
$folderReady = false;
$databaseReady = false;
$ftpUserReady = false;

try {
    ensure_directory_exists($publicPath);
    $folderReady = true;

    $welcomeContent = "<?php echo 'Vitejte na hostingu zakaznika: " . $customer . "'; ?>";
    write_file_strict($publicPath . '/index.php', $welcomeContent);

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

    create_ftp_user($customer, $ftpPassword);
    $ftpUserReady = true;

    $hostings[$customer] = [
        'customer' => $customer,
        'portal_user' => $portalUser,
        'portal_password_hash' => password_hash($portalPassword, PASSWORD_DEFAULT),
        'ftp_user' => $ftpUser,
        'ftp_password' => $ftpPassword,
        'ftp_host' => 'localhost',
        'ftp_port' => 2121,
        'ftp_home' => ftp_customer_home_path($customer),
        'db_host' => $dbHost,
        'db_name' => $dbName,
        'db_user' => $dbUser,
        'db_password' => $dbPassword,
        'created_at' => date(DATE_ATOM),
    ];

    save_hostings($hostings);

    // Zápis do hostitelského /etc/hosts (pokud je připojený do kontejneru a zapisovatelný)
    $hostRecords = "\n127.0.0.1 www.{$customer}.cz {$customer}.cz # BSPWE AUTO GENERATED\n";
    if (is_writable('/host_etc_hosts')) {
        file_put_contents('/host_etc_hosts', $hostRecords, FILE_APPEND);
    } else {
        error_log("Soubor /host_etc_hosts neni zapisovatelny!");
    }

    $credentials = [
        '[HOSTING]',
        'Nazev: ' . $customer,
        'Web URL: ' . build_customer_url($customer),
        'Portal login: ' . $portalUser,
        'Portal heslo: ' . $portalPassword,
        'FTP host: localhost',
        'FTP port: 2121',
        'FTP login: ' . $ftpUser,
        'FTP heslo: ' . $ftpPassword,
        'FTP public root: ' . ftp_customer_home_path($customer),
        'Public slozka: ' . $publicPath,
        '',
        '[DATABASE]',
        'Host: ' . $dbHost,
        'Databaze: ' . $dbName,
        'Uzivatel: ' . $dbUser,
        'Heslo: ' . $dbPassword,
        'phpMyAdmin: ' . build_phpmyadmin_url(),
    ];

    write_file_strict($customerRoot . '/hosting_credentials.txt', implode(PHP_EOL, $credentials));

    set_flash('success', 'Hosting byl uspesne vytvoren.', [
        'created' => [
            'Hosting' => $customer,
            'Web URL' => build_customer_url($customer),
            'Portal login' => $portalUser,
            'Portal heslo' => $portalPassword,
            'FTP host' => 'localhost',
            'FTP port' => 2121,
            'FTP login' => $ftpUser,
            'FTP heslo' => $ftpPassword,
            'DB host' => $dbHost,
            'DB name' => $dbName,
            'DB user' => $dbUser,
            'DB heslo' => $dbPassword,
        ],
    ]);
} catch (Throwable $exception) {
    if ($ftpUserReady) {
        try {
            delete_ftp_user($customer);
        } catch (Throwable $rollbackException) {
        }
    }

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
