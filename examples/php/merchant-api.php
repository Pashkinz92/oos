<?php

//
// настраиваемые данные
//
// URL тестового сервера
$merchant_api_url_base = "https://oosdemo.pscb.ru/merchantApi";
// номер магазина в OOS
$market_place_id = 42;
// секретный ключ для доступа к API OOS, соответствует номеру магазина
$secret_merchant_key = 'supersecretkeyhere';
// номер платежа в вашем магазине
$order_id = "ORDER-2128506";

/**
 * Запрос к API Мерчанта.
 * @param $method string имя вызываемого метода API; например "checkPayment"
 * @param $params mixed словарь аргументов передаваемых в метод API
 * @return mixed ответ в виде объекта JSON
 * @throws Exception в случае сетевой ошибки
 */
function merchant_api($method, $params) {
    global $secret_merchant_key, $merchant_api_url_base;

    $url = "$merchant_api_url_base/$method";

    $request_body = json_encode($params);

    $raw_signature = $request_body . $secret_merchant_key;
    $signa = hash('sha256', $raw_signature);

    $request_headers = array(
        "Signature: " . $signa,
        "Expect: ",
        "Content-Type: application/json",
        "Content-Length: " . strlen($request_body),
    );

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; OOS API client; '.php_uname('s').'; PHP/'.phpversion().')');
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $request_body);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $request_headers);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    // use it for debugging purposes
    // включить для отладки
    //curl_setopt($curl, CURLOPT_VERBOSE, 1);

    // use it if the following code fails with Exception "Could not get reply from OOS..."
    // попробуйте эти опции если не удается соединенится с сервером (Could not get reply from OOS)
    //curl_setopt($curl, CURLOPT_SSLVERSION, 3);
    //curl_setopt($curl, CURLOPT_SSL_CIPHER_LIST, 'SSLv3');

    $response_text = curl_exec($curl);

    if ($response_text === false) {
        throw new Exception('Could not get reply from OOS. Err No: '.curl_errno($curl).', Description: '.curl_error($curl));
    }
    $response_json = json_decode($response_text, true);
    if (!$response_json) {
        throw new Exception('Invalid data received, please make sure connection is working and requested API exists');
    }
    curl_close($curl);
    return $response_json;
}

// вызов АПИ
$check = merchant_api("checkPayment", array("marketPlace" => $market_place_id, "orderId" => $order_id));

// вывод результата в консоль
var_dump("Состояние платежа:", $check);

// анализ успешности/неуспешности вызова
if ($check['status'] === 'STATUS_SUCCESS') {
    echo "Платёж $order_id найден\n";
    $state = $check['payment']['state'];
    $amount = $check['payment']['amount'];
    if ($state == 'end') {
        echo "Платеж на сумму $amount проведен\n";
    } else {
        echo "Платеж на сумму $amount находится в состоянии $state\n";
    }
} else {
    echo "Неудача: " . $check['errorCode'] . " / " . $check['errorDescription'] . "\n";
}
