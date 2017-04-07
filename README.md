<img align="right" src="/readmeAssets/devoloAPI.jpg" width="150">

# php-devoloDHC

## php API for Devolo Home Control
(C) 2017, KiboOst

This php API allows you to control your Devolo Home Control devices.
The following devices are currently supported:

- Smart Metering Plug (get/set)
- Wall Switch / Key Fob (get/set)
- Siren (get/set)
- http devices (get/set)
- Room Thermostat / Radiator Thermostat(valve) (get/set)
- Flood Sensor (get)
- Humidity Sensor (get)
- Motion Sensor (get)
- Door/Window Contact (get)
- Scenes (get/set)
- Groups (get/set)
- Timers (get/set)
- Rules (get/set)
- Messages (get/set)

Changing settings will appear in Devolo web interface / Apps daily diary with your account as usual.

Feel free to submit an issue or pull request to add more.

**This isn't an official API | USE AT YOUR OWN RISK!**

Anyway this API use exact same commands as your Devolo Home Control, which is based on ProSyst mBS SDK. When you ask bad stuff to the central, this one doesn't burn but just answer this isn't possible or allowed.

*This API is reverse-engineered, provided for research and development for interoperability.*

<img align="right" src="/readmeAssets/requirements.png" width="48">

## Requirements
- PHP v5+
- cURL (quite standard in PHP servers).
- The API require internet access (it will authenticate against Devolo servers).

<img align="right" src="/readmeAssets/howto.png" width="48">

## How-to
- Download class/phpDevoloAPI.php and put it on your server.
- If you can, allow write permission for the API folder. It will support keeping DHC user session between consecutive executions of your script (also lot faster).
- Include phpDevoloAPI.php in your script.
- Start it with your Devolo username/password.

#### Connection

```php
require($_SERVER['DOCUMENT_ROOT']."/path/to/phpDevoloAPI.php");
$DHC = new DevoloDHC($login, $password);
if (isset($DHC->error)) echo $DHC->error;
```

If you have several Central Units, or keep the demo central on your *mydevolo* page, you can choose which to connect to:

```php
//(login | password | connect or not, default true | which central, default 0)
$DHC = new DevoloDHC($login, $password, true, 1);
if (isset($DHC->error)) echo $DHC->error;
```

Let the fun begin:

```php
//get some infos on your Devolo Home Control box:
echo "__infos__<br>";
$infos = $DHC->getInfos();
echo "<pre>".json_encode($infos['result'], JSON_PRETTY_PRINT)."</pre><br>";
```
<img align="right" src="/readmeAssets/read.png" width="48">

#### READING OPERATIONS (change devices names by yours!):

```php
//get all devices in a zone:
$zone = $DHC->getDevicesByZone('living room');
echo "<pre>zone:<br>".json_encode($zone, JSON_PRETTY_PRINT)."</pre><br>";

//get rule or timer state:
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

//get all batteries level under 20% (ommit argument to have all batteries levels):
$BatLevels = $DHC->getAllBatteries(20);
echo "<pre>Batteries Levels:<br>".json_encode($BatLevels['result'], JSON_PRETTY_PRINT)."</pre><br>";

//get daily diary, last number of events:
$diary = $DHC->getDailyDiary(10);
echo "<pre>diary:<br>".json_encode($diary['result'], JSON_PRETTY_PRINT)."</pre><br>";

//get daily device stat:
//0:today, 1:yesterday, 2:day before yesterday
$stats = $DHC->getDailyStat('My MotionSensor', 0)

//get weather report:
$weather = $DHC->getWeather()
echo "<pre>weather:<br>".json_encode($weather, JSON_PRETTY_PRINT)."</pre><br>";

//Get one device states (all sensors):
$states = $DHC->getDeviceStates('My Motion Sensor');
echo "<pre>States: My Siren:".json_encode($states, JSON_PRETTY_PRINT)."</pre><br>";

//Get one sensor data for any device, like light from a Motion Sensor or energy from a Wall Plug:
$data = $DHC->getDeviceData('My Motion Sensor', 'light');
echo "MyMotionSensor luminosity: ".$data['result']['value']."<br>";
$data = $_DHC->getDeviceData('Radiator', 'temperature');
echo $data['result']['value'];

//You can first ask without data, it will return all available sensors datas for this device:
$data = $DHC->getDeviceData('My Wall Plug');
echo "<pre>MyWallPlug available states:<br>".json_encode($data, JSON_PRETTY_PRINT)."</pre><br>";

//get url from http device:
$url = $DHC->getDeviceURL('myhttp device');

//get message data:
$url = $DHC->getMessageData('MyAlert');
```
<img align="right" src="/readmeAssets/set.png" width="48">

#### CHANGING OPERATIONS (change devices names by yours!):

```php
//TURN DEVICE ON(1) or OFF(0):
//supported: all on/off devices and http devices
$DHC->turnDeviceOnOff("My Room wallPlug", 1);

//TURN GROUP ON(1) or OFF(0):
$DHC->turnGroupOnOff("My Plugs Group", 1);

//RUN HTTP DEVICE:
$DHC->turnDeviceOnOff("My http device", 1); //, 0 won't do anything of course.

//START SCENE:
$DHC->startScene("We go out");

//SEND MESSAGE:
$DHC->sendMessage("Alert");

//CHANGE THERMOSTAT/VALVE VALUE:
$targetValue = $DHC->setDeviceValue('My radiator', 21);
echo "<pre>".json_encode($targetValue['result'], JSON_PRETTY_PRINT)."</pre><br>";
$_DHC->setDeviceValue('my thermostat', 19);
//press thermostat button:
$_DHC->pressDeviceKey('my thermostat', 1);

//TURN SIREN ON: (last number is the indice of the tone in the interface list. For example, 1 is alarm and won't stop! 0 will!)
$DHC->setDeviceValue('My Devolo Siren', 5);

//PRESS REMOTE SWITCH KEY OR KEY FOB KEY:
$DHC->pressDeviceKey('MySwitch', 3);

//TURN RULE ACTIVE (1 or 0)
$DHC->turnRuleOnOff('MyRule', 1);

//TURN TIMER ACTIVE (1 or 0)
$DHC->turnTimerOnOff('MyTimer', 1);
```
<img align="right" src="/readmeAssets/consumption.png" width="48">

#### Consumption

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

#### Unsupported device

If you have unsupported device, you can call special function with this device and post the return in a new issue.

[Request for unsupported device](../../issues/)

```php
$help = $DHC->debugDevice('MyStrangeDevice');
```

<img align="right" src="/readmeAssets/changes.png" width="48">

## Changes

#### v 2.6 (2017-04-05)
- New: getAllZones() / getAllGroups() / getAllRules() / getAllTimers() / getAllScenes() / getAllMessages()
- Change: logConsumption() now sort result with recent dates up.

#### v 2.55 (2017-03-30)
- New: getWeather()
- Change: faster getInfos()

*Development should now slow down as we have it all. Keeping an eye on issues and requests of course!*

#### v 2.5 (2017-03-29)
- New: Session preservation for consecutive executions.

#### v 2.3 (2017-03-27)
- New: Messages support (getMessageData, sendMessage)

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

<img align="right" src="/readmeAssets/mit.png" width="48">

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
