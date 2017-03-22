# php-devoloDHC

## php API for Devolo Home Control
(C) 2017, KiboOst

This php API allows you to control your Devolo Home Control devices.
The following devices are currently supported:

- Smart Metering Plug (get/set)
- Wall Switch (get/set)
- Siren (get/set)
- http devices (get/set)
- Room Thermostat / Radiator Thermostat (get/set)
- Flood Sensor (get)
- Humidity Sensor (get)
- Motion Sensor (get)
- Door/Window Contact (get)
- Scenes (get/set)
- Groups (get/set)
- Timers (get/set)
- Rules (get/set)

Changing settings will appear in Devolo web interface / Apps daily diary with your account as usual.

Feel free to submit an issue or pull request to add more.

**This isn't an official API | USE AT YOUR OWN RISK!**
Anyway this API use exact same commands as your Devolo Home Control, which is based on ProSyst mBS SDK. When you ask bad stuff to the central, this one doesn't burn but just answer this isn't possible or allow._

## Requirements
- PHP5+
- cURL
- The API require internet access (it will authenticate against Devolo servers)


## How-to
- Download class/phpDevoloAPI.php and put it on your server.
- Include phpDevoloAPI.php in your script.
- Start it with your Devolo username/password.

```php
require($_SERVER['DOCUMENT_ROOT']."/path/to/phpDevoloAPI.php");
$DHC = new DevoloDHC($login, $password);
if (isset($DHC->error)) echo $DHC->error;
```

Let the fun begin:

```php
//get some infos on your Devolo Home Control box:
echo "__infos__<br>";
$infos = $DHC->getInfos();
echo "<pre>".json_encode($infos['result'], JSON_PRETTY_PRINT)."</pre><br>";
```

For devices, rules, scenes, timers, you can call state or action by object or directly by name.

READING OPERATIONS (change devices names by yours!):

```php
$state = $DHC->isRuleActive("MyRule");
echo "Rule state:".$state['result']."<br>";
$state = $DHC->isTimerActive("MyTimer");
echo "Timer state:".$state['result']."<br>";

//Check if a device is on (0=off, 1=on)
$state = $DHC->isDeviceOn("My Wall Plug");
echo "Device state:".$state['result']."<br>";

//check a device battery level:
$batteryLevel = $DHC->getDeviceBattery('My Motion Sensor');
echo "BatteryLevel:".$batteryLevel['result']."<br>";

//get all battery level under 20% (ommit argument to have all batteries levels):
$BatLevels = $DHC->getAllBatteries(20);
echo "<pre>Batteries Levels:<br>".json_encode($BatLevels['result'], JSON_PRETTY_PRINT)."</pre><br>";

//get daily diary, last number of events:
$diary = $DHC->getDailyDiary(10);
echo "<pre>diary:<br>".json_encode($diary['result'], JSON_PRETTY_PRINT)."</pre><br>";

//get daily device stat:
//0:today, 1:yesterday, 2:day before yesterday
$stats = getDailyStat('My MotionSensor', 0)

//Get all sensors states from all device in your central (can be slow!):
$AllDevices = $DHC->getAllDevices();
foreach ($AllDevices['result'] as $device) {
    $states = $DHC->getDeviceStates($device);
    echo "<pre>states:<br>".$device['name'].":".json_encode($states, JSON_PRETTY_PRINT)."</pre><br>";
}

//Or get one device states:
$states = $DHC->getDeviceStates('My Motion Sensor');
echo "<pre>States: My Siren:".json_encode($states, JSON_PRETTY_PRINT)."</pre><br>";

//get url from http device:
$url = $DHC->getDeviceURL('myhttp device');

//You can also ask one sensor data for any device, like luminosity from a Motion Sensor or energy from a Wall Plug:
$data = $DHC->getDeviceData('My Motion Sensor', 'light');
echo "MyMotionSensor luminosity: ".$data['result']['value']."<br>";

//You can first ask without data, it will return all available sensors datas for this device:
//will echo energy datas, currentvalue, totalvalue and sincetime
$data = $DHC->getDeviceData('My Wall Plug');
echo "<pre>MyWallPlug available states:<br>".json_encode($data, JSON_PRETTY_PRINT)."</pre><br>";
```

CHANGING OPERATIONS (change devices names by yours!):

