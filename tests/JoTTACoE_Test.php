<?php

declare(strict_types=1);

/**
 * @Package:         tests
 * @File:            JoTTACoE_Test.php
 * @Create Date:     13.11.2021 15:45:00
 * @Author:          Jonathan Tanner - admin@tanner-info.ch
 * @Last Modified:   03.12.2021 16:45:19
 * @Modified By:     Jonathan Tanner
 * @Copyright:       Copyright(c) 2020 by JoT Tanner
 * @License:         Creative Commons Attribution Non Commercial Share Alike 4.0
 *                   (http://creativecommons.org/licenses/by-nc-sa/4.0/legalcode)
 */

use PHPUnit\Framework\TestCase;

//IP-Symcon "Simulator" laden
include_once __DIR__ . '/stubs/GlobalStubs.php';
include_once __DIR__ . '/stubs/KernelStubs.php';
include_once __DIR__ . '/stubs/ModuleStubs.php';
include_once __DIR__ . '/stubs/MessageStubs.php';
include_once __DIR__ . '/stubs/ConstantStubs.php';

class JoTTACoE_Test extends TestCase {
    //Manual zu PHPUnit: https://phpunit.readthedocs.io/en/9.5/

    private $moduleID = '{61108236-EBFE-207F-2FEC-55EDB2B4FDFF}';
    private $socketID = '{82347F20-F541-41E1-AC5B-A636FD3AE2D8}'; //UDP-Socket

    //wird vor jedem Test ausgeführt
    public function setup(): void {
        IPS\Kernel::reset();
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/IOStubs/library.json');
        parent::setup();
    }

    //jeder Test begint mit 'test' + Was soll getestet werden
    public function testBeispiel() {
        $var1 = 1;
        $var2 = 4;
        $var3 = 5;
        $sum = $var1 + $var2 + $var3;
        $this->assertEquals(10, $sum); //erfolgreicher Test
        //$this->assertEquals(12, $sum); //fehlerhafter Test
    }

    //Testet das Format der ModBusConfig.json
    public function testUnits() {
        $file = __DIR__ . '/../JoTTACoE/units.json';

        //Check JSON Syntax Errors
        $json = file_get_contents($file);
        $config = json_decode($json);
        $this->assertEquals(json_last_error(), JSON_ERROR_NONE, 'Error (' . json_last_error_msg() . ') in ' . $file);

        //Check definitions
        if (json_last_error() === JSON_ERROR_NONE) {
            $this->assertGreaterThanOrEqual(1, count($config), "$file does not contain definitions.");
            foreach ($config as $c) {
                if (property_exists($c, 'UnitID')) {
                    $a = 'UNITID: ' . $c->UnitID . ' - ';
                    $this->assertIsInt($c->UnitID, $a . 'Wrong definition of \'UnitID\'.');
                    $this->assertGreaterThanOrEqual(0, $c->UnitID, $a . '\'UnitID\' has to be >= 0.');
                    $this->assertIsString($c->Name, $a . 'Wrong definition of \'Name\'.');
                    $this->assertMatchesRegularExpression('/^[a-zA-Z0-9\\-]*$/', $c->Name, $a . 'Wrong characters in \'Name\'. Allowed are only [a-zA-Z0-9\\-].');
                    $this->assertIsString($c->Suffix, $a . 'Wrong definition of \'Suffix\'.');
                    $this->assertIsInt($c->Decimals, $a . 'Wrong definition of \'Decimals\'.');
                    $this->assertGreaterThanOrEqual(0, $c->Decimals, $a . '\'Decimals\' has to be >= 0.');
                } else {
                    $this->assertTrue(false, 'Definition does not contain \'UnitID\'.');
                }
            }

            //Check duplicates
            $dubChecks = ['UnitID'];
            foreach ($dubChecks as $index) {
                $col = array_column($config, $index);
                $dup = array_unique(array_diff_assoc($col, array_unique($col)));
                $this->assertCount(0, $dup, "Found duplicated '$index': " . implode(', ', $dup));
            }
        }
    }

