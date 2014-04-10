<?php


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

$config_file = getenv("CONFIG");
$all = getenv("ALL");
$su = getenv("SU");
$config = null; 

if ($all == "1") {
    $config_file = glob("config*.json");
    $all_config = array();
    foreach ($config_file as $key => $value) {
        if ($value != "config_example.json") {
            $all_config[] = $value;
        }
    }
    $config_file = implode(",", $all_config);
}

foreach (explode(",", $config_file) as $c_key => $c_file) {
    if (empty($c_file)) {
        if (!file_exists("config.json")) {
            exit("Cannot find config file.");
        } else {
            $config = json_decode(file_get_contents("config.json"), true);
        }
    } else {
        if (!file_exists("config$c_file.json")) {
            exit("Cannot find config file.");
        } else {
            $config = json_decode(file_get_contents("config$c_file.json"), true);
        }
    }   

    $reset = getenv("RESET");
    if (empty($reset)) {
        $reset = "0";
    }   


    $hosts = isset($config["hosts"]) ? $config["hosts"] : null;
    $port = isset($config["port"]) ? $config["port"] : 22;
    $package = isset($config["package"]) ? $config["package"] : null;
    $ppk = isset($config["private_key"]) ? $config["private_key"] : null;
    $user = isset($config["user"]) ? $config["user"] : null;
    $destination = isset($config["destination"]) ? $config["destination"] : null;
    $pass = isset($config["password"]) ? 
        $config["password"] : null; 

    if (!isset($user) || !isset($hosts) || !isset($package) 
            || !is_dir($package) || !is_array($hosts) || count($hosts) == 0
                || (isset($ppk) && !file_exists($ppk)) || !isset($destination)) {
        exit("Config File:config$c_file.json Error ...");
    }   

    $destination = preg_replace("/^~\//", "/home/$user/", $destination);

    $zipfile = new ZipTool();
    $zipfile->addNewFile($package); 

    if (isset($ppk) && file_exists($ppk)) {
        $key = new Crypt_RSA();
        $key->loadKey(file_get_contents($ppk));
    } elseif (isset($pass)) {
        $key = $pass;
    } else {
        var_dump($config);
        exit("No key found...");
    }   

    foreach ($hosts as $number => $host) {  

        echo "Start connecting sftp service...$user$host", PHP_EOL;
        $sftp = new Net_SFTP($host);
        if (!$sftp->login($user, $key)) {
            echo "SFTP Login Failed!", PHP_EOL;
            unset($sftp);
            continue;
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
        
        $result = $sftp->read();

        if (preg_match("/unzip: command not found/", $result)) {
            echo "Cannot find unzip package ... installing", PHP_EOL;
            echo "sudo yum install -y unzip,", $sftp->exec("sudo yum install -y unzip"), ",OK", PHP_EOL;
            echo $sftp->read(), PHP_EOL;
            echo "sudo unzip -o $destination/tmp.zip -d $destination,", $sftp->exec("sudo unzip -o $destination/tmp.zip -d $destination"), ",OK", PHP_EOL;
            echo $sftp->read(), PHP_EOL;
        } else {
            echo $result, PHP_EOL;
        }


        echo "sudo rm -rf $destination/tmp.zip,", $sftp->exec("sudo rm -rf $destination/tmp.zip"), ",OK", PHP_EOL;
        echo $sftp->read(), PHP_EOL;
        echo "sudo usermod -a -G www-data $user,", $sftp->exec("sudo usermod -a -G www-data $user"), ",OK", PHP_EOL;
        echo $sftp->read(), PHP_EOL;
        echo "sudo chgrp -R www-data $destination,", $sftp->exec("sudo chgrp -R www-data $destination"), ",OK", PHP_EOL;
        echo $sftp->read(), PHP_EOL;
        echo "sudo chmod -R g+rwxs $destination,", $sftp->exec("sudo chmod -R g+rwxs $destination"), ",OK", PHP_EOL;
        echo $sftp->read(), PHP_EOL;
        
        echo "sudo chown -R $user $destination,", $sftp->exec("sudo chown -R $user $destination"), ",OK", PHP_EOL;
        echo $sftp->read(), PHP_EOL;

        foreach (explode(",", $su) as $f_key => $f_des) {
            if (!empty($f_des)) {
                echo "sudo chmod -R 777 $destination/$f_des,", $sftp->exec("sudo chmod -R 777 $destination/$f_des"), ",OK", PHP_EOL;
                echo $sftp->read(), PHP_EOL;
            }
        }

        echo "$user$host finishing...", PHP_EOL;
        unset($sftp);
    }
}
?>