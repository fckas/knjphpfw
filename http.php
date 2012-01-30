<?php
/**
 * This file contains the knj_httpbrowser class
 *
 * PHP version 5
 *
 * @category Framework
 * @package  Knjphpfw
 * @author   Kasper Johansen <kaspernj@gmail.com>
 * @license  Public domain http://en.wikipedia.org/wiki/Public_domain
 * @link     https://github.com/kaspernj/knjphpfw
 */

/**
 * This class provides an object capable of doing post and get request
 * over a persistent http(s) connection.
 *
 * @category Framework
 * @package  Knjphpfw
 * @author   Kasper Johansen <kaspernj@gmail.com>
 * @license  Public domain http://en.wikipedia.org/wiki/Public_domain
 * @link     https://github.com/kaspernj/knjphpfw
 */
class knj_httpbrowser
{
	private $_host;
	private $_port;
	private $_httpauth;
	private $_ssl = false;
	private $_debug = false;
	private $_max_requests = 0;
	private $_request_count = 0;
	private $_nl = "\r\n";
	public $fp;
	public $headers_last;
	private $_useragent = "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)";


	/**
	 * TODO
	 *
	 * @param array $args TODO
	 */
	function __construct($args = array())
	{
		$this->args = array(
			"timeout" => ini_get("default_socket_timeout")
		);
		$this->cookies = array();

		foreach ($args as $key => $value) {
			if ($key == "ssl"
				|| $key == "nl"
				|| $key == "debug"
				|| $key == "force_connection"
			) {
				$this->$key = $value;
			}

			$this->args[$key] = $value;
		}
	}

	/**
	 * Print debugging messages if _debug is set.
	 *
	 * @param string $msg The message that should be printed.
	 *
	 * @return null
	 */
	private function debug($msg)
	{
		if ($this->_debug) {
			echo $msg ."\n";
		}
	}

	/**
	 * Connects to a server.
	 *
	 * @param string $host Server to connect to
	 * @param int    $port Default is 80, if 443 SSL is assumed.
	 * @param array  $args TODO
	 *
	 * @return bool Return true if connection was established.
	 */
	public function connect($host, $port = 80, $args = array())
	{
		$this->_host = $host;
		$this->_port = $port;

		if (!array_key_exists($host, $this->cookies)) {
			$this->cookies[$host] = array();
		}

		if ($port == 443) {
			$this->_ssl = true;
		}

		foreach ($args as $key => $value) {
			if ($key == "ssl"
				|| $key == "nl"
				|| $key == "debug"
				|| $key == "force_connection"
			) {
				$this->$key = $value;
			}

			$this->args[$key] = $value;
		}

		$this->reconnect();

		if (!$this->fp) {
			return false;
		}

		return true;
	}

	/**
	 * Reconnects to the host.
	 *
	 * @return null
	 */
	private function reconnect()
	{
		if ($this->fp) {
			$this->disconnect();
		}

		if ($this->_ssl == true) {
			$host = "ssl://" .$this->_host;
		} else {
			$host = $this->_host;
		}

		$this->fp = fsockopen(
			$host,
			$this->_port,
			$errno,
			$errstr,
			$this->args["timeout"]
		);
		if (!$this->fp) {
			throw new exception("Could not connect.");
		}
		$this->_request_count = 0;
	}

	/**
	 * Set debugging state.
	 *
	 * @param mixed $value The value to set.
	 *
	 * @return null
	 */
	public function setDebug($value)
	{
		$this->_debug = $value;
	}

	/**
	 * Set connection login info
	 *
	 * @param mixed $user   Username to use.
	 * @param mixed $passwd Password to use.
	 *
	 * @return null
	 */
	public function setHTTPAuth($user, $passwd)
	{
		$this->_httpauth = array(
			"user" => $user,
			"passwd" => $passwd
		);
	}

	/**
	 * Set the User agent string.
	 *
	 * @param mixed $useragent The string of the useragent.
	 *
	 * @return null
	 */
	public function setUserAgent($useragent)
	{
		$this->_useragent = $useragent;
	}