    //Testet ob die Instanz erstellt werden kann
    public function testCreateInstance() {
        IPS_CreateVariableProfile('~Switch', VARIABLETYPE_BOOLEAN); //Wird für Digitale Variablen benötigt
        //(Parent)Instanz erstellen
        $soID = IPS_CreateInstance($this->socketID);
        IPS_SetConfiguration($soID, json_encode(['Host' => '127.0.0.1', 'Open' => true, 'Port' => 5441]));
        IPS_ApplyChanges($soID);
        $iID = IPS_CreateInstance($this->moduleID);
        IPS_ConnectInstance($iID, $soID);
        $this->assertGreaterThan(0, $iID); //Instanz erfolgreich erstellt?

        //Instanz konfigurieren
        $analog = '[{\"ID\":1,\"Ident\":\"A1\",\"Config\":0},{\"ID\":2,\"Ident\":\"A2\",\"Config\":1},{\"ID\":3,\"Ident\":\"A3\",\"Config\":2},{\"ID\":4,\"Ident\":\"A4\",\"Config\":3}]';
        $digital = '[{\"ID\":1,\"Ident\":\"D1\",\"Config\":0},{\"ID\":2,\"Ident\":\"D2\",\"Config\":1},{\"ID\":3,\"Ident\":\"D3\",\"Config\":2},{\"ID\":4,\"Ident\":\"D4\",\"Config\":3},'
            . '{\"ID\":5,\"Ident\":\"D5\",\"Config\":0},{\"ID\":6,\"Ident\":\"D6\",\"Config\":1},{\"ID\":7,\"Ident\":\"D7\",\"Config\":2},{\"ID\":8,\"Ident\":\"D8\",\"Config\":3},'
            . '{\"ID\":9,\"Ident\":\"D9\",\"Config\":0},{\"ID\":10,\"Ident\":\"D10\",\"Config\":1},{\"ID\":11,\"Ident\":\"D11\",\"Config\":2},{\"ID\":12,\"Ident\":\"D12\",\"Config\":3},'
            . '{\"ID\":13,\"Ident\":\"D13\",\"Config\":0},{\"ID\":14,\"Ident\":\"D14\",\"Config\":1},{\"ID\":15,\"Ident\":\"D15\",\"Config\":2},{\"ID\":16,\"Ident\":\"D16\",\"Config\":3}]';
        $conf = '{"RemoteIP":"127.0.0.1","RemoteNodeNr":10,"NodeNr":10,"DisableReceiveDataFilter":1,"Analog":"' . $analog . '","Digital":"' . $digital . '"}';
        IPS_SetConfiguration($iID, $conf);
        IPS_ApplyChanges($iID);
        $this->assertFalse(@IPS_GetObjectIDByIdent('A1', $iID)); //Variable soll nicht erstellt sein
        $this->assertGreaterThan(0, IPS_GetObjectIDByIdent('A2', $iID)); //Variable soll vorhanden sein (Aktiviert)
        $this->assertGreaterThan(0, IPS_GetObjectIDByIdent('A3', $iID)); //Variable soll vorhanden sein (Eingang)
        $this->assertGreaterThan(0, IPS_GetObjectIDByIdent('A4', $iID)); //Variable soll vorhanden sein (Ausgang)
        $this->assertFalse(@IPS_GetObjectIDByIdent('D1', $iID)); //Variable soll nicht erstellt sein
        $this->assertGreaterThan(0, IPS_GetObjectIDByIdent('D2', $iID)); //Variable soll vorhanden sein (Aktiviert)
        $this->assertGreaterThan(0, IPS_GetObjectIDByIdent('D3', $iID)); //Variable soll vorhanden sein (Eingang)
        $this->assertGreaterThan(0, IPS_GetObjectIDByIdent('D4', $iID)); //Variable soll vorhanden sein (Ausgang)
        return $iID;
    }

