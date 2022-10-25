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

	// lookup Variable types
	protected function LookupVariableType($typeName) {

		$variableTypes = Array();
		$variableTypes['Boolean'] = 0;
		$variableTypes['Integer'] = 1;
		$variableTypes['Float'] = 2;
		$variableTypes['String'] = 3;

		if (array_key_exists($typeName, $variableTypes)) {

			return $variableTypes[$typeName];
		}
		else {

			return false;
		}
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

	protected function GetIdentsForDummyModuleId($moduleId) {

		$variables = IPS_GetChildrenIDs($moduleId);

		$variableIdents = Array();

		foreach ($variables as $variable) {

			$objectDetails = IPS_GetObject($variable);

			array_push($variableIdents, $objectDetails['ObjectIdent']);
		}
		
		return $variableIdents;
	}

	protected function MaintainDummyModule($moduleId, $variables) {

		if (! $variables) {

			return false;
		}

		// We save the maintained idents to perform a cleanup afterwards
		$maintainedIdents = Array();

		foreach ($variables as $variable) {

			// Check if the variable needs to be created
			$variableId = @IPS_GetObjectIDByIdent($variable->Ident, $moduleId);

			if (!$variableId) {

				$this->LogMessage("Variable with Ident " . $variable->Ident . " does not exist and will be created", "DEBUG");

				// The variable does not exist so we need to create it first
				$variableId = IPS_CreateVariable($this->LookupVariableType($variable->Type));
				IPS_SetParent($variableId, $moduleId);
				IPS_SetName($variableId, $variable->Name);
				IPS_SetIdent($variableId, $variable->Ident);

				// Assign the default value if it exists
				if (isset($variable->DefaultValue)) {

					SetValue($variableId, $variable->DefaultValue);
				}
			}

			// This part will be executed, independently if the variable was just created or did exist before
			
			// Check if a variable profile is defined and maintain it 
			if (isset($variable->Profile)) {

				$variableDetails = IPS_GetVariable($variableId);
				if ($variableDetails['VariableCustomProfile'] != $variable->Profile) {

					$this->LogMessage("Variable with Ident " . $variable->Ident . " gets the correct profile " . $variable->Profile . " assigned", "DEBUG");
					IPS_SetVariableCustomProfile($variableId, $variable->Profile);
				}
			}

			// Check if a sorting position is defined and maintain it
			if (isset($variable->Position)) {
			
				$objectDetails = IPS_GetObject($variableId);
				if ($objectDetails['ObjectPosition'] != $variable->Position) {

					$this->LogMessage("Variable with Ident " . $variable->Ident . " gets the correct position " . $variable->Position . " assigned", "DEBUG");
					IPS_SetPosition($variableId, $variable->Position);
				}
			}
		}
	}
}
?>
