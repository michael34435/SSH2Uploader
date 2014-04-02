<?php

$arguments = array();
$params = array();

foreach($argv as $k => $arg) {
    $params[] = $arg;
}

if(count($params) > 0) {
    foreach ($params as $key => $value) {
        putenv($value);
    }
}


set_include_path(get_include_path());

require_once "Net/SFTP.php";
require_once "Net/SSH2.php";
require_once "Crypt/RSA.php";
require_once "ZipTool.php";

define('NET_SSH2_LOGGING', NET_SSH2_LOG_COMPLEX);

$c_file = getenv("CONFIG");

if (empty($c_file)) {
	if (!file_exists("config.json")) {
		exit("Cannot find config file.");
	}
} else {
	if (!file_exists("config$c_file.json")) {
		exit("Cannot find config file.");
	}
}

$reset = getenv("RESET");
if (empty($reset)) {
	$reset = "0";
}

$log = getenv("LOG");
$html = getenv("HTML");

$config = json_decode(file_get_contents("config.json"), true);
$hosts = isset($config["hosts"]) ? $config["hosts"] : null;
$port = isset($config["port"]) ? $config["port"] : 22;
$package = isset($config["package"]) ? $config["package"] : null;
$ppk = isset($config["private_key"]) ? $config["private_key"] : null;
$user = isset($config["user"]) ? $config["user"] : null;
$destination = isset($config["destination"]) ? $config["destination"] : null;

if (!isset($user) || !isset($hosts) || !isset($package) 
		|| !is_dir($package) || !is_array($hosts) || count($hosts) == 0
			|| !file_exists($ppk) || !isset($destination)) {
	exit("Error ...");
}

$zipfile = new ZipTool();
$zipfile->addNewFile($package);

$key = new Crypt_RSA();
$key->loadKey(file_get_contents($ppk));

foreach ($hosts as $number => $host) {

	echo "Start connecting sftp service...$user@$host", PHP_EOL;
	$sftp = new Net_SFTP($host);
	if (!$sftp->login($user, $key)) {
    	exit("SFTP Login Failed!");
	}
	$sftp->enablePTY(); 
	echo "Start uploading tmp file to the server... from (" . $zipfile->getFileName() . ")", PHP_EOL;

	$sftp->put($sftp->pwd() . "/tmp.zip", realpath($zipfile->getFileName()), NET_SFTP_LOCAL_FILE );
	echo "Start installing ... ", PHP_EOL;

	$sftp->enableQuietMode();
	if ($reset == "1") {
		echo "sudo rm -rf $destination,", $sftp->exec("sudo rm -rf $destination"), ",OK", PHP_EOL;
		echo $sftp->read(), PHP_EOL;
	}
	echo "sudo mkdir -p $destination,", $sftp->exec("sudo mkdir -p $destination"), ",OK", PHP_EOL;
	echo $sftp->read(), PHP_EOL;
	echo "sudo mv -f " . $sftp->pwd() . "/tmp.zip $destination,", $sftp->exec("sudo mv -f " . $sftp->pwd() . "/tmp.zip $destination"), ",OK", PHP_EOL;
	echo $sftp->read(), PHP_EOL;
	echo "sudo unzip -o $destination/tmp.zip -d $destination,", $sftp->exec("sudo unzip -o $destination/tmp.zip -d $destination"), ",OK", PHP_EOL;
	echo $sftp->read(), PHP_EOL;
	echo "sudo rm -rf $destination/tmp.zip,", $sftp->exec("sudo rm -rf $destination/tmp.zip"), ",OK", PHP_EOL;
	echo $sftp->read(), PHP_EOL;
	echo "sudo usermod -a -G www-data $user,", $sftp->exec("sudo usermod -a -G www-data $user"), ",OK", PHP_EOL;
	echo $sftp->read(), PHP_EOL;
	echo "sudo chgrp -R www-data $destination,", $sftp->exec("sudo chgrp -R www-data $destination"), ",OK", PHP_EOL;
	echo $sftp->read(), PHP_EOL;
	echo "sudo chmod -R g+rwxs $destination,", $sftp->exec("sudo chmod -R g+rwxs $destination"), ",OK", PHP_EOL;
	echo $sftp->read(), PHP_EOL;
	if (!empty($log)) {
		echo "sudo chmod -R 777 $destination/$log,", $sftp->exec("sudo chmod -R 777 $destination/log"), ",OK", PHP_EOL;
		echo $sftp->read(), PHP_EOL;
	}
	if (!empty($html)) {
		echo "sudo chmod -R 777 $destination/$html,", $sftp->exec("sudo chmod -R 777 $destination/tmp/html"), ",OK", PHP_EOL;
		echo $sftp->read(), PHP_EOL;
	}
	echo "sudo chown -R $user $destination,", $sftp->exec("sudo chown -R $user $destination"), ",OK", PHP_EOL;
	echo $sftp->read(), PHP_EOL;
	echo "$user@$host finishing...", PHP_EOL;
	unset($sftp);
}
?>