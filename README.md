#php-devoloDHC

##php 'api' giving acces to Devolo Home Control
(C) 2017, KiboOst

This php code allows you to control your Devolo Home Control devices from a php script of your.
The following devices are currently supported:

- Smart Metering Plug (get/set)
- Motion Sensor (get)
- Door/Window Contact (get)
- Wall Switch (get)
- Siren (get/set)
- Flood Sensor (get)
- Humidity Sensor (get)
- http devices (get/set)
- Room Thermostat / Radiator Thermostat: not tested, should work.
- Scenes (get/set)
- Timers (get)
- Rules (get)

Feel free to submit an issue or pull request to add more.

**This isn't an official API | USE AT YOUR OWN RISK!**

##Requirements
- PHP5+
- cURL

Can be installed on your lan, but at least first time the api will need external access to gather authortization stuff from www.mydevolo.com.
Can be installed on your external domain, but the api need access to your Devolo Home Control box. Can be done throw NAT/PAT with a dyndns. In this case, specify the url as $localIP.


##How-to

Include phpDevoloAPI.php in your script.

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

//Will return 'active' or 'inactive' string:
echo $DHC->isRuleActive("MyRule-in-DHC")."<br>";
echo $DHC->isTimerActive("MyTimer-in-DHC")."<br>";

//Check if a device is on (return 'on' or 'off' string)
echo "is on? ".$DHC->isDeviceOn("My Room wallPlug")."<br>";

// TURN DEVICE ON(1) or OFF(0) (same as on/off switch in Devolo Home Control)!!
//supported: all on/off devices and http devices
echo "TurnOn:".$DHC->turnDeviceOnOff("My Room wallPlug", 1)."<br>";

// START SCENE (same as play button in Devolo Home Control)!!
echo $DHC->startScene("We go out")."<br>";

//print all devices datas:
$AllDevices = $DHC->getAllDevices();
echo "AllDevices:<pre>".json_encode($AllDevices, JSON_PRETTY_PRINT)."</pre><br>";
?>
```

See Changes below for more stuff.

##TODO

- Waiting Devolo flush modules to integrate them (shutter, relay, dimmer). Relay, Dimmer and Shutter are in the central firmware yet, but will have to get some to fully support it (last availability is March/April 2017).

##Credits

Done with help of source code from https://github.com/kdietrich/node-devolo!


##Changes

####v2017.3.4 (2017-03-10)

- New: getDeviceStates() report all sensors states from this device as array. You can now get temperature, light, lastactivity etc from a device!
```
//report all sensors states from all device in your central (can be slow!):
$AllDevices = $DHC->getAllDevices();
foreach ($AllDevices as $device) {
	$states = $DHC->getDeviceStates($device);
	echo "<pre>states ".$device['name'].":".json_encode($states, JSON_PRETTY_PRINT)."</pre><br>";  //DEBUGGGGGGGGGGG
}

//or get one device states:
$states = $DHC->getDeviceStates("My Siren");
echo "<pre>States: My Siren:".json_encode($states, JSON_PRETTY_PRINT)."</pre><br>";
//fetch the desired state to use it in your script.
```

####v2017.3.3 (2017-03-09)
- New: getDailyDiary(number_of_events)
```
$diary = $DHC->getDailyDiary(10);
echo "<pre>diary:".json_encode($diary, JSON_PRETTY_PRINT)."</pre><br>";
```

####v2017.3.2 (2017-03-09)
- New: getAllBatteries()
You can now request all devices batteries (devices without battery won't be returned).
If you pass an int as argument, it will return devices with battery level under argument:
```
$DHC = new DevoloDHC($login, $password, $localIP, $uuid, $gateway, $passkey);
$AllBatteries = $DHC->getAllBatteries(20);
echo "AllBatteries:<pre>".json_encode($AllBatteries, JSON_PRETTY_PRINT)."</pre><br>";
```

####v2017.3.1 (2017-03-08)
- New: getDeviceBattery()
- New: getAllDevices()
- New: refreshDevice()
```
$AllDevices = $DHC->getAllDevices();
foreach ($AllDevices as $device)
{
	echo "Device:".$device['name']." : ".$device['batteryLevel']."<br>";
}
//or:
$DHC->getDeviceBattery("My wall plug");
```
Note that wire connected device report -1, and virtual devices (http) report None.

####v2017.3.0 (2017-03-08)
- Code breaking: all now is in a php class to avoid variable and php session mess with your own script.
- New: No more need to get device before getting/changing its state, send its name as parameter.

####v2017.2.0 (2017-03-06)
- Support http device
- Support Scenes
```
Run http device:
$myhttpDevice =  DHC_getDeviceByName("RequestStuff");
$result = DHC_turnDeviceOnOff($myhttpDevice, 1);

Run scene:
$myScene = DHC_getSceneByName("PlayStuff");
$result = DHC_startScene($myScene);
```
Anyway, you should know your http device url, and scenes can be triggered with scene sharing url. So these are not really usefull.

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
