<?php
define("SOCKET_TIMEOUT", 30);
define("MAXIMUM_REDIRECT", 10);
define("SOCKET_CHUNK_SIZE", 128);

/*
 * Sends a GET HTTP Request through php-curl library and returns the content. If the save_file parameter is true
 * the function will save the content of the HTTP response to a temporary file and then will strip the HTTP header
 * from the content to save the result in the required file location
 *
 * @param url the URL of the request
 * @param save_file an optional parameter to indicate whether the response will be saved to a file or no
 * @param file_name the file name to which the response will be saved
 * @return the content, the HTTP header if the content is saved to a file or false if something is wrong
 */
function getHTTPCurl($url, $save_file = false, $file_name = false){
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	//skip verifying SSL peers and hosts, so the request does not fail
	//because of SSL warnings
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	//returns the HTTP header along with the HTTP response
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_BUFFERSIZE, SOCKET_CHUNK_SIZE);
	if($save_file !== false && $file_name !== false){
		$fp = fopen($file_name.".tmp", "w");
		if($fp){
			curl_setopt($ch, CURLOPT_FILE, $fp);
			curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, -1);
			curl_setopt($ch, CURLOPT_NOPROGRESS, false);
			curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, 'progressCallBack');

			curl_exec($ch);

			fclose($fp);

			//get the HTTP header size
			$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			//strip the HTTP header from the file
			return stripHeaderFromFile($file_name, $header_size);
		}
		else{
			_log("getHTTPCurl: unable to open file ".$file_name, true);
		}
	}
	else{

		$response = curl_exec($ch);

		curl_close($ch);

		return $response;
	}
}

/*
 * Removes the HTTP header from the start of the file
 *
 * @param file_name the file name that contains both the HTTP header and content
 * @param header_size the header size in bytes
 * @return the stripped headers if successful or false otherwise
 */
function stripHeaderFromFile($file_name, $header_size){
	$fp = fopen($file_name.".tmp", "r");
	$fp2 = fopen($file_name, "w");

	if($fp && $fp2){
		$headers = fread($fp, $header_size);
		while(!feof($fp)){
			$chunk = fread($fp, SOCKET_CHUNK_SIZE);

			fwrite($fp2, $chunk);
		}

		fclose($fp);
		fclose($fp2);

		unlink($file_name.".tmp");

		return $headers;
	}
	else{
		return false;
	}
}


/*
 * {@see} php-curl docs
 */
function progressCallback($resource, $download_size, $downloaded_size, $upload_size, $uploaded){
    showProgress($downloaded_size, $download_size);
}

/*
 * Prints a command line interface progress bar
 *
 * @param downloaded_size the currently downloaded size
 * @param download_size the total size of the content
 * @param cli_size the size of the CLI
 */
function showProgress($downloaded_size, $download_size, $cli_size=30) {
    
    if($downloaded_size > $download_size){
    	return;
    }

    //avoid division by 0
    if($download_size == 0){
    	return;
    }

    static $start_time;

    if(!isset($start_time) || empty($start_time)){
    	$start_time = time();
    }

    $current_time = time();

    $percentage = (double) ($downloaded_size / $download_size);

    $bar = floor($percentage * $cli_size);

    $status_bar_str = "\r[";
    $status_bar_str .= str_repeat("=", $bar);

    if($bar < $cli_size){
        $status_bar_str .= ">";
        $repeat = $cli_size - $bar;
        $status_bar_str .= str_repeat(" ", $repeat);
    } else {
        $status_bar_str .= "=";
    }

    $disp = number_format($percentage * 100, 0);

    $status_bar_str .="] $disp%  ".$downloaded_size."/".$download_size;

    if($downloaded_size == 0){
    	$download_rate = 0;
    }
    else{
    	$download_rate = ($current_time - $start_time) / $downloaded_size;
	}
    $left = $download_size - $downloaded_size;
    
    $estimated = round($download_rate * $left, 2);
    $elapsed = $current_time - $start_time;

    $status_bar_str .= " remaining: ".number_format($estimated)." sec.  elapsed: ".number_format($elapsed)." sec.";

    echo "$status_bar_str  ";

    flush();

    if($downloaded_size == $download_size) {
        echo "\n";
    }
}

