<?php

class WebSocketFrame {

	public $payload;

	function __construct($frame) {
		echo "Decoding Frame:\n";

		$protcol = ord(self::string_shift($frame));
		$this->fin  = (bool) ($protcol & 0x80);
		$this->rsv1 = (bool) ($protcol & 0x40);
		$this->rsv2 = (bool) ($protcol & 0x20);
		$this->rsv3 = (bool) ($protcol & 0x10);
		$this->opcode = $protcol & 0xF;

		switch ($this->opcode) {
			case 0:
			// continuation frame
			break;

			case 1:
			// text frame
			break;

			case 2:
			// binary frame
			break;
			
			case 3:
			case 4:
			case 5:
			case 6:
			case 7:
			// reseved for non-control frames
			break;

			case 8:
			// Disconnect
			break;

			case 9:
			// ping
			break;

			case 10:
			// pong
			break;

			case 11:
			case 12:
			case 13:
			case 14:
			case 15:
			// reserved for control frames
			break;
		}

		$lenMask = ord(self::string_shift($frame));
		$masked  = (bool) ($lenMask & 0x80);
		$len  = $lenMask & 0x7F;

		if ($len == 126) {
			$len = self::string_shift($frame, 2);
			$unpacked = unpack('nlen', $len);
			$this->len = $unpacked['len'];
		}
		elseif ($len == 127) {
			$len = self::string_shift($frame, 8);
			$unpacked = unpack('Nh/Nl', $len); // php's pack doesn't have a specific unsigned 64-bit int format, hack it
			$this->len = $unpacked['h'] << 32 | $unpacked['l'];
		}
		else {
			$this->len = $len;
		}

		// echo "Length: $this->len\n";
		print_r($this);

		if ($masked) {
			$maskingKey = self::string_shift($frame, 4);
			$this->payload = self::transformData($frame, $maskingKey);
		}
		else {
			$this->payload = $frame;
		}
	}

	private static function string_shift(&$string, $bytes = 1) {
		$chr = substr($string, 0, $bytes);
		$string = substr($string, $bytes);
		return $chr;
	} // function string_shift

	private static function transformData($data, $maskingKey) {
		for ($i=0, $len = strlen($data); $i < $len; $i++) { 
			$data[$i] = $data[$i] ^ $maskingKey[$i%4];
		}
		return $data;
	}

}

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
		echo "Sending message:\n";
		echo $message, "\n";
		
		socket_write($this->socket, "\x00$message\xFF");
	} // function message


	public function handleInput() {
		$input = socket_read($this->socket, 100000/*1024*/);
		if (!$input) {
			$this->disconnect();
			return;
		}

		if (self::State_Connected != $this->state) {
			$this->handleLogin($input);
			return;
		}
		$frame = new WebSocketFrame($input);
		if ($p = $frame->payload) {
			if (isset($p[150]))
				echo "Length of " . strlen($p);
			else 
				echo $p;
				
		}
		echo "\n\n\n";
		
		// Action::perform($this, $input);
		
	} // function handleInput
	
	public function handleLogin($in) {
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

		socket_write($this->socket, $upgrade);
		$this->state = self::State_Connected;
		// $this->message('foo');
		
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

		echo "\n\n=======DISCONNECTED=======\n\n";
		
		
		socket_close($this->socket);
		Server::removeClient($this);
	} // function disconnect
	
	public function rejectConnection() {
		// implement me
	} // function rejectConnection
} // class Client
