<?php

class DevoloDHC {

	public $_version = "1.0";

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
	//Infos
	public function getAuth() //return array of infos for faster connections with all datas
	{
		$auth = array(
					"uuid" => $this->_uuid,
					"gateway" => $this->_gateway,
					"passkey" => $this->_passkey,
					"call" => 'new DevoloDHC($login, $password, $localIP, $uuid, $gateway, $passkey)'
					);
		return array('result'=>$auth);
	}

	public function getInfos() //return infos from this api and the Devolo central
	{
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
		return array('result'=>$infos);
	}

	//IS:
	public function isRuleActive($rule)
	{
		if ( is_string($rule) ) $rule = $this->getRuleByName($rule);
		if ( isset($rule['error']) ) return $rule;

		$jsonArray = $this->fetchItems(array($rule["element"]));
		$state = $jsonArray["result"]["items"][0]["properties"]["enabled"];
		$state = ($state > 0 ? "active" : "inactive");
		return array('result'=>$state);
	}

	public function isTimerActive($timer)
	{
		if ( is_string($timer) ) $timer = $this->getTimerByName($timer);
		if ( isset($timer['error']) ) return $timer;

		return $this->isRuleActive($timer);
	}

	public function isDeviceOn($device) //return true of false if find a sensor state in device
	{
		if ( is_string($device) ) $device = $this->getDeviceByName($device);
		if ( isset($device['error']) ) return $device;

		$sensors = (isset($device['sensors']) ? json_decode($device['sensors'], true): null);
		if ($sensors == null) return array('result'=>null, 'error' => 'Unfound sensor for device');

		for($i=0; $i<count($sensors); $i++)
		{
			$sensor = $sensors[$i];
			$sensorType = $this->getSensorType($sensor);

			if (in_array($sensorType, $this->_DevicesOnOff))
			{
				$sensorDatas = $this->fetchItems(array($sensor));
				if ( isset($sensorDatas["result"]["items"][0]["properties"]["state"]) )
				{
					$state = $sensorDatas["result"]["items"][0]["properties"]["state"];
					$isOn = ($state > 0 ? "on" : "off");
					return array('result' => $isOn);
				}
				$param = $this->getValuesByType($sensorType);
			}
		}
		return array('result'=>null, 'error' => 'Unfound OnOff sensor for device');
	}

	//GET:
	public function getDeviceStates($device, $DebugReport=null) //return array of sensor type and state
	{
		if ( is_string($device) ) $device = $this->getDeviceByName($device);
		if ( isset($device['error']) ) return $device;

		$sensors = (isset($device['sensors']) ? json_decode($device['sensors'], true) : null);
		if ($sensors == null) return array('result'=>null, 'error' => 'Unfound device');

		//fetch sensors:
		$arrayStates = array();

		for($i=0; $i<count($sensors); $i++)
		{
			$sensor = $sensors[$i];
			$sensorType = $this->getSensorType($sensor);
			$param = $this->getValuesByType($sensorType);
			if ($param != null)
			{
				$sensorDatas = $this->fetchItems(array($sensor));
				if ($DebugReport == true) echo "<br><pre>".json_encode($sensorDatas, JSON_PRETTY_PRINT)."</pre><br>";
				$jsonSensor = array('sensorType' => $sensorType);
				foreach ($param as $key)
				{
					$value = $sensorDatas["result"]["items"][0]["properties"][$key];
					//Seems Devolo doesn't know all about its own motion sensor...
					if ($key=="sensorType" and $value=="unknown") continue;
					//echo "sensorType:".$sensorType.", key:".$key.", value:".$value."<br>";
					$value = $this->formatStates($sensorType, $key, $value);
					$jsonSensor[$key] = $value;
				}
				array_push($arrayStates, $jsonSensor);
			}
			elseif( !in_array($sensorType, $this->_SensorsNoValues) ) //Unknown, unsupported sensor!
			{
				$sensorDatas = $this->fetchItems(array($sensor));
				echo "DEBUG - UNKNOWN PARAM - Please help and report this message on https://github.com/KiboOst/php-devoloDHC or email it to".base64_decode('a2lib29zdEBmcmVlLmZy')." <br>";
				echo "<pre>infos:".json_encode($sensorDatas, JSON_PRETTY_PRINT)."</pre><br>";
			}
		}
		return array('result'=>$arrayStates);
	}

