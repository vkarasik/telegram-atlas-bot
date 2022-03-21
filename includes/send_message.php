<?php

function sendMessage($url, $method, $data)
{
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_HEADER => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_URL => $url . '/' . $method,
        CURLOPT_POSTFIELDS => $data,
        // CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
    ]);
    $result = curl_exec($curl);
    curl_close($curl);
    file_put_contents('log/result.txt', print_r($result, 1));
}
