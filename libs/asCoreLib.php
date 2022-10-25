<?php
/**
 * AsCoreLib:
 * ----------
 * v0.1
 */

const GUID_DUMMY="{485D0419-BE97-4548-AA9C-C083EB82E61E}";

// Klassendefinition
class AsCoreLib extends IPSModule {
 
	// Der Konstruktor des Moduls
	// Überschreibt den Standard Kontruktor von IPS
	public function __construct($InstanceID) {

		// Diese Zeile nicht löschen
        parent::__construct($InstanceID);
 
        // Selbsterstellter Code
    }
 
    // Überschreibt die interne IPS_Create($id) Funktion
    public function Create() {
            
		// Diese Zeile nicht löschen.
		parent::Create();

		// Properties
		$this->RegisterPropertyBoolean("DebugOutput", false);
		$this->RegisterPropertyString("ApiUsername","");
		$this->RegisterPropertyString("ApiPassword","");
		
    }

	public function Destroy() {

		// Never delete this line
		parent::Destroy();
	}
 
    // Überschreibt die intere IPS_ApplyChanges($id) Funktion
    public function ApplyChanges() {

       	// Diese Zeile nicht löschen
       	parent::ApplyChanges();
    }
	
	protected function LogMessage($message, $severity = 'INFO') {
		
		$logMappings = Array();
		$logMappings['DEBUG'] 	= 10206;
		$logMappings['INFO']	= 10201;
		$logMappings['NOTIFY']	= 10203;
		$logMappings['WARN'] 	= 10204;
		$logMappings['CRIT']	= 10205;
		
		$messageComplete = $severity . " - " . $message;

		// Write Debug output also as INFO when the DebutOutput switch is set
		if ( ($severity == 'DEBUG') && ($this->ReadPropertyBoolean('DebugOutput') == true )) {
			
			parent::LogMessage($messageComplete, 10201);
		}
		
		// Log message with original severity
		parent::LogMessage($messageComplete, $logMappings[$severity]);
	}

	protected function CallAPI($method, $url, $data = false) {
    
		$curl = curl_init();

		switch ($method)
		{
			case "POST":
				curl_setopt($curl, CURLOPT_POST, 1);
				curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

				if ($data)
					curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
				break;
			case "PUT":
				curl_setopt($curl, CURLOPT_PUT, 1);
				break;
			default:
				if ($data)
					$url = sprintf("%s?%s", $url, http_build_query($data));
		}

		// Optional Authentication:
		curl_setopt($curl, CURLOPT_VERBOSE, TRUE);

		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_USERPWD, $this->ReadPropertyString("ApiUsername") . ":" . $this->ReadPropertyString("ApiPassword") );

		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		$result = curl_exec($curl);

		curl_close($curl);

		return $result;
	}

	//Convenience Function to keep a similar way to interact with Variable values:
	protected function WriteValue($varIdent, $newValue) {

		SetValue($this->GetIDForIdent($varIdent), $newValue);
	}

	protected function ReadValue($varIdent) {

		return GetValue($varIdent);
	}

	// Function to standardize Ident fom Variable text creation:
	protected function generateIdent($variableDisplayName) {

		return preg_replace('/\s+/','',$variableDisplayName);
	}

	// Create a Dummy instance below the current instance:
	protected function CreateDummyModule($moduleName) {

		$instanceId = IPS_CreateInstance(GUID_DUMMY);
		IPS_SetName($instanceId, $moduleName);
		IPS_SetParent($instanceId, $this->InstanceID);

		return $instanceId;
	}

	protected function CheckDummyModule($moduleName) {

		$instanceId = @IPS_GetObjectIDByName($moduleName, $this->InstanceID);

		if ($instanceId) {

			$instanceInfo = IPS_GetInstance($instanceId);

			if ($instanceInfo['ModuleInfo']['ModuleID'] != GUID_DUMMY) {

				return false;
			}
		}
		
		return $instanceId;
	}
}
?>