	public function getDeviceData($device, $askData=null) //get device sensor data. If not asked data, return available datas
	{
		if ( is_string($device) ) $device = $this->getDeviceByName($device);
		if ( isset($device['error']) ) return $device;

		$datas = $this->getDeviceStates($device);
		$itemCount = count($datas);
		$availableDatas = array();
		foreach ($datas['result'] as $item)
		{
			array_push($availableDatas, $item['sensorType']);
			if ($item['sensorType'] == $askData) return array('result'=>$item);
		}
		$error = array('result'=>null,
					'error' => 'Unfound data for this Device',
					'available' => $availableDatas
					);
		return $error;
	}

	public function getDeviceURL($device)
	{
		if ( is_string($device) ) $device = $this->getDeviceByName($device);
		if ( isset($device['error']) ) return $device;

		$uid = $device['uid'];
		if (!stristr($uid, 'hdm:DevoloHttp:virtual')) return array('result'=>null, 'error' => 'This is not an http virtual device');

		$hdm = str_replace("hdm:DevoloHttp:virtual", "hs.hdm:DevoloHttp:virtual", $uid);
		$hdmDatas = $this->fetchItems(array($hdm));
		$url = $hdmDatas['result']['items'][0]['properties']['httpSettings']['request'];
		return array('result'=>$url);
	}

	public function getDeviceBattery($device)
	{
		if ( is_string($device) ) $device = $this->getDeviceByName($device);
		if ( isset($device['error']) ) return $device;

		$batLevel = $device['batteryLevel'];
		if ($batLevel == "None" or $batLevel == -1) $batLevel = "No battery";

		return array('result'=>$batLevel);
	}

	public function getAllBatteries($lowLevel=100, $filter=1)
	{
		$jsonDatas = array();
		for($i=0; $i<count($this->_AllDevices); $i++)
		{
			$thisDevice = $this->_AllDevices[$i];
			$thisDeviceName = $thisDevice['name'] ;
			$thisBatLevel = $thisDevice['batteryLevel'];
			if (($thisBatLevel == -1) or ($thisBatLevel == "None")) {if ($filter==1) continue; }

			$datas = array("name" => $thisDeviceName, "battery_percent" => $thisBatLevel);
			if ($thisBatLevel <= $lowLevel) array_push($jsonDatas, $datas);
		}
		return array('result'=>$jsonDatas);
	}

	public function getDailyDiary($numEvents=20)
	{
		$jsonString = '{"jsonrpc":"2.0", "method":"FIM/invokeOperation","params":["devolo.DeviceEvents","retrieveDailyData",[0,0,'.$numEvents.']]}';
		$result = $this->sendCommand($jsonString);

		$jsonDatas = array();
		$numEvents = count($result['result']); //may have less than requested
		for($i=$numEvents-1; $i>=1; $i--) //put recent on top
		{
			$event = $result['result'][$i];
			$deviceName = $event['deviceName'];
			$deviceZone = $event['deviceZone'];
			$author = $event['author'];
			$timeOfDay = $event['timeOfDay'];
			$timeOfDay = gmdate("H:i:s", $timeOfDay);

			$datas = array(
							"deviceName" => $deviceName,
							"deviceZone" => $deviceZone,
							"author" => $author,
							"timeOfDay" => $timeOfDay
							);
			array_push($jsonDatas, $datas);
		}
		return array('result'=>$jsonDatas);
	}

	public function getAllDevices() { return array('result'=>$this->_AllDevices); }

	//SET:
	public function startScene($scene)
	{
		if ( is_string($scene) ) $scene = $this->getSceneByName($scene);
		if ( isset($scene['error']) ) return $scene;

		$element = $scene['element'];
		$answer = $this->invokeOperation($element, "start");
		$result = ( ($answer['result'] == null) ? true : false );
		return array('result'=>$result);
	}

