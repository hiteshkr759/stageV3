<?php
require_once ('../GlobalFile/MSmysqliDb.php');
class MicrosoftDatabase{
	
	private $host 		    = "localhost";
	private $user 		    = "root";
	private $pwd 		    = "";
	private $db 		    = "microsoft_sync";
	private $db_Conn 		= null;

	protected $eventTag = array(
		'Tag',
		'EventId'
	);

	protected $eventAudience = array(
		'EventsFor',
		'EventId'
	);


	protected $eventProduct = array(
		'Product',
		'EventId'
	);


	protected $eventLocationFieldName = array(
		'Code',
		'Address',
		'Street',
		'City',
		'State',
		'PostalCode',
		'Country',
		'Latitude',
		'Longitude',
	);
	

	protected $eventFieldName = array(
		'EventId',
		'Title',
		'URL',
		'Summary',
		'Status',
		'Description',
		'OnlineUrl',
		'Category',
		'StartDate',
		'EndDate',
		'RegistrationOpenDate',
		'RegistrationCloseDate',
		'RegistrationFees',
		'SurveyUrl',
		'SurveyResultUrl',
		'IsPublishable',
		'AssistanceRequest',
		'AssistanceDetails',
		'Timezone',
		'PrimaryLanguage',
		'Source',
		'AccountCode',
		'LocationCode',
		'IsActive'
	);	

	


	protected function __construct() {
		$this->db_Conn = new MSmysqliDb ($this->host, $this->user, $this->pwd, $this->db);
		$is_localhostCheck = $this->is_localhost();
		if(!$is_localhostCheck){
			$this->setLiveDatabase();
		}
		//print_r('I am in database');
		//print_r($this->db_Conn);
	}

	function is_localhost() {
	    $whitelist = array( 'http://postpage.azurewebsites.net', '::1' );
	    if( in_array( $_SERVER['REMOTE_ADDR'], $whitelist) ){
	    	echo "I In LIVE".$_SERVER['REMOTE_ADDR'];
	        return true;
	    }
	    echo "I am local hjosj".$_SERVER['REMOTE_ADDR'];
	    return false;
	}	

	function setLiveDatabase(){
		$this->servername 	= "postpage-mysqldbserver.mysql.database.azure.com";
		$this->username 	= "mysqldbuser@postpage-mysqldbserver";
		$this->password 	= "admin@123";
		$this->dbName 		= "microsoft_events_stage_v3";
	}

	protected function getDbConncetion(){
		return $this->db_Conn;
	}

	// protected function insert($tabelName,$record){
	// 	print_r('I am in insert');
	// 	print_r($this-> db_Conn);
	// 	//$insert = $this-> db_Conn -> insert ($tabelName,$record);
	// 	//return $insert;
	// }

}
class Sync extends MicrosoftDatabase {

	
	private $accessToken    		= "";
	private $curlError 	    		= array();
	private $defaultHeader  		= array();
	private $eventObject 			= array();
	private $db_conn 				= null;


	private $currentLocCode 		= '';
	private $currentProcessingTag 	= '';

	private $trackEventTagging      = array();


	private $storeRawEventObject 	= array();



	private $restoreEventMapping  	= array(
		"Address" => "LocationName",
		"Timezone" => "TimeZone"
	);

	/* Call Api */

	public function __construct($tokenUrl,$param) {
		echo "<pre>";
		$this->authentication($tokenUrl,$param);
		$db = new MicrosoftDatabase();
		$this->db_conn  =  $db->getDbConncetion();
	}

	/* GET REQUEST TO API */

	public function get($url,$param=null,$header=null){
		$ch = curl_init();
		if(!is_null($param)){
			$url = $url . "?" . http_build_query($param);
		}
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");	
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		$response = curl_exec($ch);
		$err = curl_error($ch);
		curl_close($ch);
		if ($err) {
			array_push($this->curlError,$err);
			return $err;
		} else {
		   $response = json_decode($response, TRUE);
		}
		return $response;

	}

