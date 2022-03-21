<?php

include __DIR__ . '/includes/config.php';
include __DIR__ . '/includes/send_message.php';

# Токен и ресурс Telegram
$url = "https://api.telegram.org/bot" . $botToken;

# Настройки Rest Countries. Источник данных для локального JSON
$api_endpoint = 'https://restcountries.eu/rest/v2/all?fields=alpha2Code;capital;latlng;name';

# Принимаем запрос
$data = file_get_contents('php://input');
$data = json_decode($data, true);

# Определяем какого типа запрос
if ($data['callback_query']) {
    $message = $data['callback_query']['data'];
    $data = $data['callback_query'];
    $is_callback = true;
} else {
    $message = $data['message']['text'];
    $data = $data['message'];
}

# Запись ответа в файл для отладки
file_put_contents('log/data.txt', print_r($data, true));

$chat_id = $data['from']['id'];
$user_name = $data['from']['first_name'];
$message_id = $data['message']['message_id'];

# Сохранить в историю запросов
file_put_contents('log/history.csv', "$chat_id;$user_name;$location\n", FILE_APPEND);

# Проверим файл игрока и создаем если он новый
$user = "users/$chat_id.json";
if (!file_exists($user)) {
    $user_info = [
        'username' => $user_name,
        'attempts' => 0,
        'win' => 0,
        'lose' => 0,
    ];
    $user_info = json_encode($user_info);
    file_put_contents($user, $user_info);
} else {
    $user_info = file_get_contents($user);
    $user_info = json_decode($user_info, true);
}

# Формируем данные для вопроса
$countries = get_countries();
$rand_index = rand(0, 249);
$country = get_random_country($countries, $rand_index);
$country_name = $country['name'];
$country_capital = $country['capital'];
$country_code = $country['alpha2Code'];
$latlng = $country['latlng'];
$flag = get_flag($country_code);

# Обработка команд
if ($message === '/start') {
    $send_data = [
        'chat_id' => $chat_id,
        'photo' => $flag,
        'caption' => "What is the capital of $country_name?",
        'reply_markup' => json_encode([
            'inline_keyboard' => get_city_variants($countries),
        ]),
    ];
    $method = 'sendPhoto';
    sendMessage($url, $method, $send_data);
    return;
}

if (preg_match('/\/getlocation/', $message)) {
    $latlng = explode('?', $message);
    $send_data = [
        'chat_id' => $chat_id,
        'latitude' => $latlng[1],
        'longitude' => $latlng[2],
    ];
    $method = 'sendLocation';
    sendMessage($url, $method, $send_data);
    return;
}

if ($is_callback) {
    $answer = explode('/', $message);
    $right_capital = get_capital($answer[0]);
    $answer_capital = $answer[1];
    $latlng = get_location($answer[0]);

    if ($answer_capital == $right_capital) {

        $score = update_user_info($chat_id, $user_info, true);

        $send_data = [
            'text' => "Well Done! 👍\n$score",
            'chat_id' => $chat_id,
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "The next one! 😎", "callback_data" => "/start"],
                        ['text' => "Where is it? 😳", "callback_data" => "/getlocation?$latlng[0]?$latlng[1]"],
                    ],
                ],
            ]),
        ];
    } else {
        $score = update_user_info($chat_id, $user_info, false);

        $send_data = [
            'text' => "I did'n hear that! 🤦‍♂\nRight answer is $right_capital!\n$score",
            'chat_id' => $chat_id,
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "Give me a chance! 🙏", "callback_data" => "/start"],
                        ['text' => "Where is it? 😳", "callback_data" => "/getlocation?$latlng[0]?$latlng[1]"],
                    ],
                ],
            ]),
        ];
    }
    $method = 'sendMessage';
    sendMessage($url, $method, $send_data);
    return;
}

function update_user_info($chat_id, $user_info, $result)
{
    $user_info['attempts'] = $user_info['attempts'] + 1;
    if ($result) {
        $user_info['win'] = $user_info['win'] + 1;
    }
    $user_info['lose'] = $user_info['attempts'] - $user_info['win'];
    $score = "Attempts: " . $user_info['attempts'] . " / " . "Wins: " . $user_info['win'] . " / " . "Losses: " . $user_info['lose'];
    $user_info = json_encode($user_info);
    file_put_contents($GLOBALS['user'], $user_info);
    return $score;
}

function get_capital($index)
{
    $countries = $GLOBALS['countries'];
    $country = $countries[$index];
    return $country['capital'];
}

function get_location($index)
{
    $countries = $GLOBALS['countries'];
    $country = $countries[$index];
    return $country['latlng'];
}

function get_countries()
{
    $countries = json_decode(file_get_contents('countries.json'), true);
    return $countries;
}

function get_random_country($countries, $rand_index)
{
    return $countries[$rand_index];
}

function get_city_variants($countries)
{
    $cities = [];
    while (count($cities) <= 4) {
        $rand_index = rand(0, 249);
        $rand_country = get_random_country($countries, $rand_index);
        $city = $rand_country['capital'];
        if (!in_array($city, $cities)) {
            array_push($cities, $city);
        }
    }
    $cities[rand(0, 3)] = $GLOBALS['country_capital']; // Добавляем правильный ответ
    $country_index = $GLOBALS['rand_index'];

    $inputs = [
        [
            ['text' => "$cities[0]", "callback_data" => "$country_index/$cities[0]"],
            ['text' => "$cities[1]", "callback_data" => "$country_index/$cities[1]"],
        ],
        [
            ['text' => "$cities[2]", "callback_data" => "$country_index/$cities[2]"],
            ['text' => "$cities[3]", "callback_data" => "$country_index/$cities[3]"],
        ],
    ];
    return $inputs;
}

function get_flag($country_code)
{
    $country_code = strtolower($country_code);
    return "https://flagcdn.com/w1280/$country_code.png";
}
