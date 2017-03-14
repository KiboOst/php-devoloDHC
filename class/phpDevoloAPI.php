<?php

class DevoloDHC {

	public $_version = "1.23";

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

		$answer = $this->fetchItems(array($rule["element"]));
		if (isset($answer['error']["message"]) ) return array('result'=>null, 'error'=>$answer['error']["message"]);
		$state = $answer["result"]["items"][0]["properties"]["enabled"];
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

		foreach ($sensors as $sensor)
		{
			$sensorType = $this->getSensorType($sensor);

			if (in_array($sensorType, $this->_DevicesOnOff))
			{
				$answer = $this->fetchItems(array($sensor));
				if (isset($answer['error']["message"]) ) return array('result'=>null, 'error'=>$answer['error']["message"]);
				if ( isset($answer["result"]["items"][0]["properties"]["state"]) )
				{
					$state = $answer["result"]["items"][0]["properties"]["state"];
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

		foreach($sensors as $sensor)
		{
			$sensorType = $this->getSensorType($sensor);
			$param = $this->getValuesByType($sensorType);
			if ($param != null)
			{
				$answer = $this->fetchItems(array($sensor));
				if (isset($answer['error']["message"]) ) return array('result'=>null, 'error'=>$answer['error']["message"]);
				if ($DebugReport == true) echo "<br><pre>".json_encode($answer, JSON_PRETTY_PRINT)."</pre><br>";
				$jsonSensor = array('sensorType' => $sensorType);
				foreach ($param as $key)
				{
					$value = $answer["result"]["items"][0]["properties"][$key];
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
				$answer = $this->fetchItems(array($sensor));
				echo "DEBUG - UNKNOWN PARAM - Please help and report this message on https://github.com/KiboOst/php-devoloDHC or email it to".base64_decode('a2lib29zdEBmcmVlLmZy')." <br>";
				echo "<pre>infos:".json_encode($answer, JSON_PRETTY_PRINT)."</pre><br>";
			}
		}
		return array('result'=>$arrayStates);
	}

	public function getDeviceData($device, $askData=null) //get device sensor data. If not asked data, return available datas
	{
		if ( is_string($device) ) $device = $this->getDeviceByName($device);
		if ( isset($device['error']) ) return $device;

		$datas = $this->getDeviceStates($device);
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
		$answer = $this->fetchItems(array($hdm));
		if (isset($answer['error']["message"]) ) return array('result'=>null, 'error'=>$answer['error']["message"]);
		$url = $answer['result']['items'][0]['properties']['httpSettings']['request'];
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
		$c = count($this->_AllDevices);
		foreach ($this->_AllDevices as $thisDevice)
		{
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
		if (!is_int($numEvents)) return array('error'=> 'Provide numeric argument as number of events to report');
		if ($numEvents < 0) return array('error'=> 'Dude, what should I report as negative number of events ? Are you in the future ?');

		$jsonString = '{"jsonrpc":"2.0", "method":"FIM/invokeOperation","params":["devolo.DeviceEvents","retrieveDailyData",[0,0,'.$numEvents.']]}';
		$answer = $this->sendCommand($jsonString);
		if (isset($answer['error']["message"]) ) return array('result'=>null, 'error'=>$answer['error']["message"]);

		$jsonDatas = array();
		$numEvents = count($answer['result'])-1; //may have less than requested
		foreach (array_reverse($answer['result']) as $event)
		{
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

	public function getDailyStat($device, $dayBefore)
	{
		if ( is_string($device) ) $device = $this->getDeviceByName($device);
		if ( isset($device['error']) ) return $device;

		if (!is_int($dayBefore)) return array('error'=> 'second argument should be 0 1 or 2 for today, yesterday, day before yesterday');

		$operation = "retrieveDailyStatistics";
		$sensor = "st.".$device['uid'];
		$answer = $this->invokeOperation($sensor, $operation, $dayBefore);
		if (isset($answer['error']["message"]) ) return array('result'=>null, 'error'=>$answer['error']["message"]);

		$jsonDatas = array();
		foreach ($answer['result'] as $item)
		{
			$sensor = $item['widgetElementUID'];
			$values = $item['value'];
			$timesOfDay = $item['timeOfDay'];

			if (strpos($device['model'], 'Door/Window:Sensor'))
			{
				if (strpos($sensor, 'BinarySensor:hdm') !== false) $sensor = 'opened';
				if (strpos($sensor, '#MultilevelSensor(1)') !== false) $sensor = 'temperature';
				if (strpos($sensor, '#MultilevelSensor(3)') !== false) $sensor = 'light';
			}
			if (strpos($device['model'], 'Motion:Sensor'))
			{
				if (strpos($sensor, 'BinarySensor:hdm') !== false) $sensor = 'alarm';
				if (strpos($sensor, '#MultilevelSensor(1)') !== false) $sensor = 'temperature';
				if (strpos($sensor, '#MultilevelSensor(3)') !== false) $sensor = 'light';
			}
			if (strpos($device['model'], 'Wall:Plug:Switch:and:Meter'))
			{
				if (strpos($sensor, 'Meter:hdm') !== false) $sensor = 'consumption';
			}

			$sensorData = array('sensor'=>$sensor);
			foreach (array_reverse($values) as $value => $timeOfDay)
			{
				$timeOfDay = gmdate("H:i:s", $timeOfDay);
				$datas = array($timeOfDay=>$value);
				$sensorData[$timeOfDay] = $value;
			}
			array_push($jsonDatas, $sensorData);
		}
		return array('result'=>$jsonDatas);
	}

	public function logConsumption($filePath='/')
	{
		if (file_exists($filePath))
		{
			$prevDatas = json_decode(file_get_contents($filePath), true);
		}
		else
		{
			$prevDatas = array();
		}

		//get yesterday sums for each device:
		$yesterday = date('d.m.Y',strtotime("-1 days"));
		$datasArray = array();
		foreach ($this->_AllDevices as $device)
		{
			if (strpos($device['model'], 'Wall:Plug:Switch:and:Meter'))
			{
				$name = $device['name'];
				$datas = $this->getDailyStat($device, 1);
				$sum = array_sum($datas['result'][0])/1000;
				$sum = $sum.'kWh';
				$datasArray[$yesterday][$name] = $sum;
			}
		}

		//add yesterday sums to previously loaded datas:
		$prevDatas[$yesterday] = $datasArray[$yesterday];
		$put = file_put_contents($filePath, json_encode($prevDatas, JSON_PRETTY_PRINT));
		if ($put) return array('result'=>$datasArray);
		return array('result'=>$datasArray, 'error'=>'Unable to write file!');
	}

	public function getLogConsumption($filePath, $dateStart=null, $dateEnd=null)
	{
		if (file_exists($filePath))
		{
			$prevDatas = json_decode(file_get_contents($filePath), true);
			$keys = array_keys($prevDatas);
			$logDateStart = $keys[0];
			$logDateEnd = $keys[count($prevDatas)-1];

			if (!isset($dateStart)) $dateStart = $logDateStart;
			if (!isset($dateEnd)) $dateEnd = $logDateEnd;

			$sumArray = array();
			$c = count($prevDatas);
			for($i=0; $i<$c; $i++)
			{
				$thisDate = $keys[$i];
				$data = $prevDatas[$thisDate];
				if ( strtotime($thisDate)<=strtotime($dateEnd) and strtotime($thisDate)>=strtotime($dateStart))
				{
					foreach ($data as $name => $value)
					{
						if (!isset($sumArray[$name])) $sumArray[$name] = 0;
						$sumArray[$name] += $value;
					}
				}
			}
			foreach ($sumArray as $name => $value)
			{
				$sumArray[$name] = $value.'kWh';
			}
			return array('result'=>$sumArray);
		}
		else
		{
			return array('result'=>null, 'error'=>'Unable to open file!');
		}
	}

	public function getAllDevices() { return array('result'=>$this->_AllDevices); }

	//SET:
	public function startScene($scene)
	{
		if ( is_string($scene) ) $scene = $this->getSceneByName($scene);
		if ( isset($scene['error']) ) return $scene;

		$element = $scene['element'];
		$answer = $this->invokeOperation($element, "start");
		if (isset($answer['error']["message"]) ) return array('result'=>null, 'error'=>$answer['error']["message"]);
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

		foreach ($sensors as $sensor)
		{
			$sensorType = $this->getSensorType($sensor);

			if (in_array($sensorType, $this->_DevicesOnOff))
			{
				$operation = ($state == 0 ? 'turnOff' : 'turnOn');
				$answer = $this->invokeOperation($sensor, $operation);
				if (isset($answer['error']["message"]) ) return array('result'=>null, 'error'=>$answer['error']["message"]);
				return array('result'=>true);
			}
			if (in_array($sensorType, $this->_DevicesSend) and ($state == 1))
			{
				$operation = "send";
				$answer = $this->invokeOperation($sensor, $operation);
				if (isset($answer['error']["message"]) ) return array('result'=>null, 'error'=>$answer['error']["message"]);
				return array('result'=>true);
			}
		}
		return array('result'=>null, 'error' => 'No supported sensor for this device');
	}

	public function turnGroupOnOff($group, $state=0)
	{
		if ( is_string($group) ) $group = $this->getGroupByName($group);
		if ( isset($group['error']) ) return $group;

		$sensor = 'devolo.BinarySwitch:'.$group['id'];
		if ($state < 0) $state = 0;

		$operation = ($state == 0 ? 'turnOff' : 'turnOn');
		$answer = $this->invokeOperation($sensor, $operation);
		if (isset($answer['error']["message"]) ) return array('result'=>null, 'error'=>$answer['error']["message"]);
		return array('result'=>true);
	}

	public function setDeviceValue($device, $value)
	{
		if ( is_string($device) ) $device = $this->getDeviceByName($device);
		if ( isset($device['error']) ) return $device;

		$sensors = (isset($device['sensors']) ? json_decode($device['sensors'], true) : null);
		if ($sensors == null) return array('result'=>null, 'error' => 'No sensor found in this device');

		foreach ($sensors as $sensor)
		{
			$sensorType = $this->getSensorType($sensor);

			if (in_array($sensorType, $this->_DevicesSendValue))
			{
				$operation = 'sendValue';
				$answer = $this->invokeOperation($sensor, $operation, $value);
				if (isset($answer['error']["message"]) ) return array('result'=>null, 'error'=>$answer['error']["message"]);
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

		foreach($sensors as $sensor)
		{
			$sensorType = $this->getSensorType($sensor);
			if (in_array($sensorType, $this->_DevicesPressKey))
			{
				$operation = 'pressKey';
				$answer = $this->invokeOperation($sensor, $operation, $key);
				if (isset($answer['error']["message"]) ) return array('result'=>null, 'error'=>$answer['error']["message"]);
				return array('result'=>true);
			}
		}
		return array('result'=>null, 'error' => 'No supported sensor for this device');
	}

	public function resetSessionTimeout() //not allowed acton :-(
	{
		$jsonString = '{"jsonrpc":"2.0", "method":"FIM/invokeOperation","params":["'.$this->_uuid.'","resetSessionTimeout",[]]}';
		$answer = $this->sendCommand($jsonString);
		if (isset($answer['error']["message"]) ) return array('result'=>null, 'error'=>$answer['error']["message"]);
		return array('result'=>$answer['result']);
	}

	//GET shorcuts:
	public function getDeviceByName($name)
	{
		foreach($this->_AllDevices as $thisDevice)
		{
			if ($thisDevice['name'] == $name) return $thisDevice;
		}
		return array('result'=>null, 'error' => 'Unfound device');
	}

	public function getRuleByName($name)
	{
		if (count($this->_AllRules) == 0) $this->getRules();

		foreach($this->_AllRules as $thisRule)
		{
			if ($thisRule['name'] == $name) return $thisRule;
		}
		return array('result'=>null, 'error' => 'Unfound rule');
	}

	public function getTimerByName($name)
	{
		if (count($this->_AllTimers) == 0) $this->getTimers();

		foreach($this->_AllTimers as $thisTimer)
		{
			if ($thisTimer['name'] == $name) return $thisTimer;
		}
		return array('result'=>null, 'error' => 'Unfound timer');
	}

	public function getSceneByName($name)
	{
		if (count($this->_AllScenes) == 0) $this->getScenes();

		foreach($this->_AllScenes as $thisScene)
		{
			if ($thisScene['name'] == $name) return $thisScene;
		}
		return array('result'=>null, 'error' => 'Unfound scene');
	}

	public function getGroupByName($name)
	{
		foreach($this->_AllGroups as $thisGroup)
		{
			if ($thisGroup['name'] == $name) return $thisGroup;
		}
		return array('result'=>null, 'error' => 'Unfound group');
	}

	//internal functions==================================================
	protected function getDevices()
	{
		if (count($this->_AllZones) == 0) $this->getZones();

		//get all devices from all zones:
		$UIDSarray = array();
		foreach ($this->_AllZones as $thisZone)
		{
			$thisDevices = $thisZone["deviceUIDs"];
			foreach ($thisDevices as $thisDevice)
			{
				array_push($UIDSarray, $thisDevice);
			}
		}

		//request all infos for all devices at once:
		$jsonArray = $this->fetchItems($UIDSarray);

		//store devices:
		$devices = array();
		foreach ($jsonArray['result']["items"] as $thisDevice)
		{
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

	protected function getZones() //also get groups!
	{
		$this->_AllZones = array();
		$this->_AllGroups = array();

		$json = '{
			"jsonrpc":"2.0",
			"method":"FIM/getFunctionalItems",
			"params":[
				["devolo.Grouping"],0
				]
			}';

		$data = $this->_request('http', 'POST', $this->_localHost, '/remote/json-rpc', $json, null, null, $this->_sessionID);
		$jsonArray = json_decode($data, true);

		//get all zones:

		$zones = $jsonArray['result']["items"][0]['properties']['zones'];
		foreach ($zones as $thisZone)
		{
			$thisID = $thisZone["id"];
			$thisName = $thisZone["name"];
			$thisDevices = $thisZone["deviceUIDs"];

			$zone = array("name" => $thisName,
							"id" => $thisID,
							"deviceUIDs" => $thisDevices
							);
			array_push($this->_AllZones, $zone);
		}

		//get each group infos:
		$jsonArray = $this->fetchItems($jsonArray['result']["items"][0]['properties']['smartGroupWidgetUIDs']);

		foreach ($jsonArray['result']["items"] as $thisGroup)
		{
			$thisID = $thisGroup["UID"];
			$thisName = $thisGroup['properties']["itemName"];
			$thisOurOfSync = $thisGroup['properties']["outOfSync"];
			$thisSync = $thisGroup['properties']["synchronized"];
			$thisDevices = $thisGroup['properties']["deviceUIDs"];

			$group = array("name" => $thisName,
							"id" => $thisID,
							"outOfSync" => $thisOurOfSync,
							"synchronized" => $thisSync,
							"deviceUIDs" => $thisDevices
							);
			array_push($this->_AllGroups, $group);
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

		$data = $this->_request('http', 'POST', $this->_localHost, '/remote/json-rpc', $json, null, null, $this->_sessionID);
		$jsonArray = json_decode($data, true);

		//request datas for all scenes:
		$jsonArray = $this->fetchItems($jsonArray['result']["items"][0]['properties']['sceneUIDs']);

		foreach($jsonArray['result']["items"] as $thisScene)
		{
			$scene = array("name" => $thisScene["properties"]["itemName"],
							"id" => $thisScene["UID"],
							"element" => str_replace("Scene", "SceneControl", $thisScene["UID"])
							);
			array_push($this->_AllScenes, $scene);
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

		$data = $this->_request('http', 'POST', $this->_localHost, '/remote/json-rpc', $json, null, null, $this->_sessionID);
		$jsonArray = json_decode($data, true);

		//request datas for all rules:
		$jsonArray = $this->fetchItems($jsonArray['result']["items"][0]['properties']['scheduleUIDs']);

		foreach($jsonArray['result']["items"] as $thisTimer)
		{
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

		$data = $this->_request('http', 'POST', $this->_localHost, '/remote/json-rpc', $json, null, null, $this->_sessionID);
		$jsonArray = json_decode($data, true);

		//request datas for all rules:
		$jsonArray = $this->fetchItems($jsonArray['result']["items"][0]['properties']['serviceUIDs']);

		foreach($jsonArray['result']["items"] as $thisRule)
		{
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
	protected function _request($protocol, $method, $host, $path, $jsonString, $login, $password, $cookie) //standard function handling all get/post request with curl | return string
	{
		if (!isset($this->_curlHdl))
		{
			$this->_curlHdl = curl_init();
			curl_setopt($this->_curlHdl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($this->_curlHdl, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($this->_curlHdl, CURLOPT_USERAGENT, 'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:51.0) Gecko/20100101 Firefox/51.0');
			curl_setopt($this->_curlHdl, CURLOPT_AUTOREFERER, true);
			curl_setopt($this->_curlHdl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($this->_curlHdl, CURLOPT_HTTPHEADER, array(
														'Connection: keep-alive',
														'Content-Type: application/json'
														)
													);
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
			$auth = $login.":".$password;
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

		if ( isset($cookie) ) //auth ok and get connection cookie, can ask and send stuff to central !!
		{
			$addCookies = 'JSESSIONID='.$cookie.'; GW_ID='.$this->_gateway.'; FIM_WS_FILTER=(|(GW_ID='.$this->_gateway.')(!(GW_ID=*)))';
			curl_setopt($this->_curlHdl, CURLOPT_COOKIE, $addCookies);

			curl_setopt($this->_curlHdl, CURLOPT_HEADER, false);
			curl_setopt($this->_curlHdl, CURLINFO_HEADER_OUT, false );
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

		$data = $this->_request('http', 'POST', $this->_localHost, '/remote/json-rpc', $jsonString, null, null, $this->_sessionID);
		$jsonArray = json_decode($data, true);
		return $jsonArray;
	}

	public function sendCommand($jsonString) //directly send json to central. Only works when all required authorisations are set | return array
	{
		$data = $this->_request('http', 'POST', $this->_localHost, '/remote/json-rpc', $jsonString, null, null, $this->_sessionID);
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
	protected $_curlHdl = null;

	//user central stuff:
	protected $_login;
	protected $_password;
	protected $_localHost;
	protected $_uuid;
	protected $_gateway;
	protected $_passkey;
	public $_sessionID; //the one to get first!

	//central stuff stuff(!):
	public $_AllGroups = null;
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
		if ( !isset($this->_uuid) or !isset($this->_gateway) or !isset($this->_passkey) )
		{
			$this->initAuth();
		}
		$this->getSessionID();
		$this->getDevices();
	}

//DevoloDHC end
}

?>
