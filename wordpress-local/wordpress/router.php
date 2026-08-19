<?php
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
if (preg_match("|^/wp-json/(.*)$|", $path, $m)) {
    $_GET["rest_route"] = "/" . $m[1];
    require __DIR__ . "/index.php";
    return true;
}
if (file_exists(__DIR__ . $path)) { return false; }
$_SERVER["PATH_INFO"] = $path;
$_SERVER["SCRIPT_NAME"] = "/index.php";
require __DIR__ . "/index.php";
