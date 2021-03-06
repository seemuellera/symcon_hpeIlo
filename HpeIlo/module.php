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
	

		// Variables
		$this->RegisterVariableBoolean("Status","Status", "~Switch");
		$this->RegisterVariableBoolean("IloCardReachable","ILO card reachable", "~Alert");
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

		$this->updateSystemHealth();
		$this->updateThermalData();
		$this->updatePowerInformation();
	}
	
	public function PressPowerButton() {
		
		$url = "https://" . $this->ReadPropertyString("hostname") . "/redfish/v1/Systems/1/Actions/ComputerSystem.Reset/";
		$dataJson = '{"ResetType": "PushPowerButton"}';
		$resultObjectReset = $this->CallAPI("POST", $url, $dataJson);
		
		if (! $resultObjectReset) {
			
			$this->updateIloReachable(false);
			return;
		}
		else {
			
			$this->updateIloReachable(true);
		}
		
		sleep(3);
		$this->RefreshInformation();
	}

	public function ForcePowerOff() {
		
		$url = "https://" . $this->ReadPropertyString("hostname") . "/redfish/v1/Systems/1/Actions/ComputerSystem.Reset/";
		$dataJson = '{"ResetType": "ForceOff"}';
		$resultObjectReset = $this->CallAPI("POST", $url, $dataJson);
		
		if (! $resultObjectReset) {
			
			$this->updateIloReachable(false);
			return;
		}
		else {
			
			$this->updateIloReachable(true);
		}

		
		sleep(3);
		$this->RefreshInformation();
	}
	
	public function ForcePowerOn() {
		
		$url = "https://" . $this->ReadPropertyString("hostname") . "/redfish/v1/Systems/1/Actions/ComputerSystem.Reset/";
		$dataJson = '{"ResetType": "On"}';
		$resultObjectReset = $this->CallAPI("POST", $url, $dataJson);
		
		if (! $resultObjectReset) {
			
			$this->updateIloReachable(false);
			return;
		}
		else {
			
			$this->updateIloReachable(true);
		}

		
		sleep(3);
		$this->RefreshInformation();
	}

	public function RequestAction($Ident, $Value) {
	
	
		switch ($Ident) {
		
			
			case "Status":
				// Default Action for Status Variable
				if ($Value) {
				
					// A powern on was requested but we only need to execute it when the system is already running
					if (! GetValue($this->GetIDForIdent("Status"))) {
				
						$this->LogMessage("Switch on System was requested");
						$this->PressPowerButton();
					}
					else {
						
						$this->LogMessage("Switch on System was requested but it is already running");
					}
				}
				else {
				
					if (GetValue($this->GetIDForIdent("Status"))) {
					
						$this->LogMessage("Switch off System was requested");
						$this->PressPowerButton();
					}
					else {
						
						$this->LogMessage("Switch off System was requested but it is already off");
					}
				}

				// Neuen Wert in die Statusvariable schreiben
				SetValue($this->GetIDForIdent($Ident), $Value);
				break;
			default:
				throw new Exception("Invalid Ident");
			
		}
	}
	
	// Version 1.0
	protected function LogMessage($message, $severity = 'INFO') {
		
		$logMappings = Array();
		// $logMappings['DEBUG'] 	= 10206; Deactivated the normal debug, because it is not active
		$logMappings['DEBUG'] 	= 10201;
		$logMappings['INFO']	= 10201;
		$logMappings['NOTIFY']	= 10203;
		$logMappings['WARN'] 	= 10204;
		$logMappings['CRIT']	= 10205;
		
		if ( ($severity == 'DEBUG') && ($this->ReadPropertyBoolean('DebugOutput') == false )) {
			
			return;
		}
		
		$messageComplete = $severity . " - " . $message;
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
		
		if (! $result) {
			
			$this->updateIloReachable(false);
			return;
		}
		else {
			
			$this->updateIloReachable(true);
		}

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
				SetValue($this->GetIDForIdent("Status") , 1);
				break;
			case "Starting":
				SetValue($this->GetIDForIdent("Status") , 1);
				break;
			default:
				SetValue($this->GetIDForIdent("Status") , 0);
				$this->LogMessage("Received unknow power status of " . $resultObject->Status->State, "CRIT");
				break;
		}
	}

	protected function updateThermalData() {
		
		$url = "https://" . $this->ReadPropertyString("hostname") . "/rest/v1/Chassis/1/Thermal";
		$result = $this->CallAPI("GET",$url);
		
		if (! $result) {
			
			$this->updateIloReachable(false);
			return;
		}
		else {
			
			$this->updateIloReachable(true);
		}

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
		
		if (! $result) {
			
			$this->updateIloReachable(false);
			return;
		}
		else {
			
			$this->updateIloReachable(true);
		}

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
	
	protected function updateIloReachable($newState) {
		
		if (GetValue($this->GetIDForIdent("IloCardReachable")) == $newState) {
			
			return;
		}
		
		SetValue($this->GetIDForIdent("IloCardReachable"), $newState);
		
		if ($newState) {
			
			$this->LogMessage("ILO card is now reachable");
		}
		else { 
		
			$this->LogMessage("ILO card is now unreachable","WARN");
		}
	}
	
}
?>
