<?php

// Klassendefinition
class HpeIlo extends IPSModule {
 
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
		$this->RegisterPropertyString("Sender","HpeIlo");
		$this->RegisterPropertyInteger("RefreshInterval",0);
		$this->RegisterPropertyString("hostname","");
		$this->RegisterPropertyString("username","");
		$this->RegisterPropertyString("password","");
		
		// Variable profiles
		$variableProfileHealthState = "HPEILO.HealthState";
		if (IPS_VariableProfileExists($variableProfileHealthState) ) {
		
			IPS_DeleteVariableProfile($variableProfileHealthState);
		}			
		IPS_CreateVariableProfile($variableProfileHealthState, 0);
		IPS_SetVariableProfileIcon($variableProfileHealthState, "Help");
		IPS_SetVariableProfileAssociation($variableProfileHealthState, 1, "Healthy", "", 0x00FF00);
		IPS_SetVariableProfileAssociation($variableProfileHealthState, 0, "Unhealthy", "", 0xFF0000);
	

		$variableProfilePowerState = "HPEILO.PowerState";
		if (IPS_VariableProfileExists($variableProfilePowerState) ) {
		
			IPS_DeleteVariableProfile($variableProfilePowerState);
		}			
		IPS_CreateVariableProfile($variableProfilePowerState, 1);
		IPS_SetVariableProfileIcon($variableProfilePowerState, "Electricity");
		IPS_SetVariableProfileAssociation($variableProfilePowerState, 2, "On", "", 0x00FF00);
		IPS_SetVariableProfileAssociation($variableProfilePowerState, 1, "Starting", "", 0x80FF80);
		IPS_SetVariableProfileAssociation($variableProfilePowerState, 0, "Off", "", 0xC0C0C0);
	

		// Variables
		$this->RegisterVariableInteger("Status","Status", $variableProfilePowerState);
		$this->RegisterVariableBoolean("SystemHealth","System Health",$variableProfileHealthState);
		$this->RegisterVariableFloat("TemperatureInlet","Temperature - Inlet","~Temperature");
		$this->RegisterVariableFloat("TemperatureCpu1","Temperature - CPU 1","~Temperature");
		$this->RegisterVariableFloat("TemperatureCpu2","Temperature - CPU 2","~Temperature");
		$this->RegisterVariableFloat("TemperatureGpu","Temperature - GPU","~Temperature");
		$this->RegisterVariableFloat("TemperaturePs1","Temperature - Power Supply 1","~Temperature");
		$this->RegisterVariableFloat("TemperaturePs2","Temperature - Power Supply 2","~Temperature");
		$this->RegisterVariableFloat("TemperatureSystemBoard","Temperature - System Board","~Temperature");
		$this->RegisterVariableFloat("PowerConsumption","Power Consumption","~Watt.3680");
		$this->RegisterVariableBoolean("PowerSupply1Health","Power Supply 1 Health", $variableProfileHealthState);
		$this->RegisterVariableBoolean("PowerSupply2Health","Power Supply 2 Health", $variableProfileHealthState);
		

