<?php

// настраиваемые данные
$merchant_api_url_base = "https://oosdemo.pscb.ru/merchantApi";
$secret_merchant_key = 'supersecretkeyhere';
$market_place_id = 42;
$order_id = "ORDER-28286814037";

/**
 * Запрос к API Мерчанта.
 * @param $method string имя вызываемого метода API; например "checkPayment"
 * @param $params mixed словарь аргументов передаваемых в метод API
 * @return mixed ответ в виде объекта JSON
 * @throws Exception в случае сетевой ошибки
 */
function merchant_api($method, $params) {
    global$secret_merchant_key, $merchant_api_url_base;

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

    static $curl = null;
    if (is_null($curl)) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; OOS API client; '.php_uname('s').'; PHP/'.phpversion().')');
    }
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $request_body);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $request_headers);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    $response_text = curl_exec($curl);

    if ($response_text === false) {
        throw new Exception('Could not get reply: '.curl_error($curl));
    }
    $response_json = json_decode($response_text, true);
    if (!$response_json) {
        throw new Exception('Invalid data received, please make sure connection is working and requested API exists');
    }
    return $response_json;
}

// вызов АПИ
$check = merchant_api("checkPayment", array("marketPlace" => $market_place_id, "orderId" => $order_id));

// вывод результата в консоль
var_dump("Состояние платежа:", $check);

// анализ успешности/неуспешности вызова
if ($check['status'] === 'STATUS_SUCCESS') {
    echo "Это успех\n";
} else {
    echo "Неудача: " . $check['errorCode'] . " / " . $check['errorDescription'] . "\n";
}
