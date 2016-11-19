<?php

namespace Level7;

class RtpTester
{
	const MODE_SERVER = 's';
	const MODE_CLIENT = 'c';

	private $debug = false;
	private $mode;
	private $serverIp;
	private $port = 15001;
	private $pps = 50;
	private $bs = 160;
	private $socket;

	public function __construct($argv)
	{
		error_reporting(E_ALL);

		if (in_array("-h", $argv) || in_array("--h", $argv) || in_array("-help", $argv) || in_array("--help", $argv)) {
			$this->printUsage();
		}
		
		if (in_array("-s", $argv) && in_array("-c", $argv)) {
			die("Error: both -s and -c is not allowed\n");
		}

		if (in_array("-s", $argv)) {
			$this->mode = self::MODE_SERVER;
		}

		if (in_array("-c", $argv)) {
			
			$key = array_search("-c", $argv);

			if (!isset($argv[$key+1])) {
				die("Error: <server_ip> parameter missing\n");
			}

			if (!preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $argv[$key+1])) {
				die("Error: <server_ip> parameter has invalid format\n");
			}

			$this->serverIp = $argv[$key+1];

			if ($key = array_search("-i", $argv)) {

				if (!isset($argv[$key+1])) {
					die("Error: <pps> parameter missing\n");
				}

				if (!preg_match('/^[0-9]+$/', $argv[$key+1]) || $argv[$key+1] < 30 || $argv[$key+1] > 50) {
					die("Error: <pps> has to be a number between 30-50\n");
				}

				$this->pps = $argv[$key+1];
			}

			$this->mode = self::MODE_CLIENT;
		}
	}

	public function run()
	{
		if ($this->mode == self::MODE_CLIENT) {
			$this->startClient();
		} else if ($this->mode == self::MODE_SERVER) {
			$this->startServer();
		} else {
			$this->printUsage();
		}
	}

	private function startServer()
	{
		$this->createSocket();

		echo sprintf("Listening for UDP packets on 0.0.0.0:%d...\n", $this->port);
		
	    while (true) {
	        $msg = $this->readMessage();
	    }
	}

	private function startClient()
	{
		$this->createSocket();

		$msec = 1000000;

		$sleep = $msec / $this->pps;

		$seq = 0;

		while (true) {
			$header = $seq . ";" . microtime(true) .";";

			$msg = str_pad($header, $this->bs, "Q");

			$this->sendMessage($msg);
			$seq++;
			usleep($sleep);
		}
	}

    private function createSocket()
    {
        if (!$this->socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP))    {
            $err_no = socket_last_error($this->socket);
            throw new \Exception(socket_strerror($err_no));
        }
        
        if (!@socket_bind($this->socket, "0.0.0.0", $this->port)) {
            $err_no = socket_last_error($this->socket);
            throw new \Exception(sprintf("Failed to bind 0.0.0.0:%d, %s". $this->port, socket_strerror($err_no)));
        }
        
        if (!@socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array("sec"=>0,"usec"=>0))) {
            $err_no = socket_last_error($this->socket);
            throw new \Exception(socket_strerror($err_no));
        }
        
        if (!@socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, array("sec"=>5,"usec"=>0))) {
            $err_no = socket_last_error($this->socket);
            throw new \Exception(socket_strerror($err_no));
        }
    }

    /**
     * Reads incoming UDP packet
     */
    private function readMessage()
    {
        $fromIp = "";
        $fromPort = 0;
        $msg = null;
        
        socket_recvfrom($this->socket, $msg, 10000, 0, $fromIp, $fromPort);
        
        return [
        	"msg" 		=> $msg,
        	"from_ip" 	=> $fromIp,
        	"from_port"	=> $fromPort
        ];
    }

    private function sendMessage($data)
    {
	    if (!@socket_sendto($this->socket, $data, strlen($data), 0, $this->serverIp, $this->port)) {
	      	$err_no = socket_last_error($this->socket);
	      	throw new \Exception("Failed to send data to ".$this->serverIp.":".$this->port.", ".socket_strerror($err_no));
	    }
    }

	private function printUsage()
	{
		$usage = "rtp-tester.php [options]\n\n";
		$usage.= "available options:\n";
		$usage.= " -s               run is server mode\n";
		$usage.= " -c <server_ip>   run is client mode\n";
		$usage.= " -i <pps>         packets per second to send (default: 50)\n";
		$usage.= " -p <bs>          payload size in bytes (default: 160 Bytes)\n";

		die($usage . "\n");
	}
}