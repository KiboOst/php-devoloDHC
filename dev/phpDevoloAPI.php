<?php

//https://github.com/KiboOst/php-devoloDHC

/*
v2.70: Support of Philips Hue
$_DHC->getHueHSB('myHue')
$_DHC->getHueRGB('myHue')
$_DHC->setHueRGB('myHue', array(128, 128, 128))
$_DHC->turnDeviceOnOff('myHue', 1)
*/

class DevoloDHC{

    public $_version = '2.7dev';
    //user functions======================================================
    public function getInfos() //return infos from this api, Devolo user, and Devolo central
    {

        if (!isset($this->_userInfos))
        {
            //get uuid:
            $jsonString = '{"jsonrpc":"2.0", "method":"FIM/getFunctionalItemUIDs","params":["(objectClass=com.devolo.fi.page.Dashboard)"]}';
            $answer = $this->sendCommand($jsonString);
            $uuid = $answer['result'][0];
            $uuid = explode('devolo.Dashboard.', $uuid)[1];
            $this->_uuid = $uuid;

            //get user infos:
            $jsonString = '{"jsonrpc":"2.0", "method":"FIM/getFunctionalItems","params":[["devolo.UserPrefs.'.$this->_uuid.'"],0]}';
            $answer = $this->sendCommand($jsonString);
            $this->_userInfos = $answer['result']['items'][0]['properties'];
        }

        if (!isset($this->_centralInfos))
        {
            //get portal manager token
            $jsonString = '{"jsonrpc":"2.0", "method":"FIM/getFunctionalItemUIDs","params":["(objectClass=com.devolo.fi.gw.PortalManager)"]}';
            $answer = $this->sendCommand($jsonString);
            if ( isset($answer['result'][0]) )
            {
                $var = $answer['result'][0];
                $this->_token = str_replace('devolo.mprm.gw.PortalManager.', '', $var);
            }
            else return array('error'=>'Could not find info token.');

            //get central infos:
            $jsonString = '{"jsonrpc":"2.0", "method":"FIM/getFunctionalItems","params":[["devolo.mprm.gw.PortalManager.'.$this->_token.'"],0]}';
            $answer = $this->sendCommand($jsonString);

            $centralInfos = 'None';
            if ( isset($answer['result']['items'][0]['properties']) )
            {
                $this->_centralInfos = $answer['result']['items'][0]['properties'];
                $this->_gateway = $this->_centralInfos['gateway'];
            }
        }

        $infos = array(
                'phpAPI version' => $this->_version,
                'user' => $this->_userInfos,
                'central' => $this->_centralInfos
                );
        return array('result'=>$infos);
    }

