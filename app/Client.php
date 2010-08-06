<?php

class Client {
	const State_New       = 1; // Just connected
	const State_Connected = 2;
	
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

		if (self::State_Connected != $this->state) {
			$this->handleLogin($input);
			return;
		}
		
		Action::perform($this, $input);
		
	} // function handleInput
	
	public function handleLogin($in) {
		$request = $host = $origin = $key1 = $key2 = $verifier = $response = $sec = '';
		
		if (preg_match('/GET (.*) HTTP/',                    $in,$m)) $request  = $m[1];
		if (preg_match('/Host: (.*)(\r\n|$)/',               $in,$m)) $host     = $m[1];
		if (preg_match('/Origin: (.*)(\r\n|$)/',             $in,$m)) $origin   = $m[1];
		if (preg_match('/Sec-WebSocket-Key1: (.*)(\r\n|$)/', $in,$m)) $key1     = $m[1];
		if (preg_match('/Sec-WebSocket-Key2: (.*)(\r\n|$)/', $in,$m)) $key2     = $m[1];
		if (preg_match('/\r\n\r\n(.*)$/',                    $in,$m)) $verifier = $m[1];
		
		// Not all clients send the Sec-WebSocket stuff, so only build out the response if a challenge was provided
		if ($key1 && $key2 && $verifier) {
			$digits1 = preg_replace('/[^0-9]/', '', $key1);
			$digits2 = preg_replace('/[^0-9]/', '', $key2);
			$spaces1 = substr_count($key1, ' ');
			$spaces2 = substr_count($key2, ' ');
			
			$cat = pack('N',$digits1/$spaces1) . pack('N', $digits2/$spaces2) . $verifier;
			
			$response = "\r\n" . md5($cat, TRUE);
			$sec = 'Sec-';
		}
		
		$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n"
		          . "Upgrade: WebSocket\r\n"
		          . "Connection: Upgrade\r\n"
		          . "{$sec}WebSocket-Origin: $origin\r\n"
		          . "{$sec}WebSocket-Location: ws://$host$request\r\n"
		          . $response
		          . "\r\n";
		socket_write($this->socket, $upgrade);
		$this->state = self::State_Connected;
	} // function handleLogin
	
	public function getSocket() {
		return $this->socket;
	} // function getSocket
	
	public function getPosition() {
		return $this->position;
	} // function getPosition
	
	public function disconnect() {
		socket_close($this->socket);
		Server::removeClient($this);
	} // function disconnect
	
} // class Client