	/**
	 * Set the max number of request allowed per connection.
	 *
	 * @param int $max_requests The maximum number of request per connection.
	 *
	 * @return null
	 */
	public function setAutoReconnect($max_requests)
	{
		if (!is_numeric($max_requests) || $max_requests <= 0) {
			throw new exception("Invalid value given: " .$max_requests);
		}

		$this->_max_requests = $max_requests;
	}

	/**
	 * Reconnect if the max requsts have been made with the current connetion,
	 * and check if the current connection is still active.
	 *
	 * @return null
	 */
	private function countRequests()
	{
		if ($this->_max_requests
			&& $this->_request_count >= $this->_max_requests
		) {
			$this->reconnect();
		}

		$this->checkConnected();
		$this->_request_count++;
	}

	/**
	 * Check if the connection is still active.
	 *
	 * @return null
	 */
	private function checkConnected()
	{
		while (true) {
			if (!$this->_host || !$this->fp) {
				if ($this->force_connection) {
					usleep(100000);
					$this->reconnect();
				} else {
					throw new exception("Not connected.");
				}
			} else {
				break;
			}
		}
	}

	/**
	 * Posts a message to a page.
	 *
	 * @param string $addr Absolute URI to the desired page
	 * @param array  $post TODO
	 *
	 * @return string Response as a string.
	 */
	public function post($addr, $post)
	{
		$this->countRequests();

		$postdata = "";
		foreach ($post as $key => $value) {
			if ($postdata) {
				$postdata .= "&";
			}

			$postdata .= urlencode($key) ."=" .urlencode($value);
		}

		//URI must be absolute
		if (substr($addr, 0, 1) != "/") {
			$addr = "/".$addr;
		}

		$headers
			= "POST " .$addr ." HTTP/1.1" .$this->_nl
			."Content-Type: application/x-www-form-urlencoded" .$this->_nl
			."User-Agent: " .$this->_useragent .$this->_nl
			."Host: " .$this->_host .$this->_nl
			."Content-Length: " .strlen($postdata) .$this->_nl
			."Connection: Keep-Alive" .$this->_nl;

		$headers .= $this->getAuthHeader();

		if ($this->cookies[$this->_host]) {
			foreach ($this->cookies[$this->_host] as $key => $value) {
				$headers .= "Cookie: " .urlencode($key) ."=" .$value .$this->_nl;
			}
		}

		$headers .= "" .$this->_nl;

		if (!fwrite($this->fp, $headers .$postdata)) {
			throw new exception("Could not write to socket.");
		}

		$this->last_url = "http://" .$this->_host .$addr;
		return $this->readHTML();
	}

	/**
	 * TODO
	 *
	 * @param string $addr     Absolute URI to the desired page
	 * @param string $postdata TODO
	 *
	 * @return string Response as a string.
	 */
	public function post_raw($addr, $postdata)
	{
		$this->countRequests();

		//URI must be absolute
		if (substr($addr, 0, 1) != "/") {
			$addr = "/".$addr;
		}

		$headers = "POST " .$addr ." HTTP/1.1" .$this->_nl;
		$headers .= "Authorization: Basic "
			.base64_encode("306761540:XXnz*2ms") .$this->_nl;
		$headers .= "Host: " .$host .$this->_nl;

		$headers .= "Connection: close" .$this->_nl;
		$headers .= "Content-Length: " .strlen($postdata) .$this->_nl;
		$headers .= "Content-Type: text/xml; charset=\"utf-8\"" .$this->_nl;
		$headers .= $this->_nl;

		if (!fwrite($this->fp, $headers .$postdata)) {
			throw new exception("Could not write to socket.");
		}

		$this->last_url = "http://" .$this->_host .$addr;
		return $this->readHTML();
	}

