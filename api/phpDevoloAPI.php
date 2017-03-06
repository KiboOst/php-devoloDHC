<?php
/*

https://github.com/KiboOst/php-devoloDHC

*/
require("config.php");

session_start();
session_name("DHC");
$_SESSION['DHCversion'] = "2017.2.0";
$_SESSION['DHCdebug'] = 0;

$_SESSION['DHCcentralHost'] = $DHCcentralHost;
$_SESSION['DHCHost'] = 'www.mydevolo.com';
$_SESSION['DHCVersion'] = '/v1';

$_SESSION['DHCuuid'] = null;
$_SESSION['DHCgateway'] = null;
$_SESSION['DHCpasskey'] = null;
$_SESSION['DHCsessionID'] = null;

$_SESSION['DHCzones'] = array(); // array(id / name / array(deviceUIDs))
$_SESSION['DHCdevices'] = array();

$_SESSION['DHCrules'] = array();
$_SESSION['DHCtimers'] = array();
$_SESSION['DHCscenes'] = array();

$_SESSION['DHCDevicesOnOff'] = ["BinarySwitch", "BinarySensor", "SirenBinarySensor"]; //supported devices type for on/off
$_SESSION['DHCDevicesSend'] = ["HttpRequest"]; //supported devices type for send

//user functions======================================================
function DHC_turnDeviceOnOff($device, $state=0)
{
	$sensors = (isset($device['sensors']) ? $device['sensors'] : null);
	if ($sensors == null) return false;

	$sensors = json_decode($sensors, true);

	for($i=0; $i<count($sensors); $i++)
	{
		$sensor = $sensors[$i];
		$sensorType = DHCi_getSensorType($sensor);

		if (in_array($sensorType, $_SESSION['DHCDevicesOnOff']))
		{
			$operation = ($state == 0 ? 'turnOff' : 'turnOn');
			$result = DHC_invokeOperation($sensor, $operation);
			return $result;
		}
		if (in_array($sensorType, $_SESSION['DHCDevicesSend']) and ($state == 1))
		{
			$operation = "send";
			$result = DHC_invokeOperation($sensor, $operation);
			return $result;
		}
	}
	return false;
}

function DHC_isDeviceOn($device) //return true of false if find a sensor state in device
{
	$sensors = (isset($device['sensors']) ? $device['sensors'] : null);
	if ($sensors == null) return false;

	$sensors = json_decode($sensors, true);

	for($i=0; $i<count($sensors); $i++)
	{
		$sensor = $sensors[$i];
		$sensorType = DHCi_getSensorType($sensor);

		if (in_array($sensorType, $_SESSION['DHCDevicesOnOff']))
		{
			$param = DHCi_getStatebyType($sensorType);
			if ($param != null)
			{
				$sensorDatas = DHC_fetchItems(array($sensor));
				$state = $sensorDatas["result"]["items"][0]["properties"][$param];
				$isOn = ($state == "1" ? true : false);
				return $isOn;
			}
		}
	}
	return false;
}

function DHC_getDeviceByName($name) //return device array (name, uids, elements)
{
	for($i=0; $i<count($_SESSION['DHCdevices']); $i++)
	{
		$thisDevice = $_SESSION['DHCdevices'][$i];
		if ($thisDevice['name'] == $name) return $thisDevice;
	}
	return false;
}

function DHC_turnOnOff($sensor, $state=0)
{
	$operation = ($state == 0 ? 'turnOff' : 'turnOn');
	$result = DHC_invokeOperation($sensor, $operation);
}

function DHC_changeValue($sensor, $value=0)
{
	echo "Not implemented yet!<br>";
}

function DHC_isRuleOn($rule)
{
	$jsonArray = DHC_fetchItems(array($rule["element"]));
	return $jsonArray["result"]["items"][0]["properties"]["enabled"];
}
function DHC_isTimerOn($timer)
{
	return DHC_isRuleOn($timer);
}

function DHC_startScene($scene)
{
	$element = $scene['element'];
	$result = DHC_invokeOperation($element, "start");
	return $result;
}

