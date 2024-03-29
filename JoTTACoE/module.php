<?php

declare(strict_types=1);
/**
 * @Package:         JoTTACoE
 * @File:            module.php
 * @Create Date:     05.11.2020 11:25:00
 * @Author:          Jonathan Tanner - admin@tanner-info.ch
 * @Last Modified:   18.05.2023 11:06:25
 * @Modified By:     Jonathan Tanner
 * @Copyright:       Copyright(c) 2020 by JoT Tanner
 * @License:         Creative Commons Attribution Non Commercial Share Alike 4.0
 *                   (http://creativecommons.org/licenses/by-nc-sa/4.0/legalcode)
 */
require_once __DIR__ . '/../libs/JoT_Traits.php'; //Bibliothek mit allgemeinen Definitionen & Traits

/**
 * JoTTACoE ist eine Unterklasse von IPSModule für die Integration der Geräte von Technische Alternative mittels CoE via CMI.
 */
class JoTTACoE extends IPSModule {
    use VariableProfile;
    use Translation;
    use RequestAction;
    use ModuleInfo;
    use TestFunction;

    protected const PREFIX = 'JoTTACoE';
    protected const MODULEID = '{61108236-EBFE-207F-2FEC-55EDB2B4FDFF}';
    protected const STATUS_Ok_InstanceActive = 102;
    protected const STATUS_Ok_WaitingData = 204;
    protected const STATUS_Error_WrongIO = 418;
    protected const STATUS_Error_FailedDependency = 424;