/*
 * Sends a GET HTTP Request through socket and returns the content. If sae_file parameter is true,
 * it will save the HTTP content to the file. If an HTTP header file is detected, it is read and stored in memory
 * whereas the HTTP content is written to the file pointer
 * @param url the URL of the request
 * @param save_file An optional parameter the indicates if the response has to be written to a file
 * @param file_name An optional parameter that stores the file location
 * @return the content or false if not written to file. Otherwise, either the HTTP headers if present or the filename
 */
function getHTTPSocket($url, $save_file = false, $file_name = false){
	$hostname = getComponentFromURL($url, "host");
	$port = intval(getPortFromURL($url));
	$scheme = getComponentFromURL($url, "scheme");

	if($hostname !== false && $port !== false){
		if($scheme == "https" || $scheme == "http"){
			$socket = NULL;

			if($scheme === "https"){
				$socket = fsockopen("ssl://".$hostname, $port, $err_no, $err_str, 5);
			}
			else if($scheme === "http"){
				$socket = fsockopen($hostname, $port, $err_no, $err_str, 5);
			}

			if($socket){
				$path = (getComponentFromURL($url, "path") !== false) ? getComponentFromURL($url, "path") : "/";
				$query = (getComponentFromURL($url, "query") !== false) ? "?".getComponentFromURL($url, "query") : "";
				$anchor = (getComponentFromURL($url, "anchor") !== false) ? "#".getComponentFromURL($url, "anchor") : "";

				$headers = "GET ".$path.$query.$anchor." HTTP/1.1\r\n";
				$headers .= "Host: $hostname\r\n";
				$headers .= "Accept: */*\r\n";
				$headers .= "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.87 Safari/537.36\r\n";
				$headers .= "Connection: close\r\n\r\n";

				fwrite($socket, $headers);

    			$response = "";

    			if($save_file !== false && $file_name !== false){
    				$fp = fopen($file_name, "w");
    				if($fp){
    					$response = fread($socket, 64);
    					if(preg_match("/^HTTP/", $response)){
    						$headers = "";
    						$downloaded_size = 0;

    						while(!feof($socket)){
    							$response .= fread($socket, SOCKET_CHUNK_SIZE);
    							if(strpos($response, "\r\n\r\n") !== false){
    								$response = explode("\r\n\r\n", $response);
    								$headers = $response[0];
    								$response = $response[1];
    								fwrite($fp, $response);
    								$downloaded_size = mb_strlen($response, '8bit');
    								$response = "";
    								break;
    							}
    						}
    						if(preg_match("/Content\-Length: (\d+?)\r\n/si", $headers, $match)){
    							$download_size = (double)$match[1];
    							while(!feof($socket)){
    								$response = fread($socket, SOCKET_CHUNK_SIZE);
    								fwrite($fp, $response);

    								$downloaded_size += mb_strlen($response, '8bit');
									
    								showProgress($downloaded_size, $download_size);
    							}
    						}
    					}
    					else{
    						_log("Downloading...");
    						flush();
    						fwrite($fp, $response);
	    					while(!feof($socket)){
	    						$response = fread($socket, SOCKET_CHUNK_SIZE);
	    						fwrite($fp, $response);
	    					}
    					}

    					fclose($fp);
    					fclose($socket);

    					if(isset($headers)){
    						return $headers;
    					}

    					return $file_name;
    				}
    				else{
    					fclose($socket);
    					_log("getHTTPSocket: unable to open file ".$file_name, true);
    				}
    			}
    			else{

    				while(!feof($socket)){
    					$response .= fread($socket, SOCKET_CHUNK_SIZE);
    				}

    				fclose($socket);

    				if($response != ""){
    					return $response;
    				}
    			}
			}
			else{
				_log("getHTTPSocket: ".err_str, true);
			}
		}
	}

	return false;
}

/*
 * Reads an HTTP Response and parses the HTTP header to detect redirection given by 3xx response codes
 *
 * @param response the HTTP response
 * @return true if a 3xx status code is found or false otherwise
 */
function isHTTPRedirect($response){
	$statusCode = getStatusCode($response);
	$redirectCodes = array("300", "301", "302", "303", "307");

	if($statusCode !== false && in_array($statusCode, $redirectCodes)){
		return true;
	}

	return false;
}

