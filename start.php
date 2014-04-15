<?php

// initial ...
if (!is_dir("tmp")) {
    mkdir("tmp");
}

if (!is_dir("down")) {
    mkdir("down");
}


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
$mode = getenv("MODE");
$file = getenv("FILE");
$config = null; 

if ($all == "1") {
    $config_file = glob("config\config*.json");
    $all_config = array();
    foreach ($config_file as $key => $value) {
        if ($value != "config\config_example.json") {
            $all_config[] = $value;
        }
    }
    $config_file = implode(",", $all_config);
}

if ($mode == "download") {
    $download_mode = true;
} else {
    $download_mode = false;
}



foreach (explode(",", $config_file) as $c_key => $c_file) {
    if (empty($c_file)) {
        if (!file_exists("config\config.json")) {
            exit("Cannot find config file.");
        } else {
            $config = json_decode(file_get_contents("config\config.json"), true);
        }
    } else {
        if (!file_exists("config\config$c_file.json")) {
            exit("Cannot find config file.");
        } else {
            $config = json_decode(file_get_contents("config\config$c_file.json"), true);
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

    if (!is_array($hosts)) {
        $remote = $hosts;
        $hosts = array();
        $hosts[] = $remote;
    }

    if (!isset($user) || !isset($hosts) || (!isset($package) && !$download_mode)
            || (!is_dir($package) && !$download_mode) || !is_array($hosts) || count($hosts) == 0
                || (isset($ppk) && !file_exists($ppk)) || !isset($destination)) {
        exit("Config File:config$c_file.json Error ...");
    }   

    if ($download_mode && empty($file)) {
        exit("please assign a file...");
    } else {
        $file = explode(",", $file);
    }

    if (!is_array($destination)) {
        $path = preg_replace("/^~\//", "/home/$user/", $destination);
        $destination = array();
        $destination[] = $path;
    } else {
        foreach ($destination as $des_key => $des_value) {
            $destination[$des_key] = preg_replace("/^~\//", "/home/$user/", $des_value);
        }
    }

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

        echo "Start connecting sftp service...$user@$host", PHP_EOL;
        $sftp = new Net_SFTP($host);
        if (!$sftp->login($user, $key)) {
            echo "SFTP Login Failed!", PHP_EOL;
            unset($sftp);
            continue;
        }
        $sftp->enablePTY(); 
        if (!$download_mode) {
            echo "Start uploading tmp file to the server... from (" . $zipfile->getFileName() . ")", PHP_EOL;       

            $sftp->put($sftp->pwd() . "/tmp.zip", realpath($zipfile->getFileName()), NET_SFTP_LOCAL_FILE );
            echo "Start installing ... ", PHP_EOL;  
        }
        $sftp->enableQuietMode();

        foreach ($destination as $destination_key => $destination_value) {
            if (!$download_mode) {
                if ($reset == "1") {
                    echo "sudo rm -rf $destination_value,", $sftp->exec("sudo rm -rf $destination_value"), ",OK", PHP_EOL;
                    echo $sftp->read(), PHP_EOL;
                }
                echo "sudo mkdir -p $destination_value,", $sftp->exec("sudo mkdir -p $destination_value"), ",OK", PHP_EOL;
                echo $sftp->read(), PHP_EOL;
                echo "sudo cp -f " . $sftp->pwd() . "/tmp.zip $destination_value,", $sftp->exec("sudo cp -f " . $sftp->pwd() . "/tmp.zip $destination_value"), ",OK", PHP_EOL;
                echo $sftp->read(), PHP_EOL;
                echo "sudo unzip -o $destination_value/tmp.zip -d $destination_value,", $sftp->exec("sudo unzip -o $destination_value/tmp.zip -d $destination_value"), ",OK", PHP_EOL;
                
                $result = $sftp->read();        

                if (preg_match("/unzip: command not found/", $result)) {
                    echo "Cannot find unzip package ... installing", PHP_EOL;
                    echo "sudo yum install -y unzip,", $sftp->exec("sudo yum install -y unzip"), ",OK", PHP_EOL;
                    echo $sftp->read(), PHP_EOL;
                    echo "sudo unzip -o $destination_value/tmp.zip -d $destination_value,", $sftp->exec("sudo unzip -o $destination_value/tmp.zip -d $destination_value"), ",OK", PHP_EOL;
                    echo $sftp->read(), PHP_EOL;
                } else {
                    echo $result, PHP_EOL;
                }   
            

                echo "sudo rm -rf $destination_value/tmp.zip,", $sftp->exec("sudo rm -rf $destination_value/tmp.zip"), ",OK", PHP_EOL;
                echo $sftp->read(), PHP_EOL;
                echo "sudo usermod -a -G www-data $user,", $sftp->exec("sudo usermod -a -G www-data $user"), ",OK", PHP_EOL;
                echo $sftp->read(), PHP_EOL;
                echo "sudo chgrp -R www-data $destination_value,", $sftp->exec("sudo chgrp -R www-data $destination_value"), ",OK", PHP_EOL;
                echo $sftp->read(), PHP_EOL;
                echo "sudo chmod -R g+rwxs $destination_value,", $sftp->exec("sudo chmod -R g+rwxs $destination_value"), ",OK", PHP_EOL;
                echo $sftp->read(), PHP_EOL;
                
                echo "sudo chown -R $user $destination_value,", $sftp->exec("sudo chown -R $user $destination_value"), ",OK", PHP_EOL;
                echo $sftp->read(), PHP_EOL;        

                foreach (explode(",", $su) as $f_key => $f_des) {
                    if (!empty($f_des)) {
                        echo "sudo chmod -R 777 $destination_value/$f_des,", $sftp->exec("sudo chmod -R 777 $destination_value/$f_des"), ",OK", PHP_EOL;
                        echo $sftp->read(), PHP_EOL;
                    }
                }
            } else {
                echo "Start downloading from server ...$user@$host", PHP_EOL;
                foreach ($file as $file_key => $file_value) {
                    $file_value_copy = preg_replace("/" . dirname($file_value) . "/", "", $file_value);
                    if (!is_dir("down/$host")) {
                        mkdir("down/$host/", "0777", true);
                    }
                    file_put_contents("down/$host/$file_value_copy", $sftp->get("$destination_value/$file_value"));
                }
            }
        }

        echo "sudo rm -rf " . $sftp->pwd() . "/tmp.zip,", $sftp->exec("sudo rm -rf " . $sftp->pwd() . "/tmp.zip"), ",OK", PHP_EOL;
        echo $sftp->read(), PHP_EOL;
        
        echo "$user@$host finishing...", PHP_EOL;
        unset($sftp);
    }
}
?>