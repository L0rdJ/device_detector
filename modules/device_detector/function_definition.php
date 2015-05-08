<?php

/**
 * @package Device Detector
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    08 May 2014
 * */

$FunctionList = array(
    'wurfl'        => array(
        'name'           => 'wurfl',
        'call_method'    => array(
            'class'  => 'DeviceDetectorFetchFunctions',
            'method' => 'getWurflData'
        ),
        'parameter_type' => 'standard',
        'parameters'     => array()
    ),
    'detect_right' => array(
        'name'           => 'detect_right',
        'call_method'    => array(
            'class'  => 'DeviceDetectorFetchFunctions',
            'method' => 'getDetectRightData'
        ),
        'parameter_type' => 'standard',
        'parameters'     => array()
    ),
    'device_atlas' => array(
        'name'           => 'device_atlas',
        'call_method'    => array(
            'class'  => 'DeviceDetectorFetchFunctions',
            'method' => 'getDeviceAtlasData'
        ),
        'parameter_type' => 'standard',
        'parameters'     => array()
    )
);
