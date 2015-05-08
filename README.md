Device Detector
===================

General description
-------------------

eZ Publish 4.x extension, which detects user devices using external APIs


Installation
------------
1. Download and enable device_detector extension
2. Regenerate eZ Publish autoloads and clear eZ Publish ini caches:
```
$ cd EZP-ROOT
$ php bin/php/ezcache.php --clear-tag=ini
$ php bin/php/ezpgenerateautoloads.php
```
3. Provide write access for cache directories (it is required only for WURFL):
```
$ chmod 0777 -R extension/device_detector/resources/WURFL/storage/persistence
$ chmod 0777 -R extension/device_detector/resources/WURFL/storage/cache
```
4. Install "php5-sqlite" package (it is required only for DetectRight http://stackoverflow.com/questions/4608863/setting-up-sqlite3-pdo-drivers-in-php):
```
$ sudo apt-get install php5-sqlite
```
5. Obtain DeviceAtlas licence key at https://deviceatlas.com/ and set it as `[DeviceAtlas] LicenceKey` setting in device_detector.ini

Example usage
-------------
```
{fetch( 'device_detector', 'wurfl' )|attribute( 'show' )}
{fetch( 'device_detector', 'detect_right' )|attribute( 'show' )}
{fetch( 'device_detector', 'device_atlas' )|attribute( 'show' )}
```