	/* POST REQUEST TO API */

	public function post($url,$param,$header=null){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($param));
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);
		$response = curl_exec($ch);
		$err = curl_error($ch);
		curl_close($ch);
		if ($err) {
			array_push($this->curlError,$err);
			return $err;
		} else {
		   $response = json_decode($response, TRUE);
		}
		return $response;
	}

	/* GET ACESS TOKE */


	public function authentication($tokenUrl,$param){
		//echo "I am callerd";
		$getAccessToken = $this->post($tokenUrl,$param);
		if(array_key_exists("access_token",$getAccessToken)){
			$this->accessToken = $getAccessToken;
			$this->defaultHeader = array(
				"authorization: {$getAccessToken['token_type']} {$getAccessToken['access_token']}",
				"content-type: application/json"
			);
		}else{
			die("AUTH FAIL");
		}
		//echo $this->accessToken;
	}


	public function returnValue($array=array(),$keys=array()){
	  $myValue = array();
	  foreach($keys as $key => $value){
	  	$value = $this->lookUp($value);
	    if(!empty($array[$value])){
	      $val = $array[$value];
	      if(is_array($array[$value])){
	        $val = join(",",$array[$value]);
	      }else{
	        $val = $array[$value];
	      }
	    }
	    else{
	      $val = 'Not Found';
	    }
	    if($value == 'Code'){
	    	/* It Will Update Later ON with Location code -- as of now it will be <blank> */
	      $val  = "";
	    }
	    array_push($myValue,$val);
	  }
	  return array_combine ($keys , $myValue );
	}

	// function returnValue($array=array(),$keys=array(),$codePreFix=''){
	//   $myValue = array();
	//   foreach($keys as $key => $value){
	//   	$value = $this->lookUp($value);
	//     if(!empty($array[$value])){
	//       $val = $array[$value];
	//       if(is_array($array[$value])){
	//         $val = join(",",$array[$value]);
	//       }else{
	//         $val = $array[$value];
	//       }
	//     }
	//     else{
	//       $val = 'Not Found';
	//     }
	//     if($value == 'Code'){
	//       sleep(1);
	//       $date = new DateTime();
	//       $val  = $codePreFix.$date->getTimestamp();
	//     }
	//     array_push($myValue,$val);
	//   }
	//   return array_combine ($keys , $myValue );
	// }

	public function lookUp($key){
		if(array_key_exists($key,$this->restoreEventMapping)){
			return $this->restoreEventMapping[$key];
		}
		return $key;
	}

	function printEventData($event,$key){
		//print_r($event);
		$key = (int) $key + 1;
		echo "Event NO " . $key ; 
		echo "<hr>";
		echo "Event Id          ".$event['EventId']."<br>";
		echo "Event Title       ".$event['Title']."<br>";
		echo "Event EventsFor   ".join(' , ',$event['EventsFor'])."<br>";
		echo "Event Product     ".join(' , ',$event['Product'])."<br>";
		echo "Event Tag         ".join(' , ',$event['Tag'])."<br>";
		echo "<Hr>";
	
	}

	/* FETCH EVENTS */

	public function getMicrosoftEvents($eventUrl,$param,$storeEvent=true,$header=null){
		if(is_null($header)){
			$header = $this->defaultHeader;
		}
		$getEvents = $this->get($eventUrl,$param,$header);
		$this->eventObject = $getEvents;
		//$this->printEventData();
		if($storeEvent){
			$this->saveEvents();
		}
		return $getEvents;
	}

	public function normalizeObject($array,$normalizeValue,$sepration=','){
		$newArray = array();
		$allKeys  = array_keys($array);
		if(array_key_exists($normalizeValue,$array) && (strpos($array[$normalizeValue] , $sepration ) !== false)){
			$normalizeValArray 	= explode($sepration,$array[$normalizeValue]);
			$normalizedArray 	= array();
			foreach ($normalizeValArray as $key => $value) {
		        $normalizedArray 					= $array;
		        $normalizedArray[$normalizeValue] 	= $value;
		        array_push($newArray,$normalizedArray);
		    }
		}else{
			array_push($newArray,$array);	
		}
		return $newArray;
	}



	public function insertSingleObject($tableName,$object){
		$db = $this->db_conn;
		//$db->setTrace (true);
		$insert = $db -> insert($tableName,$object);
		//print_r($db->trace);
		return $insert;
	}

	public function insertMultiObject($tableName,$multiObject){
		$insertMultiResult = array();
		foreach($multiObject as $object){
			$insert = $this -> insertSingleObject($tableName,$object);
			array_push($insertMultiResult, $insert);
		}
		return $insertMultiResult;
	}

	public function getLocationCode(){
		return $this->currentLocCode['Code'];
	}

	public function setLocationCode($locObj,$inserteId=null,$codePreFix=''){
		if(!is_null($inserteId)){
			$db = $this-> db_conn;
			$db -> where('Id',$inserteId);
			$date = new DateTime();
			$LocationCodeNo = (int) $date->getTimestamp() +  (int) $inserteId ;
			$locObj['Code'] = $codePreFix.$LocationCodeNo;
			$loc = $db ->update('ms_location',$locObj);
		}
		$this->currentLocCode = $locObj;
	}


	public function isOfflineEvent($obj){
		if(array_key_exists('Category',$obj) && $obj['Category'] == 'Online' ){
			return false;
		}
		return true;
	}

	function checkIsSameArray($tempNormalizeOldObject , $selectAll){
			$letsCheckThis = array();
			foreach ($tempNormalizeOldObject as $obj) {
				foreach ($selectAll as $select) {
					if($obj == $select){
						array_push($letsCheckThis,true);
					}else{
						array_push($letsCheckThis,false);
					}
				}
			}
			$letsCheckThis = array_unique($letsCheckThis);
			if(count($letsCheckThis) == 1){
				return $letsCheckThis[0];
			}else{
				return false;
			}
	}

	public function checkEntryIfExist($tableName,$oldvalue,$valueTobeChecked = array(), $multpleValue = false){
		$db = $this->db_conn;
		$db->setTrace (true);
		$tempNormalizeOldObject = array();
		if($multpleValue){
			$condion = $oldvalue[0];
			$tempNormalizeOldObject = $oldvalue;
		}else{
			if(array_key_exists('Code',$oldvalue)){
				unset($oldvalue['Code']);
			}
			$condion = $oldvalue;
			array_push($tempNormalizeOldObject, $oldvalue);
		}
		foreach($valueTobeChecked as $val){
			$db -> where($val,$condion[$val]);
		}
		$selectAllRaw = $db->get($tableName);
		$selectAll = array_map(function($val){
			unset($val["Id"]);
			if(array_key_exists('Code',$val)){
				$this->setLocationCode($val);
				unset($val['Code']);
			}
			return $val; 
		},$selectAllRaw);

		//print_r($tempNormalizeOldObject);
		//print_r($selectAll);

		if($this->checkIsSameArray($tempNormalizeOldObject,$selectAll)){
			return true;
		}else{
			return false;
		}		
	}

	public function deleteMultipleObject($tableName,$oldvalue,$valueTobeChecked = array(), $multpleValue = false){
		$db = $this->db_conn;
		$tempNormalizeOldObject = array();
		if($multpleValue){
			$condion = $oldvalue[0];
			$tempNormalizeOldObject = $oldvalue;
		}else{
			if(array_key_exists('Code',$oldvalue)){
				unset($oldvalue['Code']);
			}
			$condion = $oldvalue;
			array_push($tempNormalizeOldObject, $oldvalue);
		}
		foreach($valueTobeChecked as $val){
			$db -> where($val,$condion[$val]);
		}
		$db -> delete($tableName);
		return true;
	}

	

	public function updateSingleObject($tableName,$oldvalue,$valueTobeChecked = array()){
		$db = $this->db_conn;
		foreach($valueTobeChecked as $value){
			$value = trim($value);			
			$db->where($value,$oldvalue[$value]);
		}
		$db_temp =  $db->copy();
		$id = $db->update($tableName,$oldvalue);
		if ($id){
			$select = $db_temp ->getOne($tableName);
			return $select;
		}
		else{
			die('Insertion Fails in tabel '.$tableName);
		}
	}

	
	public function checkUpdateRequired($tableName,$oldvalue,$valueTobeChecked = array(), $multpleValue = false){
		$db = $this->db_conn;
		$db->setTrace (true);
		$tempNormalizeOldObject = array();
		if($multpleValue){
			$condion = $oldvalue[0];
			$tempNormalizeOldObject = $oldvalue;
		}else{
			if(array_key_exists('Code',$oldvalue)){
				unset($oldvalue['Code']);
			}
			$condion = $oldvalue;
			array_push($tempNormalizeOldObject, $oldvalue);
		}
		//echo "Cond";
		//print_r($condion);
		foreach($valueTobeChecked as $val){
			$db -> where($val,$condion[$val]);
		}
		$selectAll = $db->get($tableName);
		//echo "Cont<HR>".$db->count;
		if($multpleValue){
			//echo "I am going to delete multiple".$oldvalue;
			/* Deleting all the multple record in the tabel holding multiple record */
			$deletedAllMultipleRecord = $this->deleteMultipleObject($tableName,$oldvalue,$valueTobeChecked);

			if($deletedAllMultipleRecord){
				/* Delete all the Records of multple -- Insert it again */ 
				return false;
			}
			return true;
		}else{

			if($db->count){
				return true;
			}
			return false;
		}
	}


	// public function checkUpdateRequired($tableName,$oldvalue,$valueTobeChecked = array(), $multpleValue = false){
	// 	$db = $this->db_conn;
	// 	$db->setTrace (true);
	// 	$tempNormalizeOldObject = array();
	// 	if($multpleValue){
	// 		$condion = $oldvalue[0];
	// 		$tempNormalizeOldObject = $oldvalue;
	// 	}else{
	// 		if(array_key_exists('Code',$oldvalue)){
	// 			unset($oldvalue['Code']);
	// 		}
	// 		$condion = $oldvalue;
	// 		array_push($tempNormalizeOldObject, $oldvalue);
	// 	}
	// 	echo "Cond";
	// 	print_r($condion);
	// 	foreach($valueTobeChecked as $val){
	// 		$db -> where($val,$condion[$val]);
	// 	}
	// 	$selectAll = $db->get($tableName);
	// 	echo "Cont<HR>".$db->count;
	// 	if($multpleValue){
	// 		echo "I am going to delete multiple".$oldvalue;
	// 		/* Deleting all the multple record in the tabel holding multiple record */
	// 		$deletedAllMultipleRecord = $this->deleteMultipleObject($tableName,$oldvalue,$valueTobeChecked);

	// 		if($deletedAllMultipleRecord){
	// 			/* Delete all the Records of multple -- Insert it again */ 
	// 			return false;
	// 		}
	// 		return true;
	// 	}else{

	// 		if($db->count){
	// 			return true;
	// 		}
	// 		return false;
	// 	}
	// }

	public function _bolNa($bol){
		if($bol){
			echo "True";
		}else{
			echo "False";
		}
	}

	public function getTag(){
		return $this->currentProcessingTag ;
	}

	public function setTag($tagName){
		$this->currentProcessingTag = $tagName;
	}

	public function checkMultipleEntryExist($tableName,$oldvalue,$valueTobeChecked = array(), $multpleValue = true){
		$db = $this->db_conn;
		if($multpleValue){
			$condion = $oldvalue[0];
			$tempNormalizeOldObject = $oldvalue;
		}
		foreach($valueTobeChecked as $val){
			$db -> where($val,$condion[$val]);
		}
		$db->get($tableName);
		if($db->count){
			return true;
		}
		return false;
	}


	public function insertEvent($tableName,$eventObject){
		$valueTobeChecked = array('EventId');		
		$isExist = $this->checkEntryIfExist($tableName,$eventObject,$valueTobeChecked);
		//echo "EXIST<br>".$isExist;
		if(!$isExist){
			$updateRequired = $this->checkUpdateRequired($tableName,$eventObject,$valueTobeChecked);
			if ($updateRequired){
				$eventObject['LocationCode'] = $this->getLocationCode();
				$this->updateSingleObject($tableName,$eventObject,$valueTobeChecked);
			}else{
				$insertedEvent		 = $this->insertSingleObject($tableName,$eventObject);
				//print_r($insertedEvent);
			}
		}
		//die(" I am stoped before for updaibg");
	}
	
	
	// public function insertLocation($tableName,$locationObject){
	// 	$valueTobeChecked = array('Address','Country');
	// 	$isExist = $this->checkEntryIfExist($tableName,$locationObject,$valueTobeChecked);
	// 	if(!$isExist){
	// 		//$updateRequired = $this->checkUpdateRequired($tableName,$locationObject,$valueTobeChecked);
	// 		// echo "<br>UPDATE<Br>".$updateRequired;
	// 		// if ($updateRequired){
	// 		// 	$this->updateSingleObject($tableName,$locationObject,$valueTobeChecked);
	// 		// 	$this->setLocationCode($locationObject);
	// 		// }else{
	// 		// 	$insertedLocation		 = $this->insertSingleObject($tableName,$locationObject);
	// 		// 	$this->setLocationCode($locationObject);
	// 		// }
	// 		$insertedLocation		 = $this->insertSingleObject($tableName,$locationObject);
	// 		$this->setLocationCode($locationObject);

	// 	}
	// 	//die("I am going to insert".true);

	// }


	public function insertLocation($tableName,$locationObject){
		/* Check All the colum for duplicate entry */
		$valueTobeChecked = $this->eventLocationFieldName;
		$valueTobeChecked = array_values(array_diff($valueTobeChecked, array("Code")));
		$isExist = $this->checkEntryIfExist($tableName,$locationObject,$valueTobeChecked);
		if(!$isExist){
			$insertedLocation		 = $this->insertSingleObject($tableName,$locationObject);
			$this->setLocationCode($locationObject,$insertedLocation,'LOC-');
		}
		

	}



	public function insertProduct($tableName,$productObject){
		$productNormalizedObject = $this->normalizeObject($productObject,'Product');
		$valueTobeChecked = array('EventId');
		$isExist = $this->checkMultipleEntryExist($tableName,$productNormalizedObject,$valueTobeChecked,true);
		if(!$isExist){
			$insertedProduct = $this->insertMultiObject($tableName,$productNormalizedObject);
		}else{
			$this->deleteMultipleEntry($tableName,$productNormalizedObject,$valueTobeChecked,true);
			$insertedProduct = $this->insertMultiObject($tableName,$productNormalizedObject);
		}
	}


	public function insertAudience($tableName,$audienceObject){
		$audienceNormalizedObject	= $this->normalizeObject($audienceObject,'EventsFor');
		$valueTobeChecked = array('EventId');
		$isExist = $this->checkMultipleEntryExist($tableName,$audienceNormalizedObject,$valueTobeChecked,true);
		if(!$isExist){
			$insertedAudience 			= $this->insertMultiObject($tableName,$audienceNormalizedObject);
		}else{
			$this->deleteMultipleEntry($tableName,$audienceNormalizedObject,$valueTobeChecked,true);
			$insertedAudience = $this->insertMultiObject($tableName,$audienceNormalizedObject);
		}
	}



	public function insertTag($tableName,$tagObject){
		$tagNormalizedObject	= $this->normalizeObject($tagObject,'Tag');
		$valueTobeChecked = array('EventId');
		$isExist = $this->checkMultipleEntryExist($tableName,$tagNormalizedObject,$valueTobeChecked,true);
		if(!$isExist){
			$insertedTag 			= $this->insertMultiObject($tableName,$tagNormalizedObject);
		}else{
			$this->deleteMultipleEntry($tableName,$tagNormalizedObject,$valueTobeChecked,true);
			$insertedTag = $this->insertMultiObject($tableName,$tagNormalizedObject);
		}
	}

	// public function insertProduct($tableName,$productObject){
	// 	$productNormalizedObject = $this->normalizeObject($productObject,'Product');
	// 	$valueTobeChecked = array('EventId');
	// 	print_r($productNormalizedObject);
	// 	$productNormalizedObject[1]["Product"] = "MAKE ME FUNNY";
	// 	$productNormalizedObject[1]["EventId"] = "205714";
	// 	$isExist = $this->checkEntryIfExist($tableName,$productNormalizedObject,$valueTobeChecked,true);
	// 	echo "IS EXIST".$isExist;
	// 	// print_r($productNormalizedObject);
	// 	if(!$isExist){
	// 		echo "I am tring to insert Product";
	// 		//$updateRequired  = $this->checkUpdateRequired($tableName,$productNormalizedObject,$valueTobeChecked,true);
	// 		// echo "PRoduct UPDATE REQ".$updateRequired;
	// 		$insertedProduct = $this->insertMultiObject($tableName,$productNormalizedObject);

	// 		// print_r($insertedProduct);
	// 	}else{
	// 		echo "I am not exisat";
	// 	}
	// }



	public function deleteMultipleEntry($tableName,$oldvalue,$valueTobeChecked = array(),$multpleValue = true){
		$db = $this->db_conn;
		if($multpleValue){
			$condion = $oldvalue[0];
			$tempNormalizeOldObject = $oldvalue;
		}
		foreach($valueTobeChecked as $val){
			$db -> where($val,$condion[$val]);
		}
		$db->delete($tableName);
	}

	

	public function storeRawEvent($key,$eventObject){
		$events = $this->modifyEventsObjects($eventObject['Events']);
		$this->storeRawEventObject[$key] = $events;
	}

	public function setEventObject($eventId,$eventTag,$eventObject){
		$trackEventTag  = $this->trackEventTagging;
		if(array_key_exists($eventId, $trackEventTag)){
			$currentTag = $trackEventTag[$eventId]['Tag'];
		}else{
			$currentTag = array();
		}
		array_push($currentTag, $eventTag);
		$trackEventTag[$eventId]['Tag'] = $currentTag;
		$trackEventTag[$eventId]['EventObject'] = $eventObject;
		$trackEventTag[$eventId]['EventObject']['Tag'] = $currentTag;
		$trackEventTag[$eventId] = $trackEventTag[$eventId]['EventObject'];
		//$trackEventTag[$eventId]['EventObject'] = $eventObject;
		//$trackEventTag[$eventId]['EventObject']['Tag'] = $currentTag;
		//$trackEventTag[$eventId] = $trackEventTag[$eventId]['EventObject'];
		$this->trackEventTagging = $trackEventTag;
		//$trackEventTag[$eventId]['Tag'] = $currentTag;
		//$eventObject['Tag'] = $this->trackEventTagging[$eventId];
		//return $eventObject;



		// $trackEventTag[$eventId] = $eventObject;

		// //print_r($trackEventTag);

	}

	public function normalizeFetchEvent(){
		$rawEventObj = $this->storeRawEventObject;
		foreach($rawEventObj as $currentTag => $events){
			foreach($events as $event){
				$eventId  = $event['EventId'];
				$this -> setEventObject($eventId,$currentTag,$event);
			}
		}
		//print_r($this->trackEventTagging);
		//die('I am stoped before Norm');
		//echo sizeof($this->trackEventTagging);
		//print_r($this->trackEventTagging);
		return $this->trackEventTagging;
		//print_r($this->storeRawEventObject);
	}
	



	public function modifyEventsObjects($objs){
		$newEventObejct = array_map(function($obj){
			$obj['EventId'] = "MS-".$obj['EventId'];
			return $obj;
		},$objs);
		return $newEventObejct;
	}


	/* Store Event */

	public function saveEvents($allEvents){
		$serachEvents  = $allEvents;
		$counter  = 0;
		foreach($serachEvents as $key => $event){
			//print_r($event);	
			//die("I am stoped");
			$isOffline = $this->isOfflineEvent($event);
			if($isOffline){
				$eventLocation 	= 	$this->returnValue($event,$this->eventLocationFieldName);
				$this->insertLocation("ms_location",$eventLocation);
				$event['LocationCode'] = $this->getLocationCode();
			}else{
				$event['LocationCode'] = "Online";
			}
			$eventData 		= 	$this->returnValue($event,$this->eventFieldName);
			$eventAudience 	= 	$this->returnValue($event,$this->eventAudience);
			$eventProduct 	= 	$this->returnValue($event,$this->eventProduct);
			$eventTag		= 	$this->returnValue($event,$this->eventTag);

			//print_r($eventTag);

			
			// die('I am stoped');


			$this->insertAudience("ms_audience",$eventAudience);
			$this->insertProduct("ms_product",$eventProduct);
			$this->insertEvent("ms_events",$eventData);
			$this->insertTag("ms_tag_product",$eventTag);
			$this->printEventData($event,$counter);
			$counter++;

			// if($counter == 1 ){
			// 	die('I am stoped');
			// }


		}
	}

}

