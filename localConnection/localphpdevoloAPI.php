<?php

class localDevoloDHC extends DevoloDHC{

	//direct2central login:
	protected $_Host = 'www.mydevolo.com';
	protected $_apiVersion = '/v1';
	public $_uuid = null;
	public $_gateway = null;
	public $_passkey = null;
	public $_localHost = null;

	//subclassing:
	protected $_curlHdl = null; //transfer cookie jar!!
	public $_error = null;

	//user central stuff:
	protected $_login;
	protected $_password;
	protected $_sessionID; //the one to get first!

	function __construct($login, $password, $localHost, $uuid=null, $gateway=null, $passkey=null, $connect=true, $gateIdx=0)
	{
		$this->_login = $login;
		$this->_password = $password;
		$this->_localHost = $localHost;

		if (isset($uuid)) $this->_uuid = $uuid;
		if (isset($gateway)) $this->_gateway = $gateway;
		if (isset($passkey)) $this->_passkey = $passkey;

		parent::__construct($login, $password, $connect, $gateIdx); //construct main devoloAPI

		if ( isset($this->error) or ($connect==false) )
		{
			if ( isset($this->error) ) echo 'DevoloDHC class error:'.$this->error.'<br>';

			//use alternative login
			$this->_dhcUrl = $this->_localHost;
			if ( !isset($this->_uuid) or !isset($this->_gateway) or !isset($this->_passkey) )
			{
				//get uuid, gateway and passkey from Devolo server:
				$this->initAuth();
			}
			//connect directly to the central:
			$this->getSessionID();
			$this->getDevices();
		}
	}

	public function getAuth() //return array of infos for faster connections with all datas
	{
		$auth = array(
					"uuid" => $this->_uuid,
					"gateway" => $this->_gateway,
					"passkey" => $this->_passkey,
					"call" => 'new localDevoloDHC($login, $password, $localHost, $uuid, $gateway, $passkey, false)'
					);
		return array('result'=>$auth);
	}

	protected function _MyRequest($protocol, $method, $host, $path, $jsonString, $login, $password) //standard function handling all get/post request with curl | return string
	{
		if (!isset($this->_curlHdl))
		{
			$this->_curlHdl = curl_init();
			curl_setopt($this->_curlHdl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($this->_curlHdl, CURLOPT_FOLLOWLOCATION, true);

			curl_setopt($this->_curlHdl, CURLOPT_COOKIESESSION, false);

			curl_setopt($this->_curlHdl, CURLOPT_COOKIEJAR, '');
			curl_setopt($this->_curlHdl, CURLOPT_COOKIEFILE, '');

			curl_setopt($this->_curlHdl, CURLOPT_REFERER, 'http://www.google.com/');
			curl_setopt($this->_curlHdl, CURLOPT_USERAGENT, 'Mozilla/5.0+(Windows;+WOW64;+x64;+rv:52.0)+Gecko/20100101+Firefox/52.0');

			curl_setopt($this->_curlHdl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($this->_curlHdl, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($this->_curlHdl, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
			curl_setopt($this->_curlHdl,CURLOPT_ENCODING , '');
		}
		$url = $protocol."://".$host.$path;
		curl_setopt($this->_curlHdl, CURLOPT_URL, $url);

		if ($protocol == 'http')
		{
			curl_setopt($this->_curlHdl, CURLOPT_HEADER, true);
			curl_setopt($this->_curlHdl, CURLINFO_HEADER_OUT, true );
		}

		if ( isset($login) and isset($password) )
		{
			$auth = urldecode($login).":".urldecode($password);
			curl_setopt($this->_curlHdl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($this->_curlHdl, CURLOPT_USERPWD, $auth);
		}

		if ($method == 'POST')
			{
				$jsonString = str_replace('"jsonrpc":"2.0",', '"jsonrpc":"2.0", "id":'.$this->_POSTid.',', $jsonString);
				$this->_POSTid++;
				curl_setopt($this->_curlHdl, CURLOPT_CUSTOMREQUEST, 'POST');
				curl_setopt($this->_curlHdl, CURLOPT_POSTFIELDS, $jsonString);
			}

        $response = curl_exec($this->_curlHdl);

		//$info   = curl_getinfo($this->_curlHdl);
		//echo "<pre>cURL info".json_encode($info, JSON_PRETTY_PRINT)."</pre><br>";

		if($response === false)
		{
			echo 'cURL error: ' . curl_error($this->_curlHdl);
		}
		else
		{
			return $response;
		}
	}

	public function initAuth() //get uuid, gateway and passkey from www.devolo.com for authorization
	{
		//get uuid:
		$data = $this->_MyRequest('https', 'GET', $this->_Host, $this->_apiVersion.'/users/uuid', null, $this->_login, $this->_password, null);
		$data = json_decode($data, true);
		if (isset($data["uuid"]))
		{
			$this->_uuid = $data["uuid"];
		}
		else
		{
			$this->_error = "Couldn't find Devolo uuid.";
			return false;
		}

		//get gateway:
		$path = $this->_apiVersion.'/users/'.$this->_uuid.'/hc/gateways';
		$data = $this->_MyRequest('https', 'GET', $this->_Host, $path, null, $this->_login, $this->_password, null);
		$data = json_decode($data, true);
		if (isset($data["items"][0]["href"]))
		{
			$var = explode( "/gateways/", $data["items"][0]["href"] );
			$this->_gateway = $var[1];
		}
		else
		{
			$this->_error = "Couldn't find Devolo gateway.";
			return false;
		}


		//get localPasskey:
		$path = $this->_apiVersion.'/users/'.$this->_uuid.'/hc/gateways/'.$this->_gateway;
		$data = $this->_MyRequest('https', 'GET', $this->_Host, $path, null, $this->_login, $this->_password, null);
		$data = json_decode($data, true);
		if (isset($data["localPasskey"]))
		{
			$this->_passkey = $data["localPasskey"];
			if ($data["state"] != 'devolo.hc_gateway.state.idle')
			{
				$this->error = "Devolo Central not IDLE.";
				return false;
			}
		}
		else
		{
			$this->_error = "Couldn't find Devolo localPasskey.";
			return false;
		}

		return true;
	}

	public function getSessionID() //get and set cookie for later authorized requests
	{
		$this->_sessionID = null;

		//get token:
		$data = $this->_MyRequest('http', 'GET', $this->_dhcUrl, '/dhlp/portal/light', null, $this->_uuid, $this->_passkey, null);
		$var = explode('?token=', $data);
		if(count($var)>1)
		{
			$var = explode('","', $var[1]);
			$token = $var[0];
		}
		else
		{
			$this->_error = "Couldn't find Devolo Central Token in response request.";
			return false;
		}

		$path = '/dhlp/portal/light/?token='.$token;
		$data = $this->_MyRequest('http', 'GET', $this->_dhcUrl, $path, null, $this->_uuid, $this->_passkey, null);
		$var = explode("JSESSIONID=", $data);
		if(count($var)>1)
		{
			$var = explode("; ", $var[1]);
			$this->_sessionID = $var[0];
		}
		else
		{
			$this->_error = "Couldn't find sessionID from response request.";
			return false;
		}
		return true;
	}

//localDevoloDHC end
}

?>
