<?php

require_once 'vendor/autoload.php';

use PHPYoutubeDL\PHPYoutubeDL;

function showProgress($downloaded_size, $download_size){
	echo $downloaded_size ."/". $download_size;
	echo PHP_EOL;
	flush();
}

$php_youtube_dl = new PHPYoutubeDl("https://www.youtube.com/watch?v=xxxxxx");
$php_youtube_dl->setProgressCallback('showProgress');

$php_youtube_dl->startDownload();

?>