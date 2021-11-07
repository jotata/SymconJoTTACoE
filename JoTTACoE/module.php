<?php

declare(strict_types=1);
/**
 * @Package:         JoTTACoE
 * @File:            module.php
 * @Create Date:     05.11.2020 11:25:00
 * @Author:          Jonathan Tanner - admin@tanner-info.ch
 * @Last Modified:   07.11.2021 11:42:28
 * @Modified By:     Jonathan Tanner
 * @Copyright:       Copyright(c) 2020 by JoT Tanner
 * @License:         Creative Commons Attribution Non Commercial Share Alike 4.0
 *                   (http://creativecommons.org/licenses/by-nc-sa/4.0/legalcode)
 */
require_once __DIR__ . '/../libs/JoT_Traits.php';  //Bibliothek mit allgemeinen Definitionen & Traits

/**
 * JoTTACoE ist eine Unterklasse von IPSModule für die Integration der Geräte von Technische Alternative mittels CoE via CMI.
 */
class JoTTACoE extends IPSModule {
    use VariableProfile;
    use Translation;
    use RequestAction;
    protected const PREFIX = 'JoTTACoE';
    protected const MODULEID = '{61108236-EBFE-207F-2FEC-55EDB2B4FDFF}';
    protected const STATUS_Error_WrongDevice = 416;
    protected const STATUS_Error_RequestTimeout = 408;
    protected const STATUS_Error_PreconditionRequired = 428;
    protected const LED_Off = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUAQMAAAC3R49OAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAADUExURcPDw9YpAkQAAAAJcEhZcwAAFiQAABYkAZsVxhQAAAANSURBVBjTY6AqYGAAAABQAAGwhtz8AAAAAElFTkSuQmCC';
    protected const LED_Read = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAIAAAAC64paAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAAFiUAABYlAUlSJPAAAAA3SURBVDhPpcexDQAwCMAw/n+a7p6IKnnxzH7wiU984hOf+MQnPvGJT3ziE5/4xCc+8YlP/N3OA6M/joCROxOnAAAAAElFTkSuQmCC';
    protected const LED_Write = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAIAAAAC64paAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAAFiUAABYlAUlSJPAAAAAiSURBVDhPY/zPQD5ggtJkgVHNJIJRzSSCUc0kgiGpmYEBACKcASfOmBk0AAAAAElFTkSuQmCC';

    /**
     * Interne Funktion des SDK.
     * Initialisiert Properties, Attributes und Timer.
     * @access public
     */
    public function Create() {
        parent::Create();

        //Eigenschaften definieren
        $this->ConnectParent('{82347F20-F541-41E1-AC5B-A636FD3AE2D8}'); //UDP-Socket
        $this->RegisterPropertyString('RemoteIP', ''); //IP der Remote-CMI
        $this->RegisterPropertyInteger('RemoteNodeNr', 0); //Konten, von welchem Daten empfamgen werden
        $this->RegisterPropertyInteger('NodeNr', 32); //KnotenNr dieser Instanz
        $this->RegisterPropertyInteger('Analog', 1); //Anzahl Analoge Variablen
        $this->RegisterPropertyInteger('Digital', 1); //Anzahl Digitale Variablen
        $this->RegisterPropertyBoolean('UpdateProfiles', 1); //Automatische Updates der Profile via CMI
        $this->RegisterMessage($this->InstanceID, IM_CONNECT); //Instanz verfügbar
    }

    /**
     * Interne Funktion des SDK.
     * Wird beim Entfernen des Modules aufgerufen
     * @access public
     */
    public function Destroy() {
		parent::Destroy();
	}

    /**
     * Interne Funktion des SDK.
     * Wird ausgeführt wenn die Konfigurations-Änderungen gespeichet werden.
     * @access public
     */
    public function ApplyChanges() {
        parent::ApplyChanges();
        
        //ReceiveDataFilter anpassen
        $remoteNodeNr = trim(json_encode(chr($this->ReadPropertyInteger('RemoteNodeNr'))), '"'); //RemoteNodeNr JSON-Codiert
        if ($remoteNodeNr === '\u0000') { //Empfang deaktiviert
            $filter = 'DEAKTIVIERT';
        } else { //Empfang aktiviert
            $remoteIP =  $this->ReadPropertyString('RemoteIP');
            $filter = '.*' . preg_quote(',"Buffer":"' . $remoteNodeNr); //Erstes Byte von Buffer muss RemoteNodeNr JSON-Codiert entsprechen
            $filter .= '.*' . preg_quote(',"ClientIP":"' . $remoteIP . '",'); //Client-IP muss Host-IP aus dem UDP-Socket entsprechen
        }
        $this->SetReceiveDataFilter($filter . '.*');

        //Analoge Instanz-Variablen pflegen
        $x = $this->ReadPropertyInteger('Analog');
        for ($i = 1; $i <= 32; $i++){
            $keep = ($i <= $x); //true, wenn $i innerhalb der Anzahl Analoger Variablen liegt, sonst false
            $this->MaintainVariable("Analog$i", "Analog $i", VARIABLETYPE_FLOAT, '', $i, $keep);
        }
        //Digitale Instanz-Variablen pflegen
        $x = $this->ReadPropertyInteger('Digital');
        for ($i = 1; $i <= 32; $i++){
            $keep = ($i <= $x); //true, wenn $i innerhalb der Anzahl Digitaler Variablen liegt, sonst false
            $this->MaintainVariable("Digital$i", "Digital $i", VARIABLETYPE_BOOLEAN, '', $i + 32, $keep);
        }
    }

