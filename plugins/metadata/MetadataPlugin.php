<?php
/**
 * Enable adding custom metadata objects that releate to core objects
 * @package plugins.metadata
 */
class MetadataPlugin extends KalturaPlugin implements IKalturaVersion, IKalturaPermissions, IKalturaServices, IKalturaEventConsumers, IKalturaObjectLoader, IKalturaBulkUploadHandler, IKalturaSearchDataContributor, IKalturaMemoryCleaner, IKalturaConfigurator, IKalturaSchemaContributor, IKalturaSphinxConfiguration, IKalturaEnumerator
{

	const SPHINX_DEFAULT_NUMBER_OF_DATE_FIELDS = 10;
	const SPHINX_DEFAULT_NUMBER_OF_INT_FIELDS = 10;
	
	const PLUGIN_NAME = 'metadata';
	
	const SPHINX_EXPANDER_FIELD_DATA = 'data';
	const SPHINX_EXPENDER_FIELD_INT = 'date_'; //for backward compatibility, all partners in production are using int field for date.
	
	const PLUGIN_VERSION_MAJOR = 2;
	const PLUGIN_VERSION_MINOR = 1;
	const PLUGIN_VERSION_BUILD = 0;
	
	const METADATA_FLOW_MANAGER_CLASS = 'kMetadataFlowManager';
	const METADATA_COPY_HANDLER_CLASS = 'kMetadataObjectCopiedHandler';
	const METADATA_DELETE_HANDLER_CLASS = 'kMetadataObjectDeletedHandler';
	
	const BULK_UPLOAD_COLUMN_PROFILE_ID = 'metadataProfileId';
	const BULK_UPLOAD_COLUMN_XML = 'metadataXml';
	const BULK_UPLOAD_COLUMN_URL = 'metadataUrl';
	const BULK_UPLOAD_COLUMN_FIELD_PREFIX = 'metadataField_';
	const BULK_UPLOAD_MULTI_VALUES_DELIMITER = '|,|';
	
	const BULK_UPLOAD_DATE_FORMAT = '%Y-%m-%dT%H:%i:%s';

