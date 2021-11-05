[![IP-Symcon is awesome!](https://img.shields.io/badge/IP--Symcon-6.0-blue.svg)](https://www.symcon.de)
[![Check Style](https://github.com/jotata/SymconJoTKPP/workflows/Check%20Style/badge.svg)](https://github.com/jotata/SymconJoTKPP/actions?query=workflow%3A%22Check+Style%22)
[![Run Tests](https://github.com/jotata/SymconJoTKPP/workflows/Run%20Tests/badge.svg)](https://github.com/jotata/SymconJoTKPP/actions?query=workflow%3A%22Run+Tests%22)
[![Tested with](https://img.shields.io/badge/tested-Kostal%20PLENTICORE%20plus%207.0-blue)](https://www.kostal-solar-electric.com/de-de/produkte/solar-wechselrichter/plenticore-plus)
[![Release Version](https://img.shields.io/github/v/release/jotata/SymconJoTKPP)](https://github.com/jotata/SymconJoTKPP/releases)

# SymconJoTKPP
Erweiterung zur Abfrage der Werte eines Kostal Wechselrichters via ModBus in IP-Symcon.

## Dokumentation
<p align="right"><a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=9M6W4KM34HWMA&source=url" target="_blank"><img src="https://www.paypalobjects.com/de_DE/CH/i/btn/btn_donateCC_LG.gif" border="0" /></a></p>

**Inhaltsverzeichnis**
1. [Funktionsumfang](#1-funktionsumfang)  
2. [Voraussetzungen](#2-voraussetzungen)  
3. [Unterstützte Geräte](#3-unterst%C3%BCtze-ger%C3%A4te)
4. [Modul-Installation / Update](#4-modul-installation--update) 
5. [Einrichten der Instanz in IP-Symcon](#5-einrichten-der-instanz-in-ip-symcon)
    1. [Erstellen einer neuen Instanz](#1-erstellen-einer-neuen-instanz)
    2. [Konfiguration der Instanz](#2-konfiguration-der-instanz)
    3. [Modul-Funktionen](#3-modul-funktionen)
    4. [Batterie-Management](#4-batterie-management)
    5. [Fehlersuche](#5-fehlersuche)
6. [Anhang](#6-anhang)  
    1. [Modul-Informationen](#1-modul-informationen)
    2. [Changelog](#2-changelog)
    3. [Spenden](#3-spenden)
7. [Support](#7-support)
8. [Lizenz](#8-lizenz)

## 1. Funktionsumfang
Das Modul "JoTKPP" stellt eine Instanz zur Abfrage der Werte von Kostal-Wechselrichtern für IP-Symcon zur Verfügung.
Die Daten werden mittels ModBus abgefragt. Der Benutzer kann frei entscheiden, welche Werte abgefragt werden und ob dafür eine Instanz-Variable angelegt werden soll.
Über IPS Ereignisse oder einen Aufruf der verschiedenen [RequestRead-Funktionen](#3-modul-funktionen) können individuelle Abfrage-Muster realisiert werden (nur bei Bedarf, einmal am Tag, alle x Sekunden, usw.).
Das Modul prüft zudem, ob online eine neue FW-Version für den Wechselrichter verfügbar ist.

## 2. Voraussetzungen
 - IPS 5.5 oder höher  
 - Kostal Wechselrichter mit aktivierter ModBus-Schnittstelle

## 3. Unterstütze Geräte
Das Modul wird grundsätzlich für einen Kostal PLENTICORE Plus 7.0 programmiert / getestet.
Da Kostal aber für alle Geräte der Serien ["PLENTICORE plus"](https://www.kostal-solar-electric.com/de-de/products/hybrid-inverters/plenticore-plus) & ["PIKO IQ"](https://www.kostal-solar-electric.com/de-de/products/string-inverter/piko-iq) dieselben ModBus-Spezifikationen herausgibt, sollten auch andere Geräte dieser Serien funktionieren.

Hersteller: KOSTAL
Modelle:
- PLENTICORE plus 4.2
- PLENTICORE plus 5.5
- PLENTICORE plus 7.0 (getestet)
- PLENTICORE plus 8.5
- PLENTICORE plus 10 
- PIKO IQ 4.2
- PIKO IQ 5.5
- PIKO IQ 7.0
- PIKO IQ 8.5
- PIKO IQ 10

## 4. Modul-Installation / Update
Die Installation erfolgt über den IPS Module-Store. In der Suche einfach "JoTKPP" eingeben und die Installation starten.
Update erfolgt ebenfalls über den Module-Store. Einfach beim installierten Modul auf "Aktualisieren" klicken.

**Das Modul wird für den privaten Gebrauch kostenlos zur Verfügung gestellt.**

**Bei kommerzieller Nutzung (d.h. wenn Sie für die Einrichtung/Installation und/oder den Betrieb von IPS Geld erhalten) wenden Sie sich bitte an den Autor.**

**ACHTUNG: Der Autor übernimmt keine Haftung für irgendwelche Folgen welche durch die Nutzung dieses Modules entstehen!**

## 5. Einrichten der Instanz in IP-Symcon
  ### 1. Erstellen einer neuen Instanz
   1. Neue Instanz hinzufügen
   2. Im Schnellfilter "Kostal" eingeben
   3. Das Gerät "Kostal PLENTICORE plus" auswählen
   4. Name & Ort anpassen (optional)
   5. Falls noch keine ModBus Gateway Instanz vorhanden ist, wird eine solche erstellt. Diese entsprechend konfigurieren:
      - Modus: Modbus TCP
      - Geräte-ID: 71 (kann auf dem Wechselrichter in den Modbus-Einstellungen nachgeschaut werden)
      - Swap LSW/MSW for 32Bit/64Bit values (kann auf dem Wechselrichter in den Modbus-Einstellungen nachgeschaut werden):
        - Off = big-endian (ABCD) Sunspec
        - On = little-endian (CDAB) Standard Modbus
 
  ### 2. Konfiguration der Instanz
   - Abfrage-Intervall: Definiert die Zeit, in welcher die Werte via ModBus abgefragt werden sollen. Es werden nur die Werte abgefragt, bei welchen "Aktiv" angehakt ist.
   - Jetzt lesen: Liest im Hintergrund alle aktiven Idents aus und schreibt die Werte in die Instanz-Variablen.
   - Gruppe / Ident: Diese Bezeichnung kann zur Abfrage einer Gruppe von Werten oder einzelner Werte mit der entsprechenden [RequestRead-Methode](#3-modul-funktionen) verwendet werden.
   - Name: Die Bezeichnung der Instanz-Variable gemäss Kostal Spezifikation.
   - Eigener Name: Wenn euch die Bezeichnung der Instanz-Variabeln nicht gefällt, könnt ihr den Namen direkt in der Variable anpassen. Der neue Name wird dann in dieser Spalte angezeigt.
   - Profil: Standard-Profil des Modules.
   - Eigenes Profil: Ihr könnt der Instanz-Variable ein eigenes Profil zuweisen (z.B. für Batterie-Ladezustand). Dieses wird dann hier angezeigt.
   - Aktiv: Wenn der Haken gesetzt ist, wird einen entsprechende Instanz-Variable erstellt. VORSICHT: Wird der Haken entfernt und die Konfiguration gespeichert, so wird die entsprechende Instanz-Variable gelöscht.
   - Konfiguration PV-Überschuss (nur sichtbar, wenn min. eine Geräte-Eigenschaft aus der Gruppe 'Surplus' aktiviert ist):
     - Für jeden der Surplus-Idents können beliebig viele Konfigurationen erstellt werden. Diese werden nach Ein-Wert absteigend (Höchster zuerst) für die Berechnung genutzt. Wenn also mehrere Bedingungen erfüllt sind, wird die Variable immer auf den Wert des höchsten aktiven Ein-Wertes geschaltet.
     - Ein-Wert: Ist der Überschuss grösser/gleich dieser Wert, wird der Ein-Zähler um eins erhöht. Geht der Überschuss unter diesen Wert, wird der Ein-Zähler auf 0 zurück gesetzt.
     - Aus-Differenz: Wenn der Überschuss für diese Konfiguration einmal aktiviert ist, wird der Aus-Zähler um eins erhöht, wenn der Überschuss auf diese Differenz unter den Ein-Wert oder tiefer sinkt.
     - Ein/Aus-Zähler: Bei jeder Abfrage wird der interne Zähler um eins erhöht, wenn die Ein/Aus-Bedingung erfüllt ist. Ist der interne Zähler grösser/gleich dem hier angegebenen Wert, wird die Variable auf den Wert (bzw. beim Aus-Zähler auf 0) geschaltet.
     - Wert: Der Ident wird auf diesen Wert geschaltet, wenn die Bedingungen dieser Konfiguration erfüllt sind und diese Konfiguration jene mit dem höchsten aktiven Ein-Wert ist.
     - Aktiv: Nur aktivierte Konfigurationen werden für die Berechnung berücksichtigt.
   - Zusätzliche Abfragen erstellen:
     - Gewünsche Gruppen ODER Idents anklicken -> diese werden unten aufgelistet und können von hier direkt kopiert werden um ein Ereignis anzupassen oder in einer Modulfunktion abzufragen.
     - Einen Namen für das Ereignis eingeben (optional)
     - Den Ereignis-Typ auswählen
     - Das Ereignis mit dem Button "Ereignis erstellen" einrichten (das Ereignis wird nur erstellt, jedoch nicht aktiviert)
     - Mit dem zusätzliche Button "Ereignis (xxxxx) prüfen" kann das neu erstelle Ereignis direkt geöffnet und angepasst / aktiviert werden
     
     Mit den Bedingungen eines Ereignisses ist es möglich, sehr komplexe Abfrage-Muster zu erstellen (z.B. PV-Werte nur tagsüber abfragen, wenn da auch ein Strom fliesst).
     Ereignisse schreiben die Werte nur in eine Status-Variable, wenn diese vorher unter Geräte-Eigenschaften "aktiviert" wurden. Dort kann der Abfrage-Intervall dann einfach auf "0" gestellt werden.

  ### 3. Modul-Funktionen
  Die folgenden Funktionen stehen in IPS-Ereignissen/-Scripts zur Verfügung:
  - JoTKPP_RequestRead(): Liest alle Werte, bei welchen der Haken "Aktiv" gesetzt ist. Aktualisiert die entsprechenden Instanz-Variablen und gibt die Werte als Array zurück.
  - JoTKPP_RequestReadAll(): Liest alle Werte.*
  - JoTKPP_RequestReadIdent(string $Ident): Liest alle Werte, deren Ident angegeben wird (mehrere Idents werden durch ein Leerzeichen getrennt).*
  - JoTKPP_RequestReadGroup(string $Gruppe): Liest alle Werte, deren Gruppe angegeben wird (mehrere Gruppen werden durch ein Leerzeichen getrennt).*
  - JoTKPP_CheckFirmwareUpdate(): Holt den Namen der aktuellsten FW-Datei bei Kostal, speichert diese in einer Instanz-Variable und gibt sie als String zurück.
  - JoTKPP_GetDeviceInfo(): Gibt die wichtigsten Geräte-Informationen als Array zurück.*
  - IPS_RequestAction(int $InstanzID, string $Ident, mixed $Value): Damit können die Idents, welche mit Zugriff RW oder W markiert sind geschrieben werden.

  *) Die Werte werden auch gelesen, wenn der Haken "Aktiv" nicht gesetzt ist. Sie werden dann jedoch nur als Array zurückgegeben und nicht in eine Instanz-Variable geschrieben.
  
  ### 4. Batterie-Management
  Ab FW-Version 1.46 auf dem Wechselrichter und Modul-Version 2.0 ist es möglich, das Batterie-Management und die AC-Leistung des Wechselrichters via ModBus zu steuern.
  Voraussetzung dafür ist, dass der Installateur auf dem Wechselrichter das externe Batterie-Management unter 'Servicemenu -> Batterieeinstellungen -> Batteriesteuerung' aktiviert.
  Dieser Modus kann nicht mit dem normalen Anlagen-Benutzer aktiviert werden.

  Details zum Batterie-Management sind unter [Schnittstellen Protokolle bei den Downloads für den Wechselrichter auf der Kostal-Webseite](https://www.kostal-solar-electric.com/de-de/download/download#accordionContent7) zu finden.
  Beim Schreiben ist zu berücksichtigen, dass die Werte gemäss den Einheiten der Instanz-Variablen von IPS (also in kW anstatt W) angegeben werden müssen. Das Modul rechnet die Werte selbständig in W um, bevor diese an den Wechselrichter gesendet werden.

  **ACHTUNG: Der Autor lehnt jegliche Haftung für Schäden an den betroffenen Geräten (Wechselrichter, Batterie, PV-Anlage, usw.) ab, welche durch unsachgemässe Nutzung dieser Funktion entstehen können!**

  ### 5. Fehlersuche
  Die Debug-Funktion der Instanz liefert detaillierte Informationen über die Konvertierung der Werte und vom ModBus zurückgegebenen Fehler.

## 6. Anhang
###  1. Modul-Informationen
| Modul  | Typ    | Hersteller | Gerät           | Prefix | GUID                                   |
| :----- | :----- | :--------- | :---------------| :----- | :------------------------------------- |
| JoTKPP | Device | Kostal     | PLENTICORE plus | JoTKPP | {E64278F5-1942-5343-E226-8673886E2D05} |
| JoTKPP | Device | Kostal     | PIKO IQ         | JoTKPP | {E64278F5-1942-5343-E226-8673886E2D05} |

### 2. Changelog
Version 2.2
- FIX: RW-Variablen können nun mit IPS_RequestAction geschrieben werden.
- Schreiben für Batterie-Management und AC-Control funktioniert nun über den IPS-internen DatenFlow. Allfällig vorhandene I/O-Instanzen "JoTKPP_WriteSocket for #\[InstanzID\]" können manuell gelöscht werden.

Version 2.1
- FIX: Bei aktivem Polling wird nach einem Neustart von IPS nun kein Fehler mehr im Log generiert.
- Migrations-Funktionen für Update von Versionen < 1.4 entfernt

Version 2.0
- Schreiben für Batterie-Management und AC-Control eingebaut (Details siehe [Batterie-Management](#4-batterie-management)).
- Button 'Jetzt lesen' im Konfigurations-Form liest alle ausgewählten Idents einmalig aus.
- LED für Lese-/Schreib-Aktivität im Konfigurations-Form hinzugefügt.
- Optimierung der Daten-Übertragung.
- Profile auf DE übersetzt.
- FIX: Selten auftretende Fehlermeldung 'NaN/INF Werte werden nicht unterstützt' vermutlich behoben.
- FIX: Instanz-Variable für FW-Update wird nun bei ApplyChanges nicht mehr gelöscht, wenn FW-Check aktiviert ist. Falls FW-Check beim Update aktiviert ist, wird die Variable einmalig neu erstellt und Ereignisse müssen ev. manuell angepasst werden.
- FIX: Änderungen am Gateway & ClientSocket werden nun erkannt und Gerät wird mit neuer Konfiguration neu ausgelesen.
- FIX: Fehler-Korrektur der folgenden Idents (falsche Daten-Übermittlung (Endianess) durch Wechselrichter): EMState, BTWorkCapacity, BTGrossCapacity, ACGenEnergy
- FIX: Fehler in FW-Update-Check behoben, wenn URL nicht erreichbar ist.
- FIX: Profil-Icons für Batterie-Status waren vertauscht.
- FIX: Konfigurationsform wird nun auch bei veralteter FW-Version korrekt geladen.

Version 1.6
- Restliche noch fehlende Geräte-Parmeter gemäss KOSTAL-Spezifikation 1.9 hinzugefügt (lesend).
- Zugriffs-Art wird nun in Konfigurationsform angezeigt.
- FIX: Total-Parameter (charge/discharge energy, energy from PV, AC to grid) werden nun korrekt ausgelesen.

Version 1.5
- PV-Überschuss hinzugefügt
- Berechnung von "Home own consumtion from all" hinzugefügt
- Berechnung von "Total power from PV strings" hinzugefügt
- Status für Batterie / Netz hinzugefügt
- ModBus-Bytereihenfolge des WR kann nun im ModBus-Gateway von IPS umgestellt werden (IPS 'Swap LSW/MSW...' AN = 'little-endian (CDAB)...' auf dem Wechselrichter).
- Wenn Variablen-Werte weniger als eine Sekunde alt sind, werde diese nicht erneut vom Wechselrichter abgefragt, sondern direkt vom "Cache" zurückgegeben.
- Scale Factors hinzugefügt
- Vorbereitungen für ModBus-Write (Batterie-Management)
- FIX: Abfrage-/FW-Update Intervall können nicht mehr negativ eingegeben werden.
- FIX: Fehler bei Rückgabe von NAN/INF-Werten

Version 1.4
- Folgende zusätzliche Parameter hinzugefügt: Installed powermeter, Total DC power, Battery ready flag, Battery cross capacity, Battery actual SOC, Battery Firmware MC, Battery Type, Total battery AC/DC (dis)charge, Total energy from PV(1-3).
- Folgende Parameter korrigiert: State of energy manager, Total DC power from PV.
- Profil Status.KostalFuse umgedreht (kann leider nicht testen, ob dies bei einer ausgelösten Sicherung nun korrekt funktioniert).
- Verbesserung bei den JSON-Konfigurations-Daten.
- Verbesserung beim Device-Discovery (inkl. Prüfung bei Änderung des Gateways).
- Fehler mit JoTKPP_RequestReadIdents behoben, wenn Idents gleichen String enthalten.
- Neue Public Function JoTKPP_GetDeviceInfo() hinzugefügt.
- Geräte-Informationen können nun direkt in der Instanzkonfiguration angezeigt werden.
- Im Konfigurationsformular ist es nun möglich, einzelne Idents / Gruppen auszuwählen und für deren Abfrage ein Ereignis zu erstellen.
- Interne Variablen optimiert.
- Verbesserte Fehlermeldungen bei Modul-Funktionen.

Version 1.3
- Fehler im Profil "JoTKPP.Battery.Charge" behoben, welcher dazu führte, dass IPS beim Anzeigen von Diagrammen mit diesem Profil abstürzte.

Version 1.2
- Überprüfung der übergeordneten Instanzen optimiert.
- Fehler bei Umrechnung von SInt-Werten (z.B. Actual Battery Power) behoben, wenn diese einen negativen Wert haben.
- Zusätzlicher Wert "Actual Inverter Generation power" hinzugefügt.

Version 1.1
- Timer für FW-Update-Check in Konfigurationsformular integriert.
- Default-Positionen auf 10er-Zahlen angepasst, damit Sortierung auch innerhalb einer Gruppe möglich ist.
- Zusätzliche Meldung im Debug-Log.

Version 1.0 (RC1)
- Bezeichnungen für Powermeter-Werte angepasst.
- Verbesserte Fehlerausgabe ModBus.

Version 0.9:  
- Messwerte für Powermeter hinzugefügt.
- Verbesserungen beim DeviceDiscovery.
- Änderung des Gateways wird nun erkannt und verarbeitet.

Version 0.8:  
- Erste öffentliche Beta-Version.
- Feedbacks zu Fehlern aber auch funktionierende Geräte & Konfigurationen sind willkommen.

### 3. Spenden    
Das Modul ist für die nicht kommzerielle Nutzung kostenlos. Spenden als Unterstützung für den Autor sind aber willkommen:  
<p align="center"><a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=9M6W4KM34HWMA&source=url" target="_blank"><img src="https://www.paypalobjects.com/de_DE/CH/i/btn/btn_donateCC_LG.gif" border="0" /></a></p>

## 7. Support
Fragen, Anregungen, Kritik und Fehler zu diesem Modul können im entsprechenden [Thread des IPS-Forums](https://www.symcon.de/forum/threads/41720-Modul-JoTKPP-Solar-Wechselrichter-Kostal-PLENTICORE-plus-PIKO-IQ) deponiert werden.
Da das Modul in der Freizeit entwickelt wird, kann es jedoch eine Weile dauern, bis eine Antwort im Forum verfügbar oder ein entsprechendes Update vorhanden ist. Besten Dank für euer Verständnis :-)

## 8. Lizenz
IPS-Modul: <a href="https://creativecommons.org/licenses/by-nc-sa/4.0/" target="_blank">CC BY-NC-SA 4.0</a>