    //IS:
    public function isRuleActive($rule)
    {
        if ( is_string($rule) ) $rule = $this->getRuleByName($rule);
        if ( isset($rule['error']) ) return $rule;

        $answer = $this->fetchItems(array($rule['element']));
        if (isset($answer['error']['message']) ) return array('result'=>null, 'error'=>$answer['error']['message']);
        $state = $answer['result']['items'][0]['properties']['enabled'];
        $state = ($state > 0 ? 'active' : 'inactive');
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

            if (in_array($sensorType, $this->_SensorsOnOff))
            {
                $answer = $this->fetchItems(array($sensor));
                if (isset($answer['error']['message']) ) return array('result'=>null, 'error'=>$answer['error']['message']);
                if ( isset($answer['result']['items'][0]['properties']['state']) )
                {
                    $state = $answer['result']['items'][0]['properties']['state'];
                    $isOn = ($state > 0 ? 'on' : 'off');
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
                if (isset($answer['error']['message']) ) return array('result'=>null, 'error'=>$answer['error']['message']);
                if ($DebugReport == true) echo '<br><pre>'.json_encode($answer, JSON_PRETTY_PRINT).'</pre><br>';
                $jsonSensor = array('sensorType' => $sensorType);
                foreach ($param as $key)
                {
                    $value = $answer['result']['items'][0]['properties'][$key];
                    //Seems Devolo doesn't know all about its own motion sensor...
                    if ($key=='sensorType' and $value=='unknown') continue;
                    //echo 'sensorType:'.$sensorType.', key:'.$key.', value:'.$value.'<br>';
                    $value = $this->formatStates($sensorType, $key, $value);
                    $jsonSensor[$key] = $value;
                }
                $arrayStates[] = $jsonSensor;
            }
            elseif ( !in_array($sensorType, $this->_SensorsNoValues) ) //Unknown, unsupported sensor!
            {
                $answer = $this->fetchItems(array($sensor));
                echo "DEBUG - UNKNOWN PARAM - Please help and report this message on https://github.com/KiboOst/php-devoloDHC or email it to".base64_decode('a2lib29zdEBmcmVlLmZy')." <br>";
                echo '<pre>infos:'.json_encode($answer, JSON_PRETTY_PRINT).'</pre><br>';
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
            @array_push($availableDatas, $item['switchType']);
            if ($item['sensorType'] == $askData or @$item['switchType'] == $askData) return array('result'=>$item);
        }
        $error = array('result'=>null,
                    'error' => 'Unfound data for this Device',
                    'available' => $availableDatas
                    );
        return $error;
    }

    public function getDevicesByZone($zoneName)
    {
        foreach($this->_AllZones as $thisZone)
        {
            if ($thisZone['name'] == $zoneName)
            {
                $devicesUIDS = $thisZone['deviceUIDs'];
                $jsonArray = array();
                foreach ($this->_AllDevices as $thisDevice)
                {
                    if (in_array($thisDevice['uid'], $devicesUIDS)) $jsonArray[] = $thisDevice;
                }
                return array('result'=>$jsonArray);
            }
        }
        return array('result'=>null, 'error'=>'Unfound '.$zoneName);
    }

    public function getDeviceURL($device)
    {
        if ( is_string($device) ) $device = $this->getDeviceByName($device);
        if ( isset($device['error']) ) return $device;

        $uid = $device['uid'];
        if (!stristr($uid, 'hdm:DevoloHttp:virtual')) return array('result'=>null, 'error' => 'This is not an http virtual device');

        $hdm = str_replace('hdm:DevoloHttp:virtual', 'hs.hdm:DevoloHttp:virtual', $uid);
        $answer = $this->fetchItems(array($hdm));
        if (isset($answer['error']['message']) ) return array('result'=>null, 'error'=>$answer['error']['message']);
        $url = $answer['result']['items'][0]['properties']['httpSettings']['request'];
        return array('result'=>$url);
    }

    public function getDeviceBattery($device)
    {
        if ( is_string($device) ) $device = $this->getDeviceByName($device);
        if ( isset($device['error']) ) return $device;

        $batLevel = $device['batteryLevel'];
        if ($batLevel == 'None' or $batLevel == -1) $batLevel = 'No battery';

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
            if (($thisBatLevel == -1) or ($thisBatLevel == 'None')) {if ($filter==1) continue; }

            $datas = array('name' => $thisDeviceName, 'battery_percent' => $thisBatLevel);
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
        if (isset($answer['error']['message']) ) return array('result'=>null, 'error'=>$answer['error']['message']);

        $jsonDatas = array();
        $numEvents = count($answer['result'])-1; //may have less than requested
        foreach (array_reverse($answer['result']) as $event)
        {
            $deviceName = $event['deviceName'];
            $deviceZone = $event['deviceZone'];
            $author = $event['author'];
            $timeOfDay = $event['timeOfDay'];
            $timeOfDay = gmdate('H:i:s', $timeOfDay);

            $datas = array(
                            'deviceName' => $deviceName,
                            'deviceZone' => $deviceZone,
                            'author' => $author,
                            'timeOfDay' => $timeOfDay
                            );
            $jsonDatas[] = $datas;
        }
        return array('result'=>$jsonDatas);
    }

    public function getDailyStat($device, $dayBefore=0)
    {
        if ( is_string($device) ) $device = $this->getDeviceByName($device);
        if ( isset($device['error']) ) return $device;

        if (!is_int($dayBefore)) return array('error'=> 'second argument should be 0 1 or 2 for today, yesterday, day before yesterday');

        $operation = "retrieveDailyStatistics";
        $statSensor = $device['statUID'];
        if ($statSensor=='None') return array('result'=>null, 'error'=>"No statistic for such device.");
        $answer = $this->invokeOperation($statSensor, $operation, $dayBefore);
        if (isset($answer['error']['message']) ) return array('result'=>null, 'error'=>$answer['error']['message']);

        $jsonDatas = array();
        foreach ($answer['result'] as $item)
        {
            $sensor = $item['widgetElementUID'];
            $values = $item['value'];
            if ( isset($item['timeOfDay']) ) $timesOfDay = $item['timeOfDay'];

            if (strstr($device['model'], 'Door/Window:Sensor'))
            {
                if (strstr($sensor, 'BinarySensor:hdm')) $sensor = 'opened';
                if (strstr($sensor, '#MultilevelSensor(1)')) $sensor = 'temperature';
                if (strstr($sensor, '#MultilevelSensor(3)')) $sensor = 'light';
            }
            if (strstr($device['model'], 'Motion:Sensor'))
            {
                if (strstr($sensor, 'BinarySensor:hdm')) $sensor = 'alarm';
                if (strstr($sensor, '#MultilevelSensor(1)')) $sensor = 'temperature';
                if (strstr($sensor, '#MultilevelSensor(3)')) $sensor = 'light';
            }
            if (strstr($device['model'], 'Wall:Plug:Switch:and:Meter'))
            {
                if (strstr($sensor, 'Meter:hdm')) $sensor = 'consumption';
            }

            $sensorData = array('sensor'=>$sensor);
            $countValues = count($values)-1;
            for($i=$countValues; $i>=1; $i--)
            {
                $timeOfDay = gmdate("H:i:s", $timesOfDay[$i]);
                $sensorData[$timeOfDay] = $values[$i];
            }
            $jsonDatas[] = $sensorData;
        }
        return array('result'=>$jsonDatas);
    }

    public function getWeather()
    {
        $jsonString = '{
            "jsonrpc":"2.0",
            "method":"FIM/getFunctionalItems",
            "params":[
                ["devolo.WeatherWidget"],0
                ]
            }';

        $answer = $this->sendCommand($jsonString);
        if (isset($answer['error']['message']) ) return array('result'=>null, 'error'=>$answer['error']['message']);

        $data = $answer['result']['items'][0]['properties'];

        $this->_Weather = array();
        $this->_Weather['currentTemp'] = $data['currentTemp'];

        unset($data['forecastData'][0]['weatherCode'] );
        unset($data['forecastData'][1]['weatherCode'] );
        unset($data['forecastData'][2]['weatherCode'] );

        $this->_Weather['Today'] = $data['forecastData'][0];
        $this->_Weather['Tomorrow'] = $data['forecastData'][1];
        $this->_Weather['DayAfterT'] = $data['forecastData'][2];

        $value = $data['lastUpdateTimestamp'];
        $this->_Weather['lastUpdate'] = $this->formatStates('LastActivity', 'lastActivityTime', $value);

        return array('result'=>$this->_Weather);
    }

    public function getMessageData($msg)
    {
        if ( is_string($msg) ) $msg = $this->getMessageByName($msg);
        if ( isset($msg['error']) ) return $msg;

        $answer = $this->fetchItems(array($msg['element']));

        if (isset($answer['error']['message']) ) return array('result'=>null, 'error'=>$answer['error']['message']);

        return array('result' => $answer['result']['items'][0]['properties']['msgData']);
    }

