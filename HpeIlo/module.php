<?php

// Klassendefinition
class MetaSwitch extends IPSModule {
 
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
		$this->RegisterPropertyString("Sender","MetaSwitch");
		$this->RegisterPropertyInteger("RefreshInterval",0);

		// Variables
		$this->RegisterVariableBoolean("Status","Status","~Switch");
		$this->RegisterVariableString("Devices","Devices");
		$this->RegisterVariableString("DeviceTriggers","DeviceTriggers");

		// Default Actions
		$this->EnableAction("Status");

		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'METASWITCH_RefreshInformation($_IPS[\'TARGET\']);');

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
		

		// Add the buttons for the test center
		$form['actions'][] = Array("type" => "Button", "label" => "Refresh Overall Status", "onClick" => 'METASWITCH_RefreshInformation($id);');
		$form['actions'][] = Array("type" => "Button", "label" => "Switch On", "onClick" => 'METASWITCH_SwitchOn($id);');
		$form['actions'][] = Array("type" => "Button", "label" => "Switch Off", "onClick" => 'METASWITCH_SwitchOff($id);');

		// Return the completed form
		return json_encode($form);

	}

	public function RefreshInformation() {

		// Let us assume that alls is off
		$status = false;
	
		$allDevices = $this->GetDevices();

		foreach ($allDevices as $currentDevice) {
		
			// If one device is on we set the status to on
			if (GetValue($currentDevice) ) {
			
				$status = true;	
				// As we have found a single switch on device we can stop processing the loop
				break;
			}
		}

		SetValue($this->GetIDForIdent("Status"), $status);
		
		$this->UpdateDeviceTriggers();

	}

	public function SwitchOn() {
	
		$allDevices = $this->GetDevices();

		foreach ($allDevices as $currentDevice) {
		
			$currentDeviceDetails = IPS_GetVariable($currentDevice);
			$parentId = $currentDeviceDetails['VariableAction'];

			if (! IPS_InstanceExists($parentId) ) {
			
				IPS_LogMessage($_IPS['SELF'],"METASWITCH - SwitchOn not possible for device $currentDevice - parent instance was not found");
				// Now we skip this device
				continue;
			}

			// Now we need to find out which device type we have to deal with
			$parentDetails = IPS_GetInstance($parentId);
			$parentModuleName = $parentDetails['ModuleInfo']['ModuleName'];

			if (preg_match('/Z-Wave/', $parentModuleName) ) {
			
				ZW_SwitchMode($parentId, true);
				continue;
			}

			if (preg_match('/HUELight/', $parentModuleName) ) {
			
				HUE_SetState($parentId, true);
				continue;
			}

			if (preg_match('/MetaSwitch/', $parentModuleName) ) {
			
				METASWITCH_SwitchOn($parentId);
			}
			
			IPS_LogMessage($_IPS['SELF'],"METASWITCH - SwitchOn not possible for device $currentDevice - could not identify instance type");
		}
	}

	public function SwitchOff() {
	
		$allDevices = $this->GetDevices();

		foreach ($allDevices as $currentDevice) {
		
			$currentDeviceDetails = IPS_GetVariable($currentDevice);
			$parentId = $currentDeviceDetails['VariableAction'];

			if (! IPS_InstanceExists($parentId) ) {
			
				IPS_LogMessage($_IPS['SELF'],"METASWITCH - SwitchOff not possible for device $currentDevice - parent instance was not found");
				// Now we skip this device
				continue;
			}

			// Now we need to find out which device type we have to deal with
			$parentDetails = IPS_GetInstance($parentId);
			$parentModuleName = $parentDetails['ModuleInfo']['ModuleName'];

			if (preg_match('/Z-Wave/', $parentModuleName) ) {
			
				ZW_SwitchMode($parentId, false);
				continue;
			}

			if (preg_match('/HUELight/', $parentModuleName) ) {
			
				HUE_SetState($parentId, false);
				continue;
			}

			if (preg_match('/MetaSwitch/', $parentModuleName) ) {
				                        
				METASWITCH_SwitchOff($parentId);
			}
			
			IPS_LogMessage($_IPS['SELF'],"METASWITCH - SwitchOff not possible for device $currentDevice - could not identify instance type");
		}

	}
	
	protected function UpdateDeviceTriggers() {
		
		$deviceTriggersId = $this->GetIDForIdent("DeviceTriggers");
		$allDeviceTriggers = IPS_GetChildrenIDs($deviceTriggersId);
		$allDevices = $this->GetDevices();
		
		$allDeviceTriggerNames = Array();
		
		$instanceId = IPS_GetParent($deviceTriggersId);
		
		foreach ($allDeviceTriggers as $currentDeviceTrigger) {
			
			$currentDeviceTriggerName = IPS_GetName($currentDeviceTrigger);
			$allDeviceTriggerNames[] = $currentDeviceTriggerName;
		}
		
		// Create missing devices
		foreach ($allDevices as $currentDevice) {
			
			if (! in_array($currentDevice, $allDeviceTriggerNames) ) {
				
				IPS_LogMessage($_IPS['SELF'],"METASWITCH - Creating missing event trigger for device $currentDevice");
				
				$currentEventId = IPS_CreateEvent(0);
				IPS_SetParent($currentEventId, $deviceTriggersId);
				IPS_SetName($currentEventId, $currentDevice);
				IPS_SetEventTrigger($currentEventId, 1, $currentDevice);
				IPS_SetEventScript($currentEventId, 'METASWITCH_RefreshInformation(' . $instanceId . ');');
				IPS_SetEventActive($currentEventId, true);
			}
		}
		
		// Delete surplus devices
		foreach ($allDeviceTriggers as $currentDeviceTrigger) {
			
			$currentDeviceTriggerName = IPS_GetName($currentDeviceTrigger);
			
			if (! in_array($currentDeviceTriggerName, $allDevices) ) {
				
				IPS_LogMessage($_IPS['SELF'],"METASWITCH - Removing surplus device trigger $currentDeviceTrigger");
				
				IPS_DeleteEvent($currentDeviceTrigger);
			}
		}
	}

	protected function GetDevices() {
	
		$allLinks = IPS_GetChildrenIDs($this->GetIDForIdent("Devices"));

		$allDevices = Array();

		foreach ($allLinks as $currentLink) {
		
			$currentLinkDetails = IPS_GetLink($currentLink);
			$allDevices[] = $currentLinkDetails['TargetID'];
		}

		return $allDevices;
	}

	public function RequestAction($Ident, $Value) {
	
	
		switch ($Ident) {
		
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
		}
	}

    }
?>
