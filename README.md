#php-devoloDHC

##php 'api' giving acces to Devolo Home Control
(C) 2017, KiboOst

This php code allows you to control your Devolo Home Control devices from a php script of your.
The following devices are currently supported:

- Smart Metering Plug
- Motion Sensor
- Door Sensor / Window Contact

Feel free to submit an issue or pull request to add more.

##Requirements
- PHP5+
- Curl
Can be installed on you lan, but at least first time the api will need external access to gather authortization stuff from www.mydevolo.com.
Can be installed on your external domain, but the api need access to your Devolo Home Control box. Can be done throw NAT/PAT with a dyndns. Specify the url as $DHCcentralHost instead of IP.


##How-to

1. Put config.php and phpDevoloAPI.php in a folder.
2. Give the folder write permission.
3. Set login, password and DHCcentralHost in config.php. These are your devolo web login/password and local Devolo central IP (lan) or url (wan) to access it.
The api will first request some authorization data from www.mydevolo.com then write them back into config.php as they won't change till you don't change your login/password.


- Include phpDevoloAPI.php in your script
- Start it with DHC_init();
- check it works:
```
<?php
require($_SERVER['DOCUMENT_ROOT']."/scripts/devoloAPI/phpDevoloAPI.php");
DHC_init();
$infos = DHC_getInfos();
$infos = json_encode($infos, JSON_PRETTY_PRINT);
echo "<pre>".$infos."</pre>";
?>
```

Check state of a device:
```
$mydevice =  DHC_getDeviceByName("MyWallPlug");
$isOn = DHC_isDeviceOn($mydevice);
echo "isOn ".$mydevice['name'].": ".$isOn."<br>";
```

Turn a device on:
```
$mydevice =  DHC_getDeviceByName("MyWallPlug");
DHC_turnDeviceOnOff($mydevice, 1);
```

Check state of a Rule:
```
$myrule = DHC_getRuleByName("MyDevoloRule");
$isEnabled = DHC_isRuleOn($myrule);
echo "isEnabled ".$myrule['name'].": ".$isEnabled."<br>";
```
##TODO
- Trying to change state of schedules and rules (would allow holliday mode for example).
- Waiting Devolo flush modules to integrate them (shutter, relay, dimmer).

##Credits

Done with invaluable help of source code from https://github.com/kdietrich/node-devolo!


##Changes

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