    //Testet Aufbereitung der Daten beim Empfang
    public function testReceiveData() {
        $iID = $this->testCreateInstance();
        $pID = IPS_GetInstance($iID)['ConnectionID'];

        //Analoge Werte testen
        $data = pack('C2s4C4', 10, 1, 100, 100, 100, 100, 1, 1, 1, 1); //Knoten 10 | Block 1 | A1=10.0°C | A2=10.0°C | A3=10.0°C | A4=10.0°C
        USCK_PushPacket($pID, $data, '127.0.0.1', 5441);
        $this->assertEquals(0, GetValue(IPS_GetObjectIDByIdent('A2', $iID))); //Variable darf nicht gefüllt werden (kein Eingang)
        $this->assertEquals(10.0, GetValue(IPS_GetObjectIDByIdent('A3', $iID))); //Variable = 10.0 (Eingang)
        $this->assertEquals('JoTTACoE.Temperatur.1', IPS_GetVariable(IPS_GetObjectIDByIdent('A3', $iID))['VariableProfile']); //Profil entspricht UnitID 1 (Temperatur in °C)
        $this->assertEquals(0, GetValue(IPS_GetObjectIDByIdent('A4', $iID))); //Variable darf nicht gefüllt werden (kein Eingang)

        //Digitale Werte testen
        SetValue(IPS_GetObjectIDByIdent('D7', $iID), true);
        $data = pack('C2vx10', 10, 0, 15); //Knoten 10 | Block 0 | D1=1 | D2=1 | D3=1 | D4=1 | D5-16=0
        USCK_PushPacket($pID, $data, '127.0.0.1', 5441);
        $this->assertFalse(@IPS_GetObjectIDByIdent('D1', $iID)); //Variable soll nicht erstellt sein
        $this->assertEquals(false, GetValue(IPS_GetObjectIDByIdent('D2', $iID))); //Variable darf nicht gefüllt werden (kein Eingang)
        $this->assertEquals(true, GetValue(IPS_GetObjectIDByIdent('D3', $iID))); //Variable = true (Eingang)
        $this->assertEquals(false, GetValue(IPS_GetObjectIDByIdent('D4', $iID))); //Variable darf nicht gefüllt werden (kein Eingang)
        $this->assertEquals(false, GetValue(IPS_GetObjectIDByIdent('D7', $iID))); //Variable = false (Eingang)
    }

    //Testet Aufbereitung der Daten beim Versand
    public function testSendData() {
        $iID = $this->testCreateInstance();
        $pID = IPS_GetInstance($iID)['ConnectionID'];
        JoTTACoE_TestFunction($iID, 'SetStatus', [IS_ACTIVE]); //Status ist nach Initialisierung STATUS_Ok_WaitingData (204) bis die ersten Daten ankommen. Daher Status manuell ändern.
        IPS_SetVariableCustomProfile(IPS_GetObjectIDByIdent('A4', $iID), 'JoTTACoE.Temperatur.1');

        //Analoge Werte testen
        RequestAction(IPS_GetObjectIDByIdent('A4', $iID), 10.1);
        $data = USCK_PopPacket($pID);
        $res = pack('C2s4C4', 10, 1, 0, 0, 0, 101, 0, 0, 0, 1); //Knoten 10 | Block 1 | A1=0 | A2=0 | A3=0 | A4=10.1°C (101);
        $this->assertEquals($res, $data['Buffer']);

        //Digitale Werte testen
        RequestAction(IPS_GetObjectIDByIdent('D4', $iID), true);
        $data = USCK_PopPacket($pID);
        $res = pack('C2vx10', 10, 0, 8); //Knoten 10 | Block 0 | D1=0 | D2=0 | D3=0 | D4=1 | D5-16=0
        $this->assertEquals($res, $data['Buffer']);
    }
}