/*
 * Reads an HTTP Response and parses the HTTP header to return the Location field
 *
 * @param response the HTTP response
 * @return the Location URL or false if not found
 */
function getRedirectLocation($response){
	if(preg_match("~Location: (.+)\r\n~", $response, $match)){
		return $match[1];
	}

	_log("getRedirectLocation: unparsable HTTP headers", true);
	return false;
}

/*
 * Reads an HTTP Response and parses the HTTP header to return the HTTP status code
 *
 * @param response the HTTP response
 * @return the status code or false if not found
 */
function getStatusCode($response){
	if(preg_match("~HTTP/1\.1 (\d+)~", $response, $match)){
		return $match[1];
	}

	_log("getStatusCode: invalid response from server", true);
	return false;
}

/*
 * Parses the URL and returns the specific component
 *
 * @param url the URL to be parsed
 * @param component the component of the url (scheme, hostname, port, path, query, fragment, user, pass)
 * @return the specific component from the URL or false if the URL cannot be parsed
 */
function getComponentFromURL($url, $component){
	$url_parts = parse_url($url);
	if($url_parts !== false && isset($url_parts[$component])){
		return $url_parts[$component];
	}

	return false;
}

/*
 * Parses the URL and returns the port number. In case the port is not part of the URL, the default
 * port for the service is returned (i.e) 80 for HTTP, 443 for HTTPS
 *
 * @param url the URL to be parsed
 * @return the port number if found or false otherwise
 */
function getPortFromURL($url){
	$port = getComponentFromURL($url, "port");
	if($port !== false){
		return $port;
	}

	//port is not found in URL so lets return the default port 80 for http and 443 for https
	$scheme = getComponentFromURL($url, "scheme");
	if($scheme == "http"){
		return "80";
	}
	else if($scheme == "https"){
		return "443";
	}

	_log("getPortFromURL: unsupported URL scheme");
	return false;
}

/*
 * Sends a GET HTTP Request through php-curl if installed or falls back to using a socket and returns the content
 * It can also handle the HTTP redirects if desired to the maximum of MAXIMUM_REDIRECT redirects. It saves the content
 * to the file if the optional parameter save_file is true
 *
 * @param url the URL of the request
 * @param follow_redirect this is false by default. It enables the function to handle HTTP redirects
 * @param save_file an optional paramter indicating whether the response will be saved to a file or returned
 * @param file_name an optional parameter indicating the file name to which the response is going to be written
 * @return the content, false, HTTP headers or the file_name @see getHTTPCurl and getHTTPSocket
 */
function getHTTPContent($url, $follow_redirect = false, $save_file = false, $file_name = false){
	$get_function = "";
	static $curl_warning = false;

	if(in_array("curl", get_loaded_extensions())){
		$get_function = "getHTTPCurl";
	}
	else{
		$get_function = "getHTTPSocket";
		if($curl_warning === false){
			_log("getHTTPContent: curl extension not found, falling back to socket");
			$curl_warning = true;
		}
	}

	if($follow_redirect === false){
		return $get_function($url, $save_file, $file_name);
	}

	$cnt = 0;
	while($cnt < MAXIMUM_REDIRECT){
		$response = $get_function($url, $save_file, $file_name);

		if(isHTTPRedirect($response) === false){
			return $response;
		}

		$url = getRedirectLocation($response);

		if(!filter_var($url, FILTER_VALIDATE_URL)){
			return false;
		}

		$cnt++;
	}

	_log("getHTTPContent: maximum redirects reached", true);

	return false;
}

/*
 * Gets a random video URL from the Youtube home page
 *
 * @return the randomm video url or false if no videos are found
 */
function getRandomVideoURL(){
	$response = getHTTPContent("http://youtube.com", true);
	
	if(preg_match_all('~href="/?watch\?v=(.+?)"~', $response, $matches)){
		$matches_array = $matches[1];

		shuffle($matches_array);

		return "http://www.youtube.com/watch?v=".$matches_array[0];
	}

	_log("getRandomVideoURL: no videos found", true);

	return false;
}

/*
 * Parses the video page content and fetches the currently used Youtube HTML5 Player version (i.e) en_US-vflGC4r8Z
 *
 * @param url the URL of the video
 * @return the HTML5 Player version
 */