function DHC_getRuleByName($name)
{
	if (count($_SESSION['DHCrules']) == 0) DHC_getRules();

	for($i=0; $i<count($_SESSION['DHCrules']); $i++)
	{
		$thisRule = $_SESSION['DHCrules'][$i];
		if ($thisRule['name'] == $name) return $thisRule;
	}
	return false;
}

function DHC_getTimerByName($name)
{
	if (count($_SESSION['DHCtimers']) == 0) DHC_getTimers();

	for($i=0; $i<count($_SESSION['DHCtimers']); $i++)
	{
		$thisTimer = $_SESSION['DHCtimers'][$i];
		if ($thisTimer['name'] == $name) return $thisTimer;
	}
	return false;
}

function DHC_getSceneByName($name)
{
	if (count($_SESSION['DHCscenes']) == 0) DHC_getScenes();

	for($i=0; $i<count($_SESSION['DHCscenes']); $i++)
	{
		$thisScene = $_SESSION['DHCscenes'][$i];
		if ($thisScene['name'] == $name) return $thisScene;
	}
	return false;
}

function DHC_getStates($device) //return array of sensor type and state
{
	$sensors = (isset($device['sensors']) ? $device['sensors'] : null);
	if ($sensors == null) return false;

	//fetch sensors:
	$sensors = json_decode($sensors, true);
	$arrayStates = array();

	for($i=0; $i<count($sensors); $i++)
	{
		$sensor = $sensors[$i];
		$sensorType = DHCi_getSensorType($sensor);
		$param = DHCi_getStatebyType($sensorType);
		if ($param != null)
		{
			$sensorDatas = DHC_fetchItems(array($sensor));
			$state = $sensorDatas["result"]["items"][0]["properties"][$param];

			$data = array('type' => $sensorType,
							'state' => $state);
			array_push($arrayStates, $data);
		}
		else //debug!!!
		{
			$sensorDatas = DHC_fetchItems(array($sensor));
			echo "UNKNOWN PARAM!!!!<br>";
			echo "sensor state: ".json_encode($sensorDatas)."<br>";
		}
	}
	return $arrayStates;
}





//standard functions==================================================
function DHCi_getSensorType($sensor)
{
	$sensorType = explode("devolo.", $sensor);
	if (count($sensorType) == 0) return null;
	$sensorType = explode(":", $sensorType[1]);
	$sensorType = $sensorType[0];
	return $sensorType;
}

function DHCi_getStatebyType($sensorType) //ex: devolo.BinarySensor:hdm:ZWave:D8F7DDE2/10
{
	if ($sensorType == 'BinarySwitch') return 'state'; //on/off Devolo wall plug
	if ($sensorType == 'Meter') return 'currentValue'; //watts Devolo wall plug

	if ($sensorType == 'BinarySensor') return 'state'; //alarm on/off Devolo MotionSensor or Devolo  Door/Win sensor
	if ($sensorType == 'MultiLevelSensor') return 'value'; //Fibaro smoke sensor
	if ($sensorType == 'HttpRequest') return null;

	if ($sensorType == 'SirenMultiLevelSwitch') return 'value'; //Devolo Siren ??
	if ($sensorType == 'SirenBinarySensor') return 'state'; //Devolo Siren alarm on/off
	if ($sensorType == 'SirenMultiLevelSensor') return 'value'; //Devolo Siren ??

	return null;
}

//root functions======================================================
function DHC_getScenes() //ok
{
	$json = '{
		"jsonrpc":"2.0",
		"method":"FIM/getFunctionalItems",
		"params":[
			["devolo.Scene"],0
			]
		}';
	$json = json_decode($json);
	$data = DHCrequest('http', 'POST', $_SESSION['DHCcentralHost'], '/remote/json-rpc', $json, null, null, $_SESSION['DHCsessionID']);
	$jsonArray = json_decode($data, true);

	$scenesArray = array();
	$scenesNum = count($jsonArray['result']["items"][0]['properties']['sceneUIDs']);
	for($i=0; $i<$scenesNum; $i++)
	{
		$thisScene = $jsonArray['result']["items"][0]['properties']['sceneUIDs'][$i];
		array_push($scenesArray, $thisScene);
	}

	//request datas for all rules:
	$jsonArray = DHC_fetchItems($scenesArray);

	$scenesNum = count($jsonArray['result']["items"]);
	for($i=0; $i<$scenesNum; $i++)
	{
		$thisScene = $jsonArray['result']["items"][$i];
		$rule = array("name" => $thisScene["properties"]["itemName"],
						"id" => $thisScene["UID"],
						"element" => str_replace("Scene", "SceneControl", $thisScene["UID"])
						);
		array_push($_SESSION['DHCscenes'], $rule);
	}
}

