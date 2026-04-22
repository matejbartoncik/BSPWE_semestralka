<?php
declare(strict_types=1);

session_start();

const HOSTINGS_REGISTRY_PATH = '/srv/www/.hostings.json';
const WEB_ROOT_PATH = '/srv/www';
const DEFAULT_DB_HOST = 'db';

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

function ensure_directory_exists(string $path): void
{
    if (is_dir($path)) {
        return;
    }

    if (!@mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Nepodarilo se vytvorit slozku ' . $path . '. Zkontroluj prava zapisu.');
    }
}

function run_local_command(string $command): string
{
    $output = [];
    $exitCode = 0;

    exec($command . ' 2>&1', $output, $exitCode);

    if ($exitCode !== 0) {
        throw new RuntimeException(trim(implode(PHP_EOL, $output)) ?: 'Prikaz selhal.');
    }

    return trim(implode(PHP_EOL, $output));
}

function delete_ftp_user(string $customer): void
{
    $command = sprintf(
        'pure-pw userdel %s -f /etc/pure-ftpd/passwd/pureftpd.passwd',
        escapeshellarg($customer)
    );

    run_local_command($command);
}

function customer_root_path(string $customer): string
{
    return WEB_ROOT_PATH . '/' . $customer;
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
    set_flash('error', 'Vypln platny nazev hostingu pro smazani.');
    header('Location: index.php');
    exit;
}

$hostings = load_hostings();
$hosting = $hostings[$customer] ?? null;

if ($hosting === null) {
    set_flash('error', 'Hosting s timto nazvem neexistuje.');
    header('Location: index.php');
    exit;
}

$dbHost = $hosting['db_host'] ?? (getenv('MYSQL_HOST') ?: DEFAULT_DB_HOST);
$dbRootPassword = getenv('MYSQL_ROOT_PASSWORD');

if (!is_string($dbRootPassword) || trim($dbRootPassword) === '') {
    set_flash('error', 'Smazání hostingu nelze dokončit: chybí MYSQL_ROOT_PASSWORD v prostředí web kontejneru.');
    header('Location: index.php');
    exit;
}

$dbName = (string) ($hosting['db_name'] ?? ('cust' . $customer . 'db'));
$dbUser = (string) ($hosting['db_user'] ?? ('cust' . $customer));
$ftpUser = (string) ($hosting['ftp_user'] ?? $customer);
$warnings = [];

try {
    delete_ftp_user($ftpUser);
} catch (Throwable $exception) {
    $warnings[] = 'FTP ucet se nepodarilo smazat automaticky: ' . $exception->getMessage();
}

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};charset=utf8mb4",
        'root',
        $dbRootPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]
    );

    $dbNameSafe = str_replace('`', '``', $dbName);
    $dbUserSafe = str_replace("'", "\\'", $dbUser);

    $pdo->exec("DROP DATABASE IF EXISTS `{$dbNameSafe}`");
    $pdo->exec("DROP USER IF EXISTS '{$dbUserSafe}'@'%'");
    $pdo->exec('FLUSH PRIVILEGES');
} catch (Throwable $exception) {
    $warnings[] = 'Databazi nebo DB uzivatele se nepodarilo smazat automaticky: ' . $exception->getMessage();
}

try {
    remove_directory_tree(customer_root_path($customer));
} catch (Throwable $exception) {
    $warnings[] = 'Slozku hostingu se nepodarilo smazat automaticky: ' . $exception->getMessage();
}

unset($hostings[$customer]);

try {
    save_hostings($hostings);
} catch (Throwable $exception) {
    set_flash('error', 'Smazani hostingu nedokonceno: nepodarilo se zapsat registr. ' . $exception->getMessage(), [
        'warnings' => $warnings,
    ]);
    header('Location: index.php');
    exit;
}

if (($_SESSION['customer_name'] ?? null) === $customer) {
    unset($_SESSION['customer_name']);
}

if ($warnings !== []) {
    set_flash('success', 'Hosting byl smazán, ale některé kroky vyžadují ruční kontrolu.', [
        'warnings' => $warnings,
    ]);
} else {
    set_flash('success', 'Hosting byl úspěšně smazán.');
}

header('Location: index.php');
exit;