function getCurrentHTML5PlayerID($url){
	if($url !== false){
		$response = getHTTPContent($url, true);

		if(preg_match("/jsbin\\\\?\/((?:html5)?player\-.+?)\.js/", $response, $match)){
			return $match[1];
		}
	}

	_log("getCurrentHTML5PlayerID: unparsable JS player ID");

	return false;
}

/*
 * Construcs the Youtube HTML5 Player Javascript file URL
 *
 * @param url the URL of the video
 * @return the HTML5 Player URL
 */
function getCurrentHTML5PlayerURL($url){
	return "http://s.ytimg.com/yts/jsbin/".getCurrentHTML5PlayerID($url).".js";
}

/*
 * Tries to match a specific code structure to get the cipher function name since the javascript
 * is minified and obfuscated (i.e) f=e.sig||Kr(e.s)
 *
 * @param url the URL of the video
 * @return the cipher function name or false otherwise
 */
function getCipherJSFunctionName($file_content){
	if(preg_match("/[\$a-zA-Z][a-zA-Z\d]*=([\$a-zA-Z][a-zA-Z\d]*)\.sig\|\|([\$a-zA-Z][a-zA-Z\d]*)\(\\1\.s\)/s", 
		$file_content, $match)){
		return $match[2];
	}
	
	if(preg_match("/[\$a-zA-Z][a-zA-Z\d]*\.set\s*\(\s*\"signature\"\s*,\s*([\$a-zA-Z][a-zA-Z\d]*)\s*\(\s*[\$a-zA-Z][a-zA-Z\d]*\s*\)/",
		$file_content, $match)){
		return $match[1];
	}

	_log("getCipherJSFunctionName: unparsable function name");

	return false;
}

/*
 * Parses the cipher function body
 *
 * @param file_content the HTML5 Player JS file content
 * @return the cipher function body or false otherwise
 */
function getCipherJSFunctionBody($file_content, $function_name){
	$function_name = preg_quote($function_name, "/");
	
	if(preg_match("/$function_name\s*=\s*function\s*\([\$a-zA-Z][a-zA-Z\d]*\)\s*\{(.*?)\}/s", $file_content, $match)){
		return $match[1];
	}
	else if(preg_match("/\bfunction\s+$function_name\s*\([\$a-zA-Z][a-zA-Z\d]*\)\s*\{(.*?)\}/s", $file_content, $match)){
		return $match[1];
	}
	else if(preg_match("/(?:\bvar\s+|,\s*)$function_name\s*=\s*function\s*\([\$a-zA-Z][a-zA-Z\d]*\)\s*{(.*?)}/s", $file_content, $match)){
		return $match[1];
	}

	_log("getCipherJSFunctionBody: unparsable function body");

	return false;
}

/*
 * Gets the currently used cipher by parsing the HTML5 Player JS file as a list of
 * array splice, reverse and replace instructions. If a video URL is provided, it will be
 * used to get the Player JS file location otherwise, it visits the Youtube home page, grabs
 * a random video link and uses it
 *
 * @param url the URL from where to grab the HTML5 Player JS file
 * @return the cipher function body or false otherwise
 */
