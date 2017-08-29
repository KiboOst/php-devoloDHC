<?php
/*
example file for different php-devoloDHC use cases

https://github.com/KiboOst/php-devoloDHC

*/


//start API and connect to your account:
require($_SERVER['DOCUMENT_ROOT'].'/path/to/phpDevoloAPI.php');
$_DHC = new DevoloDHC($DevoloLogin, $DevoloPass);
if (isset($_DHC->error)) die($_DHC->error);


//function to toggle the state or a rule:
function toggleRule($ruleName)
{
    global $_DHC;
    $isActive = $_DHC->isRuleActive($ruleName)['result'];

    if($isActive=='inactive')
    {
        $_DHC->turnRuleOnOff($ruleName, 1);
    }
    else
    {
        $_DHC->turnRuleOnOff($ruleName, 0);
    }
}
//then simply call toggleRule('myRule')!



//Turn a light (wall plug) on:
$_DHC->turnDeviceOnOff('myLight', 1);

//Check if a device is on:
$state = $DHC->isDeviceOn('My Wall Plug')['result'];

//Start a scene:
$_DHC->startScene('We go out');



?>