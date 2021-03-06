<?php
/**
 * @package plugins.quickTimeTools
 * @subpackage lib
 */
class KOperationEngineQtTools  extends KSingleOutputOperationEngine
{
	protected $tmpFolder;
	
	public function configure(KalturaConvartableJobData $data, KalturaBatchJob $job)
	{
		parent::configure($data, $job);
		$this->tmpFolder = KBatchBase::$taskConfig->params->localTempPath;
	}
	
	public function operate(kOperator $operator = null, $inFilePath, $configFilePath = null)
	{
		$qtInFilePath = "$this->tmpFolder/$inFilePath.stb";

		if(symlink($inFilePath, $qtInFilePath))
			$inFilePath = $qtInFilePath;
		
		return parent::operate($operator, $inFilePath, $configFilePath);
	}
}