function getCipher($url = false){
	if($url === false){
		$url = getRandomVideoURL();
	}

	$playerURL = getCurrentHTML5PlayerURL($url);
	
	$response = getHTTPContent($playerURL);

	$cipher_fname = getCipherJSFunctionName($response);

	$cipher_body = getCipherJSFunctionBody($response, $cipher_fname);

	$cipher_instructions = explode(";", $cipher_body);

	//pattern matching v=v.splice("")
	$split_pattern = "/^([\$a-zA-Z][a-zA-Z\d]*)=\\1\.[\$a-zA-Z][a-zA-Z\d]*\(\"\"\)$/s";
	$split_pattern2 = "/return\s*[\$a-zA-Z][a-zA-Z\d]*\.slice/s";
	$split_pattern3 = "/\b[\$a-zA-Z][a-zA-Z\d]*\.splice/s";
	//pattern matching v=v.reverse()
	$reverse_pattern = "/^([\$a-zA-Z][a-zA-Z\d]*)=\\1\.[\$a-zA-Z][a-zA-Z\d]*\(\)$/s";
	$reverse_pattern2 = "/\b[\$a-zA-Z][a-zA-Z\d]*\.reverse\(/s";
	//pattern matching v=v.slice(n)
	$slice_pattern = "/^([\$a-zA-Z][a-zA-Z\d]*)=\\1\.[\$a-zA-Z][a-zA-Z\d]*\((\d+)\)$/s";
	//pattern matching v=f(v,n)
	$function_pattern = "/^([\$a-zA-Z][a-zA-Z\d]*)=([\$a-zA-Z][a-zA-Z\d]*)\(\\1,(\d+)\)$/s";
	//pattern matching f(v,n)
	$function2_pattern = "/^(?:(?:[\$a-zA-Z][a-zA-Z\d]*)\.)?([\$a-zA-Z][a-zA-Z\d]*)\([\$a-zA-Z][a-zA-Z\d]*,(\d+)\)$/s";
	//pattern matching return v.join("")
	$join_pattern = "/^return\s+[\$a-zA-Z][a-zA-Z\d]*\.[\$a-zA-Z][a-zA-Z\d]*\(\"\"\)$/s";
	//pattern matching swap
	$swap_pattern = "/var\s([\$a-zA-Z][a-zA-Z\d]*)=([\$a-zA-Z][a-zA-Z\d]*)\[0\];/s";

	$translated_instructions = array();
	foreach($cipher_instructions as $instruction){
		$instruction = trim($instruction);
		if(preg_match($reverse_pattern, $instruction, $match)){
			$translated_instructions[] = "reverse";
		}
		else if(preg_match($slice_pattern, $instruction, $match)){
			$translated_instructions[] = "splice".$match[2];
		}
		else if(preg_match($function_pattern, $instruction, $match) || 
			preg_match($function2_pattern, $instruction, $match)){

			$fname = $match[count($match) - 2];
			$param = $match[count($match) - 1];

			$fname = preg_replace("/^.*\./s", "", $fname);

			$fname = preg_quote($fname, "/");

			if(preg_match("/\b$fname:\s*function\s*\(.*?\)\s*({[^{}]+})/s", $response, $match)){
				$fbody = $match[1];

				//swap
				if(preg_match($swap_pattern, $fbody, $match)){
					$translated_instructions[] = "swap".$param;
				}
				else if(preg_match($reverse_pattern2, $fbody)){
					$translated_instructions[] = "reverse";
				}
				else if(preg_match($split_pattern2, $fbody) || preg_match($split_pattern3, $fbody)){
					$translated_instructions[] = "slice".$param;
				}
				else{
					_log("getCipher: unparsable function body".$fbody."");
				}
			}
			else{
				_log("getCipher: function not found ".$fname."");
			}

		}
		else{
			if(!preg_match($split_pattern, $instruction) && !preg_match($join_pattern, $instruction)){
				_log("getCipher: unparsable instruction ".$instruction."");
			}
		}
	}

	return implode(" ", $translated_instructions);
}

/*
 * A logger function, which logs messages to STDOUT. Fatal messages will exit the application
 *
 * @param msg the message to be sent to STDOUT
 * @param fatal an optional parameter indicating whether to exit the application or no
 */

function _log($msg, $fatal = false){
	echo $msg."\n";
	if($fatal !== false){
		exit;
	}
}

/*
 * Prints the correct use of the application with available arguments
 *
 * @param command the executable file name
 * @return the string of the correct use
 */
function getCorrectUse($command){
	$use = $command;
	$use .= " [-l]";
	$use .= " [-t title]";
	$use .= " [-f format]";
	$use .= " [-c]";
	$use .= " youtube-url";

	return $use;
}

/*
 * Prints the help section of the application
 *
 * @param command the executable file name
 */
function sendHelp($command){
	$use = getCorrectUse($command);
	_log($use);
	_log("Arguments are: ");
	_log("-c        Prints currently used JS cipher function");
	_log("-t title  Provides a custom filename. The video ID and the extension will still be appended to this argument");
	_log("-f format Provides a specific format to download. You can list available format IDs using the -l option");
	_log("-l        Lists the available formats for the provided video");
	_log("-h        Prints the help");
}

/*
 * The main function
 *
 * @param argc the number of arguments
 * @param argv the arguments
 */

