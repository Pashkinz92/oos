<?php
/**
 * @package VirtueMart
 * @subpackage payment
 * @author oos.pscb.ru
 * @copyright Copyright (C) 2012-2014 oos.pscb.ru. All rights reserved.
 * @license GNU General Public License version 3
 */

header('Content-Type: application/xml; charset=utf-8');

include_once 'log_func.php';
outToLog("RESPONSE: OOS_Notify");

$encrypted_request = file_get_contents('php://input');

$ORDER = array(
    'encrypted_request' => $encrypted_request
);

$data = '';
//готовим строку параметров
foreach($ORDER as $key => $value) {
	$data .= '&'.$key.'='.$value;
}

$host = $_SERVER['HTTP_HOST'];

$fp = fsockopen($host, 80, $errno, $errstr, 30); //открываем соединение

if ($fp) {
	//строка http заголовков
	$out = "POST /index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component HTTP/1.1\n";
	$out .= "Host: ".$host."\n";
	$out .= "Content-Type: application/x-www-form-urlencoded\n";
	$out .= "Connection: close\n";
	$out .= "Content-Length: ".strlen($data)."\n\n";
	$out .= $data."\n\n";
    //outToLog("RESPONSE: out = ".$out);
    //outToLog("RESPONSE OOS_Notify: fp = ".$fp.' out = '.$out);
    outToLog("RESPONSE: OOS_Notify: send the request");
	@fputs($fp, $out); //отправляем POST запрос
	
	$body = false;
	//выводим результат
	while (!@feof($fp)) {
		$str = @fgets($fp, 1024); //читаем одну строку

		if ($body === true) {
			echo $str;
		}
		
		//отделяем заголовок от тела результата
		if ($str == "___rez___\n") {
			$body = true;
		}
	}
	
	fclose($fp); //закрываем соединение
}
?>