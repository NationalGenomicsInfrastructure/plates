<?php
class Couch {
	public function __construct($host,$port,$user,$pass) {
		$host=($port>0) ? $host.':'.$port : $host;
		$this->baseuri=rtrim('http://'.$host,"/")."/";
		$this->username=$user;
		$this->password=$pass;
	}
	
	public function getView($view=FALSE) {
		$header=array('header' => "Authorization: Basic ".base64_encode($this->username.":".$this->password));
		$uri=$view ? $this->baseuri.$view : $this->baseuri;
		$curl=curl_init();
		curl_setopt($curl,CURLOPT_URL,$uri);
		curl_setopt($curl,CURLOPT_CONNECTTIMEOUT,5);
		curl_setopt($curl,CURLOPT_TIMEOUT,120);
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_HTTPHEADER,$header);
		$data=curl_exec($curl);
		curl_close($curl);
		return json_decode($data);
	}
	
	public function testConnection() {
		$data=$this->getView();
		if(is_object($data)) {
			return $data->version;
		} else {
			return FALSE;
		}
	}
}
?>
