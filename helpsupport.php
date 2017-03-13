<?php
/*

This script is intended to gather devices and sensors datas to help supporting new/more devices in phpDevoloAPI.

- Set your login/password/localIP (or dyndns)
- Run it
- Create a new issue: https://github.com/KiboOst/php-devoloDHC/issues/new
- Post entire report in the issue, describing the device causing issue if possible.

Thanks for your support!

*/

error_reporting(E_ALL);
ini_set('display_errors', true);


//get your login/pass/localIP (you can provide uuid/gateway/passkey as usual)
require($_SERVER['DOCUMENT_ROOT']."/path/to/config.php");
require($_SERVER['DOCUMENT_ROOT']."/path/to/phpDevoloAPI.php");
$DHC = new DevoloDHC($login, $password, $localIP);

//print all devices datas:
$AllDevices = $DHC->getAllDevices();
echo "AllDevices:<pre>".json_encode($AllDevices, JSON_PRETTY_PRINT)."</pre><br>";

//report all sensors states from all device in your central (can be slow!):
foreach ($AllDevices as $device) {
    $DHC->getDeviceStates($device, true); //true argument will tell to display entire report from sensors
}

?>
