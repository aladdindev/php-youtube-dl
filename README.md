# php-youtube-dl
A PHP script to help you download your own videos from Youtube

![](http://www.aladdian.com/img/ytdl.png)

## Getting Started
Download the [PHP script][php].

[php]: https://raw.githubusercontent.com/aladdindev/php-youtube-dl/master/php-youtube-dl.php

Make sure PHP-CLI is installed and added to your PATH variable.

```
php php-youtube-dl.php -f 28 -t myvideo http://youtube.com/watch?v=xxxxxx

```

## Arguments
You can use the following arguments:

| Option  | Description |
| ---- | ---- | ---- |
| -c | Prints currently used JS cipher function. |
| -t title | Specify a custom filename. The video ID and the extension will still be appended to this argument. |
| -f format | Specify a format to download. You can access the list of available format IDs using the -l argument. |
| -l | Lists the available formats for the provided video. |
| -h | Prints the help section. |