	public function turnDeviceOnOff($device, $state=0)
	{
		if ( is_string($device) ) $device = $this->getDeviceByName($device);
		if ( isset($device['error']) ) return $device;

		$sensors = (isset($device['sensors']) ? json_decode($device['sensors'], true) : null);
		if ($sensors == null) return array('result'=>null, 'error' => 'No sensor found in this device');

		if ($state < 0) $state = 0;

		for($i=0; $i<count($sensors); $i++)
		{
			$sensor = $sensors[$i];
			$sensorType = $this->getSensorType($sensor);

			if (in_array($sensorType, $this->_DevicesOnOff))
			{
				$operation = ($state == 0 ? 'turnOff' : 'turnOn');
				$answer = $this->invokeOperation($sensor, $operation);
				if (isset($answer['error']["message"]) )
				{
					return array('result'=>null, 'error'=>$answer['error']["message"]);
				}
				return array('result'=>true);
			}
			if (in_array($sensorType, $this->_DevicesSend) and ($state == 1))
			{
				$operation = "send";
				$answer = $this->invokeOperation($sensor, $operation);
				if (isset($answer['error']["message"]) )
				{
					return array('result'=>null, 'error'=>$answer['error']["message"]);
				}
				return array('result'=>true);
			}
		}
		return array('result'=>null, 'error' => 'No supported sensor for this device');
	}

	public function setDeviceValue($device, $value)
	{
		if ( is_string($device) ) $device = $this->getDeviceByName($device);
		if ( isset($device['error']) ) return $device;

		$sensors = (isset($device['sensors']) ? json_decode($device['sensors'], true) : null);
		if ($sensors == null) return array('result'=>null, 'error' => 'No sensor found in this device');

		for($i=0; $i<count($sensors); $i++)
		{
			$sensor = $sensors[$i];
			$sensorType = $this->getSensorType($sensor);

			if (in_array($sensorType, $this->_DevicesSendValue))
			{
				$operation = 'sendValue';
				$answer = $this->invokeOperation($sensor, $operation, $value);
				if (isset($answer['error']["message"]) )
				{
					return array('result'=>null, 'error'=>$answer['error']["message"]);
				}
				return array('result'=>true);
			}
		}
		return array('result'=>null, 'error' => 'No supported sensor for this device');
	}

	public function pressDeviceKey($device, $key=null)
	{
		if (!isset($key)) return array('result'=>null, 'error' => 'No key to press');
		if ($key > 4) return array('result'=>null, 'error' => 'You really have Wall Switch with more than 4 buttons ? Let me know!');
		if ( is_string($device) ) $device = $this->getDeviceByName($device);
		if ( isset($device['error']) ) return $device;

		$sensors = (isset($device['sensors']) ? json_decode($device['sensors'], true) : null);
		if ($sensors == null) return array('result'=>null, 'error' => 'No sensor found in this device');

		for($i=0; $i<count($sensors); $i++)
		{
			$sensor = $sensors[$i];
			$sensorType = $this->getSensorType($sensor);
			if (in_array($sensorType, $this->_DevicesPressKey))
			{
				$operation = 'pressKey';
				$answer = $this->invokeOperation($sensor, $operation, $key);
				if (isset($answer['error']["message"]) )
				{
					return array('result'=>null, 'error'=>$answer['error']["message"]);
				}
				return array('result'=>true);
			}
		}
		return array('result'=>null, 'error' => 'No supported sensor for this device');
	}

	public function resetSessionTimeout() //not allowed acton :-(
	{
		$jsonString = '{"jsonrpc":"2.0", "method":"FIM/invokeOperation","params":["'.$this->_uuid.'","resetSessionTimeout",[]]}';
		$result = $this->sendCommand($jsonString);
		return array('result'=>$result);
	}

	//GET shorcuts:
	public function getDeviceByName($name)
	{
		for($i=0; $i<count($this->_AllDevices); $i++)
		{
			$thisDevice = $this->_AllDevices[$i];
			if ($thisDevice['name'] == $name) return $thisDevice;
		}
		return array('result'=>null, 'error' => 'Unfound device');
	}

	public function getRuleByName($name)
	{
		if (count($this->_AllRules) == 0) $this->getRules();

		for($i=0; $i<count($this->_AllRules); $i++)
		{
			$thisRule = $this->_AllRules[$i];
			if ($thisRule['name'] == $name) return $thisRule;
		}
		return array('result'=>null, 'error' => 'Unfound rule');
	}

	public function getTimerByName($name)
	{
		if (count($this->_AllTimers) == 0) $this->getTimers();

		for($i=0; $i<count($this->_AllTimers); $i++)
		{
			$thisTimer = $this->_AllTimers[$i];
			if ($thisTimer['name'] == $name) return $thisTimer;
		}
		return array('result'=>null, 'error' => 'Unfound timer');
	}