	/* (non-PHPdoc)
	 * @see KalturaPlugin::getInstance()
	 */
	public function getInstance($interface)
	{
		if($this instanceof $interface)
			return $this;
			
		if($interface == 'IKalturaMrssContributor')
			return kMetadataMrssManager::get();
			
		return null;
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaPlugin::getPluginName()
	 */
	public static function getPluginName()
	{
		return self::PLUGIN_NAME;
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaVersion::getVersion()
	 */
	public static function getVersion()
	{
		return new KalturaVersion(
			self::PLUGIN_VERSION_MAJOR,
			self::PLUGIN_VERSION_MINOR,
			self::PLUGIN_VERSION_BUILD
		);
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaPermissions::isAllowedPartner()
	 */
	public static function isAllowedPartner($partnerId)
	{
		if($partnerId == Partner::BATCH_PARTNER_ID)
			return true;
			
		$partner = PartnerPeer::retrieveByPK($partnerId);
		if(!$partner)
			return false;
			
		return $partner->getPluginEnabled(self::PLUGIN_NAME);
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaEnumerator::getEnums()
	 */
	public static function getEnums($baseEnumName = null)
	{
		if(is_null($baseEnumName))
			return array('MetadataConditionType');
	
		if($baseEnumName == 'ConditionType')
			return array('MetadataConditionType');
			
		return array();
	}

	
	/**
	 * @return int id of dynamic enum in the DB.
	 */
	public static function getConditionTypeCoreValue($valueName)
	{
		$value = self::getPluginName() . IKalturaEnumerator::PLUGIN_VALUE_DELIMITER . $valueName;
		return kPluginableEnumsManager::apiToCore('ConditionType', $value);
	}
	
	/**
	 * @return string external API value of dynamic enum.
	 */
	public static function getApiValue($valueName)
	{
		return self::getPluginName() . IKalturaEnumerator::PLUGIN_VALUE_DELIMITER . $valueName;
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaServices::getServicesMap()
	 */
	public static function getServicesMap()
	{
		$map = array(
			'metadata' => 'MetadataService',
			'metadataProfile' => 'MetadataProfileService',
			'metadataBatch' => 'MetadataBatchService',
		);
		return $map;
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaEventConsumers::getEventConsumers()
	 */
	public static function getEventConsumers()
	{
		return array(
			self::METADATA_FLOW_MANAGER_CLASS,
			self::METADATA_COPY_HANDLER_CLASS,
			self::METADATA_DELETE_HANDLER_CLASS,
		);
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaObjectLoader::loadObject()
	 */
	public static function loadObject($baseClass, $enumValue, array $constructorArgs = null)
	{
		if($baseClass == 'ISyncableFile' && isset($constructorArgs['objectId']))
		{
			$objectId = $constructorArgs['objectId'];
			
			switch($enumValue)
			{
				case FileSyncObjectType::METADATA:
					MetadataPeer::setUseCriteriaFilter ( false );
					$object = MetadataPeer::retrieveByPK( $objectId );
					MetadataPeer::setUseCriteriaFilter ( true );
					return $object;
					
				case FileSyncObjectType::METADATA_PROFILE:
					MetadataProfilePeer::setUseCriteriaFilter ( false );
					$object = MetadataProfilePeer::retrieveByPK( $objectId );
					MetadataProfilePeer::setUseCriteriaFilter ( true );
					return $object;
			}
		}
		
		if($baseClass == 'kJobData')
		{
			switch($enumValue)
			{
				case KalturaBatchJobType::METADATA_IMPORT:
					return new kImportJobData();
					
				case KalturaBatchJobType::METADATA_TRANSFORM:
					return new kTransformMetadataJobData();
			}
		}
	
		if($baseClass == 'KalturaJobData')
		{
			switch($enumValue)
			{
				case KalturaBatchJobType::METADATA_IMPORT:
					return new KalturaImportJobData();
					
				case KalturaBatchJobType::METADATA_TRANSFORM:
					return new KalturaTransformMetadataJobData();
			}
		}
	
		if($baseClass == 'KalturaCondition')
		{
			if($enumValue == MetadataPlugin::getConditionTypeCoreValue(MetadataConditionType::METADATA_FIELD_COMPARE))
				return new KalturaCompareMetadataCondition();
				
			if($enumValue == MetadataPlugin::getConditionTypeCoreValue(MetadataConditionType::METADATA_FIELD_MATCH))
				return new KalturaMatchMetadataCondition();
		}
		
		return null;
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaObjectLoader::getObjectClass()
	 */
	public static function getObjectClass($baseClass, $enumValue)
	{
		if($baseClass == 'ISyncableFile')
		{
			switch($enumValue)
			{
				case FileSyncObjectType::METADATA:
					return 'Metadata';
					
				case FileSyncObjectType::METADATA_PROFILE:
					return 'MetadataProfile';
			}
		}
		
		if($baseClass == 'kJobData')
		{
			switch($enumValue)
			{
				case KalturaBatchJobType::METADATA_IMPORT:
					return 'kImportJobData';
					
				case KalturaBatchJobType::METADATA_TRANSFORM:
					return 'kTransformMetadataJobData';
			}
		}
	
		if($baseClass == 'KalturaJobData')
		{
			switch($enumValue)
			{
				case KalturaBatchJobType::METADATA_IMPORT:
					return 'KalturaImportJobData';
					
				case KalturaBatchJobType::METADATA_TRANSFORM:
					return 'KalturaTransformMetadataJobData';
			}
		}
		
		return null;
	}

	
	/**
	 * @param array $fields
	 * @return string
	 */
	private static function getDateFormatRegex(&$fields = null)
	{
		$replace = array(
			'%Y' => '([1-2][0-9]{3})',
			'%m' => '([0-1][0-9])',
			'%d' => '([0-3][0-9])',
			'%H' => '([0-2][0-9])',
			'%i' => '([0-5][0-9])',
			'%s' => '([0-5][0-9])',
//			'%T' => '([A-Z]{3})',
		);
	
		$fields = array();
		$arr = null;
//		if(!preg_match_all('/%([YmdTHis])/', self::BULK_UPLOAD_DATE_FORMAT, $arr))
		if(!preg_match_all('/%([YmdHis])/', self::BULK_UPLOAD_DATE_FORMAT, $arr))
			return false;
	
		$fields = $arr[1];
		
		return '/' . str_replace(array_keys($replace), $replace, self::BULK_UPLOAD_DATE_FORMAT) . '/';
	}
	
	/**
	 * @param string $str
	 * @return int
	 */
	private static function parseFormatedDate($str)
	{
		KalturaLog::debug("parseFormatedDate($str)");
		
		if(function_exists('strptime'))
		{
			$ret = strptime($str, self::BULK_UPLOAD_DATE_FORMAT);
			if($ret)
			{
				KalturaLog::debug("Formated Date [$ret] " . date('Y-m-d\TH:i:s', $ret));
				return $ret;
			}
		}
			
		$fields = null;
		$regex = self::getDateFormatRegex($fields);
		
		$values = null;
		if(!preg_match($regex, $str, $values))
			return null;
			
		$hour = 0;
		$minute = 0;
		$second = 0;
		$month = 0;
		$day = 0;
		$year = 0;
		$is_dst = 0;
		
		foreach($fields as $index => $field)
		{
			$value = $values[$index + 1];
			
			switch($field)
			{
				case 'Y':
					$year = intval($value);
					break;
					
				case 'm':
					$month = intval($value);
					break;
					
				case 'd':
					$day = intval($value);
					break;
					
				case 'H':
					$hour = intval($value);
					break;
					
				case 'i':
					$minute = intval($value);
					break;
					
				case 's':
					$second = intval($value);
					break;
					
//				case 'T':
//					$date = date_parse($value);
//					$hour -= ($date['zone'] / 60);
//					break;
					
			}
		}
		
		KalturaLog::debug("gmmktime($hour, $minute, $second, $month, $day, $year)");
		$ret = gmmktime($hour, $minute, $second, $month, $day, $year);
		if($ret)
		{
			KalturaLog::debug("Formated Date [$ret] " . date('Y-m-d\TH:i:s', $ret));
			return $ret;
		}
			
		KalturaLog::debug("Formated Date [null]");
		return null;
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaBulkUploadHandler::handleBulkUploadData()
	 */
	public static function handleBulkUploadData(BaseObject $object, array $data)
	{
		KalturaLog::debug("Handle metadata bulk upload data:\n" . print_r($data, true));
		
		if(!isset($data[self::BULK_UPLOAD_COLUMN_PROFILE_ID]))
			return;
			
		$metadataProfileId = $data[self::BULK_UPLOAD_COLUMN_PROFILE_ID];
		$xmlData = null;
		
		if(!$object)
			return;
			
//		$criteriaFilter = FileSyncPeer::getCriteriaFilter();
//		$criteria = $criteriaFilter->getFilter();
//		$criteria->add(FileSyncPeer::PARTNER_ID, $entry->getPartnerId());
		
		$metadataProfile = MetadataProfilePeer::retrieveById($metadataProfileId);
		if(!$metadataProfile)
		{
			$errorMessage = "Metadata profile [$metadataProfileId] not found";
			KalturaLog::err($errorMessage);
			self::addBulkUploadResultDescription($object->getId(), $object->getBulkUploadId(), $errorMessage);
			return;
		}
		
		if(isset($data[self::BULK_UPLOAD_COLUMN_URL]))
		{
			try{
				$xmlData = file_get_contents($data[self::BULK_UPLOAD_COLUMN_URL]);
				KalturaLog::debug("Metadata downloaded [" . $data[self::BULK_UPLOAD_COLUMN_URL] . "]");
			}
			catch(Exception $e)
			{
				$errorMessage = "Download metadata[" . $data[self::BULK_UPLOAD_COLUMN_URL] . "] error: " . $e->getMessage();
				KalturaLog::err($errorMessage);
				self::addBulkUploadResultDescription($object->getId(), $object->getBulkUploadId(), $errorMessage);
				$xmlData = null;
			}
		}
		elseif(isset($data[self::BULK_UPLOAD_COLUMN_XML]))
		{
			$xmlData = $data[self::BULK_UPLOAD_COLUMN_XML];
		}
		else
		{
			$metadataProfileFields = array();
			MetadataProfileFieldPeer::setUseCriteriaFilter(false);
			$tmpMetadataProfileFields = MetadataProfileFieldPeer::retrieveByMetadataProfileId($metadataProfileId);
			MetadataProfileFieldPeer::setUseCriteriaFilter(true);
			
			foreach($tmpMetadataProfileFields as $metadataProfileField)
				$metadataProfileFields[$metadataProfileField->getKey()] = $metadataProfileField;
			
			KalturaLog::debug("Found fields [" . count($metadataProfileFields) . "] for metadata profile [$metadataProfileId]");
			$xml = new DOMDocument();
			$dataFound = false;
			
			foreach($data as $key => $value)
			{
				if(!$value || !strlen($value))
					continue;
					
				if(!preg_match('/^' . self::BULK_UPLOAD_COLUMN_FIELD_PREFIX . '(.+)$/', $key, $matches))
					continue;
					
				$key = $matches[1];
				if(!isset($metadataProfileFields[$key]))
				{
					$errorMessage = "Field [$key] does not exist";
					KalturaLog::debug($errorMessage);
					self::addBulkUploadResultDescription($object->getId(), $object->getBulkUploadId(), $errorMessage);
					continue;
				}
				
				$metadataProfileField = $metadataProfileFields[$key];
				KalturaLog::debug("Found field [" . $metadataProfileField->getXpath() . "] for value [$value]");
				
				$fieldValues = explode(self::BULK_UPLOAD_MULTI_VALUES_DELIMITER, $value);
				foreach($fieldValues as $fieldValue)
				{
					if($metadataProfileField->getType() == MetadataSearchFilter::KMC_FIELD_TYPE_DATE && !is_numeric($fieldValue))
					{
						$value = self::parseFormatedDate($fieldValue);
						if(!$value || !strlen($value))
						{
							$errorMessage = "Could not parse date format [$fieldValue] for field [$key]";
							KalturaLog::debug($errorMessage);
							self::addBulkUploadResultDescription($object->getId(), $object->getBulkUploadId(), $errorMessage);
							continue;
						}
							
						$fieldValue = $value;
					}
					
					if($metadataProfileField->getType() == MetadataSearchFilter::KMC_FIELD_TYPE_INT && !is_numeric($fieldValue))
					{
						$errorMessage = "Could not parse int format [$fieldValue] for field [$key]";
						KalturaLog::debug($errorMessage);
						self::addBulkUploadResultDescription($object->getId(), $object->getBulkUploadId(), $errorMessage);
						continue;
					}
						
					self::addXpath($xml, $metadataProfileField->getXpath(), $fieldValue);
				}
					
				$dataFound = true;
			}
			
			if($dataFound)
			{
				$xmlData = $xml->saveXML($xml->firstChild);
				$xmlData = trim($xmlData, " \n\r\t");
			}
		}
		
		if(!$xmlData)
			return;
		
		$errorMessage = '';
		if(!kMetadataManager::validateMetadata($metadataProfileId, $xmlData, $errorMessage))
		{
			self::addBulkUploadResultDescription($object->getId(), $object->getBulkUploadId(), $errorMessage);
			return;
		}
		
		$dbMetadata = new Metadata();
		$dbMetadata->setPartnerId($object->getPartnerId());
		$dbMetadata->setMetadataProfileId($metadataProfileId);
		$dbMetadata->setMetadataProfileVersion($metadataProfile->getVersion());
		$dbMetadata->setObjectType(kMetadataManager::getTypeNameFromObject($object));
		$dbMetadata->setObjectId($object->getId());
		$dbMetadata->setStatus(Metadata::STATUS_VALID);
		$dbMetadata->save();
		
		KalturaLog::debug("Metadata [" . $dbMetadata->getId() . "] saved [$xmlData]");
		
		$key = $dbMetadata->getSyncKey(Metadata::FILE_SYNC_METADATA_DATA);
		kFileSyncUtils::file_put_contents($key, $xmlData);
		
		kEventsManager::raiseEvent(new kObjectDataChangedEvent($dbMetadata));
	}
	
	protected static function addBulkUploadResultDescription($entryId, $bulkUploadId, $description)
	{
		$bulkUploadResult = BulkUploadResultPeer::retrieveByEntryId($entryId, $bulkUploadId);
		if(!$bulkUploadResult)
		{
			KalturaLog::err("Bulk upload results not found for entry [$entryId]");
			return;
		}
		
		$msg = $bulkUploadResult->getErrorDescription();
		if($msg)
			$msg .= "\n";
		
		$msg .= $description;
			
		$bulkUploadResult->setErrorDescription($msg);
		$bulkUploadResult->save();
	}
	
	protected static function addXpath(DOMDocument &$xml, $xPath, $value)
	{
		KalturaLog::debug("add value [$value] to xPath [$xPath]");
		$xPaths = explode('/', $xPath);
		$currentNode = $xml;
		$currentXPath = '';
		foreach($xPaths as $index => $xPath)
		{
			if(!strlen($xPath))
			{
				KalturaLog::debug("xPath [/] already exists");
				continue;
			}
				
			$currentXPath .= "/$xPath";
			if($index + 1 < count($xPaths))
			{
				$domXPath = new DOMXPath($xml);
				$nodeList = $domXPath->query($currentXPath);
				
				if($nodeList && $nodeList->length)
				{
					$currentNode = $nodeList->item(0);
					KalturaLog::debug("xPath [$xPath] already exists");
					continue;
				}
			}
			
			if(!preg_match('/\*\[\s*local-name\(\)\s*=\s*\'([^\']+)\'\s*\]/', $xPath, $matches))
			{
				KalturaLog::err("Xpath [$xPath] doesn't match");
				return false;
			}
				
			$nodeName = $matches[1];
			if($index + 1 == count($xPaths))
			{
				KalturaLog::debug("Creating node [$nodeName] xPath [$xPath] with value [$value]");
				$valueNode = $xml->createElement($nodeName, $value);
			}
			else
			{
				KalturaLog::debug("Creating node [$nodeName] xPath [$xPath]");
				$valueNode = $xml->createElement($nodeName);				
			}
			KalturaLog::debug("Appending node [$nodeName] to current node [$currentNode->localName]");
			$currentNode->appendChild($valueNode);
			$currentNode = $valueNode;
		}
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaSearchDataContributor::getSearchData()
	 */
	public static function getSearchData(BaseObject $object)
	{
		if($object instanceof entry)
		{
			if(self::isAllowedPartner($object->getPartnerId()))
				return kMetadataManager::getSearchValuesByObject(Metadata::TYPE_ENTRY, $object->getId());
		}
	
		if($object instanceof category)
		{
			if(self::isAllowedPartner($object->getPartnerId()))
				return kMetadataManager::getSearchValuesByObject(Metadata::TYPE_CATEGORY, $object->getId());
		}
	
		if($object instanceof categoryKuser)
		{
			if(self::isAllowedPartner($object->getPartnerId()))
				return kMetadataManager::getSearchValuesByObject(Metadata::TYPE_CATEGORY_USER, $object->getId());
		}
	
		if($object instanceof kuser)
		{
			if(self::isAllowedPartner($object->getPartnerId()))
				return kMetadataManager::getSearchValuesByObject(Metadata::TYPE_USER, $object->getId());
		}
			
		return null;
	}
		
	/* (non-PHPdoc)
	 * @see IKalturaSphinxConfiguration::getSphinxSchema()
	 */
	public static function getSphinxSchema()
	{
		$kalturaEntryFields = Array ();
		$searchIndexes = kConf::get('search_indexes');
		
		foreach ($searchIndexes as $indexName => $indexLimit)
		{
			for ($i=0; $i < $indexLimit; $i++)
				$kalturaEntryFields[MetadataPlugin::getSphinxFieldName(MetadataPlugin::SPHINX_EXPENDER_FIELD_INT) . $i] = SphinxFieldType::RT_ATTR_UINT;
		
			$sphinxSchema[kSphinxSearchManager::getSphinxIndexName($indexName)]['fields'] = $kalturaEntryFields;
		}
		
		return $sphinxSchema;
	}

	/**
	 * return number of fields in kaltura_entry index (in sphinx) for given type
	 * @param int $type
	 */
	public static function getAdditionalSearchableFieldsLimit($partnerId, $obejctType)
	{
		$partner = PartnerPeer::retrieveByPK ( $partnerId );
		if (!$partner)
			throw new APIException(APIErrors::INVALID_PARTNER_ID, $partnerId);
		
		If ($obejctType == MetadataObjectType::ENTRY)
		{
			$partnerSearchIndex = $partner->getSearchIndex(entryPeer::TABLE_NAME);
		}
		elseif ($obejctType == MetadataObjectType::CATEGORY)
		{
			$partnerSearchIndex = $partner->getSearchIndex(categoryPeer::TABLE_NAME);
		}
		else 
		{
			return 0;
		}
		
		$searchIndexes = kConf::get('search_indexes');
		
		if(!isset($searchIndexes[$partnerSearchIndex]))
			throw new Exception('could not find partner\'s search index ' . $partnerSearchIndex);
			
		return $searchIndexes[$partnerSearchIndex];
	}
	/* (non-PHPdoc)
	 * @see IKalturaMemoryCleaner::cleanMemory()
	 */
	public static function cleanMemory()
	{
	    MetadataProfilePeer::clearInstancePool();
	    MetadataProfileFieldPeer::clearInstancePool();
	    MetadataPeer::clearInstancePool();
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaConfigurator::getConfig()
	 */
	public static function getConfig($configName)
	{
		if($configName == 'generator')
			return new Zend_Config_Ini(dirname(__FILE__) . '/config/generator.ini');
			
		if($configName == 'testme')
			return new Zend_Config_Ini(dirname(__FILE__) . '/config/testme.ini');
			
		return null;
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaSchemaContributor::contributeToSchema()
	 */
	public static function contributeToSchema($type)
	{
		$coreType = kPluginableEnumsManager::apiToCore('SchemaType', $type);
		if($coreType != SchemaType::SYNDICATION)
			return null;
			
		$xsd = '
		
	<!-- ' . self::getPluginName() . ' -->
	
	<xs:complexType name="T_customData">
		<xs:sequence>
			<xs:any namespace="##local" processContents="skip" minOccurs="1" maxOccurs="1">
				<xs:annotation>
					<xs:documentation>Custom metadata XML according to schema profile</xs:documentation>
				</xs:annotation>		
			</xs:any>			
		</xs:sequence>
		
		<xs:attribute name="metadataId" use="required" type="xs:int">
			<xs:annotation>
				<xs:documentation>Id of the custom metadata object</xs:documentation>
			</xs:annotation>
		</xs:attribute>
		<xs:attribute name="metadataVersion" use="required" type="xs:int">
			<xs:annotation>
				<xs:documentation>Version of the custom metadata object</xs:documentation>
			</xs:annotation>
		</xs:attribute>
		<xs:attribute name="metadataProfile" use="optional" type="xs:string">
			<xs:annotation>
				<xs:documentation>Custom metadata schema profile system name</xs:documentation>
			</xs:annotation>
		</xs:attribute>
		<xs:attribute name="metadataProfileId" use="required" type="xs:int">
			<xs:annotation>
				<xs:documentation>Custom metadata schema profile id</xs:documentation>
			</xs:annotation>
		</xs:attribute>
		<xs:attribute name="metadataProfileName" use="optional" type="xs:string">
			<xs:annotation>
				<xs:documentation>Custom metadata schema profile name</xs:documentation>
			</xs:annotation>
		</xs:attribute>
		<xs:attribute name="metadataProfileVersion" use="required" type="xs:int">
			<xs:annotation>
				<xs:documentation>Custom metadata schema profile version</xs:documentation>
			</xs:annotation>
		</xs:attribute>
		
	</xs:complexType>
	
	<xs:element name="customData" type="T_customData" substitutionGroup="item-extension">
		<xs:annotation>
			<xs:documentation>Custom metadata XML</xs:documentation>
			<xs:appinfo>
				<example>
					<customData	metadataId="{metadata id}" 
								metadataVersion="1" 
								metadataProfile="MY_METADATA_PROFILE_SYSTEM_NAME}"  
								metadataProfileId="{metadata profile id}"  
								metadataProfileName="my metadata profile" 
								metadataProfileVersion="1" 
					>
						<metadata>
							<Text1>text test</Text1>
							<TextMulti>test one</TextMulti>
							<TextMulti>test two</TextMulti>
							<List1>bbb</List1>
							<Entry>0_5b3t2c8z</Entry>
						</metadata>
					</customData>
				</example>
			</xs:appinfo>
		</xs:annotation>
	</xs:element>
		';
		
		return $xsd;
	}
	
	/**
	 * 
	 * return field name as appears in sphinx schema
	 * @param string $fieldName
	 */
	public static function getSphinxFieldName($fieldName){
		if ($fieldName == self::SPHINX_EXPANDER_FIELD_DATA)
			return 'plugins_data';
			
		return self::PLUGIN_NAME . '_' . $fieldName;
	}
}
