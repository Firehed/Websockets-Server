<?php

class Server {

	private static $app;
	private static $run = TRUE;
	private static $clients = array();
	
	public static function start($address, $port) {
		if (self::$app !== NULL) throw new Exception('One server at a time!');
		if (!self::test()) exit(1);
		
		self::$app = socket_create(AF_INET, SOCK_STREAM, 0);
		socket_set_option(self::$app, SOL_SOCKET, SO_REUSEADDR, 1);
		socket_set_nonblock(self::$app);
		if (!socket_bind(self::$app, $address, $port)) throw new Exception("Can't bind to $address:$port");
		socket_listen(self::$app);
		
		self::run();
	} // function start
	
	public static function test() {
		$ok = true;
		if (!function_exists('http_parse_headers')) {
			echo "Function missing: http_parse_headers (pecl install pecl_http)\n";
			$ok = false;
		}
		return $ok;
	} // function test
	
	public static function stop() {
		self::messageAll("*** The server is shutting down now. ***");
		foreach (self::$clients as $client) {
			$client->disconnect();
		}
		socket_close(self::$app);
		self::$run = FALSE;
	} // function stop
	
	/**
	 * The main program loop
	**/
	private static function run() {
		while (self::$run) {
			$sockets = self::getSockets();
			if (socket_select($sockets, $w = NULL, $e = NULL, NULL) > 0) {
				foreach ($sockets as $position => $socket) {
					if ($socket == self::$app) {
						self::addClient($socket);
					}
					else {
						$client = self::$clients[$position];
						try {
							$client->handleInput();
						}
						catch (ClientDisconnectException $e) {
							$client->message($e);
							$client->disconnect();
						}
					}
				}
			}
			unset($sockets);
		}
	} // function run
	
	public static function messageAll($message) {
		foreach (self::$clients as $client) {
			$client->message($message);
		}
	} // function messageAll
	
	private static function getSockets() {
		$sockets[0] = self::$app;
		foreach (self::$clients as $client) {
			$sockets[$client->getPosition()] = $client->getSocket();
		}
		return $sockets;
	} // function getSockets
	
	private static function addClient($socket) {
		if ($new = socket_accept(self::$app)) {
			$c = new Client($new);
			self::$clients[$c->getPosition()] = $c;
		}
	} // function addClient
	
	public static function removeClient(Client $c) {
		unset(self::$clients[$c->getPosition()]);
	} // function removeClient
	
} // class Server
