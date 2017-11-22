<?php
/*
Require https://github.com/KiboOst/php-devoloDHC

Use this script as cron task to log consumptions and batteries levels everyday.

*/

//logs paths:
$consumptionsLogPath = $_SERVER['DOCUMENT_ROOT'].'/path/to/DHCconsumptions.json';
$batteriesLogPath = $_SERVER['DOCUMENT_ROOT'].'/path/to/DHCbatteries.json';

//API path:
require($_SERVER['DOCUMENT_ROOT'].'/path/to/phpDevoloAPI.php');

$_DHC = new DevoloDHC($login, $password);
if (isset($_DHC->error)) die($_DHC->error);

//log Devolo Wall Plugs Consumptions:
$_DHC->logConsumption($consumptionsLogPath);

//log Devolo batteries levels:
logBatteries($batteriesLogPath);


function logBatteries($filePath='/') //@log file path | always @return['result'] array of yesterday total consumptions, @return['error'] if can't write file
{
	global $_DHC;

    if (@file_exists($filePath))
    {
        $prevDatas = json_decode(file_get_contents($filePath), true);
    }
    else
    {
        $prevDatas = array();
    }

    //get today sums for each device:
    $today = date('d.m.Y');
    $datasArray = array();

    $BatLevels = $_DHC->getAllBatteries();

    foreach ($BatLevels['result'] as $device)
    {
        $name = $device['name'];
		$level = $device['battery_percent'];
        $datasArray[$today][$name] = $level;
    }

    ksort($datasArray[$today]);

    //add today to previously loaded datas:
    $prevDatas[$today] = $datasArray[$today];

    //set recent up:
    $keys = array_keys($prevDatas);
    usort($keys, 'sortByDate');
    $newArray = array();
    foreach ($keys as $key)
    {
        $newArray[$key] = $prevDatas[$key];
    }
    $prevDatas = $newArray;

    //write it to file:
    @$put = file_put_contents($filePath, json_encode($prevDatas, JSON_PRETTY_PRINT));
    if ($put) return array('result'=>$datasArray);
    return array('result'=>$datasArray, 'error'=>'Unable to write file!');
}

function sortByDate($a, $b)
{
    $t1 = strtotime($a);
    $t2 = strtotime($b);
    return ($t2 - $t1);
}
?>