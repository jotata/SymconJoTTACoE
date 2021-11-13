<?php

declare(strict_types=1);
/**
 * @Package:         JoTTACoE
 * @File:            module.php
 * @Create Date:     05.11.2020 11:25:00
 * @Author:          Jonathan Tanner - admin@tanner-info.ch
 * @Last Modified:   13.11.2021 17:48:04
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
    protected const STATUS_Ok_InstanceActive = 102;
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
        $this->RegisterPropertyString('Analog', '[{"ID":1,"Ident":"A1","Config":2}]'); //Konfiguration Analoge Variablen
        $this->RegisterPropertyString('Digital', '[{"ID":1,"Ident":"D1","Config":2}]'); //Konfiguration Digitale Variablen
        $this->RegisterMessage($this->InstanceID, IM_CONNECT); //Instanz verfügbar

        //Units einlesen und analoge Profile verwalten
        $units = file_get_contents(__DIR__ . '/units.json');
        $units = json_decode($units);
        if (json_last_error() !== JSON_ERROR_NONE) {//Fehler darf nur beim Entwickler auftreten (nach Anpassung der JSON-Daten). Wird daher direkt als echo ohne Übersetzung ausgegeben.
            echo 'Create - Error in JSON (' . json_last_error_msg() . '). Please check File-Content of ' . __DIR__ . '/units.json and run PHPUnit-Test \'testUnits\'';
            exit;
        }
        $aUnits[-1] = (object) ['Name' => 'Digital', 'ProfileName' => '~Switch', 'Suffix' => '', 'Decimals' => 0]; //Unit für Digitale In-/Outputs
        foreach ($units as $u) {
            $aUnits[$u->UnitID] = $u;
            $pName = '';
            if ($u->Name !== '') { //per Definition von Technische Alternative gibt es z.T. 'leere' UnitIDs
                $pName = self::PREFIX . '.' . $u->Name . '.' . $u->UnitID;
                $this->MaintainProfile(['ProfileName' => $pName, 'ProfileType' => VARIABLETYPE_FLOAT, 'Suffix' => $u->Suffix, 'Digits' => $u->Decimals]);
            }
            $aUnits[$u->UnitID]->ProfileName = $pName;
            unset($aUnits[$u->UnitID]->UnitID);
        }
        $this->SetBuffer('Units', json_encode($aUnits));
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
            $remoteIP = $this->ReadPropertyString('RemoteIP');
            $filter = '.*' . preg_quote(',"Buffer":"' . $remoteNodeNr); //Erstes Byte von Buffer muss RemoteNodeNr JSON-Codiert entsprechen
            $filter .= '.*' . preg_quote(',"ClientIP":"' . $remoteIP . '",'); //Client-IP muss Host-IP aus dem UDP-Socket entsprechen
        }
        $this->SendDebug('Set ReceiveDataFilter to', $filter . '.*', 0);
        $this->SetReceiveDataFilter($filter . '.*');

        if ($this->GetStatus() !== IS_CREATING) { //Während die Instanz erstellt wird, sind die Instanz-Properties noch nicht verfügbar
            $units = json_decode($this->GetBuffer('Units'));
            //Analoge Instanz-Variablen pflegen
            $x = json_decode($this->ReadPropertyString('Analog'));
            foreach ($x as $c) {
                $this->MaintainVariable($c->Ident, 'Analog ' . $c->ID, VARIABLETYPE_FLOAT, $units->{0}->ProfileName, $c->ID, ($c->Config > 0));
                if ($c->Config > 2) { //Output oder Input/Output
                    $this->EnableAction($c->Ident);
                } elseif ($c->Config > 0) { //Nur wenn Variable vorhanden ist
                    $this->DisableAction($c->Ident);
                }
            }
            //Digitale Instanz-Variablen pflegen
            $x = json_decode($this->ReadPropertyString('Digital'));
            foreach ($x as $c) {
                $this->MaintainVariable($c->Ident, 'Digital ' . $c->ID, VARIABLETYPE_BOOLEAN, $units->{-1}->ProfileName, $c->ID + 32, ($c->Config > 0));
                if ($c->Config > 2) { //Output oder Input/Output
                    $this->EnableAction($c->Ident);
                } elseif ($c->Config > 0) { //Nur wenn Variable vorhanden ist
                    $this->DisableAction($c->Ident);
                }
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
            $id = @$this->GetIDForIdent("A$i");
            $name = "Analog $i";
            if ($id !== false) {
                $name = IPS_GetObject($id)['ObjectName'];
            }
            $AnalogValues[] = ['ID' => $i, 'Ident' => "A$i", 'Name' => $name, 'Config' => 0];
        }

        //Digitale In-/Outputs
        for ($i = 1; $i <= 32; $i++) {
            $id = @$this->GetIDForIdent("D$i");
            $name = "Digital $i";
            if ($id !== false) {
                $name = IPS_GetObject($id)['ObjectName'];
            }
            $DigitalValues[] = ['ID' => $i, 'Ident' => "D$i", 'Name' => $name, 'Config' => 0];
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
        $this->SendDebug('RECEIVE DATA -> JSONString', $JSONString, 0);
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
            $type = 'D';
            if ($header['Block'] == 0) {
                $block = 1;
            } else {
                $block = 17;
            }
            $strBlock = 'block D' . $block . '-D' . ($block + 16);
            $this->SendDebug("RECEIVE DATA ($strBlock) -> RAW", $buffer, 1);
            $hex = unpack('H2', $buffer); //nur Byte 3+4 enthalten digitale Daten
            $bin = base_convert($hex[1], 16, 2); //in Binär-Zeichenfolge umwandeln
            $bin = str_repeat('0', (16 - strlen($bin))) . $bin; //auf 16 Bit mit 0 auffüllen
            $buffer = strrev($bin); //Bits in Reihenfolge umdrehen
            $this->SendDebug("RECEIVE DATA ($strBlock) -> Bits", $bin, 0);
            for ($i = 0; $i < 16; $i++) { //Bits durchlaufen und den entsprechenden Values zuweisen
                $bit = substr($buffer, $i, 1);
                $ident = 'D' . ($block + $i);
                $values[$ident]['Value'] = $bit;
                $values[$ident]['UnitID'] = -1;
            }
        } elseif ($header['Block'] > 0 && $header['Block'] < 9) { //Analoge Daten
            $type = 'A';
            $block = (($header['Block'] - 1) * 4 + 1);
            $strBlock = 'block A' . $block . '-A' . ($block + 3);
            $this->SendDebug("RECEIVE DATA ($strBlock) -> RAW", $buffer, 1);
            $x = unpack('s4Value/C4UnitID', $buffer);
            for ($i = 0; $i < 4; $i++) { //Werte berechnen und den entsprechenden Values zuweisen
                $val = $x['Value' . ($i + 1)];
                $unitID = $x['UnitID' . ($i + 1)];
                $ident = 'A' . ($block + $i);
                $values[$ident]['Value'] = $this->UnitConvertDecimals($val, 0, $units->{$unitID}->Decimals);
                $values[$ident]['UnitID'] = $unitID;
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
        foreach ($values as $ident => $value) {
            $strValues .= " | $ident: " . $value['Value'] . $units->{$value['UnitID']}->Suffix;
            $vID = @$this->GetIDForIdent($ident);
            if ($vID === false || $config[$ident] !== 2 && $config[$ident] !== 4) { //Variable nicht vorhanden oder nicht als Input (2) oder Input/Output (4) konfiguriert
                $discarded .= ", $ident";
            } else { //Variable aktualisieren
                $this->SetValue($ident, $value['Value']);
                if ($type === 'A') { //Bei analogen Werten das Profil, gemäss der vom CMI übermittelten UnitID, anpassen
                    $this->MaintainVariable($ident, '', VARIABLETYPE_FLOAT, $units->{$value['UnitID']}->ProfileName, 0, true); //Es wird nur das Profil angepasst
                }
            }
        }
        $this->SendDebug("RECEIVE DATA ($strBlock) -> Values", trim($strValues, ' |'), 0);
        if (strlen($discarded) > 0) {
            $this->SendDebug("RECEIVE DATA ($strBlock) -> Skipped", 'Variable(s) not active/input: ' . trim($discarded, ','), 0);
        }
    }

    public function Send(string $Text, string $ClientIP, int $ClientPort) {
        $this->SendDataToParent(json_encode(['DataID' => '{C8792760-65CF-4C53-B5C7-A30FCC84FEFE}', 'ClientIP' => $ClientIP, 'ClientPort' => $ClientPort, 'Buffer' => $Text]));
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
        if ($this->HasActiveParent() === false) {
            $this->SetStatus(self::STATUS_Error_PreconditionRequired);
            return false;
        }

        //Parameter definieren
        $type = substr($Ident, 0, 1);
        if ($type == 'A') { //Analoge Daten
            $config = json_decode($this->ReadPropertyString('Analog'));
            $block = ceil(intval(substr($Ident, 1)) / 4); //BlockNr (1-8) berechnen
            $min = (($block - 1) * 4 + 1); //Erste ID des Blocks berechnen
            $max = $min + 3; //Analog immer 4 Werte pro Block
        } elseif ($type == 'D') { //Digitale Daten
            $config = json_decode($this->ReadPropertyString('Digital'));
            $block = 0; //Digital 1-16
            $min = 1;
            if (intval(substr($Ident, 1) > 16)) {
                $block = 9; //Digital 17-32
                $min = 17;
            }
            $max = $min + 15; //Digital immer 16 Werte pro Block
        }
        $strBlock = "block $type$min-$type$max";

        //Werte von IPS auslesen
        $units = json_decode($this->GetBuffer('Units'));
        $values = [];
        $strValues = '';
        $discarded = '';
        $config = array_combine(array_column($config, 'Ident'), array_column($config, 'Config'));
        for ($i = $min; $i <= $max; $i++) { //Daten des ganzen Blocks ermitteln. Es müssen immer 4 (Analog) oder 16 (Digital) Werte miteinander gesendet werden
            $v = 0;
            $u = 0;
            $vID = @$this->GetIDForIdent("$type$i");
            if ($config["$type$i"] > 2 && $vID !== false) { //Als Output definiert & IPS-Variable vorhanden
                if ("$type$i" == $Ident) { //neuen Wert übernehmen
                    $v = $Value;
                } else { //Wert von IPS-Variable auslesen
                    $v = $this->GetValue("$type$i");
                }
                if ($type == 'A') { //Unit ist nur für Analoge Werte nötig
                    $var = IPS_GetVariable($vID);
                    $pName = $var['VariableProfile'];
                    if ($var['VariableCustomProfile'] !== '' && strpos($var['VariableCustomProfile'], self::PREFIX . '.') === 0) { //CustomProfile entspricht einem Modul-Profil
                        $pName = $var['VariableCustomProfile'];
                    }
                    $u = intval(filter_var($pName, FILTER_SANITIZE_NUMBER_INT)); //nur die UnitID im Profilnamen ist eine Zahl
                }
            } else { // nicht als Output definiert oder IPS-Variable nicht vorhanden
                $discarded .= "$type$i, ";
            }
            $strValues .= " | $type$i: " . $v . $units->{$u}->Suffix;
            $values["$type$i"]['Value'] = $this->UnitConvertDecimals($v, $units->{$u}->Decimals, 0); //CoE überträgt analoge Werte immer als Ganzzahl (16Bit) ohne Komma;
            $values["$type$i"]['UnitID'] = $u;
        }
        if (strlen($discarded) > 0) {
            $this->SendDebug("SEND DATA ($strBlock) -> Skipped", 'Variable(s) not active/ouput: ' . trim($discarded, ', '), 0);
        }
        $this->SendDebug("SEND DATA ($strBlock) -> Values", trim($strValues, ' |'), 0);

        //Daten senden
        $data = '';
        if ($type == 'A') { //Analoge Daten
            $data = pack('s4C4', ...array_column($values, 'Value'), ...array_column($values, 'UnitID')); //4x 16Bit Werte und 4x 8Bit UnitID
        }
        if ($type == 'D') { //Digitale Daten
            $data = strrev(implode('', array_column($values, 'Value'))); //Umgekehrte Bit-Folge der Werte (16Bit)
            $data = pack('vx10', base_convert($data, 2, 10)); //Bit-Folge in Ganzzahl umwandeln und als 16Bit Little-Endian + 10 NUL verpacken
        }
        $this->SendDebug("SEND DATA ($strBlock) -> RAW", $data, 1);
        $data = utf8_encode(pack('C2', $this->ReadPropertyInteger('NodeNr'), $block) . $data); //Header (8Bit KnotenNr + 8Bit BlockNr) hinzufügen & utf8-codieren
        $data = json_encode(['DataID' => '{C8792760-65CF-4C53-B5C7-A30FCC84FEFE}' /*UDP-Socket*/, 'ClientIP' => $this->ReadPropertyString('RemoteIP'), 'ClientPort' => 5441, 'Buffer' => $data]);
        $this->SendDebug('SEND DATA -> JSONString', $data, 0);
        $response = @$this->SendDataToParent($data);

        //Antwort UDP-Socket auswerten
        if ($response !== false) { //kein Fehler seitens IPS
            if ($this->GetStatus() !== self::STATUS_Ok_InstanceActive) {
                $this->SetStatus(self::STATUS_Ok_InstanceActive);
            }
            if ($config[$Ident] < 4) { //UDP sendet keine Antwort zurück, wenn Variable jedoch ein Input/Output ist, müsste die CMI den neuen Wert zurückmelden.
                $this->SetValue($Ident, $Value);
            }
            return true;
        }
        $this->SetStatus(self::STATUS_Error_PreconditionRequired);
        return false;
    }

    /**
     * Verschiebt das Komma in $Value auf $ToDecimals Kommastellen
     * Werte werden im CoE immer als ganze Zahlen mit Angabe der UnitID übertragen.
     * Über die UnitID sind die Kommastellen für den Wert und die Einheit definiert.
     * @param mixed $Value Wert zum konvertieren
     * @param int $FromDecimals Anzahl Nachkommastellen für den Input
     * @param int $ToDecimals Anzahl Nachkommastellen für den Output
     * @return float konvertierter Wert
     */
    private function UnitConvertDecimals($Value, int $FromDecimals, int $ToDecimals) {
        if ($FromDecimals != 0 && $ToDecimals != 0) { //Fehler darf nur beim Entwickler auftreten. Wird daher direkt als echo ohne Übersetzung ausgegeben.
            echo 'UnitConvertDecimals - One of both decimals has to be 0!';
            exit;
        }
        $val = number_format(floatval($Value), $FromDecimals, '.', ''); //Sicherstellen, dass die angegebene Anzahl Nachkommastellen vorhanden ist (abschneiden/hinzufügen)
        $val = str_replace('.', '', $val); //Komma entfernen
        if ($ToDecimals !== 0) { //Komma auf $ToDecimals schieben
            $hi = substr($val, 0, strlen($val) - $ToDecimals);
            $low = substr($val, -$ToDecimals);
            $val = "$hi.$low";
        }
        return floatval($val);
    }
}