function main($argc, $argv){
	if($argc<2){
		_log("Missing arguments, correct use is: ".getCorrectUse($argv[0])."\n For more information please use -h", true);
	}

	$list_arg = false;
	$title_arg = false;
	$format_arg = false;
	$cipher_arg = false;
	$help_arg = false;
	$url = "";

	//parsing arguments
	for($i=1; $i<$argc; $i++){
		if($argv[$i][0] == "-"){
			if(isset($argv[$i][1])){
				$option = $argv[$i][1];
				switch($option){
					case "l":
						$list_arg = true;
						break;
					case "t":
						if(!empty($argv[$i+1])){
							$title_arg = preg_replace("/[^a-z0-9\._-]+/i", "", $argv[$i+1]);
							$i++;
							break;
						}
						else{
							_log("Invalid arguments, correct use is: ".getCorrectUse($argv[0])."\n For more information please use -h", true);
							break;	
						}
					case "f":
						if(isset($argv[$i+1]) && is_numeric($argv[$i+1])){
							$format_arg = $argv[$i+1];
							$i++;
							break;
						}
						else{
							_log("Invalid arguments, correct use is: ".getCorrectUse($argv[0])."\n For more information please use -h", true);
							break;	
						}
					case "c":
						$cipher_arg = true;
						break;
					case "h":
						$help_arg = true;
						break;
					default:
						_log("Invalid arguments, correct use is: ".getCorrectUse($argv[0])."\n For more information please use -h", true);
						break;
				}
			}
		}
		else{
			$url = $argv[$i];
		}
	}

	//send cipher if its argument is present and quit
	if($cipher_arg !== false){
		_log("Current cipher: ".getCipher());
		exit;
	}

	//send the help section and quit
	if($help_arg !== false){
		sendHelp($argv[0]);
		exit;
	}
	
	//checks a valid youtube URL
	//currently not supporting URL shortners such as youtu.be
	if(!filter_var($url, FILTER_VALIDATE_URL) || !preg_match("~\byoutube.com/watch\?v=.+~", $url)){
		_log("Invalid link, correct use is: ".$argv[0]." http://www.youtube.com/watch?v=xxxxxx for more info use -h", true);
	}

	_log("Sending HTTP GET Request, URL: ".$url);
	$response = getHTTPContent($url);

	_log("Getting download links");
	$downloads = getDownloadURLS($response);
	
	$title = ($title_arg !== false) ? $title_arg : getTitle($response);
	$v = getV($url);

	if($title === false){
		_log("Main: Could not get the video title");
	}

	if($v === false){
		_log("Main: Not a valid youtube url", true);
	}

	_log("Title: ".$title);
	_log("Video ID: ".$v);

	if($list_arg !== false){
		_log("Available Formats are: ");
		listAvailableFormats($downloads);
		exit;
	}

	if($format_arg === false){
		_log("Fetching an good quality video format");
		$download = getHighQualityFormat($downloads);	
	}
	else{
		_log("Fetching format number: ".$format_arg);
		$download = getSpecificFormat($downloads, $format_arg);
		if($download === false){
			_log("Format not found, Fetching an available good quality format");
			$download = getHighQualityFormat($downloads);
		}
	}

	if($download === false){
		_log("Main: no video links found", true);
	}

	$direct_link = $download['url'];

	if(isset($download['sig'])){
		_log("Found unciphered signature: ".$download['sig']);
		$signature = $download['sig'];
		$direct_link .= "&signature=".$signature;
	}
	else if(isset($download['s'])){
		_log("Deciphering signature: ".$download['s']);
		$signature = decipherSIG($download, $url);
		_log("Deciphered signature: ".$signature);
		$direct_link .= "&signature=".$signature;
	}
	
	$filename = ($title === false) ? "" : $title;
	$filename .= "_".$v;

	_log("Downloading to file: ".$filename);

	$headers = getHTTPContent($direct_link, true, true, $filename);

	appendProperExt($download, $filename);
	
	_log("Download complete :)");
}

/*
 * Tries to guess the file extension from the mime type
 * and appends it to the file name
 *
 * @param link the link object containing the mime type
 * @param file_name the actual file name without extension
 * @return the new file name
 */
