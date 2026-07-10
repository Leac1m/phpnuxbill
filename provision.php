<?php
require_once 'init.php';

$portal = $_GET['portal'] ?? '';
$routerIp = $_GET['router'] ?? '';

if ($portal == '1') {
    global $config;
    $serverIp = parse_url($config['app_url'] ?? 'http://'.$_SERVER['HTTP_HOST'], PHP_URL_HOST);
    if (!$serverIp || $serverIp == 'localhost') {
        $serverIp = $_SERVER['HTTP_HOST'];
    }
    header('Content-Type: text/html');
    echo <<<HTML
<html>
<head>
<meta http-equiv="refresh" content="0; url=http://{$serverIp}/index.php?_route=login&nux-mac=$(mac)&nux-ip=$(ip)&nux-router={$routerIp}">
<title>Redirecting to Billing Portal...</title>
</head>
<body>
<p>Redirecting you to the Internet portal. <a href="http://{$serverIp}/index.php?_route=login&nux-mac=$(mac)&nux-ip=$(ip)&nux-router={$routerIp}">Click here if not redirected automatically.</a></p>
</body>
</html>
HTML;
    exit;
}

$token = $_GET['token'] ?? '';
if (empty($token)) {
    die("Invalid token.");
}

try {
    ORM::raw_execute("CREATE TABLE IF NOT EXISTS `tbl_provisioning_tokens` (
        `id` int NOT NULL AUTO_INCREMENT,
        `token` varchar(64) NOT NULL,
        `expires_at` datetime NOT NULL,
        `assigned_ip` varchar(32) NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    ORM::raw_execute("ALTER TABLE `tbl_routers` ADD `wg_public_key` varchar(64) DEFAULT NULL;");
} catch(Exception $e) {}

try {
    $record = ORM::for_table('tbl_provisioning_tokens')
        ->where('token', $token)
        ->where_raw('expires_at > NOW()')
        ->find_one();
} catch (Exception $e) {
    $record = null;
}

if (!$record) {
    die("Token invalid or expired.");
}

$assigned_ip = $record->assigned_ip;

$wg_api = "http://nuxbill-wireguard:8080/peers";

$ch = curl_init($wg_api);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'x-api-token: super_secret_token_change_me']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['ip' => $assigned_ip . '/32']));
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode !== 200) {
    die("Internal Error: Could not connect to WireGuard API. " . $response);
}

$data = json_decode($response, true);
$privKey = $data['private_key'];
$serverPubKey = $data['server_public_key'];
$clientPubKey = $data['public_key'];

global $config;
$serverIp = parse_url($config['app_url'] ?? 'http://'.$_SERVER['HTTP_HOST'], PHP_URL_HOST);
if (!$serverIp || $serverIp == 'localhost') {
    $serverIp = $_SERVER['HTTP_HOST'];
}

$api_password = bin2hex(random_bytes(8));

$r = ORM::for_table('tbl_routers')->create();
$r->name = 'Auto Router ' . substr($assigned_ip, strrpos($assigned_ip, '.') + 1);
$r->ip_address = $assigned_ip;
$r->username = 'phpnuxbill';
$r->password = $api_password;
$r->wg_public_key = $clientPubKey;
$r->status = 'Online';
$r->enabled = 1;
$r->save();

$record->delete();

header('Content-Type: text/plain');
echo <<<RSC
/interface wireguard add name=wg-nuxbill private-key="$privKey" listen-port=13231
/ip address add address=$assigned_ip/24 interface=wg-nuxbill
/interface wireguard peers add interface=wg-nuxbill public-key="$serverPubKey" endpoint-address="$serverIp" endpoint-port=51820 allowed-address=10.66.66.0/24 persistent-keepalive=25s
/ip service enable api
/user group add name=phpnuxbill policy=api,read,write,policy,test
/user add name=phpnuxbill group=phpnuxbill password="$api_password"
/interface wireguard enable wg-nuxbill
/ip hotspot walled-garden ip add action=accept dst-address="$serverIp" comment="phpnuxbill_auto_provision"
/tool fetch url="http://$serverIp/provision.php?portal=1&router=$assigned_ip" dst-path="hotspot/login.html"
/tool fetch url="http://$serverIp/provision.php?portal=1&router=$assigned_ip" dst-path="flash/hotspot/login.html"
RSC;

