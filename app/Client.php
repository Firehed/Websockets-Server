<?php

class Client {
	const State_New       = 1; // Just connected
	const State_Connected = 2;
	
	const Guid_v13 = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

	private static $count = 0;
	private $socket;
	private $position;
	private $state;
	
	public function __construct($socket) {
		$this->socket = $socket;
		$this->position = ++self::$count;
		$this->state = self::State_New;
	} // function __construct
	
	public function message($message) {
		socket_write($this->socket, "\x00$message\xFF");
	} // function message
	
	public function handleInput() {
		$input = socket_read($this->socket, 1024);
		if (!$input) {
			$this->disconnect();
			return;
		}
		$input = trim($input, "\x00\xFF");
		echo "Message Received:\n";
		echo $input;

		if (self::State_Connected != $this->state) {
			$this->handleLogin($input);
			return;
		}
		
		Action::perform($this, $input);
		
	} // function handleInput
	
	public function handleLogin($in) {
		/*
		GET / HTTP/1.1
		Upgrade: websocket
		Connection: Upgrade
		Host: 127.0.0.1:9000
		Origin: https://vm.wepay.com
		Sec-WebSocket-Key: iguoNlFsLEeeK2D+t90RMg==
		Sec-WebSocket-Version: 13
		*/
		// $request = $host = $origin = $key1 = $key2 = $verifier = $response = $sec = '';
		$request = $upgrade = $connection = $host = $origin = $key = $version = '';
		if (preg_match('/GET (.*) HTTP/',                       $in,$m)) $request    = $m[1];
		if (preg_match('/Upgrade: (.*)(\r\n|$)/',               $in,$m)) $upgrade    = $m[1];
		if (preg_match('/Connection: (.*)(\r\n|$)/',            $in,$m)) $connection = $m[1];
		if (preg_match('/Host: (.*)(\r\n|$)/',                  $in,$m)) $host       = $m[1];
		if (preg_match('/Origin: (.*)(\r\n|$)/',                $in,$m)) $origin     = $m[1];
		if (preg_match('/Sec-WebSocket-Key: (.*)(\r\n|$)/',     $in,$m)) $key        = $m[1];
		if (preg_match('/Sec-WebSocket-Version: (.*)(\r\n|$)/', $in,$m)) $version    = $m[1];
		unset($m);

		if (!$key) {
			$this->rejectConnection();
		}
		if ($version != 13) {
			$this->rejectConnection();
		}
		// Authenticate against the key

		$auth = self::authenticateKey($key);
		$upgrade = "HTTP/1.1 101 Switching Protocols\r\n"
		. "Upgrade: websocket\r\n"
		. "Connection: Upgrade\r\n"
		. "Sec-WebSocket-Accept: $auth\r\n"
		. "\r\n";
		
		echo $upgrade;
		// 
		// if ($key1 && $key2 && $verifier) {
		// 	$digits1 = preg_replace('/[^0-9]/', '', $key1);
		// 	$digits2 = preg_replace('/[^0-9]/', '', $key2);
		// 	$spaces1 = substr_count($key1, ' ');
		// 	$spaces2 = substr_count($key2, ' ');
		// 	
		// 	$cat = pack('N',$digits1/$spaces1) . pack('N', $digits2/$spaces2) . $verifier;
		// 	
		// 	$response = "\r\n" . md5($cat, TRUE);
		// 	$sec = 'Sec-';
		// }
		/*
		$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n"
		          . "Upgrade: WebSocket\r\n"
		          . "Connection: Upgrade\r\n"
		          . "{$sec}WebSocket-Origin: $origin\r\n"
		          . "{$sec}WebSocket-Location: ws://$host$request\r\n"
		          . $response
		          . "\r\n";
		*/
		socket_write($this->socket, $upgrade);
		$this->state = self::State_Connected;
	} // function handleLogin

	private static function authenticateKey($key) {
		return base64_encode(sha1($key . self::Guid_v13, true));
	}
	
	public function getSocket() {
		return $this->socket;
	} // function getSocket
	
	public function getPosition() {
		return $this->position;
	} // function getPosition
	
	public function disconnect() {
		echo 'Disconnected';
		
		socket_close($this->socket);
		Server::removeClient($this);
	} // function disconnect
	
	public function rejectConnection() {
		// implement me
	} // function rejectConnection
} // class Client