    /**
     * Interne Funktion des SDK.
     * Stellt Informationen für das Konfigurations-Formular zusammen
     * @return string JSON-Codiertes Formular
     * @access public
     */
    public function GetConfigurationForm() {
        
        //Variabeln in $form ersetzen
        $form = file_get_contents(__DIR__ . '/form.json');

        return $form;
    }

    /**
     * Interne Funktion des SDK.
     * Wird ausgeführt wenn eine registrierte Nachricht von IPS verfügbar ist.
     * @access public
     */
    public function MessageSink($TimeStamp, $SenderID, $MessageID, $Data) {
        //Änderung der Host-IP im UDP-Socket muss $this->ChangeReceiveDataFilter() aufrufen
    }

    /**
     * Interne Funktion des SDK.
     * Wird ausgeführt wenn eine Nachricht vom UDP-Socket empfangen wurde.
     * im ReceiveDataFilter ist bereits sichergestellt, dass nur Pakete an die NodeNr der Instanz von der IP der CMI weitergeleitet werden.
     * @access public
     */
    public function ReceiveData($JSONString) {
		$data = json_decode($JSONString);
        $buffer = utf8_decode($data->Buffer);
        $header = unpack('CNodeNr/CLength', $buffer); //Erstes Byte beinhaltet NodeNr, zweites Byte Datentyp/Länge (0=Digital, >0 = Länge analoger Daten)
        $buffer = substr($buffer, 2); //Header entfernen
        
        //Daten im Buffer sind wie folgt aufgebaut:
        //1 Byte - NodeNr
        //1 Byte - Typ/Adresse (0 = Digtal, > 1 = Analoge Adresse)
        //4x 2 Byte - Daten
        //4x 1 Byte - Datentypen

        //Art der Daten (Digital / Analog) ermitteln
        if ($header['Length'] > 0) { //Analoge Daten
            $this->SendDebug('Received data (analog)', $buffer, 1);
            $x = unpack('s4Value/C4Type', $buffer);
        } else { // Digitale Daten
            $this->SendDebug('Received data (digital)', $buffer, 1);
            $hex = unpack('H*', $buffer);
            $x = base_convert($hex[1], 16, 2);
            $values = str_split($x, 1);
            
            for ($i = 0; $i < strlen($buffer); $i++) {
                $bin = decbin(ord($buffer[$i])); //Jedes Byte in Binär umwandeln
                $bin = base_convert(bin2hex($buffer[$i]), 16, 2);
                //if (strlen($bin) < 8 ) {
                //    for ($j = 8; $j > $binlen; $binlen++) {
                //        $prep .= '0';
               //     }
                //} 

            }
        }
       
	}

    private function HandleAnalogValues(array $header, string $data) {
        $x = unpack('s s s s C C C C', $data);
    }

    /**
     * IPS-Instanz Funktion PREFIX_RequestRead.
     * Ließt alle/gewünschte Werte aus dem Gerät.
     * @param bool|string optional $force wenn auch nicht gepollte Values gelesen werden sollen.
     * @access public
     * @return array mit den angeforderten Werten, NULL bei Fehler oder Wert wenn nur ein Wert.
     */
    public function RequestRead() {
    
    }

    /**
     * Wird von IPS-Instanz Funktion PREFIX_RequestAction aufgerufen
     * und schreibt den Wert der Variable auf den Wechselrichter zurück
     * @param string $Ident der Variable
     * @param mixed $Value zu schreibender Wert
     * @return boolean true bei Erfolg oder false bei Fehler
     * @access private
     */
    private function RequestVariableAction(string $Ident, $Value) {
        
    }

    public function Send(string $Text, string $ClientIP, int $ClientPort) {
		$this->SendDataToParent(json_encode(['DataID' => '{C8792760-65CF-4C53-B5C7-A30FCC84FEFE}', "ClientIP" => $ClientIP, "ClientPort" => $ClientPort, "Buffer" => $Text]));
	}
}