$tokenUrl = "https://login.microsoftonline.com/microsoft.onmicrosoft.com/oauth2/token";
$eventUrl =	"https://geteventsservice.one.microsoft.com/api/Search";

$accessTokenParam = array(
	"grant_type" 	=> "client_credentials",
	"client_id" 	=> "ce371927-e793-4cc8-af6d-083ecf2b912c",
	"client_id" 	=> "ce371927-e793-4cc8-af6d-083ecf2b912c",
	"client_secret" => "vmOTW4Ot7taJbjesK24oL1Bauvmk0G5e/zeNHgaG6Kw=",
	"resource" 		=> "https://geteventsservice.one.microsoft.com/geteventservice"
);

$getEventParam = array(
	"RequestId" 	=> "ab1eed1a-ff06-4ae5-8690-b76fc01c7558",
	"language" 		=> "English",
	"IsPublishable" => "true"
);
// echo "<pre>";
// function array_equal($a, $b) {
//     return (
//         is_array($a) 
//         && is_array($b) 
//         && count($a) == count($b) 
//         && array_diff_assoc($a, $b) === array_diff_assoc($b, $a)
//     );
// }
// $a = array(array(1,2,3,4),array("A","B","C","D"));
// $b = array(array("A","B","C","D"),array(1,2,3,4));
// $arrayCheck = array_equal($a, $b);
// if($arrayCheck){
// 	echo "EQUAl";
// }else{
// 	echo "Not Equal";
// }
// print_r($a);

$eventSearchQuery = array("Microsoft Dynamics","Power BI","PowerApps","Microsoft Flow");
//$eventSearchQuery = array("PowerApps");
//$eventSearchQuery = array("PowerApps","Power BI");
$syncMe = new Sync($tokenUrl,$accessTokenParam);
foreach($eventSearchQuery as $searhQuery){
	$getEventParam['query'] = $searhQuery;
	echo "<Hr>";
	echo "<Hr>";
	echo $searhQuery;
	echo "<Hr>";
	echo "<Hr>";
	$syncMe -> setTag($searhQuery);
	$event 	= $syncMe -> getMicrosoftEvents($eventUrl,$getEventParam,false);
	$syncMe -> storeRawEvent($searhQuery,$event);
	
	//$evnets = $syncMe -> getMicrosoftEvents($eventUrl,$getEventParam);
}
$normalizedEvents = $syncMe -> normalizeFetchEvent();
$syncMe -> saveEvents($normalizedEvents);
?>