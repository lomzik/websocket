<?php

//пример реализации чата
class WebsocketWorkerHandler extends WebsocketWorker
{
    protected $ips;

    protected $logins = array();

    protected function onOpen($client) {//вызывается при соединении с новым клиентом
        if ($this->logins) {
            $this->sendToClient($client, 'logins', array_keys($this->logins));
        }
    }

    protected function onClose($client) {//вызывается при закрытии соединения клиентом
        if ($login = array_search(intval($client), $this->logins)) {
            unset($this->logins[$login]);
            $this->sendToMaster('logout', array('login' => $login, 'clientId' => intval($client)));
            $this->sendToClients('logout', $login);
        }
    }

    protected function onMessage($client, $data) {//вызывается при получении сообщения от клиента
        if (!strlen($data['payload']) || !mb_check_encoding($data['payload'], 'utf-8')) {
            return;
        }

        //антифлуд:
        $source = explode(':', stream_socket_get_name($client, true));
        $ip = $source[0];
        $time = time();
        if (isset($this->ips[$ip]) && $this->ips[$ip] == $time) {
            return;
        } else {
            $this->ips[$ip] = $time;
        }

        if ($login = array_search(intval($client), $this->logins)) {
            $message = $login . ': ' . strip_tags($data['payload']);
            $this->sendToMaster('message', $message);
            $this->sendToClients('message', $message);
        } else {
            if (preg_match('/^[a-zA-Z0-9]{1,10}$/', $data['payload'], $match)) {
                if (isset($this->logins[$match[0]])) {
                    $this->sendToClient($client, 'message', 'Система: выбранное вами имя занято, попробуйте другое.');
                } else {
                    $this->logins[$match[0]] = -1;
                    $this->sendToMaster('login', array('login' => $match[0], 'clientId' => intval($client)));
                }
            } else {
                $this->sendToClient($client, 'message', 'Система: ошибка при выборе имени. В имени можно использовать английские буквы и цифры. Имя не должно превышать 10 символов.');
            }
        }
        //var_export($data);
        //шлем всем сообщение, о том, что пишет один из клиентов
        //echo $data['payload'] . "\n";
    }

    protected function onSend($packet) {//вызывается при получении сообщения от мастера
        $packet = $this->unpack($packet);
        if ($packet['cmd'] == 'message') {
            $this->sendToClients('message', $packet['data']);
        } elseif ($packet['cmd'] == 'login') {
            if ($packet['data']['result']) {
                $this->logins[ $packet['data']['login'] ] = $packet['data']['clientId'];
                $this->sendToClients('login', $packet['data']['login']);
                if (isset($this->clients[ $packet['data']['clientId'] ])) {
                    $this->sendToClient($this->clients[ $packet['data']['clientId'] ], 'message', 'Система: вы вошли в чат под именем ' . $packet['data']['login']);
                }
            } else {
                $this->sendToClient($this->clients[ $packet['data']['clientId'] ], 'message', 'Система: выбранное вами имя занято, попробуйте другое.');
            }
        } elseif ($packet['cmd'] == 'logout') {
            unset($this->logins[$packet['data']['login']]);
            $this->sendToClients('logout', $packet['data']['login']);
        }
    }

    protected function sendToMaster($cmd, $data) {//отправляем сообщение на мастер, чтобы он разослал его на все воркеры
        $this->write($this->master, $this->pack($cmd, $data), self::SOCKET_MESSAGE_DELIMITER);
    }

    private function sendToClients($cmd, $data) {
        $data = $this->encode($this->pack($cmd, $data));
        foreach ($this->clients as $client) {
            $this->write($client, $data);
        }
    }

    private function sendToClient($client, $cmd, $data) {
        $this->write($client, $this->encode($this->pack($cmd, $data)));
    }

    public function pack($cmd, $data) {
        return json_encode(array('cmd' => $cmd, 'data' => $data));
    }

    public function unpack($data) {
        return json_decode($data, true);
    }
}