function appendProperExt($link, $file_name){
	if(isset($link['type'])){
		if(stripos($link['type'], "/mp4") == false){
			$ext = "mp4";
		}
		else if(stripos($link['type'], "/webm") == false){
			$ext = "webm";
		}
		else if(stripos($link['type'], "/x-flv") == false){
			$ext = "flv";
		}
		else if(stripos($link['type'], "video/3gpp") == false){
			$ext = "3gp";
		}
		else{
			return false;
		}

		$new_file = $file_name.".".$ext;
		
		rename($file_name, $new_file);
		return $new_file;
	}
}

/*
 * Gets the v parameter from the URL query string
 *
 * @param url the URL of the youtube video
 * @return the v parameter
 */
function getV($url){
	if(preg_match("~/watch\?v=(.+)~", $url, $match)){
		return $match[1];
	}

	return false;
}

/*
 * Parses the youtube video page content and extracts the title of the video
 *
 * @param page_content the content of the youtube video page
 * @return the title of the video
 */
function getTitle($page_content){
	if(preg_match("~<title>(.+?)</title>~", $page_content, $match)){
		return $match[1];
	}

	return false;
}

/*
 * Applies the cipher instructions to the s field of the link object
 * the result is in a format of 40.40 characters signature
 *
 * @param link the link object having the ciphered signature in the s field
 * @return the deciphered signature
 */
function decipherSIG($link, $original_link){
	$cipher = getCipher($original_link);

	$sig = str_split($link['s']);
	$instructions = explode(" ", $cipher);

	foreach($instructions as $instruction){
		if(strpos($instruction, "reverse") !== false){
			$sig = array_reverse($sig);
		}
		else if(strpos($instruction, "swap") !== false){
			$param = str_replace("swap", "", $instruction);
			if(is_numeric($param)){
				$replace_index = intval($param) % count($sig);
				$tmp = $sig[0];
				$sig[0] = $sig[$replace_index];
				$sig[$replace_index] = $tmp;
			}

		}
		else if(strpos($instruction, "slice") !== false){
			$param = str_replace("slice", "", $instruction);
			if(is_numeric($param)){
				$sig = array_slice($sig, intval($param));
			}
		}
	}

	return implode($sig);
}

/*
 * Tries to grab a link of a high quality video prioritizing mp4 over flv and currently not considering webm.
 * If it fails to find a good link it will return the first one
 *
 * @param links a list of available links for the video
 * @return a high quality video link or false if the links list is empty
 */
function getHighQualityFormat($links){
	if(is_array($links)){
		foreach($links as $link){
			if(isset($link['type']) && (stripos($link['type'], "video/mp4") !== false)
				&& isset($link['quality']) && (stripos($link['quality'], "hd720") !== false)){
				$high_quality_mp4 = $link;
			}
			else if(isset($link['type']) && (stripos($link['type'], "video/mp4") !== false)){
				$unknown_quality_mp4 = $link;
			}

			if(isset($link['type']) && (stripos($link['type'], "video/mp4") !== false)
				&& isset($link['quality']) && (stripos($link['quality'], "medium") !== false)){
				$medium_quality_mp4 = $link;
			}

			if(isset($link['type']) && (stripos($link['type'], "video/x-flv") !== false)){
				$small_quality_flv = $link;
			}
		}

		if(isset($high_quality_mp4)){
			return $high_quality_mp4;
		}
		else if(isset($medium_quality_mp4)){
			return $medium_quality_mp4;
		}
		else if(isset($unknown_quality_mp4)){
			return $unknown_quality_mp4;
		}
		else if(isset($small_quality_flv)){
			return $small_quality_flv;
		}
		else{
			return $links[0];
		}
	}

	return false;
}

/*
 * Gets a link object of a specified format given by the itag field
 *
 * @param links a list of available links and formats
 * @return the link object of a specific format or false if not found
 */
function getSpecificFormat($links, $preferred_format){
	if(is_array($links)){
		foreach($links as $link){
			if($link['itag'] == $preferred_format){
				return $link;
			}
		}
	}

	return false;
}

/*
 * Prints available video formats to STDOUT
 *
 * @param links the list of available links and formats
 */
function listAvailableFormats($links){
	if(is_array($links)){
		foreach($links as $link){
			if(isset($link['itag'])){
				printf("ID: ");
				printf("%s", $link['itag']);
				printf("|");
				if(isset($link['quality'])){
					printf("%s", $link['quality']);
					printf(";");
				}
				if(isset($link['type'])){
					printf("%s", $link['type']);
				}
				printf("\n");
			}
		}
	}
}

