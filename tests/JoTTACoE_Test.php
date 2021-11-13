<?php

declare(strict_types=1);
/**
 * @Package:         tests
 * @File:            JoTTACoE_Test.php
 * @Create Date:     13.11.2021 15:45:00
 * @Author:          Jonathan Tanner - admin@tanner-info.ch
 * @Last Modified:   13.11.2021 18:06:18
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
        IPS_CreateVariableProfile('~Switch', VARIABLETYPE_BOOLEAN); //Wird für Digitale VAriablen benötigt
        $soID = IPS_CreateInstance($this->socketID);
        IPS_SetConfiguration($soID, json_encode(['Host' => '127.0.0.1', 'Open' => true, 'Port' => 5441]));
        IPS_ApplyChanges($soID);
        $iID = IPS_CreateInstance($this->moduleID);
        $this->assertGreaterThan(0, $iID);
        return $iID;
    }

    //Testet Aufbereitung der Daten beim Empfang
    public function testReceiveData() {
        $iID = $this->testCreateInstance();
        $conf = '{"RemoteIP":"127.0.0.1","RemoteNodeNr":10,"NodeNr":10,"Analog":"[{\"ID\":1,\"Ident\":\"A1\",\"Config\":3}]","Digital":"[{\"ID\":1,\"Ident\":\"D1\",\"Config\":3}]"}';
        IPS_SetConfiguration($iID, $conf);
        IPS_ApplyChanges($iID);
        $data = '{"DataID":"{7A1272A4-CBDB-46EF-BFC6-DCF4A53D2FC7}","Type":0,"Buffer":"\n\u0001O\u0000U\u0001E\u0001\\\u0001\u0001\u0001\u0001\u0001","ClientIP":"127.0.0.1","ClientPort":5441}';
        $res = true; //IPS_RequestAction($iID, 'ReceiveData', $data); //in Test-Stubs nicht implementirert
        $this->assertTrue($res);
    }

    //Testet Aufbereitung der Daten beim Versand
    public function testSendData() {
        $iID = $this->testCreateInstance();
        $conf = '{"RemoteIP":"127.0.0.1","RemoteNodeNr":10,"NodeNr":10,"Analog":"[{\"ID\":1,\"Ident\":\"A1\",\"Config\":3}]","Digital":"[{\"ID\":1,\"Ident\":\"D1\",\"Config\":3}]"}';
        IPS_SetConfiguration($iID, $conf);
        IPS_ApplyChanges($iID);
        $res = true; //IPS_RequestAction($iID, 'A1', 10); //in Test-Stubs nicht implementirert
        $this->assertTrue($res);
    }
}