    /**
     * Interne Funktion des SDK.
     * Initialisiert Properties, Attributes und Timer.
     * @access public
     */
    public function Create() {
        parent::Create();

        //Eigenschaften definieren
        $this->RequireParent('{82347F20-F541-41E1-AC5B-A636FD3AE2D8}'); //UDP-Socket
        $this->RegisterPropertyString('RemoteIP', ''); //IP der Remote-CMI
        $this->RegisterPropertyInteger('RemoteNodeNr', 0); //Konten, von welchem Daten empfamgen werden
        $this->RegisterPropertyBoolean('DisableReceiveDataFilter', 0); //Wenn ReceiveDataFilter deaktiviert werden soll
        $this->RegisterPropertyInteger('NodeNr', 32); //KnotenNr dieser Instanz
        $this->RegisterPropertyInteger('OutputTimer', 0); //Sende-Intervall der Ausgänge
        $this->RegisterTimer('OutputTimer', 0, static::PREFIX . '_SendAllOutputs($_IPS["TARGET"]);'); //Timer zum Senden der Ausgänge
        $this->RegisterPropertyString('Analog', '[{"ID":1,"Ident":"A1","Config":2}]'); //Konfiguration Analoge Variablen
        $this->RegisterPropertyString('Digital', '[{"ID":1,"Ident":"D1","Config":2}]'); //Konfiguration Digitale Variablen
        $this->RegisterMessage($this->InstanceID, IM_CONNECT); //Instanz Bereit

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
     * Wird ausgeführt wenn eine registrierte Nachricht verfügbar ist.
     * @access public
     */
    public function MessageSink($TimeStamp, $SenderID, $MessageID, $Data) {
        if ($MessageID === IM_CONNECT) { //Instanz ist bereit
            $this->RegisterMessage($this->InstanceID, FM_CONNECT); //Gateway verbunden/geändert
            $this->RegisterMessage($this->InstanceID, FM_DISCONNECT); //Gateway entfernt
        }
        if ($MessageID === IM_CONNECT || $MessageID === FM_CONNECT) { //Instanz ist bereit oder Gateway wurde geändert
            foreach ($this->GetMessageList() as $id => $msgs) { //alte Nachrichten deaktivieren
                $this->UnregisterMessage($id, IM_CHANGESETTINGS);
            }
            $this->RegisterMessage(IPS_GetInstance($this->InstanceID)['ConnectionID'], IM_CHANGESETTINGS); //Gateway-Einstellungen geändert
        }
        if ($this->CheckIOConfig() === true) {
            $this->SetStatus(self::STATUS_Ok_WaitingData);
        }
    }

    /**
     * Interne Funktion des SDK.
     * Wird ausgeführt wenn die Konfigurations-Änderungen gespeichet werden.
     * @access public
     */
    public function ApplyChanges() {
        parent::ApplyChanges();

        //ReceiveDataFilter anpassen
        $filter = '';
        if ($this->ReadPropertyBoolean('DisableReceiveDataFilter') === false) {
            $remoteNodeNr = trim(json_encode(chr($this->ReadPropertyInteger('RemoteNodeNr')), JSON_UNESCAPED_SLASHES), '"'); //RemoteNodeNr JSON-Codiert (JSON_UNESCAPED_SLASHES => 47 => / anstatt \/)
            if (substr($remoteNodeNr, 0, 2) === '\u') { //https://community.symcon.de/t/modul-coe-knoten-jottacoe-technische-alternative-via-can-over-ethernet-coe/126900/18
                $remoteNodeNr = '\u' . strtoupper(substr($remoteNodeNr, 2)); //json_encode produziert Unicode (0-7, 11, 14-31) mit Kleinbuchstaben, im Buffer sind es aber Grossbuchstaben (siehe Beschreibung https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/setreceivedatafilter/)
            }
            if ($remoteNodeNr === '\u0000') { //0 => Empfang deaktiviert
                $filter = 'DEAKTIVIERT';
            } else { //Empfang aktiviert
                $remoteIP = $this->ReadPropertyString('RemoteIP');
                $filter = '.*' . preg_quote('"Buffer":"' . $remoteNodeNr); //Erstes Byte von Buffer muss RemoteNodeNr JSON-Codiert entsprechen
                $filter .= '.*' . preg_quote('"ClientIP":"' . $remoteIP . '"'); //Client-IP muss IP der Remote-CMI entsprechen
                $filter = "$filter.*";
                //$filter = "/$filter.*/i"; //Keine Unterscheidung von Gross-/Kleinschreibung, da json_encode/decode z.T. Gross-/Kleinschreibung verändert 
            }
        }
        //$this->SendDebug('Set ReceiveDataFilter to', "$filter.*", 0);
        //$this->SetReceiveDataFilter("$filter.*");
        $this->SendDebug('Set ReceiveDataFilter to', $filter, 0);
        $this->SetReceiveDataFilter($filter);

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

        //Ausgangs-Timer setzen
        $this->SetTimerInterval('OutputTimer', $this->ReadPropertyInteger('OutputTimer') * 60 * 1000); //Konfiguration in Minuten zu Millisekunden

        //Status anpassen
        if ($this->CheckIOConfig() === true) {
            $this->SetStatus(self::STATUS_Ok_WaitingData);
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
        $form = $this->AddModuleInfoAsElement($form);
        $form = str_replace('"$EnableRemoteNodeNr"', $this->ConvertToBoolStr($this->ReadPropertyBoolean('DisableReceiveDataFilter'), true), $form);
        $form = str_replace('"$AnalogValues"', json_encode($AnalogValues), $form);
        $form = str_replace('"$DigitalValues"', json_encode($DigitalValues), $form);
        return $form;
    }

    /**
     * Interne Funktion des SDK.
     * Wird ausgeführt wenn eine Nachricht vom UDP-Socket empfangen wurde.
     * Im ReceiveDataFilter ist bereits sichergestellt, dass nur Pakete an die NodeNr der Instanz von der IP der CMI weitergeleitet werden.
     * @access public
     */
    public function ReceiveData($JSONString) {
        if ($this->GetStatus() == self::STATUS_Ok_WaitingData) {
            $this->SetStatus(self::STATUS_Ok_InstanceActive);
        }
        $this->SendDebug('RECEIVE Data -> JSONString', $JSONString, 0);
        $data = json_decode($JSONString);
        $buffer = utf8_decode($data->Buffer);
        $header = unpack('CNodeNr/CBlock', $buffer); //Erstes Byte beinhaltet NodeNr, zweites Byte Datentyp/Länge (0=Digital, >0 = Länge analoger Daten)
        $buffer = substr($buffer, 2); //Header entfernen
        $this->SendDebug('RECEIVE Data -> Header', json_encode($header), 0);

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
         * - Byte 2 = Block (0,9) => (0=D1-D16, 9=D17-D32) => es werden immer 16 Bit (16 Ausgänge) pro Paket versendet. Wenn ein NetzwerkAusgang nicht konfiguriert ist, dann wird er im Paket mit 0 aufgefüllt/versendet
         * - Byte 3+4 = 16 Bit mit digitalen Werten pro Ausgang (0 oder 1). Seit CMI FW-Version 1.39.1: Byte3 = D1-8/D17-25, Byte4 = D9-16/D26-32 (vorher waren Byte 3+4 vertauscht)
         * - Byte 5-14 = nicht genutzt (aber anscheinend mit 0 aufgefüllt)
         */

        //Daten verarbeiten
        $units = json_decode($this->GetBuffer('Units'));
        $values = [];
        $block = $this->GetBlockInfoByNr($header['Block']);
        if (@$block->Type === 'D') { //Digitale Daten
            $this->SendDebug("RECEIVE Data ($block->Text) -> RAW", $buffer, 1);
            $hex = unpack('H4', substr($buffer, 1, 1) . substr($buffer, 0, 1)); //nur Byte 3+4 enthalten digitale Daten. Seit CMI FW-Version 1.39.1: Byte3 = D1-8/D17-25, Byte4 = D9-16/D26-32 (vorher waren Byte 3+4 vertauscht)
            $bin = base_convert($hex[1], 16, 2); //in Binär-Zeichenfolge umwandeln
            $bin = str_repeat('0', (16 - strlen($bin))) . $bin; //auf 16 Bit mit 0 auffüllen
            $buffer = strrev($bin); //Bits in Reihenfolge umdrehen
            $this->SendDebug("RECEIVE Data ($block->Text) -> Bits", $bin, 0);
            for ($i = 0; $i < 16; $i++) { //Bits im Buffer durchlaufen und den entsprechenden Values zuweisen
                $bit = substr($buffer, $i, 1);
                $ident = $block->Type . ($block->Min + $i);
                $values[$ident]['Value'] = boolval($bit);
                $values[$ident]['UnitID'] = -1;
            }
        } elseif (@$block->Type === 'A') { //Analoge Daten
            $this->SendDebug("RECEIVE Data ($block->Text) -> RAW", $buffer, 1);
            $x = unpack('s4Value/C4UnitID', $buffer);
            for ($i = 0; $i < 4; $i++) { //Werte berechnen und den entsprechenden Values zuweisen
                $val = $x['Value' . ($i + 1)];
                $unitID = $x['UnitID' . ($i + 1)];
                $ident = $block->Type . ($block->Min + $i);
                $values[$ident]['Value'] = $this->UnitConvertDecimals($val, 0, $units->{$unitID}->Decimals);
                $values[$ident]['UnitID'] = $unitID;
            }
        } else { //Ungültige Daten
            $this->ThrowMessage('Error - Unknown data header! NodeNr: \'' . @$header['NodeNr'] . '\' Block: \'' . @$header['Block'] . '\' - Skipping');
            return;
        }

        //Values in Instanz-Variablen schreiben
        $strValues = '';
        $discarded = '';
        foreach ($values as $ident => $value) {
            $strValues .= " | $ident: " . floatval($value['Value']) . $units->{$value['UnitID']}->Suffix;
            $vID = @$this->GetIDForIdent($ident);
            if ($vID === false || ($block->Config[$ident] !== 2 && $block->Config[$ident] !== 4)) { //Variable nicht vorhanden oder nicht als Input (2) oder Input/Output (4) konfiguriert
                $discarded .= ", $ident";
            } else { //Variable aktualisieren
                $this->SetValue($ident, $value['Value']);
                if ($block->Type === 'A') { //Bei analogen Werten das Profil, gemäss der vom CMI übermittelten UnitID, anpassen
                    $this->MaintainVariable($ident, '', VARIABLETYPE_FLOAT, $units->{$value['UnitID']}->ProfileName, 0, true); //Es wird nur das Profil angepasst
                }
            }
        }
        $this->SendDebug("RECEIVE Data ($block->Text) -> Values", trim($strValues, ' |'), 0);
        if (strlen($discarded) > 0) {
            $this->SendDebug("RECEIVE Data ($block->Text) -> Skipped", 'Variable(s) not active/input: ' . trim($discarded, ','), 0);
        }
    }

    /**
     * Sendet einen Daten-Block basierend auf einem Bit-String an die CMI.
     * @param int $BlockNr des Datenpakets
     * @param string $Bits
     * @access public
     */
    public function SendBits(int $BlockNr, string $Bits) {
        if (preg_match('/^[0-1]+$/', $Bits) === 0) { //nur 0 oder 1 erlaubt
            $this->ThrowMessage('Bits (%s) contains wrong values - has to be 0 or 1 each bit -> Stopping', $Bits);
            return false;
        }
        return $this->Send($BlockNr, str_split($Bits));
    }

    /**
     * Sendet einen ganzen Daten-Block an die CMI.
     * @param int $BlockNr des Datenpakets
     * @param array $Values mit einem Wert pro Ausgang
     * @param optional array $UnitIDs mit einer UnitID pro Ausgang
     * @access public
     */
    public function Send(int $BlockNr, array $Values, array $UnitIDs = []) {
        if ($this->CheckIOConfig() === false) {
            $this->ThrowMessage('I/O-Instance not ready - check gateway -> Stopping');
            return false;
        }

        //Gültigkeit der Parameter prüfen
        if ($BlockNr < 0 || $BlockNr > 9) { //Ungültige BlockNr
            $this->ThrowMessage('Wrong BlockNr (%s) - has to be between 0-9 -> Stopping', $BlockNr);
            return false;
        }
        $block = $this->GetBlockInfoByNr($BlockNr);
        if (count($Values) !== $block->Size) { //falsche Anzahl Werte
            $this->ThrowMessage('Count of Values (%1$u) does not match Block-Size (%2$u) -> Stopping', count($Values), $block->Size);
            return false;
        }
        if (count($UnitIDs) > 0 && count($UnitIDs) !== $block->Size) { //falsche Anzahl UnitIDs
            $this->ThrowMessage('Count of UnitIDs (%1$u) does not match Block-Size (%2$u) -> Stopping', count($UnitIDs), $block->Size);
            return false;
        }

        //Daten prüfen/konvertieren
        $units = json_decode($this->GetBuffer('Units'));
        $strValues = '';
        $strWrong = '';
        for ($i = 0; $i < $block->Size; $i++) {
            if (array_key_exists($i, $UnitIDs) === false) { //UnitID nicht definiert
                $UnitIDs[$i] = 0; //Dimensionslos
            }
            if (($block->Type === 'D' && $Values[$i] != 0 && $Values[$i] != 1)) { //ungültiger (digitaler) Wert
                $strWrong .= ', ' . $block->Idents[$i];
            }
            $strValues .= ' | ' . $block->Idents[$i] . ': ' . floatval($Values[$i]) . $units->{$UnitIDs[$i]}->Suffix; //Werte immer als Zahl ausgeben (auch boolsche Werte)
            $Values[$i] = $this->UnitConvertDecimals($Values[$i], $units->{$UnitIDs[$i]}->Decimals, 0); //CoE überträgt analoge Werte immer als Ganzzahl (16Bit) ohne Komma
            if ($block->Type === 'A' && ($Values[$i] < -32767 || $Values[$i] > 32767)) { //ungültiger analoger Wert
                $strWrong .= ', ' . $block->Idents[$i];
            }
        }
        $this->SendDebug("SEND Data ($block->Text) -> Values", trim($strValues, ' |'), 0);
        if ($strWrong !== '') { //ungültige Werte erkannt
            $this->ThrowMessage('Wrong value(s) for %s -> Stopping', trim($strWrong, ' ,'));
            return false;
        }

        //Daten senden
        $data = '';
        if ($block->Type === 'A') { //Analoge Daten
            $data = pack('s4C4', ...$Values, ...$UnitIDs); //4x 16Bit Werte und 4x 8Bit UnitID
        } elseif ($block->Type === 'D') { //Digitale Daten
            $data = strrev(implode('', $Values)); //Umgekehrte Bit-Folge der Werte (16Bit)
            $data = pack('vx10', base_convert($data, 2, 10)); //Bit-Folge in Ganzzahl umwandeln und als 16Bit Little-Endian + 10 NUL verpacken
        }
        $this->SendDebug("SEND Data ($block->Text) -> RAW", $data, 1);
        $data = utf8_encode(pack('C2', $this->ReadPropertyInteger('NodeNr'), $block->Nr) . $data); //Header (8Bit KnotenNr + 8Bit BlockNr) hinzufügen & utf8-codieren
        $data = json_encode(['DataID' => '{C8792760-65CF-4C53-B5C7-A30FCC84FEFE}' /*Erweitert (Socket) TX GUID*/, 'Type' => 0 /*Data*/, 'ClientIP' => $this->ReadPropertyString('RemoteIP'), 'ClientPort' => 5441, 'Buffer' => $data]);
        $this->SendDebug("SEND Data ($block->Text) -> JSONString", $data, 0);
        $response = @$this->SendDataToParent($data);

        //Antwort UDP-Socket auswerten
        if ($response === false) { //Fehler seitens IPS
            $this->SetStatus(self::STATUS_Error_FailedDependency);
            return false;
        }
        if ($this->GetStatus() !== self::STATUS_Ok_InstanceActive) {
            $this->SetStatus(self::STATUS_Ok_InstanceActive);
        }
        return true;
    }

    /**
     * Sendet alle Ausgangs-Variablen an die CMI
     * (wird normalerwise mittels Timer aufgerufen um ein Timeout auf den Eingängen der Regler zu verhindern)
     * @access public
     */
    public function SendAllOutputs() {
        $this->SendDebug('SEND all Outputs', 'Running...', 0);

        //Alle Ausgänge ermitteln und in Blöcken zusmmenfassen
        $send = [];
        $idents = '';
        for ($i = 0; $i < 10; $i++) { //Alle möglichen Blöcke (0-9) durchlaufen
            $block = $this->GetBlockInfoByNr($i);
            $sent = false;
            foreach ($block->Idents as $idt) {
                if ($block->Config[$idt] > 2) { //Output oder Input/Output
                    if ($sent === false) { //Block wird noch nicht gesendet (wenn Block gesendet wird, gehen alle Idents innerhalb des Blocks mit)
                        $send[$block->Nr] = $this->GetBlockValuesFromInstance($block);
                        $sent = true;
                    }
                    $idents .= ", $idt";
                }
            }
        }

        //Ausgangs-Blöcke senden
        foreach ($send as $blockNr => $values) {
            $this->Send($blockNr, array_column($values, 'Value'), array_column($values, 'UnitID'));
        }
        $this->SendDebug('SEND all Outputs', 'Done', 0);

        //Bestätigung wenn in KonfigurationsForm aufgerufen
        if (func_num_args() === 1 && func_get_arg(0) === true) { //wird nur bei Button SendAllOutputs in Konfigurationsform mitgegeben
            echo $this->Translate('Sent outputs') . ': ' . trim($idents, ', ');
        }
    }

    /**
     * Liest alle Werte / UnitIDs eines Blocks aus den Instanz-Variablen aus.
     * Ist eine Instanz-Variable des Blocks nicht aktiv / als Ausgang konfiguriert, wird dafür 0 zurückgegeben.
     * @param object $Block Objekt welches durch $this->GetBlockInfoBy... generiert wird.
     * @return array mit Value & UnitID pro Instanz-Variable, wobei der Key dem Ident der Instanz-Variable entspricht
     * @access public
     */
    private function GetBlockValuesFromInstance(object $Block) {
        $values = [];
        $discarded = '';
        foreach ($Block->Idents as $idt) { //Daten des ganzen Blocks ermitteln.
            $v = 0; //Value
            $u = 0; //UnitID
            $vID = @$this->GetIDForIdent($idt);
            if ($Block->Config[$idt] > 2 && $vID !== false) { //Als Output definiert & IPS-Variable vorhanden
                $v = $this->GetValue($idt);
                if ($Block->Type === 'A') { //UnitID ist nur für Analoge Werte nötig
                    $var = IPS_GetVariable($vID);
                    $pName = $var['VariableProfile'];
                    if ($var['VariableCustomProfile'] !== '' && strpos($var['VariableCustomProfile'], self::PREFIX . '.') === 0) { //CustomProfile entspricht einem Modul-Profil
                        $pName = $var['VariableCustomProfile'];
                    }
                    $u = intval(substr($pName, strrpos($pName, '.') + 1)); //Zahl nach dem letzten Punkt in Profilnamen = UnitID
                }
            } else { // nicht als Output definiert oder IPS-Variable nicht vorhanden
                $discarded .= "$idt, ";
            }
            $values[$idt]['Value'] = $v;
            $values[$idt]['UnitID'] = $u;
        }
        if (strlen($discarded) > 0) {
            $this->SendDebug("SEND Prepare ($Block->Text) -> Skipped", 'Variable(s) not active/ouput: ' . trim($discarded, ', '), 0);
        }
        return $values;
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
        $this->SendDebug('SEND Instance-Variable changed', "Ident: $Ident - Value: " . floatval($Value) . ' - Running...', 0);

        //Block-Informationen definieren
        $block = $this->GetBlockInfoByIdent($Ident);
        $values = $this->GetBlockValuesFromInstance($block); //Werte des ganzen Blocks ermitteln
        $values[$Ident]['Value'] = $Value; //Neuen Wert in Block aktualisieren

        //Daten senden & Antwort auswerten
        $response = $this->Send($block->Nr, array_column($values, 'Value'), array_column($values, 'UnitID'));
        if ($response !== false) { //kein Fehler seitens IPS
            if ($block->Config[$Ident] < 4) { //UDP sendet keine Antwort zurück, wenn Variable jedoch ein Input/Output ist, müsste die CMI den neuen Wert zurückmelden.
                $this->SetValue($Ident, $Value);
            }
            $this->SendDebug('SEND Instance-Variable changed', 'Done', 0);
            return true;
        }
        $this->SetStatus(self::STATUS_Error_FailedDependency);
        return false;
    }

    /**
     * Gibt alle Block-Informationen basierend auf dem Variblen-Ident zurück.
     * @param string $Ident einer Instanz-Variable
     * @return stdObj mit Type, Nr, Min, Max, Size, Text, Idents, Config des Blocks oder false bei ungültigem Ident
     * @access private
     */
    private function GetBlockInfoByIdent(string $Ident) {
        //Block-Infos zusammenstellen
        $type = substr($Ident, 0, 1);
        $id = intval(substr($Ident, 1));
        $blockNr = -1;
        if ($id > 0 && $id < 33) {
            if ($type === 'A') { //Analoger Block
                $blockNr = intval(ceil($id / 4)); //BlockNr (1-8) berechnen
            } elseif ($type == 'D') { //Digitaler Block
                $blockNr = 0; //Digital 1-16
                if ($id > 16) { //Digital 17-32
                    $blockNr = 9;
                }
            }
            if ($blockNr > -1 && $blockNr < 10) { //gültiger Ident
                return $this->GetBlockInfoByNr($blockNr);
            }
        }
        $this->ThrowMessage('Wrong Ident (%s) - has to be between A1-A32 or D1-D32', $Ident);
        return false;
    }

    /**
     * Gibt alle Block-Informationen basierend auf der BlockNr zurück.
     * @param int $BlockNr zwischen 0-9
     * @return stdObj mit Type, Nr, Min, Max, Size, Text, Idents, Config des Blocks oder false bei ungültiger BlockNr
     * @access private
     */
    private function GetBlockInfoByNr(int $BlockNr) {
        //Block-Infos zusammenstellen
        if ($BlockNr === 0 || $BlockNr === 9) { //Digital
            $type = 'D';
            $min = 1; //erste ID von Block 0
            if ($BlockNr === 9) { //Digital 17-32
                $min = 17; //erste ID von Block 9
            }
            $max = $min + 15; //Digital immer 16 Werte pro Block
            $conf = json_decode($this->ReadPropertyString('Digital'));
        } elseif ($BlockNr > 0 && $BlockNr < 9) { //Analog
            $type = 'A';
            $min = (($BlockNr - 1) * 4 + 1); //Erste ID des Blocks berechnen
            $max = $min + 3; //Letzte Nr des Blocks berechnen
            $conf = json_decode($this->ReadPropertyString('Analog'));
        } else { //ungültige BlockNr
            $this->ThrowMessage('Wrong BlockNr (%s) - has to be between 0-9', $BlockNr);
            return false;
        }
        $text = "Block $type$min-$type$max";
        $conf = array_combine(array_column($conf, 'Ident'), array_column($conf, 'Config'));
        $config = [];
        $idents = [];
        for ($i = $min; $i <= $max; $i++) { //Alle Idents desselben Blocks zusammenstellen
            $idents[] = $type . $i;
            $config[$type . $i] = $conf[$type . $i]; //Nur Config der Idents aus dem Block übernehmen
        }
        return (object) ['Type' => $type, 'Nr' => $BlockNr, 'Min' => $min, 'Max' => $max, 'Size' => ($max - $min + 1), 'Text' => $text, 'Idents' => $idents, 'Config' => $config];
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

    /**
     * Überprüft die Konfiguration der übergeordneten I/O-Instanz (UDP-Socket)
     * @return boolean true wenn alles i.O. sonst false
     */
    private function CheckIOConfig() {
        $pID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if (@IPS_GetInstance($pID)['ModuleInfo']['ModuleID'] !== '{82347F20-F541-41E1-AC5B-A636FD3AE2D8}') { //Parent ist kein UDP-Socket
            $this->SetStatus(self::STATUS_Error_WrongIO);
            return false;
        }
        if ($this->HasActiveParent()) {
            $conf = json_decode(IPS_GetConfiguration($pID));
            if ($conf->BindPort === 5441 && $conf->Open === true && $conf->EnableBroadcast === false) {
                return true;
            }
        }
        $this->SetStatus(self::STATUS_Error_FailedDependency);
        return false;
    }
}