	/**
	 * TODO
	 *
	 * @param string $addr Absolute URI to the desired page
	 * @param array  $post TODO
	 *
	 * @return string Response as a string.
	 */
	public function postFormData($addr, $post)
	{
		$this->countRequests();

		$boundary = "---------------------------" .round(mktime(true));

		$postdata = "";
		foreach ($post as $key => $value) {
			if ($postdata) {
				$postdata .= "" .$this->_nl;
			}

			$postdata .= "--" .$boundary .$this->_nl;
			$postdata .= 'Content-Disposition: form-data; name="'
				.$key .'"' .$this->_nl;
			$postdata .= "" .$this->_nl;
			$postdata .= $value;
		}

		$postdata .= $this->_nl ."--" .$boundary ."--";

		//URI must be absolute
		if (substr($addr, 0, 1) != "/") {
			$addr = "/".$addr;
		}

		$headers
			= "POST " .$addr ." HTTP/1.1" .$this->_nl
			."Host: " .$this->_host .$this->_nl . $this->_nl
			."User-Agent: " .$this->_useragent .$this->_nl
			."Keep-Alive: 300" . $this->_nl
			."Connection: keep-alive" . $this->_nl
			."Content-Length: " .strlen($postdata) .$this->_nl
			."Content-Type: multipart/form-data; boundary=" .$boundary .$this->_nl;

		$headers .= $this->getAuthHeader();

		if ($this->cookies[$this->_host]) {
			foreach ($this->cookies[$this->_host] as $key => $value) {
				$headers .= "Cookie: " .urlencode($key) ."=" .urlencode($value)
					."; FService=Password=miden&Fkode=F0623" . $this->_nl;
			}
		}

		$headers .= $this->_nl;

		fputs($this->fp, $headers);

		$count = 0;
		while ($count < strlen($postdata)) {
			fputs($this->fp, substr($postdata, $count, 2048));
			$count += 2048;
		}

		$this->last_url = "http://" .$this->_host .$addr;
		return $this->readHTML();
	}

	/**
	 * Posts a file to the server.
	 *
	 * @param string $addr Absolute URI to the desired page
	 * @param array  $post TODO
	 * @param array  $file TODO
	 *
	 * @return string Response as a string.
	 */
	public function postFile($addr, $post, $file)
	{
		$this->countRequests();

		if (is_array($file)
			&& $file["content"]
			&& $file["filename"]
			&& $file["inputname"]
		) {
			$boundary = "---------------------------" .round(mktime(true));

			$postdata .= "--" .$boundary . $this->_nl;
			$postdata .= "Content-Disposition: form-data; name=\""
				.htmlspecialchars($file["inputname"]) ."\"; filename=\""
				.htmlspecialchars($file["filename"]) ."\"" .$this->_nl;
			$postdata .= "Content-Type: application/octet-stream" . $this->_nl;
			$postdata .= $this->_nl;
			$postdata .= $file["content"];
			$postdata .= $this->_nl ."-" .$boundary ."--" . $this->_nl;
		} else {
			$input_name = $file[0]["input"];
			$file = $file[0]["file"];

			$boundary = "---------------------------" .round(mktime(true));
			$cont = file_get_contents($file);
			$info = pathinfo($file);

			$postdata .= "--" .$boundary .$this->_nl;
			$postdata .= "Content-Disposition: form-data; name=\""
				.htmlspecialchars($input_name) ."\"; filename=\""
				.htmlspecialchars($info["basename"]) ."\"" .$this->_nl;
			$postdata .= "Content-Type: application/octet-stream" .$this->_nl;
			$postdata .= $this->_nl;
			$postdata .= $cont;
			$postdata .= $this->_nl ."--" .$boundary ."--" .$this->_nl;
		}

		if (is_array($post)) {
			foreach ($post as $key => $value) {
				if ($postdata) {
					$postdata .= "&";
				}

				$postdata .= urlencode($key) ."=" .urlencode($value);
			}
		}

		//URI must be absolute
		if (substr($addr, 0, 1) != "/") {
			$addr = "/".$addr;
		}

		$headers .= "POST " .$addr ." HTTP/1.1" .$this->_nl;
		$headers .= "Host: " .$this->_host .$this->_nl;
		$headers .= "Content-Type: multipart/form-data; boundary="
			.$boundary .$this->_nl;
		$headers .= "Content-Length: " .strlen($postdata) .$this->_nl;
		$headers .= "Connection: Keep-Alive" .$this->_nl;
		$headers .= "User-Agent: " .$this->_useragent .$this->_nl;
		$headers .= $this->getAuthHeader();

		if ($this->cookies[$this->_host]) {
			foreach ($this->cookies[$this->_host] as $key => $value) {
				$headers .= "Cookie: " .urlencode($key) ."=" .$value .$this->_nl;
			}
		}

		$headers .= "" .$this->_nl;


		$sendd = $headers .$postdata;
		$length = strlen($sendd);

		while ($sendd && $count < ($length + 2048)) {
			if (fwrite($this->fp, substr($sendd, $count, 2048)) === false) {
				$msg = "Could not write to socket. Is the connection closed?";
				throw new exception($msg);
			}

			$count += 2048;
		}

		return $this->readHTML();
	}

