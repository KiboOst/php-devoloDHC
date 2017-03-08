<?php

class DevoloDHC {

	public $_version = "2017.3.1";
	public $_debug = 0;

	protected $_Host = 'www.mydevolo.com';
	protected $_apiVersion = '/v1';

	protected $_login;
	protected $_password;
	protected $_localHost;
	protected $_uuid;
	protected $_gateway;
	protected $_passkey;
	protected $_sessionID = null; //the one to get first!

	public $_AllZones = null;
	public $_AllDevices = null;
	public $_AllRules = null;
	public $_AllTimers = null;
	public $_AllScenes = null;

	protected $_DevicesOnOff = array("BinarySwitch", "BinarySensor", "SirenBinarySensor"); //supported devices type for on/off
	protected $_DevicesSend = array("HttpRequest"); //supported devices type for send

	protected $_SensorTypes = array(
										'BinarySwitch' => 'state', //on/off Devolo wall plug
										'BinarySensor' => 'state', //alarm on/off Devolo MotionSensor or Devolo  Door/Win sensor
										'SirenBinarySensor' => 'state', //Devolo Siren alarm on/off
										'Meter' => 'currentValue', //watts Devolo wall plug
										'MultiLevelSensor' => 'value', //Fibaro smoke sensor
										'SirenMultiLevelSwitch' => 'value', //Devolo Siren ??
										'SirenMultiLevelSensor' => 'value' //Devolo Siren ??
									);

	function __construct($login, $password, $localHost, $uuid=null, $gateway=null, $passkey=null)
	{
		$this->_login = $login;
		$this->_password = $password;
		$this->_localHost = $localHost;
		if (isset($uuid)) $this->_uuid = $uuid;
		if (isset($gateway)) $this->_gateway = $gateway;
		if (isset($passkey)) $this->_passkey = $passkey;
		$this->init();
	}

	//user functions======================================================
	//General
	public function getAuth() //return array of infos for faster connections with all datas
	{
		$auth = array(
					"uuid" => $this->_uuid,
					"gateway" => $this->_gateway,
					"passkey" => $this->_passkey,
					"call" => 'new DevoloDHC($login, $password, $localIP, $uuid, $gateway, $passkey)'
					);
		return $auth;
	}

	public function getInfos() //return infos from this api and the Devolo central
	{
		global $login, $password;
		$path = $this->_apiVersion.'/users/'.$this->_uuid.'/hc/gateways/'.$this->_gateway;
		$data = $this->_request('https', 'GET', $this->_Host, $path, null, $this->_login, $this->_password, null);

		$data = json_decode($data, true);

		$infos = array(
				'phpAPI version' => $this->_version,
				'uuid' => $this->_uuid,
				'gateway' => $this->_gateway,
				'name' => $data["name"],
				'role' => $data["role"],
				'status' => $data["status"],
				'state' => $data["state"],
				'type' => $data["type"],
				'firmwareVersion' => $data["firmwareVersion"],
				'externalAccess' => $data["externalAccess"]
				);

		return $infos;
	}

	//GET
	public function isRuleActive($rule)
	{
		if ( is_string($rule) ) $rule = $this->getRuleByName($rule);
		if ( is_string($rule) ) return $rule;

		$jsonArray = $this->fetchItems(array($rule["element"]));
		return $jsonArray["result"]["items"][0]["properties"]["enabled"];
	}

	public function isTimerActive($timer)
	{
		if ( is_string($timer) ) $timer = $this->getTimerByName($timer);
		if ( is_string($timer) ) return $timer;

		return $this->isRuleActive($timer);
	}