    public function getHueHSB($device) #Philips Hue
    {
        if ( is_string($device) ) $device = $this->getDeviceByName($device);
        if ( isset($device['error']) ) return $device;

        $sensors = (isset($device['sensors']) ? json_decode($device['sensors'], true): null);
        if ($sensors == null) return array('result'=>null, 'error' => 'Unfound sensor for device');

        foreach ($sensors as $sensor)
        {
            $sensorType = $this->getSensorType($sensor);

            if (in_array($sensorType, $this->_SensorsSendHSB))
            {
                $answer = $this->fetchItems(array($sensor));
                if (isset($answer['error']['message']) ) return array('result'=>null, 'error'=>$answer['error']['message']);
                if ( isset($answer['result']['items'][0]['properties']['state']) )
                {
                    $hue = $answer['result']['items'][0]['properties']['hue'];
                    $sat = $answer['result']['items'][0]['properties']['sat'];
                    $bri = $answer['result']['items'][0]['properties']['bri'];
                    return array('result' => array('hue' => $hue, 'sat' => $sat, 'bri' => $bri));
                }
            }
        }
        return array('result'=>null, 'error' => 'Unfound OnOff sensor for device');
    }

    public function getHueRGB($device) #Philips Hue
    {
        if ( is_string($device) ) $device = $this->getDeviceByName($device);
        if ( isset($device['error']) ) return $device;

        $var = $this->getHueHSB($device);
        if ( isset($var['error']) ) return $var;

        $hue = $var['result']['hue'];
        $sat = $var['result']['sat'];
        $bri = $var['result']['bri'];

        #Convert Philips 65353 values:
        $hue = ($hue * 360) / 65535;
        $sat = ($sat * 100) / 65535;
        $bri = ($bri * 100) / 65535;

        $RGB = $this->HSLtoRGB($hue, $sat, $bri);
        return array('result' => $RGB);
    }


    //CONSUMPTION:
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
        $yesterday = date('d.m.Y',strtotime('-1 days'));
        $datasArray = array();
        foreach ($this->_AllDevices as $device)
        {
            if (strstr($device['model'], 'Wall:Plug:Switch:and:Meter'))
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

        //set recent up:
        $keys = array_keys($prevDatas);
        usort($keys, array('DevoloDHC','sortByDate'));
        $newArray = array();
        foreach ($keys as $key)
        {
            $newArray[$key] = $prevDatas[$key];
        }
        $prevDatas = $newArray;

        //write it to file:
        $put = file_put_contents($filePath, json_encode($prevDatas, JSON_PRETTY_PRINT));
        if ($put) return array('result'=>$datasArray);
        return array('result'=>$datasArray, 'error'=>'Unable to write file!');
    }

    private function sortByDate($a, $b)
    {
        $t1 = strtotime($a);
        $t2 = strtotime($b);
        return ($t2 - $t1);
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
                if ( strtotime($thisDate)<=strtotime($dateStart) and strtotime($thisDate)>=strtotime($dateEnd))
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

    //SET:
    public function startScene($scene)
    {
        if ( is_string($scene) ) $scene = $this->getSceneByName($scene);
        if ( isset($scene['error']) ) return $scene;

        $element = $scene['element'];
        $answer = $this->invokeOperation($element, "start");
        if (isset($answer['error']['message']) ) return array('result'=>null, 'error'=>$answer['error']['message']);
        $result = ( ($answer['result'] == null) ? true : false );
        return array('result'=>$result);
    }

    public function turnRuleOnOff($rule, $state=0)
    {
        if ( is_string($rule) ) $rule = $this->getRuleByName($rule);
        if ( isset($rule['error']) ) return $rule;

        $value = ( ($state == 0) ? 'false' : 'true' );

        $jsonString = '{
            "jsonrpc":"2.0",
            "method":"FIM/setProperty",
            "params":["'.$rule['element'].'","enabled",'.$value.']}';

        $answer = $this->sendCommand($jsonString);
        if (isset($answer['error']['message']) ) return array('result'=>null, 'error'=>$answer['error']['message']);
        return array('result'=>$answer);
    }

    public function turnTimerOnOff($timer, $state=0)
    {
        if ( is_string($timer) ) $timer = $this->getTimerByName($timer);
        if ( isset($timer['error']) ) return $timer;

        $value = ( ($state == 0) ? 'false' : 'true' );

        $jsonString = '{
            "jsonrpc":"2.0",
            "method":"FIM/setProperty",
            "params":["'.$timer['element'].'","enabled",'.$value.']}';

        $answer = $this->sendCommand($jsonString);
        if (isset($answer['error']['message']) ) return array('result'=>null, 'error'=>$answer['error']['message']);
        return array('result'=>$answer);
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

            if (in_array($sensorType, $this->_SensorsOnOff))
            {
                $operation = ($state == 0 ? 'turnOff' : 'turnOn');
                $answer = $this->invokeOperation($sensor, $operation);
                if (isset($answer['error']['message']) ) return array('result'=>null, 'error'=>$answer['error']['message']);
                return array('result'=>true);
            }
            if (in_array($sensorType, $this->_SensorsSend) and ($state == 1))
            {
                $operation = "send";
                $answer = $this->invokeOperation($sensor, $operation);
                if (isset($answer['error']['message']) ) return array('result'=>null, 'error'=>$answer['error']['message']);
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
        if (isset($answer['error']['message']) ) return array('result'=>null, 'error'=>$answer['error']['message']);
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

            if (in_array($sensorType, $this->_SensorsSendValue))
            {
                $operation = 'sendValue';
                $answer = $this->invokeOperation($sensor, $operation, $value);
                if (isset($answer['error']['message']) ) return array('result'=>null, 'error'=>$answer['error']['message']);
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
            if (in_array($sensorType, $this->_SensorsPressKey))
            {
                $operation = 'pressKey';
                $answer = $this->invokeOperation($sensor, $operation, $key);
                if (isset($answer['error']['message']) ) return array('result'=>null, 'error'=>$answer['error']['message']);
                return array('result'=>true);
            }
        }
        return array('result'=>null, 'error' => 'No supported sensor for this device');
    }

