#!/usr/bin/php
<?php

set_time_limit(0);
include 'config.php';
include 'app/Action.php';
include 'app/Client.php';
include 'app/Server.php';

Server::start(ADDRESS, PORT);

class ClientDisconnectException extends Exception {
	public function __toString() {
		return $this->getMessage();
	} // function __toString
}
