<?php

class Clarity {
	public function __construct($base,$user,$pass) {
		$this->baseuri=rtrim($base,"/")."/";
		$this->username=$user;
		$this->password=$pass;
	}

	private function getXML($endpoint) {
		if(stristr($endpoint,$this->baseuri)) {
			$uri=$endpoint;
		} else {
			$uri=$this->baseuri.$endpoint;
		}

		$header=array('header' => "Authorization: Basic ".base64_encode($this->username.":".$this->password)."\r\ncontent-type: text/xml; charset=utf-8");

		$curl=curl_init();
		curl_setopt($curl,CURLOPT_URL,$uri);
		curl_setopt($curl,CURLOPT_CONNECTTIMEOUT,5);
		curl_setopt($curl,CURLOPT_TIMEOUT,120);
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_HTTPHEADER,$header);
		$data=curl_exec($curl);
		curl_close($curl);

		if($data) {
			try {
				$results=new SimpleXmlElement($data);
			} catch(Exception $e) {
				$results=FALSE;
			}
		} else {
			$results=FALSE;
		}
		
		return $results;
	}

	public function testConnection() {
		return $this->getXML('');
	}

	public function getEntity($endpoint) {
		$xmldata=$this->getXML($endpoint);
		if($xmldata) {
			$results=$this->getElements($xmldata);
			
			if(is_array($results)) {
				/*
				If item is not found this is returned:
				SimpleXMLElement Object
				(
				    [message] => Project not found: P7699
				)			
				*/
				if(isset($results['message'])) {
					return FALSE;
				} else {
					return $results;
				}
			} else {
				return FALSE;
			}
		} else {
			return FALSE;
		}
	}

	public function getList($endpoint) {
		$xmldata=$this->getXML($endpoint);
		foreach($xmldata as $data) {
			if($data->getName()=="next-page") {
				$nextpage=$this->getAttributes($data);
				$results=array_merge($results,$this->getList($nextpage['uri']));
			} else {
				$item=$this->getElements($data);
				if(array_key_exists("limsid",$item)) {
					$results[$item['limsid']]=$item;
				} else {
					$results[]=$item;
				}
			}
		}
		
		if(is_array($results)) {
			return $results;
		} else {
			return FALSE;
		}
	}
	
	private function getElements($xmldata) {
		$results=array();
		$results=$this->getAttributes($xmldata);
		foreach($xmldata as $data) {
			$element_name=$data->getName();
			if(count($data)>0) {
				$results[$element_name]=$this->getElements($data);
			} else {
				$results[$element_name]=(string) $data;
				if(count($data->attributes())>0) {
					$results[$element_name]=$this->getAttributes($data);
				}
			}
		}

		$results=array_merge($results,$this->getNSData($xmldata));
		
		return $results;
	}
	
	private function getAttributes($xmldata) {
		$results=array();
		foreach($xmldata->attributes() as $data) {  
			$results[$data->getName()]=(string) $data;
		}
		return $results;
	}
	
	private function getNSData($xmldata) {
		$namespaces=$xmldata->getNameSpaces(true);
		$results=array();
		foreach($namespaces as $prefix => $nsuri) {
			$nsdata=$xmldata->children($namespaces[$prefix]);
			if(count($nsdata)>0) {
				foreach($nsdata as $data) {
					switch($prefix) {
						case "udf":
							$udfname=$data->attributes()->name;
							$results[$prefix][(string) $udfname]=(string) $data;
						break;
						
						case "file":
							foreach($data->attributes() as $key => $value) {
								$_results[$key]=(string) $value;
							}
							$results[$prefix][]=$_results;
						break;
					}
				}
			}
		}
		return $results;
	}
}

?>