	/**
	 * Generate the auth header if needed.
	 *
	 * @return string Header line.
	 */
	private function getAuthHeader()
	{
		$headers = "";

		if ($this->_httpauth) {
			$auth = base64_encode(
				$this->_httpauth["user"] .":" .$this->_httpauth["passwd"]
			);
			$headers .= "Authorization: Basic " .$auth .$this->_nl;
		}

		return $headers;
	}

	/**
	 * Returns the current cookies.
	 *
	 * @return array Array of hosts each with an array of set cookies.
	 */
	public function getCookies()
	{
		return $this->cookies;
	}

	/**
	 * Alias of getAddr()
	 *
	 * @param string $addr See getAddr()
	 *
	 * @return string See getAddr()
	 */
	public function get($addr, $args = null)
	{
		return $this->getAddr($addr);
	}

	/**
	 * Fetch a page via get.
	 *
	 * @param string $addr Absolute URI to the desired page
	 * @param mixed  $args TODO
	 *
	 * @return string Response as a string.
	 */
	public function getAddr($addr, $args = null)
	{
		$this->countRequests();

		if (is_string($args)) {
			$host = $args;
		}

		if (!$host) {
			$host = $this->_host;
		}

		//URI must be absolute
		if (substr($addr, 0, 1) != "/") {
			$addr = "/".$addr;
		}

		$headers
			= "GET " .$addr ." HTTP/1.1" .$this->_nl
			."Host: " .$host .$this->_nl
			."User-Agent: " .$this->_useragent .$this->_nl
			."Connection: Keep-Alive" .$this->_nl;

		if ($args["addheader"]) {
			foreach ($args["addheader"] as $header) {
				$headers .= $header .$this->_nl;
			}
		}

		if ($this->cookies[$this->_host]) {
			foreach ($this->cookies[$this->_host] as $key => $value) {
				$headers .= "Cookie: " .urlencode($key) ."="
					.urlencode($value) .$this->_nl;
			}
		}

		$headers .= $this->getAuthHeader();
		$headers .= $this->_nl;

		$this->debug("getAddr()-headers:\n" .$headers ."\n");

		//Sometimes trying more times than one fixes the problem.
		$tries = 0;
		$tries_max = 5;
		while (!fwrite($this->fp, $headers)) {
			sleep(1);
			try {
				$this->reconnect();
			} catch (exception $e) {
			}

			$tries++;
			if ($tries >= $tries_max) {
				throw new exception("Could not write to socket.");
			}
		}

		$this->last_url = "http://" .$this->_host .$addr;
		return $this->readHTML();
	}

