# php-devoloDHC dev version

Dev version to help supporting more devices inside Devolo Home Control.

Not having myself these devices, I can't fully test them, or would need co-user invitation from users having them. If you have unsupported devices, you can help testing this version.

## Feature development

- Philips Hue support

<img align="right" src="/readmeAssets/howto.png" width="48">

## How-to

- See https://github.com/KiboOst/php-devoloDHC for getting it running.
- Replace stable version by dev version.

<img align="right" src="/readmeAssets/requirements.png" width="48">

## Features to test

Philips Hue:

```php
//get HSB values of Hue:
$_DHC->getHueHSB('myHue')

//get RGB values of Hue:
$_DHC->getHueRGB('myHue')

//set RGB values:
$_DHC->setHueRGB('myHue', array(128, 128, 128))

//turn Hue on/off (0/1):
$_DHC->turnDeviceOnOff('myHue', 1)
```

#### Unsupported device

If you have unsupported device, you can call special function with this device and post the return in a new issue.

[Request for unsupported device](../../issues/)

```php
$help = $DHC->debugDevice('MyStrangeDevice');
```

<img align="right" src="/readmeAssets/changes.png" width="48">

## Changes

#### v 2.7dev (2017-05-16)
- New: getHueHSB() / getHueRGB() / setHueRGB()
- This version is identical to v2.63 with Hue support testing.

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
