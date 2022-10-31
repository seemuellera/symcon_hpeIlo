<?php

include_once __DIR__ . '/../libs/asCoreLib.php';

// Klassendefinition
class HpeIlo extends AsCoreLib {

	// Global properties
	protected $chassisData;
	protected $thermalData;
	protected $powerData;

	protected $urlTable;
	protected $attributeTable;
 
	// Der Konstruktor des Moduls
	// Überschreibt den Standard Kontruktor von IPS
	public function __construct($InstanceID) {

		// Diese Zeile nicht löschen
        parent::__construct($InstanceID);
 
        // Selbsterstellter Code
		$this->urlTable = Array();
		$this->urlTable[4]['chassis'] = '/rest/v1/Chassis/1';
		$this->urlTable[5]['chassis'] = '/redfish/v1/Chassis/1';
		$this->urlTable[4]['thermal'] = '/rest/v1/Chassis/1/Thermal';
		$this->urlTable[5]['thermal'] = '/redfish/v1/Chassis/1/Thermal';
		$this->urlTable[4]['power'] = '/rest/v1/Chassis/1/Power';
		$this->urlTable[5]['power'] = '/redfish/v1/Chassis/1/Power';
		$this->urlTable[4]['reset'] = '/redfish/v1/Systems/1/Actions/ComputerSystem.Reset/';
		$this->urlTable[5]['reset'] = '/redfish/v1/Systems/1/Actions/ComputerSystem.Reset/';

		$this->attributeTable = Array();
		$this->attributeTable[4]['fanName'] = 'FanName';
		$this->attributeTable[5]['fanName'] = 'Name';
    }
 