	public function isDeviceOn($device) //return true of false if find a sensor state in device
	{
		if ( is_string($device) ) $device = $this->getDeviceByName($device);
		if ( is_string($device) ) return $device;

		$sensors = (isset($device['sensors']) ? $device['sensors'] : null);
		if ($sensors == null) return "Unfound sensor for device";

		$sensors = json_decode($sensors, true);

		for($i=0; $i<count($sensors); $i++)
		{
			$sensor = $sensors[$i];
			$sensorType = $this->getSensorType($sensor);

			if (in_array($sensorType, $this->_DevicesOnOff))
			{
				$param = $this->getStatebyType($sensorType);
				if ($param != null)
				{
					$sensorDatas = $this->fetchItems(array($sensor));
					$state = $sensorDatas["result"]["items"][0]["properties"][$param];
					$isOn = ($state > 0 ? true : false);
					return $isOn;
				}
			}
		}
		return "Unfound OnOff sensor for device";
	}

	public function getDeviceByName($name)
	{
		for($i=0; $i<count($this->_AllDevices); $i++)
		{
			$thisDevice = $this->_AllDevices[$i];
			if ($thisDevice['name'] == $name) return $thisDevice;
		}
		return "Unfound device";
	}

	public function getRuleByName($name)
	{
		if (count($this->_AllRules) == 0) $this->getRules();

		for($i=0; $i<count($this->_AllRules); $i++)
		{
			$thisRule = $this->_AllRules[$i];
			if ($thisRule['name'] == $name) return $thisRule;
		}
		return "Unfound rule";
	}

	public function getTimerByName($name)
	{
		if (count($this->_AllTimers) == 0) $this->getTimers();

		for($i=0; $i<count($this->_AllTimers); $i++)
		{
			$thisTimer = $this->_AllTimers[$i];
			if ($thisTimer['name'] == $name) return $thisTimer;
		}
		return "Unfound timer";
	}

	public function getSceneByName($name)
	{
		if (count($this->_AllScenes) == 0) $this->getScenes();

		for($i=0; $i<count($this->_AllScenes); $i++)
		{
			$thisScene = $this->_AllScenes[$i];
			if ($thisScene['name'] == $name) return $thisScene;
		}
		return "Unfound scene";
	}

	public function getDeviceStates($device) //return array of sensor type and state
	{
		$sensors = (isset($device['sensors']) ? $device['sensors'] : null);
		if ($sensors == null) return "Unfound device";

		//fetch sensors:
		$sensors = json_decode($sensors, true);
		$arrayStates = array();

		for($i=0; $i<count($sensors); $i++)
		{
			$sensor = $sensors[$i];
			$sensorType = $this->getSensorType($sensor);
			$param = $this->getStatebyType($sensorType);
			if ($param != null)
			{
				$sensorDatas = $this->fetchItems(array($sensor));
				$state = $sensorDatas["result"]["items"][0]["properties"][$param];

				$data = array('type' => $sensorType,
								'state' => $state);
				array_push($arrayStates, $data);
			}
			else //debug!!!
			{
				$sensorDatas = $this->fetchItems(array($sensor));
				echo "DEBUG - UNKNOWN PARAM!!!!<br>";
				echo "sensor state: ".json_encode($sensorDatas)."<br>";
			}
		}
		return $arrayStates;
	}

	public function refreshDevice($device)
	{
		if ( is_string($device) ) $device = $this->getDeviceByName($device);
		if ( is_string($device) ) return $device;

		$refreshDevice = $this->fetchItems(array($device));
		for($i=0; $i<count($this->_AllDevices); $i++)
		{
			$thisDevice = $this->_AllDevices[$i];
			if ($thisDevice['uid'] == $device['uid'])
			{
				$this->_AllDevices[$i] = $thisDevice;
				return $thisDevice;
			}
		}
		return "Unfound device";
	}

	public function getDeviceBattery($device)
	{
		if ( is_string($device) ) $device = $this->getDeviceByName($device);
		if ( is_string($device) ) return $device;

		return $device['batteryLevel'];
	}

	public function getAllDevices() { return $this->_AllDevices; }

	//SET
	public function startScene($scene)
	{
		if ( is_string($scene) ) $scene = $this->getSceneByName($scene);
		if ( is_string($scene) ) return $scene;

		$element = $scene['element'];
		$answer = $this->invokeOperation($element, "start");
		$result = ( ($answer['result'] == null) ? true : false );
		return $result;
	}