```php
//TURN DEVICE ON(1) or OFF(0):
//supported: all on/off devices and http devices
$DHC->turnDeviceOnOff("My Room wallPlug", 1);

//RUN HTTP DEVICE:
$DHC->turnDeviceOnOff("My http device", 1); //, 0 won't do anything of course.

//START SCENE:
$DHC->startScene("We go out");

//TURN GROUP ON(1) or OFF(0):
$DHC->turnGroupOnOff("My Plugs Group", 1);

//CHANGE THERMOSTAT/VALVE VALUE:
$targetValue = $DHC->setDeviceValue('My thermostat valve', 21);
echo "<pre>".json_encode($targetValue['result'], JSON_PRETTY_PRINT)."</pre><br>";

//TURN SIREN ON: (last number is the indice of the tone in the interface list. For example, 1 is alarm and won't stop! 0 will!)
$DHC->setDeviceValue('My Devolo Siren', 5);

//PRESS REMOTE SWITCH KEY:
$DHC->pressDeviceKey('MySwitch', 3);

//TURN RULE ACTIVE (1 or 0)
$DHC->turnRuleOnOff('MyRule', 1);

//TURN TIMER ACTIVE (1 or 0)
$DHC->turnTimerOnOff('MyTimer', 1);
```

Some people would like to have more than 3days consumption log for devices like Wall Plugs.
Here are two functions to log consumptions, and read them between two dates of choice. So you can make a cron task to call this function everyday, it will log the yesterday total consumption of each Wall Plugs:

```php
$DHC->logConsumption('log.json');
```
If you don't provide a file path, or it can't write to, the api will return an error, but also provide the result (so you can write your own custom functions).

Then, to read the log and know consumption for a month, or along summer/winter etc:

```php
$stats = $DHC->getLogConsumption('log.json', '01.03.2017', '31.03.2017');
echo "<pre>".json_encode($stats, JSON_PRETTY_PRINT)."</pre><br>";
```
Of course, it needs a valid previously saved log file by the api. You can provide no dates (full log), or only one (set first as null if needed). Just respect day.month.year (php 'd.m.Y').

## TODO

- Waiting Devolo flush modules to integrate them (shutter, relay, dimmer).
Relay, Dimmer and Shutter are in the central firmware yet (v8.0.45_2016-11-17), but will have to get some to fully support it (last availability is March/April 2017).
I also highly guess the central will need a firmware update to fully support them...

## Changes

#### v 2.2 (2017-03-22)
- Minor bugfixs
- Faster, more robust starting

#### v 2.0 (2017-03-18)
- All new authentication method with only your Devolo username and password! No more passkey, localIP etc.
- Faster!
- turnRuleOnOff()
- turnTimerOnOff()

#### v 1.2 (2017-03-14)
- New: turnGroupOnOff() / Feature requested by API user.

#### v 1.1 (2017-03-13)
- New: getDailyStat()
- New: logConsumption() and getLogConsumption()
- More error handling in provided arguments

#### v 1.0 (2017-03-12)
- Changed: For convenience, all functions now return an array with datas in array['result'].
If there is an error, message is in array['error'] and result is null. So you can easily check for error first.
- Fix: $DHC->setDeviceValue('MyDevoloSiren', 1) now works (1 is the indice of the tone in the interface list).
- Fix: Lot more error handling.

#### v2017.3.5 (2017-03-11)
- New: getDeviceData() directly get a sensor data from a device, like temperature from a Motion Sensor. Each call to this function get latest datas from the device.

#### v2017.3.4 (2017-03-10)
- New: getDeviceStates() report all sensors states from this device as array. You can now get temperature, light, last activity etc from a device like Motion Sensor, etc. Each call to this function get latest datas from the device.

#### v2017.3.3 (2017-03-09)
- New: getDailyDiary(number_of_events)

#### v2017.3.2 (2017-03-09)
- New: getAllBatteries()
You can now request all devices batteries (devices without battery won't be returned).
If you pass an int as argument, it will return devices with battery level under argument:

#### v2017.3.1 (2017-03-08)
- New: getDeviceBattery() Note that wire connected device report -1, and virtual devices (http) report None.
- New: getAllDevices()

#### v2017.3.0 (2017-03-08)
- Code breaking: all now is in a php class to avoid variable and php session mess with your own script.
- New: No more need to get device/scene before getting/changing its state, send its name as parameter.

#### v2017.2.0 (2017-03-06)
- Support http device
- Support Scenes

#### v2017.1.0 (2017-03-04)
- First public version.

## License

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
