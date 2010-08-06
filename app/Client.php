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
		$input = trim($input);
		
		if (self::State_Connected != $this->state) {
			$this->handleLogin($input);
			return;
		}
		
		Action::perform($this, $input);
		
	} // function handleInput
	
	public function handleLogin($input) {
		$r=$h=$o=null;
		if(preg_match("/GET (.*) HTTP/",        $input,$match)){ $r=trim($match[1]); }
		if(preg_match("/Host: (.*)(\n|\r|$)/",  $input,$match)){ $h=trim($match[1]); }
		if(preg_match("/Origin: (.*)(\n|\r|$)/",$input,$match)){ $o=trim($match[1]); }
		$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n"
		          . "Upgrade: WebSocket\r\n"
		          . "Connection: Upgrade\r\n"
		          . "WebSocket-Origin: $o\r\n"
		          . "WebSocket-Location: ws://$h$r\r\n"
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