function DHC_getTimers() //ok
{
	$json = '{
		"jsonrpc":"2.0",
		"method":"FIM/getFunctionalItems",
		"params":[
			["devolo.Schedules"],0
			]
		}';
	$json = json_decode($json);
	$data = DHCrequest('http', 'POST', $_SESSION['DHCcentralHost'], '/remote/json-rpc', $json, null, null, $_SESSION['DHCsessionID']);
	$jsonArray = json_decode($data, true);

	$timersArray = array();
	$timersNum = count($jsonArray['result']["items"][0]['properties']['scheduleUIDs']);
	for($i=0; $i<$timersNum; $i++)
	{
		$thisTimer = $jsonArray['result']["items"][0]['properties']['scheduleUIDs'][$i];
		array_push($timersArray, $thisTimer);
	}

	//request datas for all rules:
	$jsonArray = DHC_fetchItems($timersArray);

	$timersNum = count($jsonArray['result']["items"]);
	for($i=0; $i<$timersNum; $i++)
	{
		$thisTimer = $jsonArray['result']["items"][$i];
		$rule = array("name" => $thisTimer["properties"]["itemName"],
						"id" => $thisTimer["UID"],
						"element" => str_replace("Schedule", "ScheduleControl", $thisTimer["UID"])
						);
		array_push($_SESSION['DHCtimers'], $rule);
	}
}

function DHC_getRules() //ok
{
	$json = '{
		"jsonrpc":"2.0",
		"method":"FIM/getFunctionalItems",
		"params":[
			["devolo.Services"],0
			]
		}';
	$json = json_decode($json);
	$data = DHCrequest('http', 'POST', $_SESSION['DHCcentralHost'], '/remote/json-rpc', $json, null, null, $_SESSION['DHCsessionID']);
	$jsonArray = json_decode($data, true);

	$rulesArray = array();
	$rulesNum = count($jsonArray['result']["items"][0]['properties']['serviceUIDs']);
	for($i=0; $i<$rulesNum; $i++)
	{
		$thisRule = $jsonArray['result']["items"][0]['properties']['serviceUIDs'][$i];
		array_push($rulesArray, $thisRule);
	}

	//request datas for all rules:
	$jsonArray = DHC_fetchItems($rulesArray);

	$rulesNum = count($jsonArray['result']["items"]);
	for($i=0; $i<$rulesNum; $i++)
	{
		$thisRule = $jsonArray['result']["items"][$i];
		$rule = array("name" => $thisRule["properties"]["itemName"],
						"id" => $thisRule["UID"],
						"element" => str_replace("Service", "ServiceControl", $thisRule["UID"])
						);
		array_push($_SESSION['DHCrules'], $rule);
	}
}

function DHC_getZones() //ok!
{
	$json = '{
		"jsonrpc":"2.0",
		"method":"FIM/getFunctionalItems",
		"params":[
			["devolo.Grouping"],0
			]
		}';
	$json = json_decode($json);
	$data = DHCrequest('http', 'POST', $_SESSION['DHCcentralHost'], '/remote/json-rpc', $json, null, null, $_SESSION['DHCsessionID']);

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
		array_push($_SESSION['DHCzones'], $zone);
	}
}

function DHC_getAllDevices() //ok
{
	if (count($_SESSION['DHCzones']) == 0) DHC_getZones();

	//get all devices from all zones:
	$UIDSarray = array();
	for($i=0; $i<count($_SESSION['DHCzones']); $i++)
	{
		$thisDevices = $_SESSION['DHCzones'][$i]["deviceUIDs"];
		for($j=0; $j<count($thisDevices); $j++)
		{
			array_push($UIDSarray, $thisDevices[$j]);
		}
	}

	//request all infos for all devices at once:
	$jsonArray = DHC_fetchItems($UIDSarray);

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
						);

		array_push($devices, $device);
	}
	$_SESSION['DHCdevices'] = $devices;
}

