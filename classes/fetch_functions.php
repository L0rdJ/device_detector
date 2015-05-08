<?php

/**
 * @package Device Detector
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    08 May 2014
 * */

class DeviceDetectorFetchFunctions {

    public function getWurflData() {
        require_once 'extension/device_detector/lib/WURFL/Application.php';

        $ini = eZINI::instance( 'device_detector.ini' );

        $resourcesDir   = $ini->variable( 'WURFL', 'ResourceDir' );
        $persistenceDir = $resourcesDir . '/storage/persistence';
        $cacheDir       = $resourcesDir . '/storage/cache';

        $config = new WURFL_Configuration_InMemoryConfig();
        $config->wurflFile( $resourcesDir . '/wurfl.zip' );
        $config->allowReload( true );
        $config->matchMode('performance');
        $config->persistence( 'file', array( 'dir' => $persistenceDir ) );
        $config->cache( 'file', array( 'dir' => $cacheDir, 'expiration' => 36000 ) );

        $capabilities = (array) $ini->variable( 'WURFL', 'Capabilities' );
        if( count( $capabilities ) > 0 ) {
            $config->capabilityFilter( $capabilities );
        }

        try{
            $managerFactory = new WURFL_WURFLManagerFactory( $config );
            $wurflManager   = $managerFactory->create();

            $device = $wurflManager->getDeviceForHttpRequest( $_SERVER );
            $data   = $device->getAllCapabilities();
        } catch( Exception $e ) {
            eZDebug::writeError( $e->getMessage(), __METHOD__ );

            $data = array();
        }

        return self::getResult( $data );
    }

    public function getDetectRightData() {
        $DRROOT = 'extension/device_detector/lib/DetectRight';
        include_once( $DRROOT . '/detectright.php' );

        $ini = eZINI::instance( 'device_detector.ini' );

        $data = array();
        try {
            $enabledOptions = array( 'yes', 'enabled', 'true' );
            DetectRight::$expressMode = in_array( $ini->variable( 'DetectRight', 'ExpressMode' ), $enabledOptions );
            DBLink::$useQueryCache    = in_array( $ini->variable( 'DetectRight', 'UserQueryCache' ), $enabledOptions );
            DetectRight::$uaCache     = in_array( $ini->variable( 'DetectRight', 'UACahce' ), $enabledOptions );

            DetectRight::initialize( 'DRSQLite//' . $ini->variable( 'DetectRight', 'DBFile' ) );

            $data = DetectRight::getProfileFromUA( eZSys::serverVariable( 'HTTP_USER_AGENT' ) );
        } catch( Exception $e ) {
            eZDebug::writeError( $e->getMessage(), __METHOD__ );
        }

        return self::getResult( $data );
    }

    public function getDeviceAtlasData() {
        require_once 'extension/device_detector/lib/DeviceAtlas/Client.php';

        $ini = eZINI::instance( 'device_detector.ini' );
        DeviceAtlasCloudClient::$LICENCE_KEY = $ini->variable( 'DeviceAtlas', 'LicenceKey' );

        $data = array();
        try{
            $result = DeviceAtlasCloudClient::getDeviceData();
            if (isset($result[DeviceAtlasCloudClient::KEY_PROPERTIES])) {
                $data = $result[DeviceAtlasCloudClient::KEY_PROPERTIES];
            }
        } catch (Exception $e) {
            eZDebug::writeError( $e->getMessage(), __METHOD__ );
        }

        return self::getResult( $data );
    }

    public static function getResult( $result ) {
        if( is_object( $result ) ) {
            $result = self::objectToArray( $result );
        }

        return array( 'result' => $result );
    }

    public static function objectToArray( $object ) {
        return json_decode( json_encode( $object ), true );
    }

}