	public function turnDeviceOnOff($device, $state=0)
	{
		if ( is_string($device) ) $device = $this->getDeviceByName($device);
		if ( is_string($device) ) return $device;

		$sensors = (isset($device['sensors']) ? $device['sensors'] : null);
		if ($sensors == null) return "Unfound sensor for device";

		$sensors = json_decode($sensors, true);

		for($i=0; $i<count($sensors); $i++)
		{
			$sensor = $sensors[$i];
			$sensorType = $this->getSensorType($sensor);

			if (in_array($sensorType, $this->_DevicesOnOff))
			{
				$operation = ($state == 0 ? 'turnOff' : 'turnOn');
				$answer = $this->invokeOperation($sensor, $operation);
				$result = ( ($answer["result"]['error'] == null) ? true : false );
				return $result;
			}
			if (in_array($sensorType, $this->_DevicesSend) and ($state == 1))
			{
				$operation = "send";
				$answer = $this->invokeOperation($sensor, $operation);
				$result = ( ($answer['error'] == null) ? true : false );
				return $result;
			}
		}
		return false;
	}

	public function turnSensorOnOff($sensor, $state=0) //no string!! for advanced users :-)
	{
		$operation = ($state == 0 ? 'turnOff' : 'turnOn');
		$answer = $this->invokeOperation($sensor, $operation);
		$result = ( ($answer['error'] == null) ? true : false );
		return $result;
	}


	//internal functions==================================================
	protected function getDevices()
	{
		if (count($this->_AllZones) == 0) $this->getZones();

		//get all devices from all zones:
		$UIDSarray = array();
		for($i=0; $i<count($this->_AllZones); $i++)
		{
			$thisDevices = $this->_AllZones[$i]["deviceUIDs"];
			for($j=0; $j<count($thisDevices); $j++)
			{
				array_push($UIDSarray, $thisDevices[$j]);
			}
		}

		//request all infos for all devices at once:
		$jsonArray = $this->fetchItems($UIDSarray);

		//store devices:
		$devices = array();
		$devicesNum = count($jsonArray['result']["items"]);
		for($i=0; $i<$devicesNum; $i++)
		{
			$thisDevice =  $jsonArray['result']["items"][$i];

			$name = (isset($thisDevice["properties"]["itemName"]) ? $thisDevice["properties"]["itemName"] : "None");
			$uid = (isset($thisDevice["UID"]) ? $thisDevice["UID"] : "None");
			$elementUIDs = (isset($thisDevice["properties"]["elementUIDs"]) ? $thisDevice["properties"]["elementUIDs"] : "None");

			$device = array("name" => $name,
							"uid" => $uid,
							"sensors" => json_encode($elementUIDs),
							"batteryLevel" => (isset($thisDevice["properties"]["batteryLevel"]) ? $thisDevice["properties"]["batteryLevel"] : "None"),
							"model" => (isset($thisDevice["properties"]["deviceModelUID"]) ? $thisDevice["properties"]["batteryLevel"] : "None")
							);

			array_push($devices, $device);
		}
		$this->_AllDevices = $devices;
	}

	protected function getZones()
	{
		$this->_AllZones = array();

		$json = '{
			"jsonrpc":"2.0",
			"method":"FIM/getFunctionalItems",
			"params":[
				["devolo.Grouping"],0
				]
			}';
		$json = json_decode($json);
		$data = $this->_request('http', 'POST', $this->_localHost, '/remote/json-rpc', $json, null, null, $this->_sessionID);

		$jsonArray = json_decode($data, true);

		$zonesNum = count($jsonArray['result']["items"][0]['properties']['zones']);

