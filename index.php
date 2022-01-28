<?php
session_start();

require 'vendor/autoload.php';
use Carbon\Carbon;

function throwError($message){
    header($_SERVER["SERVER_PROTOCOL"] . ' 500 Internal Server Error', true, 500);
    echo "$message";
    exit();
}

// allow only get request
if($_SERVER['REQUEST_METHOD'] != 'GET'){
    throwError("Only GET requests are allowed");
}

$_SESSION["user"] = $_SERVER['REMOTE_ADDR'];

$user   = $_SESSION["user"];
$url    = (isset($_GET['url']) ? $_GET['url'] : '');
$time   = (isset($_GET['csrf']) ? $_GET['csrf'] : '');
$csrf   = hash('md5', $time);
// csrf is here only to get some unique token to not mess up downloads
// md5 hash since it's the fastest one
// get user IP and store it in session to get some logs and prevent from spamming the server
function checkSpam($user, $url){
    // allow max 10 requests per 10 minutes
    $date       = Carbon::parse(Carbon::now()->format('H:i:s'));
    $requests   = 0;
    $logs       = fopen('logs.txt', 'a+');
    if($logs){
        while( ($line = fgets($logs)) !== false){
            $line   = json_decode($line, true);
            $to     = Carbon::parse($line['date']);
            $from   = $date;
            if( $from->diffInMinutes($to) < 10  && $user == $line['user']){
                $requests++;
            }
        }
    }
    else{
        throwError("File error");
    }
    
    if($requests > 10){
        fclose($logs);
        return false;
    }
    else{
        $data = array(
            'user'  => $user,
            'date'  => $date,
            'url'   => $url,
        );
        fwrite($logs, json_encode($data) . PHP_EOL);
        fclose($logs);
        return true;
    }
}

if(!checkSpam($user, $url)) throwError("You did too many requests lately. Check again later");
if(empty($url) || empty($csrf)) throwError("Pass right URL");

// initialize array of music songs files

function createFile($url, $csrf){
    
    function execCommand($dir, $url){
        $result = 0;
        $output = [];
        $command = "cd $dir && spotdl $url 2>&1";
        exec($command, $output, $result);
        if ($result != 0) {
            throwError(var_dump($output));
        }
        else{
            return true;
        }
    }
    
    // create a absolute path to temp folder with stored songs
    $absPath = "C:\Programy/Laragon/laragon/www/SpotifyDownloader/temp/";  
    $dir = $absPath.$csrf.'/';
    if(!mkdir($dir, 0777)) throwError("Can't create directory");
    if(!execCommand($dir, $url)) throwError("Something went wrong with executing command");
    // initialize array of songs
    $songs = [];
    // loop through all songs in the folder (as might be playlists pulled too)
    foreach (glob("temp/$csrf/".'*.mp3') as $song){
        $filename   = basename($song);
        $path       = "temp/$csrf/";
        // to each song push array with filename and path to it
        array_push($songs, ['filename' => $filename, 'path' => $path]);
    }
    echo json_encode($songs);
}
createFile($url, $csrf);
?>