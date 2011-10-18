<?php

// Reserved for protocol violations, status code 1002
class FailWebSocketConnectionException extends Exception {}

class WebSocketFrame {

	public $payload;

	public function __construct($payload) {
		$this->payload = $payload;
	} // function __construct

	public static function decode($frame) {
		$snip = 0; // this will be trimmed off the beginning of the frame as non-payload

		$header = unpack('ninfo', substr($frame, 0, 2));
		$snip += 2;
		$info = $header['info'];


		$fin    = (bool) ($info & 0x8000);
		$rsv1   = (bool) ($info & 0x4000);
		$rsv2   = (bool) ($info & 0x2000);
		$rsv3   = (bool) ($info & 0x1000);
		$opcode =        ($info & 0x0F00) >> 8;
		$masked =         $info & 0x0080;
		$len    =         $info & 0x007F;

		if ($rsv1) {
			throw new FailWebSocketConnectionException('RSV1 set without known meaning');
		}

		if ($rsv2) {
			throw new FailWebSocketConnectionException('RSV2 set without known meaning');
		}

		if ($rsv3) {
			throw new FailWebSocketConnectionException('RSV3 set without known meaning');
		}

		switch ($opcode) {
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
				throw new FailWebSocketConnectionException('Use of reserved non-control frame opcode');
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
				throw new FailWebSocketConnectionException('Use of reserved control frame opcode');
			break;
		}

		// If basic length field was one of the magic numbers, read further into the header to get the actual length
		if ($len == 126) {
			$len = substr($frame, $snip, 2);
			$snip += 2;
			$unpacked = unpack('nlen', $len);
			$len = $unpacked['len'];
		}
		elseif ($len == 127) {
			$len = substr($frame, $snip, 8);
			$snip += 8;
			$unpacked = unpack('Nh/Nl', $len); // php's pack doesn't have a specific unsigned 64-bit int format, hack it
			$len = ($unpacked['h'] << 32) | $unpacked['l'];
		}

		if ($masked) {
			$maskingKey = substr($frame, $snip, 4);
			$snip += 4;
			$payload = self::transformData(substr($frame, $snip), $maskingKey);
		}
		else {
			// The spec is unclear if this condition should actually fail the connection, since it says clients MUST mask payload
			$payload = substr($frame, $snip);
		}
		return new WebSocketFrame($payload);
	}

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
		// Action::perform($this, $input);
		
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