//calling functions===================================================
function DHC_sendCommand($jsonString) //not actually used, but works...
{
	$json = json_decode($jsonString);
	$data = DHCrequest('http', 'POST', $_SESSION['DHCcentralHost'], '/remote/json-rpc', $json, null, null, $_SESSION['DHCsessionID']);
	$jsonArray = json_decode($data, true);

	if ($_SESSION['DHCdebug'] >= 4)  echo "DHC_sendCommand $data:".$data."<br>";
	return $jsonArray;
}

function DHC_fetchItems($UIDSarray) //get infos from central for array of device, sensor, timer etc
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
	$data = DHCrequest('http', 'POST', $_SESSION['DHCcentralHost'], '/remote/json-rpc', $json, null, null, $_SESSION['DHCsessionID']);
	$jsonArray = json_decode($data, true);

	if ($_SESSION['DHCdebug'] >= 4)  echo "DHC_fetchItems datas:".$data."<br>";
	return $jsonArray;
}

function DHC_invokeOperation($sensor, $operation) //sensor string, authorized operation string !!
{
	$jsonString = '{
		"jsonrpc":"2.0",
		"method":"FIM/invokeOperation",
		"params":[
			"'.$sensor.'","'.$operation.'",[]]}';

	$json = json_decode($jsonString);
	$data = DHCrequest('http', 'POST', $_SESSION['DHCcentralHost'], '/remote/json-rpc', $json, null, null, $_SESSION['DHCsessionID']);
	if ($_SESSION['DHCdebug'] >= 4) echo "DHC_invokeOperation $data:".$data."<br>";

	$jsonArray = json_decode($data, true);
	return $jsonArray;
}

function DHCrequest($protocol, $method, $host, $path, $json, $login, $password, $cookie) //standard function handling all get/post request with curl
{
	if ($_SESSION['DHCdebug'] >= 1) echo "<br>";

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
		if ($_SESSION['DHCdebug'] >= 2) echo "DHCrequest with login/password: ".$url."<br>";
		$auth = $login.":".$password;
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_USERPWD, $auth);
	}

	if ($method == 'POST')
		{
			$data_string = json_encode($json);
			if ($_SESSION['DHCdebug'] >= 3) echo 'data_string: '.$data_string."<br>";
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
		if ($_SESSION['DHCdebug'] >= 2) echo "DHCrequest with cookie ".$url." | ".$addCookies."<br>";
		curl_setopt($curl, CURLOPT_COOKIE, $addCookies);

		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLINFO_HEADER_OUT, false );
	}

	$response = curl_exec($curl);
	$header   = curl_getinfo($curl);
	$err      = curl_errno($curl);
	$errmsg   = curl_error($curl);

	curl_close($curl);

	if ($_SESSION['DHCdebug'] >= 3) echo "response:".$response."<br>";

	return $response;
}

function DHC_getInfos() //return infos from this api and the Devolo central
{
	global $login, $password;
	$path = $_SESSION['DHCVersion'].'/users/'.$_SESSION['DHCuuid'].'/hc/gateways/'.$_SESSION['DHCgateway'];
	$data = DHCrequest('https', 'GET', $_SESSION['DHCHost'], $path, null, $login, $password, null);

	$data = json_decode($data, true);

	$DHC = array(
			'phpAPI version' => $_SESSION['DHCversion'],
			'uuid' => $_SESSION['DHCuuid'],
			'gateway' => $_SESSION['DHCgateway'],
			'name' => $data["name"],
			'role' => $data["role"],
			'status' => $data["status"],
			'state' => $data["state"],
			'type' => $data["type"],
			'firmwareVersion' => $data["firmwareVersion"],
			'externalAccess' => $data["externalAccess"]
			);

	return $DHC;
}

