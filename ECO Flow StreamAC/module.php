<?php

declare(strict_types=1);
	class ECOFlowStreamAC extends IPSModule
	{
		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RequireParent('{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}');
			//$this->ConnectParent('{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}');

			
			$this->RegisterPropertyString("accessKey", "");
			$this->RegisterPropertyString("secretKey", "");

			$this->RegisterPropertyString ("deviceName", "") ;
			$this->RegisterPropertyString ("Seriennummer", "") ;
		
			$this->RegisterAttributeString("Mqtt_Password", "");
			$this->RegisterAttributeString("Mqtt_UserName", "");
			$this->RegisterAttributeString("Mqtt_ClientID", "");

			if (!IPS_VariableProfileExists('EF.feedGridMode')) 
			{
				IPS_CreateVariableProfile('EF.feedGridMode', VARIABLETYPE_INTEGER);
				IPS_SetVariableProfileText('EF.feedGridMode', '', '');
				IPS_SetVariableProfileValues ('EF.feedGridMode', 0, 1, 0);
				
				IPS_SetVariableProfileAssociation('EF.feedGridMode', 1, "Off","" , -1);
				IPS_SetVariableProfileAssociation('EF.feedGridMode', 2, "On","" , -1);
			}

			if (!IPS_VariableProfileExists('EF.status')) 
			{
				IPS_CreateVariableProfile('EF.status',VARIABLETYPE_BOOLEAN);
				IPS_SetVariableProfileText('EF.status', '', '');
				IPS_SetVariableProfileValues ('EF.status', 0, 1, 0);
				
				IPS_SetVariableProfileAssociation('EF.status', false, "Offline","" , -1);
				IPS_SetVariableProfileAssociation('EF.status', true, "Online","" , -1);
			}


			
			$this->RegisterVariableFloat("gridConnectionPower", "gridConnectionPower", "~Watt", 10) ;
			$this->RegisterVariableFloat("sysGridConnectionPower", "sysGridConnectionPower", "~Watt",11) ;	
			$this->RegisterVariableFloat("acTotalActivePower", "acTotalActivePower", "~Watt", 12) ;
			$this->RegisterVariableFloat("ongridInActivePower", "ongridInActivePower", "~Watt", 13) ;	
			$this->RegisterVariableFloat("powGetSysLoadFromBp", "powGetSysLoadFromBp", "~Watt", 14) ;	

			$this->RegisterVariableFloat("chgPowerLoopRef", "chgPowerLoopRef", "~Watt", 15) ;

			$this->RegisterVariableFloat("chgFromGridPowerLimited", "chgFromGridPowerLimited", "~Watt", 16) ;


			$this->RegisterVariableFloat("lanSysHomeNeedPwr", "lanSysHomeNeedPwr", "~Watt", 20) ;	
			$this->RegisterVariableFloat("lanSysTargetPwr", "lanSysTargetPwr", "~Watt", 21) ;
			$this->RegisterVariableFloat("powGetSysLoad", "powGetSysLoad", "~Watt", 40) ;


			$this->RegisterVariableFloat("loadPower", "loadPower", "~Watt", 41) ;
			
			$this->RegisterVariableFloat("bmsBattSoc", "bmsBattSoc", "~Valve.F", 30) ;

			$this->RegisterVariableFloat("backupReverseSoc", "backupReverseSoc", "~Valve.F",32) ;
			$this->EnableAction('backupReverseSoc');

			$this->RegisterVariableFloat("cmsMaxChgSoc", "cmsMaxChgSoc", "~Valve.F",34) ;
			$this->RegisterVariableFloat("cmsMinDsgSoc", "cmsMinDsgSoc", "~Valve.F",34) ;
	
			


			$this->RegisterVariableBoolean("operateTouModeOpen", "operateTouModeOpen", "", 80) ;
			$this->RegisterVariableBoolean("operateScheduledOpen", "operateScheduledOpen", "", 80) ;
			$this->RegisterVariableBoolean("operateSelfPoweredOpen", "operateSelfPoweredOpen", "", 80) ;
			$this->RegisterVariableBoolean("operateIntelligentScheduleModeOpen", "operateIntelligentScheduleModeOpen", "", 80) ;

			$this->EnableAction('operateTouModeOpen');
			$this->EnableAction('operateScheduledOpen');
			$this->EnableAction('operateSelfPoweredOpen');
			$this->EnableAction('operateIntelligentScheduleModeOpen');


			$this->RegisterVariableInteger("feedGridMode", "feedGridMode", "EF.feedGridMode", 30) ;
			$this->EnableAction('feedGridMode');

			$this->RegisterVariableBoolean("status", "status", "EF.status", 30) ;
			$this->RegisterVariableInteger("LastUpdateTime", "Letztes Update", "~UnixTimestamp", 5) ;
		

			$this->RegisterTimer("UpdateConnect", 0, 'EF_UpdateConnect(' . $this->InstanceID . ');');
			
		}

		public function Destroy()
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();
			$accessKey = $this->ReadPropertyString("accessKey");
			$secretKey = $this->ReadPropertyString("secretKey");
			$SN = $this->ReadPropertyString('Seriennummer');


			if ( ($accessKey == '') || ($secretKey == '') || ($SN == '') ) 
			{
				$this->SetStatus(200); //One of the Variable is missing
				return;
			} 
			$this->SetStatus(104); //noch inaktiv
		
			$filter = '.*' .$SN. '.*';
			
			$this->SetReceiveDataFilter($filter);

			
			$response = $this->getMQTTCertification();

			$this->WriteAttributeString("Mqtt_ClientID", substr( $response['eagleEyeTraceId'], 0, 21));
			$ClientID = $this->ReadAttributeString("Mqtt_ClientID");

	
			if ($response['message'] != 'Success')
			{
				$this->SetStatus(200); //One of the Variable is missing
				$this->LogMessage('Start getMQTTCertification Daten falsch'. json_encode($response) , KL_NOTIFY);
				return;
			}

			$this->WriteAttributeString("Mqtt_Password", $response['data']['certificatePassword']);
			$this->WriteAttributeString("Mqtt_UserName", $response['data']['certificateAccount']);
			$mqtt_url 	= $response['data']['url'];
			$mqtt_port 	= $response['data']['port'];

			//$config = json_decode( $this->GetConfigurationForParent(), true);
		
			$this_Instance = IPS_GetInstance($this->InstanceID);
			$id_Mqtt_Spliiter_Instance = $this_Instance['ConnectionID'];				
			$Mqtt_Spliiter_Instance = IPS_GetInstance($id_Mqtt_Spliiter_Instance);
			IPS_SetName($id_Mqtt_Spliiter_Instance, 'EcoFlow Mqtt Client('. $this->InstanceID .')' );

			$UserName = $this->ReadAttributeString('Mqtt_UserName');
			$PW = $this->ReadAttributeString('Mqtt_Password');
			
			$t1 =  '/open/'. $UserName. '/'. $SN .'/quota';
			$t2 =  '/open/'. $UserName. '/'. $SN .'/status';
			$t3 =  '/open/'. $UserName. '/'. $SN .'/set_reply';


			$t1_full = ['Topic' => $t1, 'QoS' => 0];
			$t2_full = ['Topic' => $t2, 'QoS' => 0];
			$t3_full = ['Topic' => $t3, 'QoS' => 0];

			$Subscriptions = [$t1_full, $t2_full, $t3_full];

			$old_config = IPS_GetConfiguration($id_Mqtt_Spliiter_Instance);
			$old_Subscriptions = [];
			if ($old_config)
			{
				$old_config = json_decode($old_config, true, 4);    
				$old_Subscriptions = json_decode($old_config['Subscriptions'], true, 4);
			}

			$Subscriptions = array_merge($Subscriptions, $old_Subscriptions);

			$this->LogMessage('Start MqttClient Splitter ' . $this->ReadPropertyString("deviceName") . print_r($Subscriptions,true) , KL_NOTIFY);	
		

			$config = array(
				'ClientID'      => $ClientID,
				'Password'      => $PW,
				'Subscriptions' => json_encode($Subscriptions,JSON_UNESCAPED_SLASHES),
				'UserName'      => $UserName
				);

			IPS_SetConfiguration($id_Mqtt_Spliiter_Instance, json_encode($config,JSON_UNESCAPED_SLASHES)); 
			IPS_Sleep(1*1000);
			

	
			$result = IPS_ApplyChanges($id_Mqtt_Spliiter_Instance);
			$this->LogMessage('Start MqttClient Splitter ' . $this->ReadPropertyString("deviceName") , KL_NOTIFY);	
		
			if (!$result)
			{
				$this->LogMessage('Start MqttClient Splitter ' . 'Mist aber auch', KL_NOTIFY);	
			}

			$id_Mqtt_Client_Instance = $Mqtt_Spliiter_Instance['ConnectionID'];
			IPS_SetName($id_Mqtt_Client_Instance, 'EcoFlow Mqtt Client Socket('. $id_Mqtt_Spliiter_Instance .')' );
			//$this->LogMessage('Start MqttClient id ' . $id_Mqtt_Client_Instance, KL_NOTIFY);


			$config = [
						'Host'      => $mqtt_url,
						'Open'      => true,
						'Port'      => $mqtt_port,
						'UseSSL'    => true,
						'VerifyHost'=> true,
						'VerifyPeer'=> false
						];

			IPS_SetConfiguration($id_Mqtt_Client_Instance, json_encode($config));
			IPS_Sleep(1*1000);

			$result = IPS_ApplyChanges($id_Mqtt_Client_Instance);

			if (!$result)
			{
				$this->LogMessage('Start MqttClient Socket ' . 'Mist aber auch', KL_NOTIFY);	
			}

			$this->SetStatus(102); //actice

			IPS_Sleep(5*1000);

			// Reaktion auf Statusänderung des Sockets
			//Unregister all messages
        	foreach ($this->GetMessageList() as $senderID => $messages) 
			{
            	foreach ($messages as $message) 
				{
                	$this->UnregisterMessage($senderID, $message);
            	}
        	}

			$this_Instance = IPS_GetInstance($this->InstanceID);
			$id_Mqtt_Spliiter_Instance = $this_Instance['ConnectionID'];
			$Mqtt_Spliiter_Instance = IPS_GetInstance($id_Mqtt_Spliiter_Instance);
			$id_Mqtt_Client_Instance = $Mqtt_Spliiter_Instance['ConnectionID'];

		
			$this->RegisterMessage($id_Mqtt_Client_Instance, IM_CHANGESTATUS);
		}

		public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
		{
			$this->SendDebug('Sender ' . $SenderID, 'Message ' . $Message, 0);
			if ($Message === IM_CHANGESTATUS) 
			{
				$MqttClientStatus = IPS_GetInstance($SenderID)['InstanceStatus'];
				$this->LogMessage('Status MQTT Client ' . $MqttClientStatus , KL_NOTIFY);
				if ($MqttClientStatus >= 200)
				{
					$this->SetTimerInterval("UpdateConnect", 60 * 1000);
				}
				else
				{
					$this->SetTimerInterval("UpdateConnect", 0);
				}
			}
		}

	


		public function setbackupReverseSoc(float $value)
		{
			if ($value < 3)
			{
				$value = 3;
			}
			else if ($value > 95)
			{
				$value = 95;
			}

			$value = (int)($value); 


			$UserName = $this->ReadAttributeString('Mqtt_UserName');
			$PW = $this->ReadAttributeString('Mqtt_Password');
			$SN = $this->ReadPropertyString('Seriennummer');		
			
			$tsend =  '/open/'. $UserName. '/'. $SN .'/set';
			
			$params = ['cfgBackupReverseSoc' => $value];

			$payload = ['id' => 123,
						'version' =>"1.0",
						"cmdId" => 17,
						"cmdFunc" => 254,
						"dirDest" =>  1,
						"dirSrc" =>  1,
						"dest" => 2 ,
						"needAck" => true,


						"params" => $params ];
						

			$send_data = [
				'DataID'           => '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}',
				'PacketType'       => 3,
				'QualityOfService' => 0,
				'Retain'           => true,
				'Topic'            => $tsend,
				'Payload'          => json_encode($payload)
				];

			$send_data_str = json_encode($send_data);


			$this->Send($send_data_str);				
		}

		
		public function setfeedGridMode(float $value)
		{
			if ($value < 1)
			{
				$value = 1;
			}
			else if ($value > 2)
			{
				$value = 2;
			}

			$value = (int)($value); 


			$UserName = $this->ReadAttributeString('Mqtt_UserName');
			$PW = $this->ReadAttributeString('Mqtt_Password');
			$SN = $this->ReadPropertyString('Seriennummer');		
			
			$tsend =  '/open/'. $UserName. '/'. $SN .'/set';
			
			$params = ['cfgFeedGridMode' => $value];

			$payload = ['id' => 123,
						'version' =>"1.0",
						"cmdId" => 17,
						"cmdFunc" => 254,
						"dirDest" =>  1,
						"dirSrc" =>  1,
						"dest" => 2 ,
						"needAck" => true,

						"params" => $params ];
						

			$send_data = [
				'DataID'           => '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}',
				'PacketType'       => 3,
				'QualityOfService' => 0,
				'Retain'           => true,
				'Topic'            => $tsend,
				'Payload'          => json_encode($payload)
				];

			$send_data_str = json_encode($send_data);


			$this->Send($send_data_str);				
		}

		

	

		public function RequestAction($Ident, $Value)
        {
            switch ($Ident) {
                case 'backupReverseSoc':
					$this->setbackupReverseSoc($Value);
					break;

				case 'feedGridMode':
					$this->setfeedGridMode($Value);
					break;

                default:
                    $this->SendDebug(__FUNCTION__, 'Invalid Action: ' . $Ident, 0);
                    break;
            }
        }

		/*
		public function GetConfigurationForParent()
        {
			$UserName = $this->ReadAttributeString('Mqtt_UserName');
			$PW = $this->ReadAttributeString('Mqtt_Password');

			$SN = $this->GetValue('Seriennummer');
			
			$t1 = array('Topic' => '/open/'. $UserName. '/'. $SN .'/quota', 'Retain' => true,'QoS' => 0);
			$t2 = array('Topic' => '/open/'. $UserName. '/'. $SN .'/status', 'Retain' => true,'QoS' => 0);
			//$t3 = array('Topic' => '/open/'. $UserName. '/'. $SN .'/#', 'Retain' => true,'QoS' => 0);
			
			$Subscriptions = [$t1,  $t2];
			$Subscriptions = json_encode($Subscriptions, 1);

			$this->LogMessage('GetConfiguration ' . $Subscriptions , KL_NOTIFY);	
			$ClientID = $this->ReadAttributeString("Mqtt_ClientID");
		

			
			$settings = [
				"ClientID" => $ClientID,
				"Password" => $PW,
				"UserName" => $UserName,
				"Retain" => true,
				"Subscriptions" => $Subscriptions
            ];

            return json_encode($settings, JSON_UNESCAPED_SLASHES);
        }
*/		

		public function Send(string $PayLoad)
		{
			
			$this->LogMessage('SendData '. $PayLoad,KL_NOTIFY );
			$this->SendDataToParent($PayLoad);
		}
		

		public function ReceiveData($JSONString)
		{
			$data = json_decode($JSONString, true);
			$this->SendDebug(__FUNCTION__,  $JSONString, 0);
			
			if ($data === false)
			{
				$this->LogMessage('ReceiveData' . "Daten Fehlerhaft", KL_NOTIFY);
			}

			$Payload = json_decode($data['Payload'], true);
	

			// Daten nach Veränderung				
			if (array_key_exists('data', $Payload))
			{
				$Payload = $Payload['data'];
				//$this->LogMessage('ReceiveDataArray SetDataConf_Data' . print_r($Payload, true), KL_NOTIFY);

				if (array_key_exists('cfgBackupReverseSoc', $Payload))
				{
					$this->setvalue("backupReverseSoc", $Payload['cfgBackupReverseSoc']);
				}

				if (array_key_exists('cfgBackupReverseSoc', $Payload))
				{
					$this->setvalue("backupReverseSoc", $Payload['cfgBackupReverseSoc']);
				}
			}

			

			if (array_key_exists('quota_cloud_ts', $Payload))
			{
				$this->setvalue("LastUpdateTime", $Payload['quota_cloud_ts']);
			}

			
			if (array_key_exists('cmsMaxChgSoc', $Payload))
			{
				$this->setvalue("cmsMaxChgSoc", $Payload['cmsMaxChgSoc']);
			}

			if (array_key_exists('cmsMinDsgSoc', $Payload))
			{
				$this->setvalue("cmsMinDsgSoc", $Payload['cmsMinDsgSoc']);
			}

			if (array_key_exists('gridConnectionPower', $Payload))
			{
				$this->setvalue("gridConnectionPower", $Payload['gridConnectionPower']);
			}

			if (array_key_exists('sysGridConnectionPower', $Payload))
			{
				$this->setvalue("sysGridConnectionPower", $Payload['sysGridConnectionPower']);
			}

			if (array_key_exists('ongridInActivePower', $Payload))
			{
				$this->setvalue("ongridInActivePower", $Payload['ongridInActivePower']);
			}
			
			if (array_key_exists('acTotalActivePower', $Payload))
			{
				$this->setvalue("acTotalActivePower", $Payload['acTotalActivePower']);
			}

			if (array_key_exists('powGetSysLoadFromBp', $Payload))
			{
				$this->setvalue("powGetSysLoadFromBp", $Payload['powGetSysLoadFromBp']);
			}

			if (array_key_exists('lanSysHomeNeedPwr', $Payload))
			{
				$this->setvalue("lanSysHomeNeedPwr", $Payload['lanSysHomeNeedPwr']);
			}
			
			if (array_key_exists('lanSysTargetPwr', $Payload))
			{
				$this->setvalue("lanSysTargetPwr", $Payload['lanSysTargetPwr']);
			}
			
			if (array_key_exists('powGetSysLoad', $Payload))
			{
				$this->setvalue("powGetSysLoad", $Payload['powGetSysLoad']);
			}

			if (array_key_exists('chgPowerLoopRef', $Payload))
			{
				$this->setvalue("chgPowerLoopRef", $Payload['chgPowerLoopRef']);
			}


			if (array_key_exists('bmsBattSoc', $Payload))
			{
				$this->setvalue("bmsBattSoc", $Payload['bmsBattSoc']);
			}

			if (array_key_exists('backupReverseSoc', $Payload))
			{
				$this->setvalue("backupReverseSoc", $Payload['backupReverseSoc']);
			}
			
			if (array_key_exists('feedGridMode', $Payload))
			{
				$this->setvalue("feedGridMode", $Payload['feedGridMode']);
			}

			if (array_key_exists('energyStrategyOperateMode', $Payload))
			{
				$this->LogMessage('ReceiveDataArray EnergyStrategy' . print_r($Payload, true), KL_NOTIFY);
				$Payload = $Payload['energyStrategyOperateMode'];

				if (array_key_exists('operateTouModeOpen', $Payload))
				{
					$this->setvalue("operateTouModeOpen", $Payload['operateTouModeOpen']);
				}	

				if (array_key_exists('operateScheduledOpen', $Payload))
				{
					$this->setvalue("operateScheduledOpen", $Payload['operateScheduledOpen']);
				}


				if (array_key_exists('operateSelfPoweredOpen', $Payload))
				{
					$this->setvalue("operateSelfPoweredOpen", $Payload['operateSelfPoweredOpen']);
				}	

				if (array_key_exists('operateScheduledOpen', $Payload))
				{
					$this->setvalue("operateIntelligentScheduleModeOpen", $Payload['operateIntelligentScheduleModeOpen']);
				}	
			}

			

			if (array_key_exists('dayResidentLoadList', $Payload))
			{
				//$this->LogMessage('ReceiveDataArray Loadlist' . print_r($Payload['dayResidentLoadList'], true), KL_NOTIFY);
				//$this->LogMessage('ReceiveDataArray Loadlist' . print_r($Payload['dayResidentLoadList']['load'][0]['loadPower'], true), KL_NOTIFY);
							
				$this->setvalue("loadPower", $Payload['dayResidentLoadList']['load'][0]['loadPower'],);
			}

			if (array_key_exists('allTimerTask', $Payload))
			{
				//$this->LogMessage('ReceiveDataArray Loadlist' . print_r($Payload['dayResidentLoadList'], true), KL_NOTIFY);
				//$this->LogMessage('ReceiveDataArray Loadlist' . print_r($Payload['dayResidentLoadList']['load'][0]['loadPower'], true), KL_NOTIFY);
							
				$this->setvalue("chgFromGridPowerLimited", $Payload['allTimerTask']['timeTask'][0]['chgTask']['devTargetSoc'][0]['chgFromGridPowerLimited'],);
			}


		

			if (array_key_exists('params', $Payload))
			{
				$Payload = $Payload['params'];
				if (array_key_exists('status', $Payload))
				{
					$this->setvalue("status", $Payload['status']);
				}
			}
		}

		public function UpdateConnect()
		{

			$this_Instance = IPS_GetInstance($this->InstanceID);
			$id_Mqtt_Spliiter_Instance = $this_Instance['ConnectionID'];
			$Mqtt_Spliiter_Instance = IPS_GetInstance($id_Mqtt_Spliiter_Instance);

			$id_Mqtt_Client_Instance = $Mqtt_Spliiter_Instance['ConnectionID'];

			$MqttClientStatus = IPS_GetInstance($id_Mqtt_Client_Instance)['InstanceStatus'];
			
			if ($MqttClientStatus >=200)
			{
				$this->LogMessage('UpdateConnect' . 'Status Mqtt Client: '. $MqttClientStatus, KL_NOTIFY);
				//$result = IPS_ApplyChanges($id_Mqtt_Client_Instance);
			}
		}


		public function getMQTTCertification() 
		{
			
			$HOST = "https://api-e.ecoflow.com";
			$GET_MQTT_CERTIFICATION_URL = $HOST . "/iot-open/sign/certification";
			$DEVICE_LIST_URL = $HOST . "/iot-open/sign/device/list";
			$SET_QUOTA_URL = $HOST . "/iot-open/sign/device/quota";
			$GET_QUOTA_URL = $HOST . "/iot-open/sign/device/quota";
			$GET_ALL_QUOTA_URL = $HOST . "/iot-open/sign/device/quota/all";
			$accessKey = $this->ReadPropertyString("accessKey");
			$secretKey = $this->ReadPropertyString("secretKey");
			
			$jsonObject = [];
			$response = $this->getHttpUriRequest("GET", $GET_MQTT_CERTIFICATION_URL, $jsonObject, $accessKey, $secretKey);
			return $response;
		}


		private function getHttpUriRequest($httpMethod, $url, $req, $accessKey, $secretKey) 
		{
			$nonce = $this->createNonce(); //(string) random_int(100000, 999999);
			$timestamp = $this->createTimestamp(); //time() * 1000;
			$signature = $this->generateSignature($nonce, $timestamp, $req, $accessKey, $secretKey);
		
			$headers = array(
				'Content-Type: application/json;charset=UTF-8',
				'accessKey:'.$accessKey,
				'nonce:'.$nonce,
				'timestamp:'.$timestamp,
				'sign:'.$signature,
			);
		
			if ($httpMethod === 'GET') {
				$curl = curl_init($url);
				curl_setopt($curl, CURLOPT_URL, $url);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
				$request = json_decode(curl_exec($curl), true);
				curl_close($curl);
			} elseif ($httpMethod === 'PUT') {
				$curl = curl_init($url);
				curl_setopt($curl, CURLOPT_URL, $url);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
				curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($req));
				curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
				$request = json_decode(curl_exec($curl), true);
				curl_close($curl);
			} elseif ($httpMethod === 'POST') {
				$curl = curl_init($url);
				curl_setopt($curl, CURLOPT_URL, $url);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
				curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($req));
				curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
				$request = json_decode(curl_exec($curl), true);
				curl_close($curl);
			} 
			else 
			{
				throw new Exception("HTTP method not supported");
			}
			return $request;
				throw new RuntimeException('Error getting information: ' . $request['message']);
		}


		/**
		* Generates a signature for the given nonce, timestamp, and data.
		*
		* The generated signature is used for authentication purposes in the EcoFlow API. It ensures the integrity and
		* security of the communication between the client and the API by verifying the authenticity of the request.
		*
		* The method follows these steps to generate the signature:
		* 1. Flattens the input `$data` array into a single-dimensional array using the `flattenData()` helper function.
		* 2. Sorts the flattened data array alphabetically by the keys using the `ksort()` function with the `SORT_STRING`
		* flag.
		* 3. Concatenates the flattened and sorted data array into a string using the `http_build_query()` function.
		* 4. Appends the access key, nonce, and timestamp to the concatenated string using the `sprintf()` function.
		* 5. Removes any leading ampersand (`&`) from the resulting base string using the `ltrim()` function.
		* 6. Encrypts the base string using the HMAC-SHA256 algorithm along with the secret key.
		* 7. Converts the resulting byte array into a hexadecimal string using the `bin2hex()` function.
		*
		* @param string $nonce The nonce value. This is a random string that is used once in each request to prevent
		*                          replay attacks.
		* @param string $timestamp The timestamp value. This is the current time in milliseconds.
		* @param array{
		*     sn?: string,
		*     cmdCode?: string,
		*     params?: array<string, string|int>
		* } $data The data to be included in the signature. This can include the serial number (sn), command code
		*     (cmdCode), and other parameters (params).
		*
		* @return string The generated signature. This is a hexadecimal string that is used for authentication in the
		*                EcoFlow API.
		*
		* @throws Exception If an error occurs during the generation of the signature.
		*/
		private function generateSignature(string $nonce, string $timestamp, array $data, $accessKey, $secretKey) : string 
		{
			//global $accessKey, $secretKey;
			// Flatten, sort, and concatenate the data array.
			$flattenedData = $this->flattenData($data);
			ksort($flattenedData, SORT_STRING);

			// Concatenate accessKey, nonce, and timestamp.
			$signatureBase = http_build_query($flattenedData);
			$signatureBase = urldecode($signatureBase);
			$signatureBase .= sprintf('&accessKey=%s&nonce=%s&timestamp=%s', $accessKey, $nonce, $timestamp);
			$signatureBase = ltrim($signatureBase, '&');

			// Encrypt with HMAC-SHA256 and secretKey.
			$signatureBytes = hash_hmac('sha256', $signatureBase, $secretKey, true);

			// Convert bytes to hexadecimal string.
			return bin2hex($signatureBytes);
		}


		/**
		* Flattens a multidimensional array into a one-dimensional array with dot notation keys.
		*
		* This method is used to flatten a multidimensional array into a one-dimensional array. The keys of the
		* one-dimensional array are generated by concatenating the keys of the multidimensional array with a dot ('.').
		* The keys are prefixed with the provided prefix, if any. If the key is 0, it is replaced with an empty string.
		*
		* If the value of a key-value pair is an array, a recursive call is made to flatten the nested array. The nested
		* array is passed as the `$data` parameter, and the newly generated key is passed as the `$prefix` parameter.
		*
		* After processing all the key-value pairs, the method returns the `$flattened` array, which contains the
		* flattened version of the input `$data` array.
		*
		* @param array<int|string, array<int|string, array<int, string>|int|string>|string>|array<string, int|string> $data
		*
		* @return array<int|string, int|string> The flattened one-dimensional array with dot notation keys.
		*/
		private function flattenData(array $data, string $prefix = ''): array 
		{
			$flattened = [];
			foreach ($data as $key => $value) 
			{
				if (is_integer($key)) 
				{
					$newKey = $prefix === '' ? $key : sprintf('%s%s', $prefix, "[". $key."]");
				}
				else
				{
					$newKey = $prefix === '' ? $key : sprintf('%s.%s', $prefix, $key);
				}
				$newKey = is_string($newKey) ? rtrim($newKey, '.') : (string) $newKey;

				if (is_array($value)) 
				{
					// Recursive call for nested arrays.
					$flattened = array_merge($flattened, flattenData($value, $newKey));

				continue;
				}
	
			// Append to a flattened array.
			$flattened[$newKey] = $value;
			}
		return $flattened;
	}


		/**
			* Generates a random nonce.
			*
			* This method generates a random nonce for the EcoFlow API. A nonce is a random string that is used once in each
			* request to prevent replay attacks. The nonce is generated as a random integer between 100000 and 999999, which is
			* then converted to a string.
			*
			* The purpose of the nonce is to ensure the uniqueness and integrity of each API request by including a one-time,
			* randomly generated value. This helps to protect against duplicate or replayed requests.
			*
			* The method utilises PHP's built-in `random_int()` function to generate a cryptographically secure random integer
			* within the specified range. The generated integer is then cast to a string to be included in the API request.
			*
			* @return string The randomly generated nonce as a string.
			*
			* @throws RandomException If an error occurs during the generation of the random integer, such as a failure of the
			*                         random number generator or an invalid range.
			*/
		private function createNonce(): string 
		{
			return (string) random_int(100000, 999999);
		}


		/**
		* Generates a timestamp in milliseconds.
		*
		* This method generates a timestamp for use in the EcoFlow API. The timestamp is created by instantiating a
		* DateTime object with the current time in the UTC time zone. The DateTime object is then formatted to include
		* microseconds using the format string 'U.u'. The formatted time is multiplied by 1000 to convert it to
		* milliseconds and rounded to the nearest whole number.
		*
		* The generated timestamp is used as part of the authentication process when making requests to the EcoFlow API.
		* It ensures that the timestamp is based on a standardised time reference and is not affected by local time
		* differences, making it suitable for use in a distributed system.
		*
		* @return string The generated timestamp as a string representation of the current time in milliseconds.
		*
		* @throws Exception If an error occurs during the creation of the DateTime object or when formatting the time.
		*                   The calling code should handle this exception appropriately.
		*/
		private function createTimestamp(): string 
		{
			$dateTime = new DateTime((string) null, new DateTimeZone('UTC'));
			$formatted = (int) $dateTime->format('U.u');

			return (string) round($formatted * 1000);
		}
	}