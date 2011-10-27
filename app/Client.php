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
		socket_write($this->socket, new WebSocketFrame($message));
	} // function message

	public function handleInput() {
		// Reading very long messages (32k+) seems buggy even with a very high $input param, so read a byte at a time until EOM
		$input = '';
		$read = true;
		do {
			$byte = socket_read($this->socket, 1);
			if ($byte === false) {
				// error/disconnect
				$read = false;
			}
			elseif ($byte === '') {
				// End of message
				$read = false;
			}
			else {
				// Received byte, continue reading
				$input .= $byte;
			}
		} while ($read);

		// Empty packet: disconnect
		if (!$input) {
			$this->disconnect();
			return;
		}

		if (self::State_Connected != $this->state) {
			$this->handleLogin($input);
			return;
		}
		$frame = WebSocketFrame::decode($input);
		if (null !== $msg = json_decode($frame->payload)) {
			Server::messageAll($frame->payload);
		}
		
	} // function handleInput

	public function handleLogin($in) {
		$request = $upgrade = $connection = $host = $origin = $key = $version = '';

		$headers = http_parse_headers($in);
		if (isset($headers['Upgrade']))               $upgrade    = $headers['Upgrade'];
		if (isset($headers['Connection']))            $connection = $headers['Connection'];
		if (isset($headers['Host']))                  $host       = $headers['Host'];
		if (isset($headers['Origin']))                $origin     = $headers['Origin'];
		if (isset($headers['Sec-Websocket-Key']))     $key        = $headers['Sec-Websocket-Key'];
		if (isset($headers['Sec-Websocket-Version'])) $version    = $headers['Sec-Websocket-Version'];

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
		$this->message('foo');
		
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
		socket_close($this->socket);
		Server::removeClient($this);
	} // function disconnect
	
	public function rejectConnection() {
		// implement me
	} // function rejectConnection
} // class Client