    // Überschreibt die interne IPS_Create($id) Funktion
    public function Create() {
            
		// Diese Zeile nicht löschen.
		parent::Create();

		// Properties
		$this->RegisterPropertyString("Sender","HpeIlo");
		$this->RegisterPropertyInteger("RefreshInterval",0);
		$this->RegisterPropertyString("hostname","");
		$this->RegisterPropertyInteger("iloVersion", 4);
		$this->RegisterPropertyBoolean("ignorePowerOn", false);
		$this->RegisterPropertyBoolean("ignorePowerOff", false);
		$this->RegisterPropertyBoolean("ignorePowerOnVoice", false);
		$this->RegisterPropertyBoolean("ignorePowerOffVoice", false);
		
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
		$this->RegisterVariableBoolean("IloCardReachable","ILO card reachable", "~Alert.Reversed");
		$this->RegisterVariableBoolean("SystemHealth","System Health",$variableProfileHealthState);
		$this->RegisterVariableFloat("PowerConsumption","Power Consumption","~Watt.3680");
		
		// Attributes
		$this->RegisterAttributeInteger("DummyModuleFans",0);
		$this->RegisterAttributeInteger("DummyModulePowerSupplies",0);	
		$this->RegisterAttributeInteger("DummyModuleTemperatureSensors",0);

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

		if (! $this->CheckDummyModule("Fans")) {

			$dummyModuleFansId = $this->CreateDummyModule("Fans");
			$this->WriteAttributeInteger("DummyModuleFans", $dummyModuleFansId);
		}
		
		if (! $this->CheckDummyModule("Power Supplies")) {

			$dummyModulePowerSuppliesId = $this->CreateDummyModule("Power Supplies");
			$this->WriteAttributeInteger("DummyModulePowerSupplies", $dummyModulePowerSuppliesId);
		}

		if (! $this->CheckDummyModule("Temperature Sensors")) {

			$dummyModuleTemperatureSensorsId = $this->CreateDummyModule("Temperature Sensors");
			$this->WriteAttributeInteger("DummyModuleTemperatureSensors", $dummyModuleTemperatureSensorsId);
		}

		$this->fetchIloData();

		$this->detectFans();
		$this->detectTemperatureSensors();
		$this->detectPowerSupplies();

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
		$form['elements'][] = Array("type" => "CheckBox", "name" => "DebugOutput", "caption" => "Write Debug messages to INFO log");
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "RefreshInterval", "caption" => "Refresh Interval");
		$form['elements'][] = Array("type" => "ValidationTextBox", "name" => "hostname", "caption" => "Hostname or IP address");
		$form['elements'][] = Array("type" => "ValidationTextBox", "name" => "ApiUsername", "caption" => "Username");
		$form['elements'][] = Array("type" => "PasswordTextBox", "name" => "ApiPassword", "caption" => "Password");
		$form['elements'][] = Array(
			"type" => "Select", 
			"name" => "iloVersion", 
			"caption" => "Select the ILO version",
			"options" => Array(
				Array(
					"caption" => "ILO 4",
					"value" => 4
				),
				Array(
					"caption" => "ILO 5",
					"value" => 5
				)
			)
		);
		$form['elements'][] = Array("type" => "CheckBox", "name" => "ignorePowerOn", "caption" => "Ignore Power On events via Web Interface");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "ignorePowerOff", "caption" => "Ignore Power Off events via Web Interface");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "ignorePowerOnVoice", "caption" => "Ignore Power On events via Voice Assistant");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "ignorePowerOffVoice", "caption" => "Ignore Power Off events via Voice Assistant");
		

		// Add the buttons for the test center
		$form['actions'][] = Array("type" => "Button", "label" => "Refresh Overall Status", "onClick" => 'HPEILO_RefreshInformation($id);');
		$form['actions'][] = Array("type" => "Button", "label" => "Press Power Button", "onClick" => 'HPEILO_PressPowerButton($id);');
		$form['actions'][] = Array("type" => "Button", "label" => "Force Power Off", "onClick" => 'HPEILO_ForcePowerOff($id);');
		$form['actions'][] = Array("type" => "Button", "label" => "Force Power On", "onClick" => 'HPEILO_ForcePowerOn($id);');
		$form['actions'][] = Array("type" => "Button", "label" => "Print Server information", "onClick" => 'HPEILO_PrintServerSummary($id);');

		// Return the completed form
		return json_encode($form);

	}

	public function RefreshInformation() {

		$this->LogMessage("Data refresh was triggered","DEBUG");

		// Fetch all the data first
		$this->LogMessage("Fetching data from ILO API","DEBUG");
		$result = $this->fetchIloData();

		if (! $result) {

			$this->LogMessage("Data could not be retrieved. Maybe the ILO card is offline","DEBUG");
			return false;
		}

		$this->LogMessage("- Updating Fan data","DEBUG");
		$this->updateFans();
		$this->LogMessage("- Updating Temperature Sensor data","DEBUG");
		$this->updateTemperatureSensors();
		$this->LogMessage("- Updating Power supply data","DEBUG");
		$this->updatePowerSupplies();
		$this->LogMessage("- Updating Global System data","DEBUG");
		$this->updateGlobalSystemData();
	}

	protected function fetchIloData() {

		// Chassis Data
		$urlChassis = "https://" . $this->ReadPropertyString("hostname") . $this->urlTable[$this->ReadPropertyInteger("iloVersion")]['chassis'];
		$resultChassis = $this->CallAPI("GET",$urlChassis);
		
		// Check reachability on the first run
		if (! $resultChassis) {
			
			$this->updateIloReachable(false);
			return false;
		}
		else {
			
			$this->updateIloReachable(true);
		}

		$this->chassisData = json_decode($resultChassis);

		// Termal data
		$urlThermal = "https://" . $this->ReadPropertyString("hostname") . $this->urlTable[$this->ReadPropertyInteger("iloVersion")]['thermal'];
		$resultThermal = $this->CallAPI("GET",$urlThermal);
		$this->thermalData = json_decode($resultThermal);

		// Power data
		$urlPower = "https://" . $this->ReadPropertyString("hostname") . $this->urlTable[$this->ReadPropertyInteger("iloVersion")]['power'];
		$resultPower = $this->CallAPI("GET",$urlPower);
		$this->powerData = json_decode($resultPower);

		return true;
	}
	
	public function PressPowerButton() {
		
		$url = "https://" . $this->ReadPropertyString("hostname") . $this->urlTable[$this->ReadPropertyInteger("iloVersion")]['reset'];
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
		
		$url = "https://" . $this->ReadPropertyString("hostname") . $this->urlTable[$this->ReadPropertyInteger("iloVersion")]['reset'];
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
		
		$url = "https://" . $this->ReadPropertyString("hostname") . $this->urlTable[$this->ReadPropertyInteger("iloVersion")]['reset'];
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
						if (! $this->ReadPropertyBoolean("ignorePowerOn") ) {
							
							$this->PressPowerButton();
						}
						else {
							
							$this->LogMessage("Power On event was ignored because property is set", "DEBUG");
						}
					}
					else {
						
						$this->LogMessage("Switch On System was requested but it is already running", "DEBUG");
					}
				}
				else {
				
					if (GetValue($this->GetIDForIdent("Status"))) {
					
						$this->LogMessage("Switch off System was requested");
						if (! $this->ReadPropertyBoolean("ignorePowerOff") ) {
							
							$this->PressPowerButton();
						}
						else {
							
							$this->LogMessage("Power Off event was ignored because property is set", "DEBUG");
						}
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

	protected function getNumberOfPowerSupplies() {

		if (! $this->powerData) {

			return false;
		}

		return count($this->powerData->PowerSupplies);
	}

	protected function getNumberOfFans() {

		if (! $this->thermalData) {

			return false;
		}

		return count($this->thermalData->Fans);
	}

	protected function getNumberOfTemperatureSensors() {

		if (! $this->thermalData) {

			return false;
		}

		return count($this->thermalData->Temperatures);
	}

	protected function getModel() {

		if (! $this->chassisData) {

			return false;
		}

		return $this->chassisData->Manufacturer . " " . $this->chassisData->Model;
	}

	public function PrintServerSummary() {

		$this->fetchIloData();

		$text = "Server Model: " . $this->getModel() . "\n" .
				"Number of Power supplies: " . $this->getNumberOfPowerSupplies() . "\n" . 
				"Number of Fans: " . $this->getNumberOfFans() . "\n" . 
				"Number of Temperature Sensors: " . $this->getNumberOfTemperatureSensors();

		echo $text;
	}

	
	protected function detectFans() {

		if (! $this->thermalData) {

			$this->LogMessage("No Fan data found","DEBUG");
			return;
		}

		$this->LogMessage("Fan detection: found " . count($this->thermalData->Fans) . " fans","DEBUG");

		$allVariables = Array();

		foreach ($this->thermalData->Fans as $currentFan) {

			$fanName = $currentFan->{$this->attributeTable[4]['fanName']};

			preg_match('/Fan (\d+)/', $fanName, $matches);
			$fanId = $matches[1][0];
			$sortBase = $fanId * 10;

			$fanState = new stdClass();
			$fanState->Type = "Boolean";
			$fanState->Name = "$fanName State";
			$fanState->Ident = $this->generateIdent("HpeIloFan" . $fanName . "State");
			$fanState->Position = $sortBase + 1;
			$fanState->Profile = "HPEILO.HealthState";
			$fanState->DefaultValue = false;
			array_push($allVariables, $fanState);

			$fanSpeed = new stdClass();
			$fanSpeed->Type = "Integer";
			$fanSpeed->Name = "$fanName Speed";
			$fanSpeed->Ident = $this->generateIdent("HpeIloFan" . $fanName . "Speed");
			$fanSpeed->Position = $sortBase + 2;
			$fanSpeed->Profile = "~Intensity.100";
			array_push($allVariables, $fanSpeed);
		}

		$this->MaintainDummyModule($this->ReadAttributeInteger("DummyModuleFans"), $allVariables);
	}

	protected function detectTemperatureSensors() {

		if (! $this->thermalData) {

			$this->LogMessage("No Temperature Sensor data found","DEBUG");
			return;
		}

		$this->LogMessage("Temperature Sensor detection: found " . count($this->thermalData->Temperatures) . " sensors","DEBUG");

		$allVariables = Array();

		foreach ($this->thermalData->Temperatures as $currentTemperature) {

			// Skip the sensor if absent
			if ($currentTemperature->Status->State == "Absent") {

				continue;
			}

			$sensorName = $currentTemperature->Name;

			if ($this->ReadPropertyInteger("iloVersion") == 5) {

				$sortBase = $currentTemperature->SensorNumber * 10;
			}
			else {
			
				$sortBase = $currentTemperature->Number * 10;
			}
			
			$sensorTemperature = new stdClass();
			$sensorTemperature->Type = "Float";
			$sensorTemperature->Name = "$sensorName Current Temperature";
			$sensorTemperature->Ident = $this->generateIdent("HpeIloTemperature" . $sensorName . "CurrentTemperature");
			$sensorTemperature->Position = $sortBase + 0;
			$sensorTemperature->Profile = "~Temperature";
			array_push($allVariables, $sensorTemperature);

			$sensorState = new stdClass();
			$sensorState->Type = "Boolean";
			$sensorState->Name = "$sensorName State";
			$sensorState->Ident = $this->generateIdent("HpeIloTemperature" . $sensorName . "State");
			$sensorState->Position = $sortBase + 1;
			$sensorState->Profile = "HPEILO.HealthState";
			$sensorState->DefaultValue = false;
			array_push($allVariables, $sensorState);

			$sensorCriticalTemperature = new stdClass();
			$sensorCriticalTemperature->Type = "Float";
			$sensorCriticalTemperature->Name = "$sensorName Critical Temperature";
			$sensorCriticalTemperature->Ident = $this->generateIdent("HpeIloTemperature" . $sensorName . "CriticalTemperature");
			$sensorCriticalTemperature->Position = $sortBase + 2;
			$sensorCriticalTemperature->Profile = "~Temperature";
			array_push($allVariables, $sensorCriticalTemperature);
		}

		$this->MaintainDummyModule($this->ReadAttributeInteger("DummyModuleTemperatureSensors"), $allVariables);
	}

	protected function detectPowerSupplies() {

		if (! $this->powerData) {

			$this->LogMessage("No Power Supply data found","DEBUG");
			return;
		}

		$this->LogMessage("Power supply detection: found " . count($this->powerData->PowerSupplies) . " power supplies","DEBUG");

		$allVariables = Array();

		foreach ($this->powerData->PowerSupplies as $currentPowerSupply) {

			if ($this->ReadPropertyInteger("iloVersion") == 5) {

				$bayNumber = $currentPowerSupply->Oem->Hpe->BayNumber;
			}
			else {
			
				$bayNumber = $currentPowerSupply->Oem->Hp->BayNumber;
			}
			$sortBase = $bayNumber * 10;

			$powerSupplySerialNumber = new stdClass();
			$powerSupplySerialNumber->Type = "String";
			$powerSupplySerialNumber->Name = "Power Supply " . $bayNumber . " Serial Number";
			$powerSupplySerialNumber->Ident = $this->generateIdent("HpeIloPowerSupply" . $bayNumber . "SerialNumber");
			$powerSupplySerialNumber->Position = $sortBase + 0;
			array_push($allVariables, $powerSupplySerialNumber);

			$powerSupplyState = new stdClass();
			$powerSupplyState->Type = "Boolean";
			$powerSupplyState->Name = "Power Supply " . $bayNumber . " State";
			$powerSupplyState->Ident = $this->generateIdent("HpeIloPowerSupply" . $bayNumber . "State");
			$powerSupplyState->Position = $sortBase + 1;
			$powerSupplyState->Profile = "HPEILO.HealthState";
			$powerSupplyState->DefaultValue = false;
			array_push($allVariables, $powerSupplyState);

			$powerSupplyPower = new stdClass();
			$powerSupplyPower->Type = "Float";
			$powerSupplyPower->Name = "Power Supply " . $bayNumber . " Power";
			$powerSupplyPower->Ident = $this->generateIdent("HpeIloPowerSupply" . $bayNumber . "Power");
			$powerSupplyPower->Position = $sortBase + 2;
			$powerSupplyPower->Profile = "~Watt.3680";
			array_push($allVariables, $powerSupplyPower);

			$powerSupplyPowerRating = new stdClass();
			$powerSupplyPowerRating->Type = "Float";
			$powerSupplyPowerRating->Name = "Power Supply " . $bayNumber . " Power Rating";
			$powerSupplyPowerRating->Ident = $this->generateIdent("HpeIloPowerSupply" . $bayNumber . "PowerRating");
			$powerSupplyPowerRating->Position = $sortBase + 3;
			$powerSupplyPowerRating->Profile = "~Watt.3680";
			array_push($allVariables, $powerSupplyPowerRating);

			$powerSupplyVoltage = new stdClass();
			$powerSupplyVoltage->Type = "Float";
			$powerSupplyVoltage->Name = "Power Supply " . $bayNumber . " Input Voltage";
			$powerSupplyVoltage->Ident = $this->generateIdent("HpeIloPowerSupply" . $bayNumber . "InputVoltage");
			$powerSupplyVoltage->Position = $sortBase + 4;
			$powerSupplyVoltage->Profile = "~Volt.230";
			array_push($allVariables, $powerSupplyVoltage);
		}

		$this->MaintainDummyModule($this->ReadAttributeInteger("DummyModulePowerSupplies"), $allVariables);
	}

	protected function updateFans() {

		if (! $this->thermalData) {

			$this->LogMessage("No Fan data found","DEBUG");
			return;
		}

		$this->LogMessage("Fan update: found " . count($this->thermalData->Fans) . " fans","DEBUG");

		foreach ($this->thermalData->Fans as $currentFan) {

			if ($this->ReadPropertyInteger("iloVersion") == 5) {

				$fanName = $currentFan->Name;
			}
			else {
			
				$fanName = $currentFan->FanName;
			}

			$this->LogMessage("Fan update: updating fan $fanName","DEBUG");

			$identState = $this->generateIdent("HpeIloFan" . $fanName . "State");
			if ($currentFan->Status->Health == "OK") {

				$this->WriteDummyModuleValue($this->ReadAttributeInteger("DummyModuleFans"), $identState, true);
			}
			else {

				$this->WriteDummyModuleValue($this->ReadAttributeInteger("DummyModuleFans"), $identState, false);
			}

			$identSpeed = $this->generateIdent("HpeIloFan" . $fanName . "Speed");

			if ($this->ReadPropertyInteger("iloVersion") == 5) {
			
				$this->WriteDummyModuleValue($this->ReadAttributeInteger("DummyModuleFans"), $identSpeed, $currentFan->Reading);
			}
			else {

				$this->WriteDummyModuleValue($this->ReadAttributeInteger("DummyModuleFans"), $identSpeed, $currentFan->CurrentReading);
			}
		}
	}

	protected function updateTemperatureSensors() {

		if (! $this->thermalData) {

			$this->LogMessage("No Temperature Sensor data found","DEBUG");
			return;
		}

		$this->LogMessage("Temperature Sensor update: found " . count($this->thermalData->Temperatures) . " sensors","DEBUG");

		foreach ($this->thermalData->Temperatures as $currentTemperature) {

			// Skip the sensor if absent
			if ($currentTemperature->Status->State == "Absent") {

				continue;
			}

			$sensorName = $currentTemperature->Name;

			$this->LogMessage("Temperature Sensor update: updating sensor $sensorName","DEBUG");

			$identState = $this->generateIdent("HpeIloTemperature" . $sensorName . "State");
			if ($currentTemperature->Status->Health == "OK") {

				$this->WriteDummyModuleValue($this->ReadAttributeInteger("DummyModuleTemperatureSensors"), $identState, true);
			}
			else {

				$this->WriteDummyModuleValue($this->ReadAttributeInteger("DummyModuleTemperatureSensors"), $identState, false);
			}

			$identCurrentTemperature = $this->generateIdent("HpeIloTemperature" . $sensorName . "CurrentTemperature");
			if ($this->ReadPropertyInteger("iloVersion") == 5) {
			
				$this->WriteDummyModuleValue($this->ReadAttributeInteger("DummyModuleTemperatureSensors"), $identCurrentTemperature, $currentTemperature->ReadingCelsius);
			}
			else {

				$this->WriteDummyModuleValue($this->ReadAttributeInteger("DummyModuleTemperatureSensors"), $identCurrentTemperature, $currentTemperature->CurrentReading);
			}

			$identCriticalTemperature = $this->generateIdent("HpeIloTemperature" . $sensorName . "CriticalTemperature");
			$this->WriteDummyModuleValue($this->ReadAttributeInteger("DummyModuleTemperatureSensors"), $identCriticalTemperature, $currentTemperature->UpperThresholdCritical);
		}
	}

	protected function updatePowerSupplies() {

		if (! $this->powerData) {

			$this->LogMessage("No Power Supply data found","DEBUG");
			return;
		}

		$this->LogMessage("Power supply update: found " . count($this->powerData->PowerSupplies) . " power supplies","DEBUG");

		foreach ($this->powerData->PowerSupplies as $currentPowerSupply) {

			if ($this->ReadPropertyInteger("iloVersion") == 5) {

				$bayNumber = $currentPowerSupply->Oem->Hpe->BayNumber;
			}
			else {
			
				$bayNumber = $currentPowerSupply->Oem->Hp->BayNumber;
			}

			$this->LogMessage("Power supply update: updating Powersupply in bay number $bayNumber","DEBUG");

			$identSerialNumber = $this->generateIdent("HpeIloPowerSupply" . $bayNumber . "SerialNumber");
			$this->WriteDummyModuleValue($this->ReadAttributeInteger("DummyModulePowerSupplies"), $identSerialNumber, $currentPowerSupply->SerialNumber);

			$identState = $this->generateIdent("HpeIloPowerSupply" . $bayNumber . "State");
			if ($currentPowerSupply->Status->Health == "OK") {

				$this->WriteDummyModuleValue($this->ReadAttributeInteger("DummyModulePowerSupplies"), $identState, true);
			}
			else {

				$this->WriteDummyModuleValue($this->ReadAttributeInteger("DummyModulePowerSupplies"), $identState, false);
			}

			$identPower = $this->generateIdent("HpeIloPowerSupply" . $bayNumber . "Power");
			$this->WriteDummyModuleValue($this->ReadAttributeInteger("DummyModulePowerSupplies"), $identPower, $currentPowerSupply->LastPowerOutputWatts);

			$identPowerRating = $this->generateIdent("HpeIloPowerSupply" . $bayNumber . "PowerRating");
			$this->WriteDummyModuleValue($this->ReadAttributeInteger("DummyModulePowerSupplies"), $identPowerRating, $currentPowerSupply->PowerCapacityWatts);

			$identInputVoltage = $this->generateIdent("HpeIloPowerSupply" . $bayNumber . "InputVoltage");
			$this->WriteDummyModuleValue($this->ReadAttributeInteger("DummyModulePowerSupplies"), $identInputVoltage, $currentPowerSupply->LineInputVoltage);
		}
	}

	protected function updateGlobalSystemData() {

		if (! $this->chassisData) {

			$this->LogMessage("No Chassis Data found","DEBUG");
			return;
		}

		// Global System Health Status
		if ($this->chassisData->Status->Health == "OK") {

			$this->WriteValue("SystemHealth", true);
		}
		else {
			
			$this->WriteValue("SystemHealth", false);
		}

		// System Power State
		switch ($this->chassisData->Status->State) {
			
			case "Disabled":
				$this->WriteValue("Status", 0);
				break;
			case "Enabled":
				$this->WriteValue("Status", 1);
				break;
			case "Starting":
				$this->WriteValue("Status", 1);
				break;
			default:
				$this->WriteValue("Status", 0);
				$this->LogMessage("Received unknow power status of " . $this->chassisData->Status->State, "CRIT");
				break;
		}

		if (! $this->powerData) {

			$this->LogMessage("No Power Data found","DEBUG");
			return;
		}

		if ($this->ReadPropertyInteger("iloVersion") == 5) {

			$this->WriteValue("PowerConsumption", $this->powerData->PowerControl[0]->PowerConsumedWatts);
		}
		else {

			$this->WriteValue("PowerConsumption", $this->powerData->PowerConsumedWatts);
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
