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

		// Variables
		// $this->RegisterVariableBoolean("Status","Status","~Switch");
		$this->RegisterVariableString("SystemHealth","System Health");
		$this->RegisterVariableFloat("TemperatureInlet","Temperature - Inlet","~Temperature");
		$this->RegisterVariableFloat("TemperatureCpu1","Temperature - CPU 1","~Temperature");
		$this->RegisterVariableFloat("TemperatureCpu2","Temperature - CPU 2","~Temperature");
		$this->RegisterVariableFloat("TemperatureGpu","Temperature - GPU","~Temperature");
		$this->RegisterVariableFloat("TemperaturePs1","Temperature - Power Supply 1","~Temperature");
		$this->RegisterVariableFloat("TemperaturePs2","Temperature - Power Supply 2","~Temperature");
		$this->RegisterVariableFloat("TemperatureSystemBoard","Temperature - System Board","~Temperature");
		

		// Default Actions
		// $this->EnableAction("Status");

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
		/*
		$form['actions'][] = Array("type" => "Button", "label" => "Switch On", "onClick" => 'METASWITCH_SwitchOn($id);');
		$form['actions'][] = Array("type" => "Button", "label" => "Switch Off", "onClick" => 'METASWITCH_SwitchOff($id);');
		*/

		// Return the completed form
		return json_encode($form);

	}

	public function RefreshInformation() {

		IPS_LogMessage($_IPS['SELF'],"HPEILO - Refresh in progress");
		$this->updateSystemHealth();
	}

	public function RequestAction($Ident, $Value) {
	
	
		switch ($Ident) {
		
			/*
			case "Status":
				// Default Action for Status Variable
				if ($Value) {
				
					$this->SwitchOn();
				}
				else {
				
					$this->SwitchOff();
				}

				// Neuen Wert in die Statusvariable schreiben
				SetValue($this->GetIDForIdent($Ident), $Value);
				break;
			default:
				throw new Exception("Invalid Ident");
			*/
		}
	}
	
	protected function CallAPI($method, $url, $data = false) {
    
		$curl = curl_init();

		switch ($method)
		{
			case "POST":
				curl_setopt($curl, CURLOPT_POST, 1);

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
		SetValue($this->GetIDForIdent("SystemHealth") , $resultObject->Status->Health);
	}

	protected function updateThermalData() {
		
		$url = "https://" . $this->ReadPropertyString("hostname") . "/rest/v1/Chassis/1/Thermal";
		$result = $this->CallAPI("GET",$url);

		$resultObject = json_decode($result);
		//print_r($resultChassisObject);
		SetValue($this->GetIDForIdent("SystemHealth") , $resultObject->Status->Health);
		
		foreach ($resultThermalObject->Temperatures as $currentSensor) {

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
}
?>