    public function sendMessage($msg)
    {
        if ( is_string($msg) ) $msg = $this->getMessageByName($msg);
        if ( isset($msg['error']) ) return $msg;

        $element = $msg['element'];
        $answer = $this->invokeOperation($element, "send");
        if (isset($answer['error']['message']) ) return array('result'=>null, 'error'=>$answer['error']['message']);
        $result = ( ($answer['result'] == null) ? true : false );
        return array('result'=>$result);
    }

    public function setHueRGB($device, $rgb) #Philips Hue
    {
        if ( is_string($device) ) $device = $this->getDeviceByName($device);
        if ( isset($device['error']) ) return $device;

        #check RGB array:
        $msg = 'Provide array(R,G,B) as 0-255 int value as second argument';
        if (!is_array($rgb)) return array('result'=>null, 'error'=>$msg);
        if (count($rgb)!=3) return array('result'=>null, 'error'=>$msg);
        if (!is_int($rgb[0]) or !is_int($rgb[1]) or !is_int($rgb[2])) return array('result'=>null, 'error'=>$msg);

        $sensors = (isset($device['sensors']) ? json_decode($device['sensors'], true) : null);
        if ($sensors == null) return array('result'=>null, 'error' => 'No sensor found in this device');

        foreach ($sensors as $sensor)
        {
            $sensorType = $this->getSensorType($sensor);

            if (in_array($sensorType, $this->_SensorsSendHSB))
            {
                $HSL = $this->RGB2HueSB($rgb[0], $rgb[1], $rgb[2]);
                echo '<br>';##DEBUG
                var_dump($HSL);##DEBUG
                $value = '['.$HSL[0].','.$HSL[1].','.$HSL[2].']';
                echo '<br>', $value;##DEBUG
                $operation = 'sendHSB';
                $answer = $this->invokeOperation($sensor, $operation, $value);
                if (isset($answer['error']['message']) ) return array('result'=>null, 'error'=>$answer['error']['message']);
                return array('result'=>true);
            }
        }
        return array('result'=>null, 'error' => 'No supported sensor for this device');
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

    public function getMessageByName($name)
    {
        if (count($this->_AllMessages) == 0) $this->getMessages();

        foreach($this->_AllMessages['customMessages'] as $thisMsg)
        {
            if ($thisMsg['name'] == $name) return $thisMsg;
        }
        return array('result'=>null, 'error' => 'Unfound Message');
    }

    //internal functions==================================================
    protected function getSensorType($sensor)
    {
        //devolo.BinarySensor:hdm:ZWave:D8F7DDE2/10 -> BinarySensor
        $sensorType = explode('devolo.', $sensor);
        if (count($sensorType) == 0) return null;
        $sensorType = explode(':', $sensorType[1]);
        $sensorType = $sensorType[0];
        return $sensorType;
    }

    protected function getValuesByType($sensorType)
    {
        foreach($this->_SensorValuesByType as $type => $param)
        {
            if ($type == $sensorType) return $param;
        }
        return null;
    }

    public function resetSessionTimeout()
    {
        //cookie expire in 30min, anyway Devolo Central send resetSessionTimeout every 10mins
        if (!isset($this->_uuid))
        {
            //get uuid:
            $jsonString = '{"jsonrpc":"2.0", "method":"FIM/getFunctionalItemUIDs","params":["(objectClass=com.devolo.fi.page.Dashboard)"]}';
            $answer = $this->sendCommand($jsonString);
            if (isset($answer['result'][0]) ) $uuid = $answer['result'][0];
            else return array('error'=> array('message'=>"can't find uuid!"));
            $uuid = $answer['result'][0];
            $uuid = explode('devolo.Dashboard.', $uuid)[1];
            $this->_uuid = $uuid;
        }

        $jsonString = '{"jsonrpc":"2.0", "method":"FIM/invokeOperation","params":["devolo.UserPrefs.'.$this->_uuid.'","resetSessionTimeout",[]]}';
        $answer = $this->sendCommand($jsonString);
        if (isset($answer['error']['message']) ) return array('result'=>null, 'error'=>$answer['error']['message']);
        return array('result'=>$answer['result']);
    }

    public function debugDevice($device)
    {
        if ( is_string($device) ) $device = $this->getDeviceByName($device);
        if ( isset($device['error']) ) return $device;

        $jsonArray = $this->fetchItems(array($device['uid']));
        echo '<pre>Device:<br>',json_encode($jsonArray, JSON_PRETTY_PRINT),'</pre><br>';

        $elements = $jsonArray['result']['items'][0]['properties']['elementUIDs'];
        $elementsArray = $this->fetchItems($elements);
        echo '<pre>elementUIDs:<br>',json_encode($elementsArray, JSON_PRETTY_PRINT),'</pre><br>';

        $settings = $jsonArray['result']['items'][0]['properties']['settingUIDs'];
        $settingsArray = $this->fetchItems($settings);
        echo '<pre>settingUIDs:<br>',json_encode($settingsArray, JSON_PRETTY_PRINT),'</pre><br>';
    }

    //color functions=====================================================
    protected function HSLtoRGB($hue, $sat, $val, $array = false, $format = 'rgb(%d, %d, %d)')
    {
        if ($hue < 0)   $hue = 0;
        if ($hue > 360) $hue = 360;
        if ($sat < 0)   $sat = 0;
        if ($sat > 100) $sat = 100;
        if ($val < 0)   $val = 0;
        if ($val > 100) $val = 100;

        $dS = $sat/100.0;
        $dV = $val/100.0;
        $dC = $dV*$dS;
        $dH = $hue/60.0;
        $dT = $dH;

        while ($dT >= 2.0) $dT -= 2.0;
        $dX = $dC*(1-abs($dT-1));

        switch(floor($dH))
        {
            case 0:
                $dR = $dC; $dG = $dX; $dB = 0.0; break;
            case 1:
                $dR = $dX; $dG = $dC; $dB = 0.0; break;
            case 2:
                $dR = 0.0; $dG = $dC; $dB = $dX; break;
            case 3:
                $dR = 0.0; $dG = $dX; $dB = $dC; break;
            case 4:
                $dR = $dX; $dG = 0.0; $dB = $dC; break;
            case 5:
                $dR = $dC; $dG = 0.0; $dB = $dX; break;
            default:
                $dR = 0.0; $dG = 0.0; $dB = 0.0; break;
        }

        $dM  = $dV - $dC;
        $dR += $dM; $dG += $dM; $dB += $dM;
        $dR *= 255; $dG *= 255; $dB *= 255;
        $rgb = ($array) ? array('R'=>round($dR), 'G'=>round($dG), 'B'=>round($dB)) : sprintf($format, round($dR), round($dG), round($dB));

        return $rgb;
    }

    protected function RGB2HueSB ($R, $G, $B)
    {
        $var_R = ($R / 255);
        $var_G = ($G / 255);
        $var_B = ($B / 255);

        $var_Min = min($var_R, $var_G, $var_B);
        $var_Max = max($var_R, $var_G, $var_B);
        $del_Max = $var_Max - $var_Min;

        $V = $var_Max;
        if ($del_Max == 0)
        {
            $H = 0;
            $S = 0;
        }
        else
        {
            $S = $del_Max / $var_Max;

            $del_R = ( ( ( $var_Max - $var_R ) / 6 ) + ( $del_Max / 2 ) ) / $del_Max;
            $del_G = ( ( ( $var_Max - $var_G ) / 6 ) + ( $del_Max / 2 ) ) / $del_Max;
            $del_B = ( ( ( $var_Max - $var_B ) / 6 ) + ( $del_Max / 2 ) ) / $del_Max;

            if      ($var_R == $var_Max) $H = $del_B - $del_G;
            else if ($var_G == $var_Max) $H = ( 1 / 3 ) + $del_R - $del_B;
            else if ($var_B == $var_Max) $H = ( 2 / 3 ) + $del_G - $del_R;

            if ($H<0) $H++;
            if ($H>1) $H--;
        }

        $HSL = array();
        $HSL[0] = round($H * 65535);
        $HSL[1] = round($S * 65535);
        $HSL[2] = round($V * 65535);
        return $HSL;
    }


    //getter functions====================================================
    protected function getDevices()
    {
        if (count($this->_AllZones) == 0)
        {
            $result = $this->getZones();
            if (isset($result['error'])) return $result;
        }

        //get all devices from all zones:
        $UIDSarray = array();
        foreach ($this->_AllZones as $thisZone)
        {
            $thisDevices = $thisZone['deviceUIDs'];
            foreach ($thisDevices as $thisDevice)
            {
                $UIDSarray[] = $thisDevice;
            }
        }

        //request all infos for all devices at once:
        $jsonArray = $this->fetchItems($UIDSarray);

        //store devices:
        $devices = array();
        foreach ($jsonArray['result']["items"] as $thisDevice)
        {
            $name = (isset($thisDevice['properties']['itemName']) ? $thisDevice['properties']['itemName'] : 'None');
            $uid = (isset($thisDevice['UID']) ? $thisDevice['UID'] : 'None');
            $elementUIDs = (isset($thisDevice['properties']['elementUIDs']) ? $thisDevice['properties']['elementUIDs'] : 'None');

            $device = array('name' => $name,
                            'uid' => $uid,
                            'sensors' => json_encode($elementUIDs),
                            'zoneId' => (isset($thisDevice['properties']['zoneId']) ? $thisDevice['properties']['zoneId'] : 'None'),
                            'statUID' => (isset($thisDevice['properties']['statisticsUID']) ? $thisDevice['properties']['statisticsUID'] : 'None'),
                            'batteryLevel' => (isset($thisDevice['properties']['batteryLevel']) ? $thisDevice['properties']['batteryLevel'] : 'None'),
                            'model' => (isset($thisDevice['properties']['deviceModelUID']) ? $thisDevice['properties']['deviceModelUID'] : 'None')
                            );
            $devices[] = $device;
        }
        $this->_AllDevices = $devices;
    }

    protected function getZones() //also get groups!
    {
        $this->_AllZones = array();
        $this->_AllGroups = array();

        $jsonString = '{
            "jsonrpc":"2.0",
            "method":"FIM/getFunctionalItems",
            "params":[
                ["devolo.Grouping"],0
                ]
            }';

        $data = $this->_request('POST', $this->_dhcUrl, '/remote/json-rpc', $jsonString);
        $jsonArray = json_decode($data, true);

        //avoid account with just demo gateway:
        if (!isset($jsonArray['result']["items"][0]['properties']['zones']))
        {
            $this->error = 'Seems a demo Gateway, or no zones ?';
            return array('result'=>null, 'error'=>$this->error);
        }

        //get all zones:
        $zones = $jsonArray['result']["items"][0]['properties']['zones'];
        foreach ($zones as $thisZone)
        {
            $thisID = $thisZone['id'];
            $thisName = $thisZone['name'];
            $thisDevices = $thisZone['deviceUIDs'];

            $zone = array('name' => $thisName,
                            'id' => $thisID,
                            'deviceUIDs' => $thisDevices
                            );
            $this->_AllZones[] = $zone;
        }

        //get each group infos:
        $jsonArray = $this->fetchItems($jsonArray['result']['items'][0]['properties']['smartGroupWidgetUIDs']);

        foreach ($jsonArray['result']['items'] as $thisGroup)
        {
            $thisID = $thisGroup['UID'];
            $thisName = $thisGroup['properties']['itemName'];
            $thisOurOfSync = $thisGroup['properties']['outOfSync'];
            $thisSync = $thisGroup['properties']['synchronized'];
            $thisDevices = $thisGroup['properties']['deviceUIDs'];

            $group = array('name' => $thisName,
                            'id' => $thisID,
                            'outOfSync' => $thisOurOfSync,
                            'synchronized' => $thisSync,
                            'deviceUIDs' => $thisDevices
                            );
            $this->_AllGroups[] = $group;
        }
    }