	public function getSceneByName($name)
	{
		if (count($this->_AllScenes) == 0) $this->getScenes();

		for($i=0; $i<count($this->_AllScenes); $i++)
		{
			$thisScene = $this->_AllScenes[$i];
			if ($thisScene['name'] == $name) return $thisScene;
		}
		return array('result'=>null, 'error' => 'Unfound scene');
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
							"model" => (isset($thisDevice["properties"]["deviceModelUID"]) ? $thisDevice["properties"]["deviceModelUID"] : "None")
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

	protected function formatStates($sensorType, $key, $value)
	{
		if ($sensorType=="Meter" and $key=="totalValue") return $value."kWh";
		if ($sensorType=="Meter" and $key=="currentValue") return $value."W";
		if ($key=="sinceTime")
		{
			$ts = $value;
			$ts = substr($ts, 0, -3) - 3600; //microtime timestamp from Berlin
			$date = new DateTime();
			$date->setTimestamp($ts);
			$date->setTimezone(new DateTimeZone(date_default_timezone_get())); //set it to php server timezone
			$date = $date->format('d.m.Y H:i');
			return $date;
		}
		if ($sensorType=="LastActivity" and $key=="lastActivityTime")
		{
			if ($value == -1) return "Never";
			//convert javascript timestamp to date:
			$ts = $value;
			$ts = substr($ts, 0, -3) - 3600; //microtime timestamp from Berlin
			$date = new DateTime();
			$date->setTimestamp($ts);
			$date->setTimezone(new DateTimeZone(date_default_timezone_get())); //set it to php server timezone

			//format it:
			$nowDate = new DateTime();
			$interval = $nowDate->diff($date)->days;
			switch($interval) {
				case 0:
					$date = 'Today '.$date->format('H:i');
					break;
				case -1:
					$date = 'Yesterday '.$date->format('H:i');
					break;
				default:
					$date = $date->format('d.m.Y H:i');
			}
			return $date;
		}
		return $value;
	}

	protected function getSensorType($sensor)
	{
		$sensorType = explode("devolo.", $sensor);
		if (count($sensorType) == 0) return null;
		$sensorType = explode(":", $sensorType[1]);
		$sensorType = $sensorType[0];
		return $sensorType;
	}

	protected function getValuesByType($sensorType) //ex: devolo.BinarySensor:hdm:ZWave:D8F7DDE2/10
	{
		foreach ($this->_SensorValuesByType as $type => $param)
		{
			if ($type == $sensorType) return $param;
		}
		return null;
	}


	//calling functions===================================================
	protected function _request($protocol, $method, $host, $path, $json, $login, $password, $cookie) //standard function handling all get/post request with curl | return string
	{
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
			$auth = $login.":".$password;
			curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($curl, CURLOPT_USERPWD, $auth);
		}

		if ($method == 'POST')
			{
				$json->{'id'}=$this->_POSTid;
				$this->_POSTid++;
				$data_string = json_encode($json);
				curl_setopt($curl, CURLOPT_HTTPHEADER, array(
														'Content-Type: application/json',
														'Content-Length: '.strlen($data_string))
													);
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
				curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
			}

		if ( isset($cookie) ) //auth ok and get connection cookie, can ask and send stuff to central !!
		{
			$addCookies = 'JSESSIONID='.$cookie.'; GW_ID='.$this->_gateway.'; FIM_WS_FILTER=(|(GW_ID='.$this->_gateway.')(!(GW_ID=*)))';
			//$addCookies = 'JSESSIONID='.$cookie;
			curl_setopt($curl, CURLOPT_COOKIE, $addCookies);

			curl_setopt($curl, CURLOPT_HEADER, false);
			curl_setopt($curl, CURLINFO_HEADER_OUT, false );
		}

		$response = curl_exec($curl);
		$header   = curl_getinfo($curl);
		$err      = curl_errno($curl);
		$errmsg   = curl_error($curl);

		curl_close($curl);

		return $response;
	}