	/**
	 * Read the HTML after sending a request and handle any HTTP commands.
	 *
	 * @return string The body of the responce.
	 */
	private function readHTML()
	{
		//TODO handle content encoding
		$chunk = 0;
		$chunked = false;
		$state = "headers";
		$readsize = 1024;
		$first = true;
		$headers = "";
		$cont100 = null;
		$html = "";
		$location = null;

		while (true) {
			if ($readsize == 0) {
				break;
			}

			$line = fgets($this->fp, $readsize);

			if (strlen($line) == 0) {
				break;
			} elseif ($line === false) {
				throw new exception("Could not read from socket.");
			} elseif ($first && $line == "\r\n") {
				/**
				 * Fixes an error when some servers sometimes sends \r\n in the end,
				 * if this is a second request.
				 */
				$line = fgets($this->fp, $readsize);
			}

			if ($state == "headers") {
				if ($line == "\r\n" || $line == "\n" || $line == $this->_nl) {
					if ($cont100 == true) {
						unset($cont100);
					} else {
						$state = "body";
						if ($contentlength == 0 && $contentlength !== null) {
							break;
						}
					}

					if ($contentlength < 1024 && $contentlength !== null) {
						$readsize = $contentlength;
					}
				} else {
					$headers .= $line;

					if (preg_match("/^Content-Length: ([0-9]+)\s*$/", $line, $match)) {
						$contentlength = $match[1];
						$contentlength_set = true;
					} elseif (preg_match("/^Transfer-Encoding: chunked\s*$/", $line, $match)) {
						$chunked = true;
					} elseif (preg_match("/^Set-Cookie: (\S+)=(\S+)(;|)( path=\/;| path=\/)\s*/U", $line, $match)) {
						$key = urldecode($match[1]);
						$value = urldecode($match[2]);

						$this->cookies[$this->_host][$key] = $value;
					} elseif (preg_match("/^Set-Cookie: (\S+)=(\S+)\s*$/U", urldecode($line), $match)) {
						$key = urldecode($match[1]);
						$value = urldecode($match[2]);

						$this->cookies[$this->_host][$key] = $value;
					} elseif (preg_match("/^HTTP\/1\.1 100 Continue\s*$/", $line, $match)) {
						$cont100 = true;
					} elseif (preg_match("/^Location: (.*)\s*$/", $line, $match)) {
						$location = trim($match[1]);
					} else {
						//echo "NU: " .$line;
					}
				}
			} elseif ($state == "body") {
				if ($chunked == true) {
					if ($line == "0\r\n" || $line == "0\n") {
						break;
					}

					//Read body with cunked data.
					if ($chunk == 0) {
						$chunk = hexdec($line);
					} else {
						if (strlen($line) > $chunk) {
							$html .= $line;
							$chunk = 0;
						} else {
							$html .= $line;
							$chunk -= strlen($line);
						}
					}
				} else {
					$html .= $line;

					if ($contentlength !== null) {
						/*
						 * Ellers fuckede det helt, og serveren vil i nogen
						 * tilfælde slet ikke svare, før der sendes et nyt request.
						 */
						if (($contentlength - strlen($html)) < 1024) {
							$readsize = $contentlength - strlen($html) + 1;

							if ($readsize <= 0) {
								$readsize = 1024;
							}
						}

						if (strlen($html) >= $contentlength) {
							break;
						}

						if ($readsize <= 0) {
							break;
						}
					}
				}
			}

			$first = false;
		}

		$this->debug("Received headers:\n" .$headers ."\n");
		$this->debug("Received HTML:\n" .$html ."\n");

		if ($location) {
			$this->debug(
				'Received location-header - trying to follow "' .$match[1] .'".'
			);
			return $this->getAddr($location);
		}

		if (preg_match('/<h2>Object moved to <a href="([^"]*)">here<\/a>.<\/h2>/', $html, $match)) {
			$this->debug('"Object moved to" found in HTML - trying to follow.');
			return $this->getAddr(urldecode($match[1]));
		}

		$this->headers_last = $headers;
		$this->html_last = $html;
		return $html;
	}

	/**
	 * Get ASP.NET form viewstate value.
	 *
	 * @return string Viewstate value
	 */
	public function aspxGetViewstate()
	{
		if (preg_match('/<input[^>]*? name="__VIEWSTATE"[^>]*? value="([^"]*)"[^>]*? \/>/', $this->html_last, $match)) {
			return urldecode($match[1]);
		}

		return '';
	}

	/**
	 *  Closes the connection.
	 *
	 * @return null
	 */
	public function disconnect()
	{
		fclose($this->fp);
		unset($this->fp);
	}
}