/*
 * Parses the video page content to extract the JS object which provides information
 * about available links, formats and mime types. It prioritizes the use of fmt_url_map and adaptive_fmts objects
 * over fmt_stream_map and url_encoded_fmt_stream_map. For more info, check your youtube video page source
 *
 * @param page_content the video page content
 * @return a string of available links
 */
function getDownloadURLS($page_content){
	//var_dump($page_content);
	if(preg_match("/ytplayer\.config\s*=\s*\\{(.*?)\\};/s", $page_content, $match)){
		$unformatted = $match[1];
		$unformatted = str_replace("\u0026", '&', $unformatted);
		
		if(preg_match('/"fmt_url_map":\s*"(.*?)"/s', $unformatted, $match)){
			//$type = "fmt_url_map";
			$urls_map = $match[1];
		}
		else if(preg_match('/"fmt_stream_map":\s*"(.*?)"/s', $unformatted, $match)){
			//$type = "fmt_stream_map";
			$url_maps = $match[1];
		}
		else if(preg_match('/"url_encoded_fmt_stream_map":\s*"(.*?)"/s', $unformatted, $match)){
			//$type = "url_encoded_fmt_stream_map";
			$url_maps = $match[1];
 		}

 		if(preg_match('/"adaptive_fmts":\s*"(.*?)"/s', $unformatted, $match)){
 			$url_maps2 = $match[1];
 		}

 		if(!isset($url_maps)){
 			if(stripos($page_content, "This video has been age-restricted") !== false){
 				_log("getDownloadURLS: the video has been age-restricted and currently unsupported", true);
 			}
 			else if(stripos($page_content, "large volume of requests") !== false){
 				_log("getDownloadURLS: large volume of requests, please visit youtube to solve the captcha", true);
 			}
 			else if(stripos($page_content, "is not available") !== false){
 				_log("getDownloadURLS: the video is not available0", true);
 			}
 			else if(stripos($page_content, "Content Warning") !== false){
 				_log("getDownloadURLS: content warning", true);
 			}
 			else if(stripos($page_content, "removed by the user") !== false){
 				_log("getDownloadURLS: the video was removed by the user", true);
 			}
 			else if(stripos($page_content, "copyright claim") !== false){
 				_log("getDownloadURLS: copyright claim", true);
 			}
 			else if(stripos($page_content, "in your country") !== false){
 				_log("getDownloadURLS: the video is not available in your country", true);
 			}
 			else{
 				_log("getDownloadURLS: no links are found for this video", true);
 			}
 		}

 		$url_maps .= ",".$url_maps2;

 		return parseURLMAP($url_maps);
	}

	_log("getDownloadURLS: no links are found for this video", true);

	return false;
}

/*
 * Parses an inline URL map JS object and constructs a list of links objects given by
 * the download URL of the video, the mime type, the quality, the format (itag) and the signature (s or sig)
 *
 * @param url_maps the string of the js object to be parsed
 * @return an array of links objects
 */
function parseURLMAP($url_maps){
	$url_maps = explode(",", $url_maps);

	$links = array();

	foreach($url_maps as $url_map){
		$link = array();

		if(preg_match("/\bsig=([^&]+)/s", $url_map, $match)){
			$link['sig'] = urldecode($match[1]);
		}

		if(preg_match("/\bs=([^&]+)/s", $url_map, $match)){
			$link['s'] = urldecode($match[1]);
		}

		if(preg_match("/\bitag=([^&]+)/s", $url_map, $match)){
			$link['itag'] = urldecode($match[1]);
		}

		if(preg_match("/\burl=([^&]+)/s", $url_map, $match)){
			$link['url'] = urldecode($match[1]);
		}

		if(preg_match("/\bquality=([^&]+)/s", $url_map, $match)){
			$link['quality'] = urldecode($match[1]);
		}

		if(preg_match("/\btype=([^&]+)/s", $url_map, $match)){
			$link['type'] = urldecode($match[1]);
		}

		if(isset($link['url'])){
			$links[] = $link;
		}
	}

	return $links;
}

set_time_limit(0);
main($argc, $argv);

?>