    protected function getScenes()
    {
        $this->_AllScenes = array();

        $jsonString = '{
            "jsonrpc":"2.0",
            "method":"FIM/getFunctionalItems",
            "params":[
                ["devolo.Scene"],0
                ]
            }';

        $data = $this->_request('POST', $this->_dhcUrl, '/remote/json-rpc', $jsonString);
        $jsonArray = json_decode($data, true);

        //request datas for all scenes:
        $jsonArray = $this->fetchItems($jsonArray['result']['items'][0]['properties']['sceneUIDs']);

        foreach($jsonArray['result']['items'] as $thisScene)
        {
            $scene = array('name' => $thisScene['properties']['itemName'],
                            'id' => $thisScene['UID'],
                            'element' => str_replace('Scene', 'SceneControl', $thisScene['UID'])
                            );
            array_push($this->_AllScenes, $scene);
        }
    }

    protected function getTimers()
    {
        $this->_AllTimers = array();

        $jsonString = '{
            "jsonrpc":"2.0",
            "method":"FIM/getFunctionalItems",
            "params":[
                ["devolo.Schedules"],0
                ]
            }';

        $data = $this->_request('POST', $this->_dhcUrl, '/remote/json-rpc', $jsonString);
        $jsonArray = json_decode($data, true);

        //request datas for all rules:
        $jsonArray = $this->fetchItems($jsonArray['result']['items'][0]['properties']['scheduleUIDs']);

        foreach($jsonArray['result']['items'] as $thisTimer)
        {
            $rule = array('name' => $thisTimer['properties']['itemName'],
                            'id' => $thisTimer['UID'],
                            'element' => str_replace('Schedule', 'ScheduleControl', $thisTimer['UID'])
                            );
            array_push($this->_AllTimers, $rule);
        }
    }

    protected function getRules()
    {
        $this->_AllRules = array();

        $jsonString = '{
            "jsonrpc":"2.0",
            "method":"FIM/getFunctionalItems",
            "params":[
                ["devolo.Services"],0
                ]
            }';

        $data = $this->_request('POST', $this->_dhcUrl, '/remote/json-rpc', $jsonString);
        $jsonArray = json_decode($data, true);

        //request datas for all rules:
        $jsonArray = $this->fetchItems($jsonArray['result']['items'][0]['properties']['serviceUIDs']);

        foreach($jsonArray['result']['items'] as $thisRule)
        {
            $rule = array('name' => $thisRule['properties']['itemName'],
                            'id' => $thisRule['UID'],
                            'element' => str_replace('Service', 'ServiceControl', $thisRule['UID'])
                            );
            array_push($this->_AllRules, $rule);
        }
    }

    protected function getMessages()
    {
        $this->_AllMessages = array();

        $jsonString = '{
            "jsonrpc":"2.0",
            "method":"FIM/getFunctionalItems",
            "params":[
                ["devolo.Messages"],0
                ]
            }';

        $data = $this->_request('POST', $this->_dhcUrl, '/remote/json-rpc', $jsonString);
        $jsonArray = json_decode($data, true);

        $this->_AllMessages['pnEndpoints'] = $jsonArray['result']['items'][0]['properties']['pnEndpoints'];
        $this->_AllMessages['phoneNumbers'] = $jsonArray['result']['items'][0]['properties']['phoneNumbers'];
        $this->_AllMessages['emailExt'] = $jsonArray['result']['items'][0]['properties']['emailExt'];
        $this->_AllMessages['emailAddresses'] = $jsonArray['result']['items'][0]['properties']['emailAddresses'];

        //fetch custom Messages:
        $jsonArray = $this->fetchItems($jsonArray['result']['items'][0]['properties']['customMessageUIDs']);

        $this->_AllMessages['customMessages'] = array();
        foreach($jsonArray['result']['items'] as $thisMsg)
        {
            $msg = array('name' => $thisMsg['properties']['itemName'],
                            'id' => $thisMsg['UID'],
                            'description' => $thisMsg['properties']['description'],
                            'base' => $thisMsg['properties']['base'],
                            'element' => $thisMsg['properties']['elementUIDs'][0]
                            );
            array_push($this->_AllMessages['customMessages'], $msg);
        }
    }

    protected function formatStates($sensorType, $key, $value)
    {
        if ($sensorType=='Meter' and $key=='totalValue') return $value.'kWh';
        if ($sensorType=='Meter' and $key=='currentValue') return $value.'W';
        if ($key=='sinceTime')
        {
            $ts = $value;
            $ts = substr($ts, 0, -3) - 3600; //microtime timestamp from Berlin
            $date = new DateTime();
            $date->setTimestamp($ts);
            $date->setTimezone(new DateTimeZone(date_default_timezone_get())); //set it to php server timezone
            $date = $date->format('d.m.Y H:i');
            return $date;
        }
        if ($sensorType=='LastActivity' and $key=='lastActivityTime')
        {
            if ($value == -1) return 'Never';
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


    //calling functions===================================================
    protected function _request($method, $host, $path, $jsonString=null, $postinfo=null) //standard function handling all get/post request with curl | return string
    {
        if (!isset($this->_curlHdl))
        {
            $this->_curlHdl = curl_init();
            curl_setopt($this->_curlHdl, CURLOPT_URL, $this->_authUrl);

            curl_setopt($this->_curlHdl, CURLOPT_COOKIEJAR, $this->_cookFile);
            curl_setopt($this->_curlHdl, CURLOPT_COOKIEFILE, $this->_cookFile);

            curl_setopt($this->_curlHdl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($this->_curlHdl, CURLOPT_SSL_VERIFYPEER, false);

            curl_setopt($this->_curlHdl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->_curlHdl, CURLOPT_FOLLOWLOCATION, true);

            curl_setopt($this->_curlHdl, CURLOPT_REFERER, 'http://www.google.com/');
            curl_setopt($this->_curlHdl, CURLOPT_USERAGENT, 'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:51.0) Gecko/20100101 Firefox/51.0');

            curl_setopt($this->_curlHdl, CURLOPT_ENCODING , "gzip");
        }

        $url = filter_var($host.$path, FILTER_SANITIZE_URL);

        curl_setopt($this->_curlHdl, CURLOPT_URL, $url);

        if ($method == 'POST')
        {
            curl_setopt($this->_curlHdl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->_curlHdl, CURLOPT_POST, true);
        }
        else
        {
            curl_setopt($this->_curlHdl, CURLOPT_POST, false);
        }

        if ( isset($jsonString) )
        {
            $jsonString = str_replace('"jsonrpc":"2.0",', '"jsonrpc":"2.0", "id":'.$this->_POSTid.',', $jsonString);
            $this->_POSTid++;
            curl_setopt($this->_curlHdl, CURLOPT_HEADER, false);
            curl_setopt($this->_curlHdl, CURLINFO_HEADER_OUT, false );
            curl_setopt($this->_curlHdl, CURLOPT_POST, false);
            curl_setopt($this->_curlHdl, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($this->_curlHdl, CURLOPT_POSTFIELDS, $jsonString);
        }

        if ( isset($postinfo) )
        {
            curl_setopt($this->_curlHdl, CURLOPT_POSTFIELDS, $postinfo);
        }

        $response = curl_exec($this->_curlHdl);

        //$info   = curl_getinfo($this->_curlHdl);
        //echo "<pre>cURL info".json_encode($info, JSON_PRETTY_PRINT)."</pre><br>";

        $this->error = null;
        if ($response === false) $this->error = curl_error($this->_curlHdl);
        return $response;
    }

    protected function fetchItems($UIDSarray) //get infos from central for array of device, sensor, timer etc | return array
    {
        $devicesJson = json_encode($UIDSarray);
        $jsonString = '{
            "jsonrpc":"2.0",
            "method":"FIM/getFunctionalItems",
            "params":[
                '.$devicesJson.',0
                ]
            }';

        $data = $this->_request('POST', $this->_dhcUrl, '/remote/json-rpc', $jsonString);
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

        $data = $this->_request('POST', $this->_dhcUrl, '/remote/json-rpc', $jsonString);
        $jsonArray = json_decode($data, true);
        return $jsonArray;
    }

    public function sendCommand($jsonString) //directly send json to central. Only works when all required authorisations are set | return array
    {
        $data = $this->_request('POST', $this->_dhcUrl, '/remote/json-rpc', $jsonString);
        $jsonArray = json_decode($data, true);
        return $jsonArray;
    }

    //functions authorization=============================================

    //user central stuff:
    public $_userInfos;
    public $_centralInfos;
    public $_gateway;
    public $_uuid = null;
    public $_token;
    public $error = null;
    public $_wasCookiesLoaded = false;
    public $_cookFile = '';
    public $_gateIdx = 0;

    //central stuff stuff(!):
    public $_AllDevices = null;
    public $_AllZones = null;
    public $_AllGroups = null;
    public $_AllRules = null;
    public $_AllTimers = null;
    public $_AllScenes = null;
    public $_AllMessages = null;
    public $_Weather = null;

    //authentication:
    protected $_login;
    protected $_password;
    protected $_authUrl = 'https://www.mydevolo.com';
    protected $_dhcUrl =  'https://homecontrol.mydevolo.com';
    protected $_lang = '/en';
    protected $_POSTid = 0;
    protected $_curlHdl = null;

    //types stuff:
    /*
    Devolo Home Control Portal(web interface or app interface to access HCB Home Control Box)
        -> HCB
            ->Device
                - sensor (type, data), handle operations ?
                - sensor (type, data), handle operations ?
            ->Device
                - sensor (type, data), handle operations ?
            etc
    */
    protected $_SensorsOnOff        = array('BinarySwitch', 'BinarySensor', 'HueBulbSwitch'); //supported sensor types for on/off operation
    protected $_SensorsSend         = array('HttpRequest'); //supported sensor types for send operation
    protected $_SensorsSendValue    = array('MultiLevelSwitch', 'SirenMultiLevelSwitch'); //supported sensor types for sendValue operation
    protected $_SensorsPressKey     = array('RemoteControl'); //supported sensor types for pressKey operation
    protected $_SensorsSendHSB      = array('HueBulbColor'); //supported sensor types for sendHSB operation

    protected $_SensorsNoValues     = array('HttpRequest'); //virtual device sensor

    protected $_SensorValuesByType  = array(
                                        'Meter'                 => array('sensorType', 'currentValue', 'totalValue', 'sinceTime'),
                                        'BinarySwitch'          => array('switchType', 'state', 'targetState'),
                                        'MildewSensor'          => array('sensorType', 'state'),
                                        'BinarySensor'          => array('sensorType', 'state'),
                                        'SirenBinarySensor'     => array('sensorType', 'state'),
                                        'MultiLevelSensor'      => array('sensorType', 'value'),
                                        'HumidityBarZone'       => array('sensorType', 'value'),
                                        'DewpointSensor'        => array('sensorType', 'value'),
                                        'HumidityBarValue'      => array('sensorType', 'value'),
                                        'SirenMultiLevelSensor' => array('sensorType', 'value'),
                                        'SirenMultiLevelSwitch' => array('switchType', 'targetValue'),
                                        'MultiLevelSwitch'      => array('switchType', 'value', 'targetValue', 'min', 'max'),
                                        'RemoteControl'         => array('keyCount', 'keyPressed'),
                                        'HueBulbSwitch'         => array('sensorType', 'state'),
                                        'HueBulbColor'          => array('switchType', 'hue', 'sat', 'bri', 'targetHsb'),
                                        'LastActivity'          => array('lastActivityTime')
                                        );

    protected function getCSRF($htmlString)
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($htmlString);
        $nodes = $dom->getElementsByTagName('input');
        foreach($nodes as $node)
        {
            if ($node->hasAttributes())
            {
                foreach($node->attributes as $attribute)
                {
                    if ($attribute->nodeName == 'type' && $attribute->nodeValue == 'hidden')
                    {
                        $name = $node->getAttribute('name');
                        if (stripos($name, 'csrf') !== false) return $node->getAttribute('value');
                    }
                }
            }
        }
        return False;
    }

    protected function cookies_are_hot()
    {
        if ( is_writable(__DIR__) ) //API can write in his folder
        {
            $this->_cookFile = __DIR__.'/dhc_cookies.txt';

            if ( is_writable($this->_cookFile) and (time()-filemtime($this->_cookFile) < 1200) ) //cookie file exist and is younger than 20mins
            {
                //return true; //no check is 0.15s faster!
                $var = file_get_contents($this->_cookFile);
                if (strstr($var, 'JSESSIONID'))
                {
                    $answer = $this->resetSessionTimeout();
                    if ( !isset($answer['error']['message']) )
                    {
                        $this->_wasCookiesLoaded = true;
                        return true;
                    }
                    else
                    {
                        curl_close($this->_curlHdl); //was initialized by resetSessionTimeout and will keep old cookies file!
                        $this->_curlHdl = null;
                        @unlink($this->_cookFile);
                        return false;
                    }
                }
            }
            if ( is_writable($this->_cookFile) ) unlink($this->_cookFile);
        }
        else
        {
            $this->_wasCookiesLoaded = 'Unable to write cookies file';
            return false;
        }
        return false;
    }

    protected function auth()
    {
        if ($this->cookies_are_hot()) return true;
        //No young cookie file, full authentication:

        //___________get CSRF_______________________________________________________
        $response = $this->_request('GET', $this->_authUrl, $this->_lang, null);

        if($response==false)
        {
            $this->error = "Can't connect to Devolo servers.";
            return false;
        }

        $csrf = $this->getCSRF($response);
        if ($csrf != false)
        {
            $this->_csrf = $csrf;
        }
        else
        {
            $this->error = "Couldn't find Devolo CSRF.";
            return false;
        }


        //___________post login/password____________________________________________
        $postinfo = '_csrf='.$csrf.'&username='.$this->_login.'&password='.$this->_password;
        $response = $this->_request('POST', $this->_authUrl, $this->_lang, null, $postinfo);


        //___________get gateway____________________________________________________
        $path = $this->_lang.'/hc/gateways/status';
        $response = $this->_request('GET', $this->_authUrl, $path, null);
        $json = json_decode($response, true);

        if (isset($json['data'][$this->_gateIdx]['id']))
        {
            $gateway = $json['data'][$this->_gateIdx]['id'];
            $this->_gateway = $gateway;
        }
        else
        {
            $this->error = "Couldn't find Devolo gateway.";
            return false;
        }

        //___________get open Gateway_______________________________________________
        $path = $this->_lang.'/hc/gateways/'.$gateway.'/open';
        $response = $this->_request('GET', $this->_authUrl, $path, null, null);

        return true;
    }

    function __construct($login, $password, $connect=true, $gateIdx=0)
    {
        $this->_login = urlencode($login);
        $this->_password = urlencode($password);
        $this->_gateIdx = $gateIdx;

        if ($connect==true)
        {
            if ($this->auth() == true) $this->getDevices();
        }
    }

    /*
    dynamic call to getAllxxx / $_DHC->getAllDevices();
    if _AllDevices is null, call getDevices, return _AllDevices

    getAllDevices() / getAllZones() / getAllGroups() / getAllRules() / getAllTimers() / getAllScenes() / getAllMessages()
    */
    public function __call($name, $args)
    {
        if (preg_match('/^get(.+)/', $name, $matches))
        {
            $var_name = '_'.$matches[1];
            $fn_name = substr($var_name, 4);

            if (@count($this->$var_name) == 0)
            {
                $this->$var_name = 'Undefined';
                call_user_func(array($this, 'get'.$fn_name));
            }
            //unknown var/function:
            return array('result'=>$this->$var_name);
        }
        //no get, no function!
        return array('result'=>null, 'error'=>'Undefined function');
    }

//DevoloDHC end
}

?>
