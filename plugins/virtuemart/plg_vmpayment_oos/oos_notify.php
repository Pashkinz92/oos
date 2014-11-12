<?php

/**
 * @package VirtueMart
 * @subpackage payment
 * @author oos.pscb.ru
 * @copyright Copyright (C) 2012-2014 oos.pscb.ru. All rights reserved.
 * @license GNU General Public License version 3
 */

include_once 'log_func.php';

header('Content-Type: application/xml; charset=utf-8');

outToLog("#oos_notify: started");
// outToLog("_SERVER dumped: " . print_r($_SERVER, true));

$encrypted_request = file_get_contents('php://input');
outToLog("#oos_notify: got encrypted_request of length " . strlen($encrypted_request));

$thisHost = $_SERVER['HTTP_HOST'];
$resendURL = '/index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component';
$contentType = $_SERVER['CONTENT_TYPE'];
outToLog("#oos_notify: contentType: $contentType");

outToLog("#oos_notify: resending request to $resendURL");
$fp = fsockopen($thisHost, 80, $errno, $errstr, 30);

if ($fp) {
	//строка http заголовков
	$out = "POST $resendURL HTTP/1.1\n";
	$out .= "Host: ".$thisHost."\n";
	//$out .= "Content-Type: application/x-www-form-urlencoded\n";
	$out .= "Content-Type: $contentType\n";
	$out .= "Connection: close\n";
	$out .= "Content-Length: ".strlen($encrypted_request)."\n\n";
	$out .= $encrypted_request."\n\n";
	@fputs($fp, $out); //отправляем POST запрос

    $respHtml = '';
	while (!@feof($fp)) {
		$line = @fgets($fp, 1024); //читаем одну строку
		// outToLog(trim($line));
        $respHtml .= $line;
	}
    fclose($fp);

    if (preg_match("/<!-- JSON BEGIN(.+)JSON END -->/", $respHtml, $matches)) {
        $respJsonStr = $matches[1];
        echo $respJsonStr;
    } else {
        outToLog("#oos_notify: failed to find JSON in response from $resendURL");
        http_response_code(500);
    }
	
} else {
    outToLog("#oos_notify: failed making a http post to $resendURL");
	http_response_code(500);
}