		// Default Actions
		$this->EnableAction("Status");

		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'HPEILO_RefreshInformation($_IPS[\'TARGET\']);');

    }

	public function Destroy() {

		// Never delete this line
		parent::Destroy();
	}
 
    // Überschreibt die intere IPS_ApplyChanges($id) Funktion
    public function ApplyChanges() {

		
		$newInterval = $this->ReadPropertyInteger("RefreshInterval") * 1000;
		$this->SetTimerInterval("RefreshInformation", $newInterval);
		

       	// Diese Zeile nicht löschen
       	parent::ApplyChanges();
    }


	public function GetConfigurationForm() {

        	
		// Initialize the form
		$form = Array(
            	"elements" => Array(),
				"actions" => Array()
        	);

		// Add the Elements
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "RefreshInterval", "caption" => "Refresh Interval");
		$form['elements'][] = Array("type" => "ValidationTextBox", "name" => "hostname", "caption" => "Hostname or IP address");
		$form['elements'][] = Array("type" => "ValidationTextBox", "name" => "username", "caption" => "Username");
		$form['elements'][] = Array("type" => "PasswordTextBox", "name" => "password", "caption" => "Password");
		

		// Add the buttons for the test center
		$form['actions'][] = Array("type" => "Button", "label" => "Refresh Overall Status", "onClick" => 'HPEILO_RefreshInformation($id);');
		$form['actions'][] = Array("type" => "Button", "label" => "Press Power Button", "onClick" => 'HPEILO_PressPowerButton($id);');
		$form['actions'][] = Array("type" => "Button", "label" => "Force Power Off", "onClick" => 'HPEILO_ForcePowerOff($id);');
		$form['actions'][] = Array("type" => "Button", "label" => "Force Power On", "onClick" => 'HPEILO_ForcePowerOn($id);');

		// Return the completed form
		return json_encode($form);

	}

	public function RefreshInformation() {

		// IPS_LogMessage($_IPS['SELF'],"HPEILO - Refresh in progress");
		$this->updateSystemHealth();
		$this->updateThermalData();
		$this->updatePowerInformation();
	}
	
	public function PressPowerButton() {
		
		$url = "https://" . $this->ReadPropertyString("hostname") . "/redfish/v1/Systems/1/Actions/ComputerSystem.Reset/";
		$dataJson = '{"ResetType": "PushPowerButton"}';
		$resultObjectReset = $this->CallAPI("POST", $url, $dataJson);
		
		sleep(3);
		$this->RefreshInformation();
	}

	public function ForcePowerOff() {
		
		$url = "https://" . $this->ReadPropertyString("hostname") . "/redfish/v1/Systems/1/Actions/ComputerSystem.Reset/";
		$dataJson = '{"ResetType": "ForceOff"}';
		$resultObjectReset = $this->CallAPI("POST", $url, $dataJson);
		
		sleep(3);
		$this->RefreshInformation();
	}
	
	public function ForcePowerOn() {
		
		$url = "https://" . $this->ReadPropertyString("hostname") . "/redfish/v1/Systems/1/Actions/ComputerSystem.Reset/";
		$dataJson = '{"ResetType": "On"}';
		$resultObjectReset = $this->CallAPI("POST", $url, $dataJson);
		
		sleep(3);
		$this->RefreshInformation();
	}

	public function RequestAction($Ident, $Value) {
	
	
		switch ($Ident) {
		
			
			case "Status":
				// Default Action for Status Variable
				if ($Value) {
				
					$this->PressPowerButton();
				}
				else {
				
					$this->PressPowerButton();
				}

				// Neuen Wert in die Statusvariable schreiben
				SetValue($this->GetIDForIdent($Ident), $Value);
				break;
			default:
				throw new Exception("Invalid Ident");
			
		}
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
		curl_setopt($curl, CURLOPT_USERPWD, $this->ReadPropertyString("username") . ":" . $this->ReadPropertyString("password") );

		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		$result = curl_exec($curl);

		curl_close($curl);

		return $result;
	}
	
	protected function updateSystemHealth() {
		
		$url = "https://" . $this->ReadPropertyString("hostname") . "/rest/v1/Chassis/1";
		$result = $this->CallAPI("GET",$url);

		$resultObject = json_decode($result);
		//print_r($resultChassisObject);
		
		if ($resultObject->Status->Health == "OK") {
			
			SetValue($this->GetIDForIdent("SystemHealth"), true);
		}
		else {
			
			SetValue($this->GetIDForIdent("SystemHealth"), false);
		}
		
		switch ($resultObject->Status->State) {
			
			case "Disabled":
				SetValue($this->GetIDForIdent("Status") , 0);
				break;
			case "Enabled":
				SetValue($this->GetIDForIdent("Status") , 2);
				break;
			case "Starting":
				SetValue($this->GetIDForIdent("Status") , 1);
				break;
			default:
				SetValue($this->GetIDForIdent("Status") , 4);
				IPS_LogMessage($_IPS['SELF'],"HPEILO - Received unknow power status of " . $resultObject->Status->State);
				break;
		}
	}

	protected function updateThermalData() {
		
		$url = "https://" . $this->ReadPropertyString("hostname") . "/rest/v1/Chassis/1/Thermal";
		$result = $this->CallAPI("GET",$url);

		$resultObject = json_decode($result);
		//print_r($resultChassisObject)
		
		foreach ($resultObject->Temperatures as $currentSensor) {

			switch ($currentSensor->Name) {
				case "01-Inlet Ambient":
					SetValue($this->GetIDForIdent("TemperatureInlet"), $currentSensor->CurrentReading);
					break;
				case "02-CPU 1":
					SetValue($this->GetIDForIdent("TemperatureCpu1"), $currentSensor->CurrentReading);
					break;
				case "03-CPU 2":
					SetValue($this->GetIDForIdent("TemperatureCpu2"), $currentSensor->CurrentReading);
					break;
				case "19-PS 1 Internal":
					SetValue($this->GetIDForIdent("TemperaturePs1"), $currentSensor->CurrentReading);
					break;
				case "20-PS 2 Internal":
					SetValue($this->GetIDForIdent("TemperaturePs2"), $currentSensor->CurrentReading);
					break;
				case "25-PCI 5 GPU":
					SetValue($this->GetIDForIdent("TemperatureGpu"), $currentSensor->CurrentReading);
					break;
				case "10-Chipset":
					SetValue($this->GetIDForIdent("TemperatureSystemBoard"), $currentSensor->CurrentReading);
					break;
			}
		}
	}
	
	protected function updatePowerInformation() {
		
		$url = "https://" . $this->ReadPropertyString("hostname") . "/rest/v1/Chassis/1/Power";
		$result = $this->CallAPI("GET",$url);

		$resultObject = json_decode($result);
		//print_r($resultChassisObject);
		SetValue($this->GetIDForIdent("PowerConsumption") , $resultObject->PowerConsumedWatts);
		
		if ($resultObject->PowerSupplies[0]->Status->Health == "OK") {
			
			SetValue($this->GetIDForIdent("PowerSupply1Health"), true);
		}
		else {
			
			SetValue($this->GetIDForIdent("PowerSupply1Health"), false);
		}
		
		if ($resultObject->PowerSupplies[1]->Status->Health == "OK") {
			
			SetValue($this->GetIDForIdent("PowerSupply2Health"), true);
		}
		else {
			
			SetValue($this->GetIDForIdent("PowerSupply2Health"), false);
		}
	}
	
}
?>
