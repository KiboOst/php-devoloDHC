# php-devoloDHC local

## php API for Devolo Home Control
(C) 2017, KiboOst

The localphpdevoloAPI is a class extending normal phpdevoloAPI.

It allows you to connect directly to your Devolo Home Control box, without contacting Devolo servers.

This can be use if you decide to make heavy polling/requests on your box, which I don't encourage, to not flood Devolo servers.


## How-to
- Download class/phpDevoloAPI.php and put it on your server.
- Download localConnection/localphpDevoloAPI.php and put it on your server.
- Include phpDevoloAPI.php and localphpDevoloAPI.php in your script.

Supported DevoloAPI: v2.21 and up.

First time execution:

The API will first request some authorization data from www.mydevolo.com to be able to directly access your Central.
These data won't change for same user, so you can get them and directly pass them next time for complete local connection.

- Note them in your script or in a config file you include before creating DevoloDHC().

- To directly connect to your DHC box, you will need its local IP on your network. You can also put a dyndns address if you have set some NAT/PAT to it.

```php
require($_SERVER['DOCUMENT_ROOT']."/path/to/phpDevoloAPI.php");
require($_SERVER['DOCUMENT_ROOT']."/path/to/localphpDevoloAPI.php");

$_DHC = new localDevoloDHC($login, $password, $localHost, null, null, null, false);
if (isset($_DHC->_error)) echo "_DHC error:".$_DHC->_error.'<br>';

$auth = $DHC->getAuth();
echo "<pre>".json_encode($auth, JSON_PRETTY_PRINT)."</pre><br>";
```

Note returned values and next time, call $_DHC = new localDevoloDHC($login, $password, $localHost, $uuid, $gateway, $passkey, false);

```php
 $DHC = new DevoloDHC($login, $password, $localIP, $uuid, $gateway, $passkey, false);
 ```

The last *false* argument say to the main API to not try to login on Devolo servers with only your login and password. If you let it to *true*, it will connect normally on Devolo servers but will provide an alternative login to your central if Devolo login fails.

## Limitation

The direct local connection to DHC box has some limitation:

- Unable to use turnRuleOnOff()
- Unable to use turnTimerOnOff()

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
