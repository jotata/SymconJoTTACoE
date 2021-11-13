<?php

declare(strict_types=1);
/**
 * @Package:         tests
 * @File:            Library_Test.php
 * @Create Date:     13.11.2021 15:45:00
 * @Author:          Jonathan Tanner - admin@tanner-info.ch
 * @Last Modified:   13.11.2021 15:45:26
 * @Modified By:     Jonathan Tanner
 * @Copyright:       Copyright(c) 2020 by JoT Tanner
 * @License:         Creative Commons Attribution Non Commercial Share Alike 4.0
 *                   (http://creativecommons.org/licenses/by-nc-sa/4.0/legalcode)
 */

use PHPUnit\Framework\TestCase;

include_once __DIR__ . '/stubs/Validator.php';

//IP-Symcon Basis-Tests
class Library_Test extends TestCaseSymconValidation {
    public function testValidateLibrary() {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateModule() {
        $this->validateModule(__DIR__ . '/../JoTTACoE');
    }
}