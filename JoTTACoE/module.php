<?php

declare(strict_types=1);
/**
 * @Package:         JoTTACoE
 * @File:            module.php
 * @Create Date:     05.11.2020 11:25:00
 * @Author:          Jonathan Tanner - admin@tanner-info.ch
 * @Last Modified:   10.11.2021 20:58:42
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
        $this->RegisterPropertyString('Analog', '{}'); //Konfiguration Analoge Variablen
        $this->RegisterPropertyString('Digital', '{}'); //Konfiguration Digitale Variablen
        $this->RegisterPropertyBoolean('UpdateProfiles', 1); //Automatische Updates der Profile via CMI
        $this->RegisterMessage($this->InstanceID, IM_CONNECT); //Instanz verfügbar

        //Units einlesen
        $units = file_get_contents(__DIR__ . '/units.json');
        $units = json_decode($units, true, 4);
        if (json_last_error() !== JSON_ERROR_NONE) {//Fehler darf nur beim Entwickler auftreten (nach Anpassung der JSON-Daten). Wird daher direkt als echo ohne Übersetzung ausgegeben.
            echo 'Create - Error in JSON (' . json_last_error_msg() . '). Please check File-Content of ' . __DIR__ . '/units.json and run PHPUnit-Test \'testUnits\'';
            exit;
        }
        $aUnits = [];
        foreach ($units as $u) { //Idents und notwendige Parameter einlesen
            $aUnits[$u['UnitID']] = $u;
            unset($aUnits[$u['UnitID']]['UnitID']);
            //Tests sind nicht nötig, da die unit.json mittels PHPUnit-Tests kontrolliert wird und die Daten somit stimmen sollten.
        }
        $this->SetBuffer('Units', json_encode($aUnits));
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
        if ($remoteNodeNr === '\u0000') { //0 => Empfang deaktiviert
            $filter = 'DEAKTIVIERT';
        } else { //Empfang aktiviert
            $remoteIP =  $this->ReadPropertyString('RemoteIP');
            $filter = '.*' . preg_quote(',"Buffer":"' . $remoteNodeNr); //Erstes Byte von Buffer muss RemoteNodeNr JSON-Codiert entsprechen
            $filter .= '.*' . preg_quote(',"ClientIP":"' . $remoteIP . '",'); //Client-IP muss Host-IP aus dem UDP-Socket entsprechen
        }
        $this->SendDebug('Set ReceiveDataFilter to', $filter . '.*', 0);
        $this->SetReceiveDataFilter($filter . '.*');

        //Analoge Instanz-Variablen pflegen
        $x = json_decode($this->ReadPropertyString('Analog'));
        foreach ($x as $c){
            $this->MaintainVariable($c->Ident, 'Analog ' . $c->ID, VARIABLETYPE_FLOAT, '', $c->ID, ($c->Config > 0));
            if ($c->Config > 2){ //Output oder Input/Output
                $this->EnableAction($c->Ident);
            } else if ($c->Config > 0) { //Nur wenn Variable vorhanden ist
                $this->DisableAction($c->Ident);
            }
        }
        //Digitale Instanz-Variablen pflegen
        $x = json_decode($this->ReadPropertyString('Digital'));
        foreach ($x as $c){
            $this->MaintainVariable($c->Ident, 'Digital ' . $c->ID, VARIABLETYPE_BOOLEAN, '', $c->ID + 32, ($c->Config > 0));
            if ($c->Config > 2){ //Output oder Input/Output
                $this->EnableAction($c->Ident);
            } else if ($c->Config > 0) { //Nur wenn Variable vorhanden ist
                $this->DisableAction($c->Ident);
            }
        }
    }

    /**
     * Interne Funktion des SDK.
     * Stellt Informationen für das Konfigurations-Formular zusammen
     * @return string JSON-Codiertes Formular
     * @access public
     */
    public function GetConfigurationForm() {
        //Analoge In-/Outputs
        for ($i = 1; $i <= 32; $i++) {
            $id = @$this->GetIDForIdent("Analog$i");
            $name = "Analog $i";
            if ($id !== false) {
                $name = IPS_GetObject($id)['ObjectName'];
            }
            $AnalogValues[] = ['ID' => $i, 'Ident' => "Analog$i", 'Name' => $name, 'Config' => 0];
        }

        //Digitale In-/Outputs
        for ($i = 1; $i <= 32; $i++) {
            $id = @$this->GetIDForIdent("Digital$i");
            $name = "Digital $i";
            if ($id !== false) {
                $name = IPS_GetObject($id)['ObjectName'];
            }
            $DigitalValues[] = ['ID' => $i, 'Ident' => "Digital$i", 'Name' => $name, 'Config' => 0];
        }

        //Variabeln in $form ersetzen
        $form = file_get_contents(__DIR__ . '/form.json');
        $form = str_replace('"$AnalogValues"', json_encode($AnalogValues), $form);
        $form = str_replace('"$DigitalValues"', json_encode($DigitalValues), $form);
        return $form;
    }

    /**
     * Interne Funktion des SDK.
     * Wird ausgeführt wenn eine registrierte Nachricht von IPS verfügbar ist.
     * @access public
     */
    public function MessageSink($TimeStamp, $SenderID, $MessageID, $Data) {
        
    }

    /**
     * Interne Funktion des SDK.
     * Wird ausgeführt wenn eine Nachricht vom UDP-Socket empfangen wurde.
     * Im ReceiveDataFilter ist bereits sichergestellt, dass nur Pakete an die NodeNr der Instanz von der IP der CMI weitergeleitet werden.
     * @access public
     */
    public function ReceiveData($JSONString) {
		$data = json_decode($JSONString);
        $buffer = utf8_decode($data->Buffer);
        $header = unpack('CNodeNr/CBlock', $buffer); //Erstes Byte beinhaltet NodeNr, zweites Byte Datentyp/Länge (0=Digital, >0 = Länge analoger Daten)
        $buffer = substr($buffer, 2); //Header entfernen
        
        /** Daten im Buffer sind wie folgt aufgebaut:
        * Analoge Pakete CoE (erkennbar am Block aus Byte 2): 
        * - Byte 1 = SenderKnoten
        * - Byte 2 = Block (1-8) => (1=A1-A4, 2=A5-A8, 3=A9-A12, 4=A13-A16, 5=A17-A20, 6=A21-A24, 7=A25-A28, 8=A29-A32) => es werden immer 4 NetzwerkAusgänge pro Paket versendet. Wenn ein NetzwerkAusgang nicht konfiguriert ist, dann wird er im Paket mit 0 aufgefüllt/versendet
        * - Byte 3+4 = Wert 1 (unsigned Short)
        * - Byte 5+6 = Wert 2 (unsigned Short)
        * - Byte 7+8 = Wert 3 (unsigned Short)
        * - Byte 9+10 = Wert 4 (unsigned Short)
        * - Byte 11 = Einheit/Datentyp Wert 1
        * - Byte 12 = Einheit/Datentyp Wert 2
        * - Byte 13 = Einheit/Datentyp Wert 3
        * - Byte 14 = Einheit/Datentyp Wert 4
        *
        * Digitale Pakete CoE (erkennbar am Block aus Byte 2): 
        * - Byte 1 = SenderKnoten
        * - Byte 2 = Block (0,9) => (0=A1-A16, 9=A17-A32) => es werden immer 16 Bit (16 Ausgänge) pro Paket versendet. Wenn ein NetzwerkAusgang nicht konfiguriert ist, dann wird er im Paket mit 0 aufgefüllt/versendet 
        * - Byte 3+4 = 16 Bit mit digitalen Werten pro Ausgang (0 oder 1)
        * - Byte 5-14 = nicht genutzt (aber anscheinend mit 0 aufgefüllt)
        */

        //Daten verarbeiten 
        $units = json_decode($this->GetBuffer('Units'));
        $values = [];
        if ($header['Block'] == 0 || $header['Block'] == 9) { //Digitale Daten
            if ($header['Block'] == 0) {
                $block = 1;
            } else {
                $block = 17;
            }
            $strBlock = 'block D' . $block . '-D' . ($block + 16);
            $this->SendDebug("Received data ($strBlock) RAW", $buffer, 1);
            $hex = unpack('H2', $buffer); //nur Byte 3+4 enthalten digitale Daten
            $bin = base_convert($hex[1], 16, 2); //in Binär-Zeichenfolge umwandeln
            $bin = str_repeat('0', (16 - strlen($bin))) . $bin; //auf 16 Bit mit 0 auffüllen
            $buffer = strrev($bin); //Bits in Reihenfolge umdrehen
            $this->SendDebug("Converted data ($strBlock) -> Bits", $bin, 0);
            for ($i = 0; $i < 16; $i++) { //Bits durchlaufen und den entsprechenden Values zuweisen
                $bit = substr($buffer, $i, 1);
                $ident = 'Digital' . ($block+$i);
                $values[$ident]['Value'] = $bit;
                $values[$ident]['Suffix'] = '';
            }
        } else if ($header['Block'] > 0 && $header['Block'] < 9) { //Analoge Daten
            $block = (($header['Block'] -1) * 4 +1);
            $strBlock = 'block A' . $block . '-A' . ($block + 3); 
            $this->SendDebug("Received data ($strBlock) RAW", $buffer, 1);
            $x = unpack('s4Value/C4UnitID', $buffer);
            for ($i = 0; $i < 4; $i++) { //Werte berechnen und den entsprechenden Values zuweisen
                $val = $x['Value' . ($i+1)];
                $unitID = $x['UnitID' . ($i+1)];
                $ident = 'Analog' . ($block+$i);
                $values[$ident]['Value'] = $this->UnitConvertDecimals($val, $units[$unitID]->Decimals);
                $values[$ident]['Suffix'] = $units[$unitID]->Suffix;
            }
            
        } else { //Ungültige Daten
            $this->ThrowMessage('Unknown data header (block): ' . $header['Block'] . ' - Skipping');
            return;
        }

        //Values in Instanz-Variablen schreiben
        $strValues = '';
        $discarded = '';
        $config = array_merge(json_decode($this->ReadPropertyString('Analog')), json_decode($this->ReadPropertyString('Digital')));
        $config = array_combine(array_column($config, 'Ident'), array_column($config, 'Config'));
        foreach ($values as $ident => $value){
            $strValues .= " | $ident: " . $value['Value'] . $value['Suffix'];
            if (@$this->GetIDForIdent($ident) === false || $config[$ident] !== 2 && $config[$ident] !== 4) { //Variable nicht vorhanden oder nicht als Input (2) oder Input/Output (4) konfiguriert
                $discarded .= ", $ident";
            } else {
                $this->SetValue($ident, $value['Value']);
            }
        }
        $this->SendDebug("Converted data ($strBlock) -> Values", trim($strValues, ' |'), 0);
        if (strlen($discarded) > 0) {
            $this->SendDebug('Discarding received value(s)', 'Variable(s) not active/input: ' . trim($discarded, ','), 0);
        }
	}

    /**
     * Wird von IPS-Instanz Funktion PREFIX_RequestAction aufgerufen
     * und schreibt den Wert der Variable auf die Remote-CMI zurück
     * @param string $Ident der Variable
     * @param mixed $Value zu schreibender Wert
     * @return boolean true bei Erfolg oder false bei Fehler
     * @access private
     */
    private function RequestVariableAction(string $Ident, $Value) {
        
    }

    /** 
     * Verschiebt das Komma in $Value auf $ToDecimals Kommastellen
     * Werte werden im CoE immer als ganze Zahlen mit Angabe der UnitID übertragen.
     * Über die UnitID sind die Kommastellen für den Wert und die Einheit definiert.
     * @param mixed $Value Wert zum konvertieren
     * @param int $ToDecimals Anzahl Nachkommastellen für den Output
     * @return float konvertierter Wert
     */
    function UnitConvertDecimals($Value, int $ToDecimals) {
        $val = str_replace('.', '', strval($Value));
        if ($ToDecimals !== 0) {
            $low = substr($val, -$ToDecimals);
            $high = substr($val, 0, (strlen($val) - $ToDecimals));
            $val = "$high.$low";
        }
        return floatval($val);
    }

    public function Send(string $Text, string $ClientIP, int $ClientPort) {
		$this->SendDataToParent(json_encode(['DataID' => '{C8792760-65CF-4C53-B5C7-A30FCC84FEFE}', "ClientIP" => $ClientIP, "ClientPort" => $ClientPort, "Buffer" => $Text]));
	}
}