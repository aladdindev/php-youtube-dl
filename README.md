# php-youtube-dl
A PHP script to help you download your videos from Youtube

![](http://www.aladdian.com/img/ytdl.png)


## Requirements
* PHP >=5.3
* PHP CURL extension is optional

## PHPYoutubeDL

### Example #1: Basic download
```php
require_once 'vendor/autoload.php';

use PHPYoutubeDl\PHPYoutubeDl;

$php_youtube_dl = new PHPYoutubeDl("http://www.youtube.com/watch?v=xxxxxx");

$php_youtube_dl->startDownload();
```

### Example #2: Prints direct download link
```php
require_once 'vendor/autoload.php';

use PHPYoutubeDl\PHPYoutubeDl;

$php_youtube_dl = new PHPYoutubeDl("http://www.youtube.com/watch?v=xxxxxx");

echo $php_youtube_dl->getDirectLink();
```

### Example #3: With progress function
```php
require_once 'vendor/autoload.php';

use PHPYoutubeDl\PHPYoutubeDl;

function showProgress($downloaded_size, $download_size){
	echo $downloaded_size / $download_size;
	echo PHP_EOL;
	flush();
}

$php_youtube_dl = new PHPYoutubeDl("http://www.youtube.com/watch?v=xxxxxx");
$php_youtube_dl->setProgressCallback('showProgress');
$php_youtube_dl->startDownload();
```

## php-youtube-dl-cli
Download the [PHP script][php].

[php]: https://raw.githubusercontent.com/aladdindev/php-youtube-dl/master/src/php-youtube-dl-cli.php

Make sure PHP-CLI is installed and added to your PATH variable.

```
php php-youtube-dl-cli.php -f 28 -t myvideo http://youtube.com/watch?v=xxxxxx

```

### Arguments
You can use the following arguments:

| Option | Description |
| ---- | ---- |
| -c | Prints currently used JS cipher function. |
| -t title | Specify a custom filename. The video ID and the extension will still be appended to this argument. |
| -f format | Specify a format to download. You can access the list of available format IDs using the -l argument. |
| -l | Lists the available formats for the provided video. |
| -h | Prints the help section. |
