<?php

class Action {
	public static function perform(Client $client, $input) {
		
		if ($input == 'shutdown') {
			Server::stop();
		}
		elseif ($input == 'exit') {
			$client->disconnect();
		}
		elseif ($input == 'mem') {
			$client->message(memory_get_usage());
		}
		elseif (substr($input, 0, 2) == 'go') {
			Server::messageAll('Message for all: ' . substr($input, 3));
		}
		else {
			// $client->message("You said $input!");
		}
	} // function perform
} // class Action
