<?php

require_once dirname(__FILE__) . '/../bootstrap.php';

/**
 * 
 * Enables to use a global data file for retriving and setting values
 * @author Roni
 *
 */
class KalturaGlobalData extends KalturaTestConfig
{
	/**
	 * 
	 * The default location for the global data file
	 * @var string
	 */
	const DEFAULT_DATA_PATH = "/opt/kaltura/app/tests/common/global.data";
		
	/**
	 * 
	 * The data file for the global data
	 * @var KalturaTestConfig
	 */
	private static $dataFile = null;
	 
	/**
	 * 
	 * The data file path
	 * @var string
	 */
	private static $dataFilePath = null;
	
	/**
	 * 
	 * Sets data in the global file with the given name and value
	 */
	public static function setData($name, $value)
	{
		if(KalturaGlobalData::$dataFile == null)
			KalturaGlobalData::initDataFile();
		
		KalturaGlobalData::$dataFile->$name = $value; 
		KalturaGlobalData::$dataFile->saveToIniFile();
	}
	
	/**
	 * 
	 * Sets data in the global file with the given name and value
	 */
	public static function getData($name)
	{
		if(KalturaGlobalData::$dataFile == null)
			KalturaGlobalData::initDataFile();
			
		$value = null; 
		 
		if(is_string($name) || is_integer($name))
		{
			$value = KalturaGlobalData::$dataFile->get($name);
//			print("KalturaGlobalData::getData name[$name] value [ " . print_r($value, true) . "] \n");
		}
			
		if(empty($value)) //Empty value equals null
			$value = null;
						
		return $value;
	}
	
	/**
	 * 
	 * Inits the global data file 
	 */
	private static function initDataFile()
	{
		if(is_null(KalturaGlobalData::$dataFilePath))
		{
			KalturaGlobalData::setDataFilePath(KalturaGlobalData::DEFAULT_DATA_PATH);
		}
		
		
		KalturaGlobalData::$dataFile = new KalturaTestConfig(KalturaGlobalData::$dataFilePath);
	}
	
	/**
	 * 
	 * Sets the global file path
	 * @param string $dataFilePath
	 */
	public static function setDataFilePath($dataFilePath)
	{
		KalturaGlobalData::$dataFilePath = $dataFilePath;
	}
	
	/**
	 * @return the $dataFilePath
	 */
	public static function getDataFilePath() {
		return KalturaGlobalData::$dataFilePath;
	}

	/**
	 * 
	 * Checks if the given name is in the global data
	 * @param string $name
	 */
	public static function isGlobalData($name)
	{
		if(KalturaGlobalData::$dataFile == null)
			KalturaGlobalData::initDataFile();
		
		$value = null;
		
		if(is_string($name) || is_integer($name))
		{
			$value = KalturaGlobalData::getData($name);
			KalturaLog::debug("Name [$name] Value [$value]\n");
		}
		
		if(is_null($value))
			return false;
		
		return true;
	}
}