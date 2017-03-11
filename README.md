#php-devoloDHC

##php API for Devolo Home Control
(C) 2017, KiboOst

This php API allows you to control your Devolo Home Control devices.
The following devices are currently supported:

- Smart Metering Plug (get/set)
- Motion Sensor (get)
- Door/Window Contact (get)
- Wall Switch (get)
- Siren (get/set)
- Flood Sensor (get)
- Humidity Sensor (get)
- http devices (get/set)
- Room Thermostat / Radiator Thermostat (get/set)
- Scenes (get/set)
- Timers (get)
- Rules (get)

Changing settings will appear in Devolo web interface / Apps daily diary with your account as usual.

Feel free to submit an issue or pull request to add more.

**This isn't an official API | USE AT YOUR OWN RISK!**
_Anyway this API use exact same commands as your Devolo Home Control, which is based on ProSyst mBS SDK. When you ask bad stuff to the central, this one doesn't burn but just answer this isn't possible or allow._

##Requirements
- PHP5+
- cURL

You can use this API on your lan (easyphp, Synology DSM, etc), but at least first time the api will need external access to gather authortization stuff from www.mydevolo.com.
Can be installed on your external domain, but the api need access to your Devolo Home Control box. Can be done throw NAT/PAT with a dyndns. In this case, specify the url as $localIP.


##How-to
- Get class/phpDevoloAPI.php and put it on your server
- Include phpDevoloAPI.php in your script.
- That's all!

First time execution:
The api will first request some authorization data from www.mydevolo.com.
These data won't change for same user, so you can get them and directly pass them next time to get faster connection.
Note them in your script or in a config file you include before creating DevoloDHC().

```
<?php
require($_SERVER['DOCUMENT_ROOT']."/path/to/phpDevoloAPI.php");
$DHC = new DevoloDHC($login, $password, $localIP);
$auth = $DHC->getAuth();
echo "<pre>".json_encode($auth, JSON_PRETTY_PRINT)."</pre><br>";
?>
```

So note return values and next time, call $DHC = new DevoloDHC($login, $password, $localIP, $uuid, $gateway, $passkey);

```
<?php
require($_SERVER['DOCUMENT_ROOT']."/path/to/phpDevoloAPI.php");
$DHC = new DevoloDHC($login, $password, $localIP, $uuid, $gateway, $passkey);
?>
```

Let the fun begin:

```
<?php
//get some infos on your Devolo Home Control box:
echo "__infos__<br>";
$infos = $DHC->getInfos();
echo "<pre>".json_encode($infos, JSON_PRETTY_PRINT)."</pre><br>";

//for devices, rules, scenes, timers, you can call state or action by object or directly by name

//READING OPERATIONS:

//Will return 'active' or 'inactive' string:
echo $DHC->isRuleActive("MyRule-in-DHC")."<br>";
echo $DHC->isTimerActive("MyTimer-in-DHC")."<br>";

//Check if a device is on (return 'on' or 'off' string)
echo "is on? ".$DHC->isDeviceOn("My Room wallPlug")."<br>";

//check a device battery level:
$batteryLevel = $DHC->getDeviceBattery('my wall switch 1');
echo "batteryLevel:".$batteryLevel."<br>";

//or:
$AllDevices = $DHC->getAllDevices();
foreach ($AllDevices as $device)
{
	echo "Device:".$device['name']." : ".$device['batteryLevel']."<br>";
}

//get all battery level under 20% (ommit argument to have all batteries levels):
$AllBatteries = $DHC->getAllBatteries(20);
echo "AllBatteries:<pre>".json_encode($AllBatteries, JSON_PRETTY_PRINT)."</pre><br>";

//get daily diary, last number of events:
$diary = $DHC->getDailyDiary(10);
echo "<pre>diary:".json_encode($diary, JSON_PRETTY_PRINT)."</pre><br>";

//Get all sensors states from all device in your central (can be slow!):
$AllDevices = $DHC->getAllDevices();
foreach ($AllDevices as $device) {
	$states = $DHC->getDeviceStates($device);
	echo "<pre>states ".$device['name'].":".json_encode($states, JSON_PRETTY_PRINT)."</pre><br>";  //DEBUGGGGGGGGGGG
}

//Or get one device states:
$states = $DHC->getDeviceStates("My Siren");
echo "<pre>States: My Siren:".json_encode($states, JSON_PRETTY_PRINT)."</pre><br>";
//->fetch the desired state to use it in your script.

//get url from http device: (return url string or {"error": "This is not an http virtual device"}
$url= $DHC->getDeviceURL('myhttp device');

//You can also ask one sensor data for any device, like luminosity from a Motion Sensor or energy from a Wall Plug:
$data = $DHC->getDeviceData('MyMotionSensor', 'light');
echo $data['value']."<br>";
//You can first ask without data, it will return all available sensors datas for this device:
$data = $DHC->getDeviceData('MyWallPlug');
echo "<pre>".json_encode($data, JSON_PRETTY_PRINT)."</pre><br>"; //will echo energy datas, currentvalue, totalvalue and sincetime

//CHANGING OPERATIONS:

// TURN DEVICE ON(1) or OFF(0):
//supported: all on/off devices and http devices
echo "TurnOn:".$DHC->turnDeviceOnOff("My Room wallPlug", 1)."<br>";

//RUN HTTP DEVICE:
$result = $DHC->turnDeviceOnOff("My http device", 1); //, 0 won't do anything of course. 

// START SCENE:
echo $DHC->startScene("We go out")."<br>";

//CHANGE THERMOSTAT/VALVE VALUE:
$targetValue = $DHC->setDeviceValue('My thermostat valve', 21);
echo "<pre>".json_encode($targetValue, JSON_PRETTY_PRINT)."</pre><br>";

?>
```

##TODO

- Waiting Devolo flush modules to integrate them (shutter, relay, dimmer). Relay, Dimmer and Shutter are in the central firmware yet, but will have to get some to fully support it (last availability is March/April 2017).

##Credits

Done with help of source code from https://github.com/kdietrich/node-devolo!

##Changes

####v2017.3.5 (2017-03-11)
- New: getDeviceData() directly get a sensor data from a device, like temperature from a Motion Sensor. Each call to this function get latest datas from the device.

####v2017.3.4 (2017-03-10)
- New: getDeviceStates() report all sensors states from this device as array. You can now get temperature, light, last activity etc from a device like Motion Sensor, etc. Each call to this function get latest datas from the device.

####v2017.3.3 (2017-03-09)
- New: getDailyDiary(number_of_events)

####v2017.3.2 (2017-03-09)
- New: getAllBatteries()
You can now request all devices batteries (devices without battery won't be returned).
If you pass an int as argument, it will return devices with battery level under argument:

####v2017.3.1 (2017-03-08)
- New: getDeviceBattery() Note that wire connected device report -1, and virtual devices (http) report None.
- New: getAllDevices()

####v2017.3.0 (2017-03-08)
- Code breaking: all now is in a php class to avoid variable and php session mess with your own script.
- New: No more need to get device/scene before getting/changing its state, send its name as parameter.

####v2017.2.0 (2017-03-06)
- Support http device
- Support Scenes

####v2017.1.0 (2017-03-04)
- First public version.

##License

The MIT License (MIT)

Copyright (c) 2017 KiboOst

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