		for($i=0; $i<$zonesNum; $i++)
		{
			$thisZone = $jsonArray['result']["items"][0]['properties']['zones'];
			$thisID = $thisZone[$i]["id"];
			$thisName = $thisZone[$i]["name"];
			$thisDevices = $thisZone[$i]["deviceUIDs"];

			$zone = array("name" => $thisName,
							"id" => $thisID,
							"deviceUIDs" => $thisDevices
							);
			array_push($this->_AllZones, $zone);
		}
	}

	protected function getScenes()
	{
		$this->_AllScenes = array();

		$json = '{
			"jsonrpc":"2.0",
			"method":"FIM/getFunctionalItems",
			"params":[
				["devolo.Scene"],0
				]
			}';
		$json = json_decode($json);
		$data = $this->_request('http', 'POST', $this->_localHost, '/remote/json-rpc', $json, null, null, $this->_sessionID);
		$jsonArray = json_decode($data, true);

		$scenesArray = array();
		$scenesNum = count($jsonArray['result']["items"][0]['properties']['sceneUIDs']);
		for($i=0; $i<$scenesNum; $i++)
		{
			$thisScene = $jsonArray['result']["items"][0]['properties']['sceneUIDs'][$i];
			array_push($scenesArray, $thisScene);
		}

		//request datas for all rules:
		$jsonArray = $this->fetchItems($scenesArray);

		$scenesNum = count($jsonArray['result']["items"]);
		for($i=0; $i<$scenesNum; $i++)
		{
			$thisScene = $jsonArray['result']["items"][$i];
			$rule = array("name" => $thisScene["properties"]["itemName"],
							"id" => $thisScene["UID"],
							"element" => str_replace("Scene", "SceneControl", $thisScene["UID"])
							);
			array_push($this->_AllScenes, $rule);
		}
	}

	protected function getTimers()
	{
		$this->_AllTimers = array();

		$json = '{
			"jsonrpc":"2.0",
			"method":"FIM/getFunctionalItems",
			"params":[
				["devolo.Schedules"],0
				]
			}';
		$json = json_decode($json);
		$data = $this->_request('http', 'POST', $this->_localHost, '/remote/json-rpc', $json, null, null, $this->_sessionID);
		$jsonArray = json_decode($data, true);

		$timersArray = array();
		$timersNum = count($jsonArray['result']["items"][0]['properties']['scheduleUIDs']);
		for($i=0; $i<$timersNum; $i++)
		{
			$thisTimer = $jsonArray['result']["items"][0]['properties']['scheduleUIDs'][$i];
			array_push($timersArray, $thisTimer);
		}

		//request datas for all rules:
		$jsonArray = $this->fetchItems($timersArray);

		$timersNum = count($jsonArray['result']["items"]);
		for($i=0; $i<$timersNum; $i++)
		{
			$thisTimer = $jsonArray['result']["items"][$i];
			$rule = array("name" => $thisTimer["properties"]["itemName"],
							"id" => $thisTimer["UID"],
							"element" => str_replace("Schedule", "ScheduleControl", $thisTimer["UID"])
							);
			array_push($this->_AllTimers, $rule);
		}
	}

	protected function getRules()
	{
		$this->_AllRules = array();

		$json = '{
			"jsonrpc":"2.0",
			"method":"FIM/getFunctionalItems",
			"params":[
				["devolo.Services"],0
				]
			}';
		$json = json_decode($json);
		$data = $this->_request('http', 'POST', $this->_localHost, '/remote/json-rpc', $json, null, null, $this->_sessionID);
		$jsonArray = json_decode($data, true);

		$rulesArray = array();
		$rulesNum = count($jsonArray['result']["items"][0]['properties']['serviceUIDs']);
		for($i=0; $i<$rulesNum; $i++)
		{
			$thisRule = $jsonArray['result']["items"][0]['properties']['serviceUIDs'][$i];
			array_push($rulesArray, $thisRule);
		}

		//request datas for all rules:
		$jsonArray = $this->fetchItems($rulesArray);

		$rulesNum = count($jsonArray['result']["items"]);
		for($i=0; $i<$rulesNum; $i++)
		{
			$thisRule = $jsonArray['result']["items"][$i];
			$rule = array("name" => $thisRule["properties"]["itemName"],
							"id" => $thisRule["UID"],
							"element" => str_replace("Service", "ServiceControl", $thisRule["UID"])
							);
			array_push($this->_AllRules, $rule);
		}
	}

	protected function getSensorType($sensor)
	{
		$sensorType = explode("devolo.", $sensor);
		if (count($sensorType) == 0) return null;
		$sensorType = explode(":", $sensorType[1]);
		$sensorType = $sensorType[0];
		return $sensorType;
	}

	protected function getStatebyType($sensorType) //ex: devolo.BinarySensor:hdm:ZWave:D8F7DDE2/10
	{
		foreach ($this->_SensorTypes as $type => $param)
		{
			if ($type == $sensorType) return $param;
		}
		return null;
	}


	//calling functions===================================================
	protected function _request($protocol, $method, $host, $path, $json, $login, $password, $cookie) //standard function handling all get/post request with curl
	{
		if ($this->_debug >= 1) echo "<br>";

		$url = $protocol."://".$host.$path;

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_USERAGENT, 'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:51.0) Gecko/20100101 Firefox/51.0');
		curl_setopt($curl, CURLOPT_AUTOREFERER, true);

		if ($protocol == 'https')
		{
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		}
		else
		{
			curl_setopt($curl, CURLOPT_HEADER, true);
			curl_setopt($curl, CURLINFO_HEADER_OUT, true );
		}

		if ( isset($login) and isset($password) )
		{
			if ($this->_debug >= 2) echo "DHCrequest with login/password: ".$url."<br>";
			$auth = $login.":".$password;
			curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($curl, CURLOPT_USERPWD, $auth);
		}

		if ($method == 'POST')
			{
				$data_string = json_encode($json);
				if ($this->_debug >= 3) echo 'data_string: '.$data_string."<br>";
				curl_setopt($curl, CURLOPT_HTTPHEADER, array(
														'Host: homecontrol.mydevolo.com',
														'Referer: https://homecontrol.mydevolo.com/root/index.html',
														'Content-Type: application/json',
														'Content-Length: '.strlen($data_string))
													);
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
				curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
			}

		if ( isset($cookie) ) //auth ok and get connection cookie, can ask and send stuff to central !!
		{
			//$addCookies = 'JSESSIONID='.$cookie.'; GW_ID='.$_SESSION['DHCgateway'].'; FIM_WS_FILTER=(|(GW_ID='.$_SESSION['DHCgateway'].')(!(GW_ID=*)))';
			$addCookies = 'JSESSIONID='.$cookie;
			if ($this->_debug >= 2) echo "DHCrequest with cookie ".$url." | ".$addCookies."<br>";
			curl_setopt($curl, CURLOPT_COOKIE, $addCookies);

			curl_setopt($curl, CURLOPT_HEADER, false);
			curl_setopt($curl, CURLINFO_HEADER_OUT, false );
		}

		$response = curl_exec($curl);
		$header   = curl_getinfo($curl);
		$err      = curl_errno($curl);
		$errmsg   = curl_error($curl);

		curl_close($curl);

		if ($this->_debug >= 3) echo "response:".$response."<br>";

		return $response;
	}

	public function fetchItems($UIDSarray) //get infos from central for array of device, sensor, timer etc
	{
		$devicesJson = json_encode($UIDSarray);
		$json = '{
			"jsonrpc":"2.0",
			"method":"FIM/getFunctionalItems",
			"params":[
				'.$devicesJson.',0
				]
			}';

		$json = json_decode($json);
		$data = $this->_request('http', 'POST', $this->_localHost, '/remote/json-rpc', $json, null, null, $this->_sessionID);
		$jsonArray = json_decode($data, true);

		if ($this->_debug >= 4)  echo "DHC_fetchItems datas:".$data."<br>";
		return $jsonArray;
	}

	public function invokeOperation($sensor, $operation) //sensor string, authorized operation string !!
	{
		$jsonString = '{
			"jsonrpc":"2.0",
			"method":"FIM/invokeOperation",
			"params":[
				"'.$sensor.'","'.$operation.'",[]]}';

		$json = json_decode($jsonString);
		$data = $this->_request('http', 'POST', $this->_localHost, '/remote/json-rpc', $json, null, null, $this->_sessionID);
		if ($this->_debug >= 4) echo "DHC_invokeOperation $data:".$data."<br>";

		$jsonArray = json_decode($data, true);
		return $jsonArray;
	}

	public function sendCommand($jsonString) //not actually used, but works...
	{
		$json = json_decode($jsonString);
		$data = $this->_request('http', 'POST', $this->_localHost, '/remote/json-rpc', $json, null, null, $this->_sessionID);
		$jsonArray = json_decode($data, true);

		if ($this->_debug >= 4)  echo "DHC_sendCommand $data:".$data."<br>";
		return $jsonArray;
	}

	//functions authorization=============================================
	protected function initAuth() //get uuid, gateway and passkey from www.devolo.com for authorization
	{
		//get uuid:
		$data = $this->_request('https', 'GET', $this->_Host, $this->_apiVersion.'/users/uuid', null, $this->_login, $this->_password, null);
		$data = json_decode($data, true);
		$this->_uuid = $data["uuid"];
		if ($this->_debug >= 1) echo "uuid:".$this->_uuid."<br>";

		//get gateway:
		$path = $this->_apiVersion.'/users/'.$this->_uuid.'/hc/gateways';
		$data = $this->_request('https', 'GET', $this->_Host, $path, null, $this->_login, $this->_password, null);
		$data = json_decode($data, true);
		$var = explode( "/gateways/", $data["items"][0]["href"] );
		$this->_gateway = $var[1];
		if ($this->_debug >= 1) echo "gateway:".$this->_gateway."<br>";

		//get localPasskey:
		$path = $this->_apiVersion.'/users/'.$this->_uuid.'/hc/gateways/'.$this->_gateway;
		$data = $this->_request('https', 'GET', $this->_Host, $path, null, $this->_login, $this->_password, null);
		$data = json_decode($data, true);
		$this->_passkey = $data["localPasskey"];
		if ($this->_debug >= 1) echo "localPasskey:".$this->_passkey."<br>";
	}

	protected function getSessionID() //get and set cookie for later authorized requests
	{
		$this->_sessionID = null;

		//get token:
		$data = $this->_request('http', 'GET', $this->_localHost, '/dhlp/portal/light', null, $this->_uuid, $this->_passkey, null);
		$var = explode('?token=', $data);
		if(count($var)>1)
		{
			$var = explode('","', $var[1]);
			$token = $var[0];
		}
		else
		{
			die("Couldn't find Devolo Central Token in response request.");
		}
		if ($this->_debug >= 1) echo "token:".$token."<br>";


		$path = '/dhlp/portal/light/?token='.$token;
		$data = $this->_request('http', 'GET', $this->_localHost, $path, null, $this->_uuid, $this->_passkey, null);
		$var = explode("JSESSIONID=", $data);
		if(count($var)>1)
		{
			$var = explode("; ", $var[1]);
			$this->_sessionID = $var[0];
		}
		else
		{
			die("Couldn't find sessionID from response request.");
		}
		if ($this->_debug >= 1) echo "sessionID:".$this->_sessionID."<br>";
	}

	protected function init() //sorry, I'm a python guy :-]
	{
		//get sessionID on local Devolo Home Control unit:
		if ( isset($this->_uuid) and isset($this->_gateway) and isset($this->_passkey) )
		{
			if ($this->_debug >= 2) echo "getSessionID()"."<br>";
			$this->getSessionID();
		}
		else
		{
			if ($this->_debug >= 2) echo "initAuth()"."<br>";
			$this->initAuth();

			if ($this->_debug >= 2) echo "getSessionID()"."<br>";
			$this->getSessionID();
		}

		$this->getDevices();
		return true;
	}

//DevoloDHC end
}

?>
