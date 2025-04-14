<?php

declare(strict_types=1);
	class PowerStream extends IPSModule
	{
		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RequireParent('{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}');
			
			$this->RegisterPropertyString("accessKey", "");
			$this->RegisterPropertyString("secretKey", "");

			$this->RegisterVariableString ("deviceName", "deviceName",  "", 10) ;
			$this->RegisterVariableString ("Seriennummer", "Seriennummer",  "", 10) ;

			//$this->RegisterVariableString("Password", "Password", "" , 20) ;
			//$this->RegisterVariableString("UserName", "UserName", "", 20) ;

			$this->RegisterAttributeString("Mqtt_Password", "");
			$this->RegisterAttributeString("Mqtt_UserName", "");
			
			$this->RegisterVariableInteger("OutputWatts", "OutputWatts", "", 30) ;


			$this->RegisterTimer("UpdateConnect", 20*1000, 'ECOFLOW_UpdateConnect(' . $this->InstanceID . ');');
			
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
	

			if ( ($accessKey == '') || ($secretKey == '')) 
			{
				$this->SetStatus(200); //One of the Variable is missing
				return;
			} 
			$this->SetStatus(104); //noch inaktiv

		

			$HOST = "https://api-e.ecoflow.com";
			$GET_MQTT_CERTIFICATION_URL = $HOST . "/iot-open/sign/certification";
			$DEVICE_LIST_URL = $HOST . "/iot-open/sign/device/list";
			$SET_QUOTA_URL = $HOST . "/iot-open/sign/device/quota";
			$GET_QUOTA_URL = $HOST . "/iot-open/sign/device/quota";
			$GET_ALL_QUOTA_URL = $HOST . "/iot-open/sign/device/quota/all";
			
			$response = $this->deviceList($DEVICE_LIST_URL);

			$this->SetValue("deviceName", $response['data'][0]['deviceName']);//sn deines Gerätes
			$this->SetValue("Seriennummer", $response['data'][0]['sn']);//sn deines Gerätes


			$response = $this->getMQTTCertification($GET_MQTT_CERTIFICATION_URL);
			if ($response['message'] != 'Success')
			{
				$this->SetStatus(200); //One of the Variable is missing
				$this->LogMessage('Start getMQTTCertification Daten falsch'. json_encode($response) , KL_NOTIFY);
				return;
			}

			//$this->SetValue("Password", $response['data']['certificatePassword']);
			//$this->SetValue("UserName", $response['data']['certificateAccount']);
		
			$this->WriteAttributeString("Mqtt_Password", $response['data']['certificatePassword']);
			$this->WriteAttributeString("Mqtt_UserName", $response['data']['certificateAccount']);
			

			$config = json_decode( $this->GetConfigurationForParent(), true);
		
			$this_Instance = IPS_GetInstance($this->InstanceID);
			$id_Mqtt_Spliiter_Instance = $this_Instance['ConnectionID'];				
			$Mqtt_Spliiter_Instance = IPS_GetInstance($id_Mqtt_Spliiter_Instance);
			IPS_SetName($id_Mqtt_Spliiter_Instance, 'EcoFlow Mqtt Client('. $this->InstanceID .')' );
		

			$this->LogMessage('Start Mqttsplitter ' . json_encode($Mqtt_Spliiter_Instance), KL_NOTIFY);

			$id_Mqtt_Client_Instance = $Mqtt_Spliiter_Instance['ConnectionID'];
			IPS_SetName($id_Mqtt_Client_Instance, 'EcoFlow Mqtt Client Socket('. $id_Mqtt_Spliiter_Instance .')' );
			$this->LogMessage('Start MqttClient id ' . $id_Mqtt_Client_Instance, KL_NOTIFY);

			
			IPS_SetConfiguration($id_Mqtt_Client_Instance, '{
				"Host":"mqtt-e.ecoflow.com",
				"Open":true,
				"Port":8883,
				"UseSSL":true,
				"VerifyHost":true,
				"VerifyPeer":false}');
			$result = IPS_ApplyChanges($id_Mqtt_Client_Instance);
			if ($result)
			$this->LogMessage('Start MqttClient id ' . 'Erfolg', KL_NOTIFY);
			else
			$this->LogMessage('Start MqttClient id ' . 'M ist aber auch', KL_NOTIFY);	

			$this->SetStatus(102); //actice

		
		}

		public function GetConfigurationForParent()
        {
			$UserName = $this->ReadAttributeString('Mqtt_UserName');
			$PW = $this->ReadAttributeString('Mqtt_Password');

			$SN = $this->GetValue('Seriennummer');
			
			$t1 = array('Topic' => '/open/'. $UserName. '/'. $SN .'/quata', 'QoS' => 0);
			$t2 = array('Topic' => '/open/'. $UserName. '/'. $SN .'/status', 'QoS' => 0);
			
			$Subscriptions = [$t1,  $t2];
			$Subscriptions = json_encode($Subscriptions, 1);
			
			$settings = [
				"ClientID" => "828a6b70d88f5f9c88678",
				
				"Password" => $PW,
				"UserName" => $UserName,
			
				"Subscriptions" => $Subscriptions
            ];

            return json_encode($settings, JSON_UNESCAPED_SLASHES);
        }
		
		/*
		public function GetConfigurationForParent()
        {
			$settings = [
          		"UseSSL"			=>true,
		  		"VerifyHost"		=>false,
				"VerifyPeer"		=>false,
                'Host'              => 'mqtt-e.ecoflow.com',
                'Port'              => 8883,
				//"Open"			=> true
            ];

            return json_encode($settings, JSON_UNESCAPED_SLASHES);
        }*/

		public function Send(string $PayLoad)
		{
			$this->SendDataToParent(json_encode(['DataID' => '{018EF6B5-AB94-40C6-AA53-46943E824ACF}', "Payload" => $PayLoad]));
			//$this->SendDataToParent(json_encode(['DataID' => '{018EF6B5-AB94-40C6-AA53-46943E824ACF}', $PayLoad]));
		}
		

		public function ReceiveData($JSONString)
		{
			$data = json_decode($JSONString, true);
			if ($data === false)
			{
				IPS_LogMessage('Device RECV', 'Datenfehlerhaft');
			}

			$Payload_params = json_decode($data['Payload'], true)['param'];

			//$this->SendDebug(__FUNCTION__, 'Daten Empfnagen: ' . $JSONString, 0);
			if (array_key_exists('invOutputWatts', $Payload_params))
			{
            	$this->setvalue("OutputWatts", intval($Payload_params['invOutputWatts'])/10);
			}
		}

		public function UpdateConnect()
		{
			$this_Instance = IPS_GetInstance($this->InstanceID);
			$id_Mqtt_Spliiter_Instance = $this_Instance['ConnectionID'];
			$Mqtt_Spliiter_Instance = IPS_GetInstance($id_Mqtt_Spliiter_Instance);

			$id_Mqtt_Client_Instance = $Mqtt_Spliiter_Instance['ConnectionID'];
			//$this->LogMessage('Start MqttClient id ' . $id_Mqtt_Client_Instance, KL_NOTIFY);

			
			//$currentStatus = $this->GetStatus();
			//$this->LogMessage('UpdateConnect' . 'Status '. $currentStatus, KL_NOTIFY);

			$MqttClientStatus = IPS_GetInstance($id_Mqtt_Client_Instance)['InstanceStatus'];
			$this->LogMessage('UpdateConnect' . 'Status Mqtt Client: '. $MqttClientStatus, KL_NOTIFY);

			if ($MqttClientStatus >=200)
			{
				$result = IPS_ApplyChanges($id_Mqtt_Client_Instance);
			}

			
		}

		public function deviceList(string $url) 
		{
	
			$accessKey = $this->ReadPropertyString("accessKey");
			$secretKey = $this->ReadPropertyString("secretKey");

			$jsonObject = [];
			$response = $this->getHttpUriRequest("GET", $url, $jsonObject, $accessKey, $secretKey);
			
			$this->SendDebug(__FUNCTION__, 'deviceList: ' . json_encode($response), 0);
        
			
			if ($response['code'] === '0') 
			{
				$data = $response;
				return $data;
			}
			//	throw new RuntimeException('Error getting deviceList: ' . $response['message']);
		}


		public function getMQTTCertification(string $url) 
		{
			$accessKey = $this->ReadPropertyString("accessKey");
			$secretKey = $this->ReadPropertyString("secretKey");
			
			$jsonObject = [];
			$response = $this->getHttpUriRequest("GET", $url, $jsonObject, $accessKey, $secretKey);
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