//functions authorization=============================================
function DHC_init() //call me first !
{
	global $login, $password, $DHCcentralHost, $uuid, $gateway, $localPasskey;

	//get sessionID on local Devolo Home Control unit:
	if ( isset($uuid) and isset($gateway) and isset($localPasskey) )
	{
		if ($_SESSION['DHCdebug'] >= 2) echo "DHC_getSessionID()"."<br>";
		$_SESSION['DHCuuid'] = $uuid;
		$_SESSION['DHCgateway'] = $gateway;
		$_SESSION['DHCpasskey'] = $localPasskey;

		DHC_getSessionID();
	}
	else
	{
		if ($_SESSION['DHCdebug'] >= 2) echo "DHC_initAuth()"."<br>";
		DHC_initAuth();

		if ($_SESSION['DHCdebug'] >= 2) echo "DHC_getSessionID()"."<br>";
		DHC_getSessionID();
	}

	DHC_getAllDevices();
	return true;
}

function DHC_initAuth() //get uuid, gateway and passkey from www.devolo.com for authorization
{
	global $login, $password;

	//get uuid:
	$data = DHCrequest('https', 'GET', $_SESSION['DHCHost'], $_SESSION['DHCVersion'].'/users/uuid', null, $login, $password, null);
	$data = json_decode($data, true);
	$_SESSION['DHCuuid'] = $data["uuid"];
	if ($_SESSION['DHCdebug'] >= 1) echo "uuid:".$_SESSION['DHCuuid']."<br>";

	//get gateway:
	$path = $_SESSION['DHCVersion'].'/users/'.$_SESSION['DHCuuid'].'/hc/gateways';
	$data = DHCrequest('https', 'GET', $_SESSION['DHCHost'], $path, null, $login, $password, null);
	$data = json_decode($data, true);
	$var = explode( "/gateways/", $data["items"][0]["href"] );
	$_SESSION['DHCgateway'] = $var[1];
	if ($_SESSION['DHCdebug'] >= 1) echo "gateway:".$_SESSION['DHCgateway']."<br>";

	//get localPasskey:
	$path = $_SESSION['DHCVersion'].'/users/'.$_SESSION['DHCuuid'].'/hc/gateways/'.$_SESSION['DHCgateway'];
	$data = DHCrequest('https', 'GET', $_SESSION['DHCHost'], $path, null, $login, $password, null);
	$data = json_decode($data, true);
	$_SESSION['DHCpasskey'] = $data["localPasskey"];
	if ($_SESSION['DHCdebug'] >= 1) echo "localPasskey:".$_SESSION['DHCpasskey']."<br>";

	//write them back in config.php:
	$content = '<?php
$login = "'.$login.'";
$password = "'.$password.'";
$DHCcentralHost = "'.$_SESSION['DHCcentralHost'].'";

$uuid = "'.$_SESSION['DHCuuid'].'";
$gateway = "'.$_SESSION['DHCgateway'].'";
$localPasskey = "'.$_SESSION['DHCpasskey'].'";
?>';
	$result = file_put_contents(__DIR__."/config.php", $content);
	if ($result == false) echo "Couldn't Write config file, check path and permissions"."<br>";
}

function DHC_getSessionID() //get and set cookie for later authorized requests
{
	$_SESSION['DHCsessionID'] = null;

	//get token:
	$data = DHCrequest('http', 'GET', $_SESSION['DHCcentralHost'], '/dhlp/portal/light', null, $_SESSION['DHCuuid'] , $_SESSION['DHCpasskey'], null);
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
	if ($_SESSION['DHCdebug'] >= 1) echo "token:".$token."<br>";


	$path = '/dhlp/portal/light/?token='.$token;
	$data = DHCrequest('http', 'GET', $_SESSION['DHCcentralHost'], $path, null, $_SESSION['DHCuuid'] , $_SESSION['DHCpasskey'], null);
	$var = explode("JSESSIONID=", $data);
	if(count($var)>1)
	{
		$var = explode("; ", $var[1]);
		$_SESSION['DHCsessionID'] = $var[0];
	}
	else
	{
		die("Couldn't find sessionID from response request.");
	}
	if ($_SESSION['DHCdebug'] >= 1) echo "sessionID:".$_SESSION['DHCsessionID']."<br>";
}

?>
