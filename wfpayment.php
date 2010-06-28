<?php
	class wfpayment{
		private $payments = array();
		
		function __construct($paras){
			$this->paras = $paras;
			
			require_once("knj/http.php");
			$this->http = new knj_httpbrowser();
			$this->http->connect("betaling.wannafind.dk", 443);
			
			$html = $this->http->getaddr("index.php");
			
			$html = $this->http->post("pg.loginauth.php", array(
				"username" => $this->paras["username"],
				"password" => $this->paras["password"]
			));
			
			if (strpos($html, "Brugernavn eller password, blev ikke godkendt.") !== false){
				throw new exception("Could not log in.");
			}
		}
		
		function http(){
			return $this->http;
		}
		
		function listPayments($paras = array()){
			if ($paras["awaiting"]){
				$html = $this->http->getaddr("pg.frontend/pg.transactions.php");
			}else{
				$sparas = array(
					"searchtype" => "",
					"fromday" => "01",
					"frommonth" => "01",
					"fromyear" => date("Y"),
					"today" => "31",
					"tomonth" => "12",
					"toyear" => date("Y"),
					"transacknum" => ""
				);
				
				if ($paras["order_id"]){
					$sparas["orderid"] = $paras["order_id"];
				}
				
				$html = $this->http->post("pg.frontend/pg.search.php?search=doit", $sparas);
			}
			
			if (!preg_match_all("/<a href=\"pg\.transactionview\.php\?id=([0-9]+)&page=([0-9]+)\">([0-9]+)<\/a><\/td>\s*<td.*>(.+)<\/td>\s*<td.*>(.+)<\/td>\s*<td.*>(.+)<\/td>\s*<td.*>.*<\/td>\s*<td.*>(.*)<\/td>\s*<td.*>(.*)<\/td>/U", $html, $matches)){
				echo $html . "\n\n";
				
				throw new exception("Could not parse payments.");
			}
			
			$payments = array();
			foreach($matches[0] AS $key => $value){
				$id = $matches[1][$key];
				
				$amount = str_replace(" DKK", "", $matches[6][$key]);
				$amount = strtr($amount, array(
					"." => "",
					"," => "."
				));
				
				$card_type = $matches[7][$key];
				if (strpos($card_type, "dk.png") !== false){
					$card_type = "dk";
				}elseif(strpos($card_type, "visa-elec.png") !== false){
					$card_type = "visa_electron";
				}elseif(strpos($card_type, "mc.png") !== false){
					$card_type = "mastercard";
				}elseif(strpos($card_type, "visa.png") !== false){
					$card_type = "visa";
				}else{
					throw new exception("Unknown card-type image: " . $card_type);
				}
				
				$date = strtr($matches[5][$key], array(
					"januar" => 1,
					"februar" => 2,
					"marts" => 3,
					"april" => 4,
					"maj" => 5,
					"juni" => 6,
					"juli" => 7,
					"august" => 8,
					"september" => 9,
					"oktober" => 10,
					"november" => 11,
					"december" => 12
				));
				
				if (!preg_match("/(\d+) (\d+) (\d+) (\d+):(\d+):(\d+)/", $date, $match)){
					throw new exception("Could not parse date: " . $date);
				}
				
				$unixt = mktime($match[4], $match[5], $match[6], $match[2], $match[1], $match[3]);
				
				$state = $matches[8][$key];
				if ($state == "Gennemført"){
					$state = "done";
				}else{
					throw new exception("Unknown state: " . $state);
				}
				
				if ($this->payments[$id]){
					$payment = $this->payments[$id];
					$payment->set("state", $matches[8][$key]);
				}else{
					$payment = new wfpayment_payment($this, array(
						"id" => $id,
						"order_id" => substr($matches[4][$key], 4),
						"customer_id" => $matches[3][$key],
						"customer_string" => $matches[4][$key],
						"date" => $unixt,
						"amount" => $amount,
						"card_type" => $card_type,
						"state" => $state
					));
				}
				
				$payments[] = $payment;
			}
			
			return $payments;
		}
	}
	
	class wfpayment_payment{
		function __construct($wfpayment, $paras){
			$this->wfpayment = $wfpayment;
			$this->http = $this->wfpayment->http();
			$this->paras = $paras;
		}
		
		function set($key, $value){
			$this->paras[$key] = $value;
		}
		
		function get($key){
			if (!array_key_exists($key, $this->paras)){
				throw new exception("No such key: " . $key);
			}
			
			return $this->paras[$key];
		}
		
		function accept(){
			if ($this->state() == "done"){
				throw new exception("This payment is already accepted.");
			}
			
			$html = $this->http->getaddr("pg.frontend/pg.transactions.php?capture=singlecapture&transid=" . $this->get("id") . "&page=1&orderby=&direction=");
		}
		
		function state(){
			$payments = $this->wfpayment->listPayments(array("order_id" => $this->get("order_id")));
			return $this->get("state");
		}
	}
?>