	protected function fetchItems($UIDSarray) //get infos from central for array of device, sensor, timer etc | return array
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
		return $jsonArray;
	}

	protected function invokeOperation($sensor, $operation, $value=null) //sensor string, authorized operation string | return array
	{
		$value = '['.$value.']';
		$jsonString = '{
			"jsonrpc":"2.0",
			"method":"FIM/invokeOperation",
			"params":["'.$sensor.'","'.$operation.'",'.$value.']}';

		$json = json_decode($jsonString);
		$data = $this->_request('http', 'POST', $this->_localHost, '/remote/json-rpc', $json, null, null, $this->_sessionID);
		$jsonArray = json_decode($data, true);
		return $jsonArray;
	}

	public function sendCommand($jsonString) //directly send json to central. Only works when all required authorisations are set | return array
	{
		$json = json_decode($jsonString);
		$data = $this->_request('http', 'POST', $this->_localHost, '/remote/json-rpc', $json, null, null, $this->_sessionID);
		$jsonArray = json_decode($data, true);
		return $jsonArray;
	}

	//functions authorization=============================================
	protected function initAuth() //get uuid, gateway and passkey from www.devolo.com for authorization
	{
		//get uuid:
		$data = $this->_request('https', 'GET', $this->_Host, $this->_apiVersion.'/users/uuid', null, $this->_login, $this->_password, null);
		$data = json_decode($data, true);
		$this->_uuid = $data["uuid"];

		//get gateway:
		$path = $this->_apiVersion.'/users/'.$this->_uuid.'/hc/gateways';
		$data = $this->_request('https', 'GET', $this->_Host, $path, null, $this->_login, $this->_password, null);
		$data = json_decode($data, true);
		$var = explode( "/gateways/", $data["items"][0]["href"] );
		$this->_gateway = $var[1];

		//get localPasskey:
		$path = $this->_apiVersion.'/users/'.$this->_uuid.'/hc/gateways/'.$this->_gateway;
		$data = $this->_request('https', 'GET', $this->_Host, $path, null, $this->_login, $this->_password, null);
		$data = json_decode($data, true);
		$this->_passkey = $data["localPasskey"];
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
	}

	protected $_Host = 'www.mydevolo.com';
	protected $_apiVersion = '/v1';
	protected $_POSTid = 0;

	//user central stuff:
	protected $_login;
	protected $_password;
	protected $_localHost;
	protected $_uuid;
	protected $_gateway;
	protected $_passkey;
	public $_sessionID; //the one to get first!

	//central stuff stuff(!):
	public $_AllZones = null;
	public $_AllDevices = null;
	public $_AllRules = null;
	public $_AllTimers = null;
	public $_AllScenes = null;

	//types stuff:
	protected $_DevicesOnOff = array("BinarySwitch", "BinarySensor"); //supported devices type for on/off operation
	protected $_DevicesSend = array("HttpRequest"); //supported devices type for send operation
	protected $_DevicesSendValue = array("MultiLevelSwitch", "SirenMultiLevelSwitch"); //supported devices type for sendValue operation
	protected $_DevicesPressKey = array("RemoteControl"); //supported devices type for pressKey operation
	protected $_SensorsNoValues = array("HttpRequest"); //virtual devices
	protected $_SensorValuesByType = array(
										'MildewSensor' => array('sensorType', 'state'),
										'BinarySensor' => array('sensorType', 'state'),
										'BinarySwitch' => array('switchType', 'state', 'targetState'),
										'SirenBinarySensor' => array('sensorType', 'state'),
										'Meter' => array('sensorType', 'currentValue', 'totalValue', 'sinceTime'),
										'MultiLevelSensor' => array('sensorType', 'value'),
										'HumidityBarZone' => array('sensorType', 'value'),
										'DewpointSensor' => array('sensorType', 'value'),
										'HumidityBarValue' => array('sensorType', 'value'),
										'SirenMultiLevelSwitch' => array('switchType', 'targetValue'),
										'SirenMultiLevelSensor' => array('sensorType', 'value'),
										'LastActivity' => array('lastActivityTime'),
										'RemoteControl' => array('keyCount', 'keyPressed'),
										'MultiLevelSwitch' => array('switchType', 'value', 'targetValue', 'min', 'max')
										);

	protected function init() //sorry, I'm a python guy :-]
	{
		//get sessionID on local Devolo Home Control unit:
		if ( !isset($this->_uuid) or !isset($this->_gateway) or !isset($this->_passkey) )
		{
			$this->initAuth();
		}
		else
		{
			$this->getSessionID();
		}
		$this->getDevices();
	}

//DevoloDHC end
}

?>
