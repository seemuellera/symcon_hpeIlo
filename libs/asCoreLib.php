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

		$this->RegisterPropertyBoolean("DebugOutput", false);
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
}
?>
