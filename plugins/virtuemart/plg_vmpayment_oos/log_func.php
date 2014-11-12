<?php 

function outToLog($logMess) {
	$file_out = dirname(__FILE__).'/log.txt';
	file_put_contents($file_out, date('l jS \of F Y h:i:s A').' || '.$logMess.PHP_EOL, FILE_APPEND | LOCK_EX);
}

?>