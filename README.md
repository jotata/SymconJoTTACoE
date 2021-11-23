[![IP-Symcon is awesome!](https://img.shields.io/badge/IP--Symcon-6.0-blue.svg)](https://www.symcon.de)
[![Check Style](https://github.com/jotata/SymconJoTKPP/workflows/Check%20Style/badge.svg)](https://github.com/jotata/SymconJoTKPP/actions?query=workflow%3A%22Check+Style%22)
[![Run Tests](https://github.com/jotata/SymconJoTKPP/workflows/Run%20Tests/badge.svg)](https://github.com/jotata/SymconJoTKPP/actions?query=workflow%3A%22Run+Tests%22)
[![Tested with](https://img.shields.io/badge/tested-UVR16x2-blue)](https://www.ta.co.at/x2-frei-programmierbare-regler/uvr16x2/)
[![Release Version](https://img.shields.io/github/v/release/jotata/SymconJoTTACoE)](https://github.com/jotata/SymconJoTTACoE/releases)

# SymconJoTTACoE
Erweiterung zum Lesen/Schreiben der Werte eines Gerätes von <a href="https://www.ta.co.at/x2-frei-programmierbare-regler/" target="_blank">Technische Alternative</a> via CAN over Ethernet (CoE) in IP-Symcon (IPS).
Die Geräte verfügen über einen CAN-Bus, welcher via CMI mit dem Netzwerk verbunden wird. Über das CoE-Protokoll lassen sich Werte aus dem CAN-Bus empfangen/senden ohne die Limitierungen der JSON-API. Diese kann nur einmal pro Minute Werte lesen aber keine Werte schreiben, benötigt dafür aber deutlich weniger Konfigurationsaufwand.

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
    4. [Fehlersuche](#5-fehlersuche)
6. [Anhang](#6-anhang)  
    1. [CoE-Datenblöcke](#1-coe-datenblöcke)
    2. [Modul-Informationen](#2-modul-informationen)
    3. [Changelog](#3-changelog)
    4. [Spenden](#4-spenden)
7. [Support](#7-support)
8. [Lizenz](#8-lizenz)

## 1. Funktionsumfang
Jede Instanz des Modules kann bis 32 analoge und 32 digitale Werte über das CoE-Protokoll empfangen oder senden. Für jede Instanz-Variable kann der Zustand einzeln konfiguriert werden (Deaktiviert, Aktiviert, Eingang, Ausgang, Eingang / Ausgang). Zudem ist es möglich, das Variablen-Profil direkt über das CoE-Protokoll auszulesen oder auch zu schreiben. Pro Instanz kann eine Remote-CMI angebunden und eine eigene KnotenNr für den CAN-Bus definiert werden.

Es ist auch möglich, das Modul in Kombination mit dem in IPS integrierten Modul "Technische Alternative" zu kombinieren. Das integrierte Modul nutzt die JSON-API um mit einem geringen Konfigurationsaufwand die Daten zu lesen (nur einmal pro Minute) und dieses Modul würde dann nur zum Senden von neuen Werten genutzt.

## 2. Voraussetzungen
 - IPS 6.0 oder höher  
 - CMI von Technische Alternative
 - Für den Empfang via CoE müssen die CoE-Ausgänge auf der CMI entsprechend konfiguriert werden.
 - Zum Senden via CoE müssen die entsprechenden CAN-Eingänge auf dem Endgerät konfiguriert werden.

## 3. Unterstütze Geräte
Das Modul wird grundsätzlich für eine UVR16x2 programmiert / getestet. Da die CMI von Technische Alternative aber alle Werte (CAN-Bus, DL-Bus, ModBus, SMS) via CoE zur Verfügung stellen kann, ist auch die Steuerung anderer Geräte am CAN-Bus und der Empfang von Daten der anderen CMI-Eingänge möglich. 

Hersteller: Technische Alternative

## 4. Modul-Installation / Update
Die Installation erfolgt über den IPS Module-Store. In der Suche einfach **CoE-Knoten (JoTTACoE)** (solange das Modul noch BETA ist muss unbeding der komplette Name angegeben werden, damit dieses angezeigt wird) eingeben und die Installation starten.
Update erfolgt ebenfalls über den Module-Store. Einfach beim installierten Modul auf "Aktualisieren" klicken.

**Das Modul wird für den privaten Gebrauch kostenlos zur Verfügung gestellt.**

**Bei kommerzieller Nutzung (d.h. wenn Sie für die Einrichtung/Installation und/oder den Betrieb von IPS Geld erhalten) wenden Sie sich bitte an den Autor.**

**ACHTUNG: Der Autor übernimmt keine Haftung für irgendwelche Folgen welche durch die Nutzung dieses Modules entstehen!**

## 5. Einrichten der Instanz in IP-Symcon
  ### 1. Erstellen einer neuen Instanz
   1. Neue Instanz hinzufügen
   2. Im Schnellfilter **CoE-Knoten** eingeben
   3. Das Gerät **CoE-Knoten (Remote-CMI)** auswählen
   4. Name & Ort anpassen (optional)
   5. Falls noch keine UDP-Socket Instanz vorhanden ist, wird eine solche erstellt. Diese entsprechend konfigurieren:
      - **Socket öffnen:** Ein
      - **Sende-Host/-Port:** leer lassen (Derselbe UDP-Socket kann für mehrere Instanzen genutzt werden. Die IP der jeweiligen CMI wird direkt im Modul eingestellt.)
      - **Empf.-Host:** IP-Adresse von IPS auswählen (Gleiche IP muss auf der CMI in der Konfiguration der CoE-Ausgänge angegeben werden.)
      - **Empf.-Port:** 5441
      - **Aktiviere Broadcast:** Aus
      - **Aktiviere Reuse Address:** Aus

  ### 2. Konfiguration der Instanz
  - **IP-Adresse:** IP-Adresse der CMI
  - **Empfange alle Daten:** Wird diese Option aktiviert, hört die Instanz auf alle Daten, welche via UDP Port 5441 an IPS gesendet werden (praktisch um im Debug-Log zu sehen, was die CMI alles sendet). Sonst hört die Instanz nur auf Daten von *IP-Adresse* und *Empfange von Knoten-Nr*. Diese Option sollte im produktiven Betrieb deaktiviert werden, da sonst mehrere CMIs in dieselben Variablen schreiben können.
  - **Empfange von Knoten-Nr:** Eine beliebige Zahl zwischen 0 (= Empfang deaktiviert) und 62. Die Knoten-Nr darf auf keinem anderen CAN-Gerät konfiguriert sein. Die gleiche Knoten-Nr muss in der CMI auf den CoE-Ausgängen angegeben werden.
  - **Eigene Knoten-Nr:** Eine beliebige Zahl zwischen 1 und 62. Die Knoten-Nr darf auf keinem anderen CAN-Gerät konfiguriert sein, kann aber dieselbe sein wie *Empfange von Knoten-Nr*. Die gleiche Knoten-Nr muss auf den CAN-Eingängen der Endgeräte angegeben werden.
  - Analoge / Digitale Variablen (bitte die Infos zu [CoE-Datenblöcke](#1-coe-datenblöcke) beachten):
    - **Ident:** Eindeutiger Name (Ident) der Variable innerhalb jeder Modul-Instanz. Diese Nr (ohne den Buchstaben) wird für den Netzwerkausgang (CoE-Ausgang auf der CMI) oder die Ausgangsnummer (CAN-Eingang auf Endgerät) angegeben.
    - **Name:** Entspricht dem Namen der Instanz-Variable in IPS (kann nur über die Eigenschaften der Variable im IPS-Objektbaum geändert werden).
    - **Variable:** Definiert einen der folgenden Zustände der Instanz-Variable:
      - **Deaktiviert:** Instanz-Variable wird nicht erstellt / verwendet und empfängt auch keine Werte.
      - **Aktiviert:** Instanz-Variable wird erstellt, empfängt / sendet aber keine Werte (Kann genutzt werden um die Variable temporär zu deaktivieren, ohne diese zu löschen. So bleibt die Objekt-ID erhalten.)
      - **Eingang:** Instanz-Variable wird erstellt und empfängt Werte vom entsprechenden CoE-Netzwerkausgang.
      - **Ausgang:** Instanz-Variable wird erstellt und kann Werte an den entsprechenden CAN-Ausgang senden. Der Wert wird nach dem Senden sofort in die Variable geschrieben, auch wenn er ev. auf dem CAN-Bus niergends empfangen wurde (CoE kennt keine Quittierung).
      - **Eingang / Ausgang:** Instanz-Variable wird erstellt und kann Werte senden & empfangen. Beim Senden wird der Wert jedoch nicht automatisch in die Variable geschrieben, sonder erst wenn dieser via CoE wieder empfangen wird (dazu muss eine entsprechende Empfangs-Quittierung auf dem Endgerät programmiert werden).

  ### 3. Konfiguration Datenübertragung Endgerät zu CMI (CAN-Ausgänge)
  Damit die CMI Werte von einem Endgerät per CoE an IPS übertragen kann, müssen diese vom jeweiligen Endgerät zuerst auf den CAN-Bus übertragen (CAN-Ausgang) und von der CMI eingelesen (CAN-Eingang) werden.
  Die hierzu verwendeten Konfigurations-Parameter (Knoten, Ausgangsnummer) können uanbhängig von den Einstellungen in der Instanz definiert werden. Der Übersicht halber empfiehlt es sich jedoch, hier jeweils die gleichen Nr zu verwenden.
  
  Details zur Konfiguration der CAN-Ausgänge auf den Endgeräten und wie diese auf der CMI empfangen werden, sind in den Handbüchern von Technische Alternative zu finden.

  ### 4. Konfiguration Datenübertragung CMI zu IPS (CoE-Ausgänge)
  Alle verfügbaren Werte auf einer CMI (CAN-Bus, DL-Bus, ModBus, SMS) können per CoE an IP-Symcon übermittelt werden. Pro Wert muss jeweils auf der CMI eine Konfiguration (Einstellungen -> Ausgänge -> CoE) erstellt werden:
  - **Bezeichnung:** frei definierbar (der Übersicht halber ist es empfehlenswert für den gleichen Wert überall dieselbe Bezeichnung zu verwenden)
  - **Eingang:** normalerweise CAN-Bus und der entsprechende Eingang (es können aber auch Werte der anderen Eingängen der CMI übertragen werden)
  - **IP:** IP-Adresse von IPS (wie im UDP-Socket definiert)
  - **Knoten:** Wert aus "Empfange von Knoten-Nr" der Modul-Instanz
  - **Netzwerkausgang:** Nr "#" der jeweilgen Variable in der Modul-Instanz. Diese muss als *Eingang* oder *Eingang / Ausgang* konfiguriert sein. Wenn die entsprechende Variable nicht als Eingang definiert ist, wird der Wert verworfen (siehe Debug-Log).
  - **Sendebedingung:** je nach Anforderung (bitte die Infos zu [CoE-Datenblöcke](#1-coe-datenblöcke) beachten)

  Die CMI übermittelt per CoE auch die Messgrösse, welche auf dem Eingang eingestellt ist. Die Instanz wertet diese aus und passt das Variablen-Profil entsprechend an. Wenn auf der Variable ein eigenes Profil definiert wird, zeigt IPS die Einheit des eigenen Profils an.

  ### 5. Konfiguration Datenübertragung IPS zu CMI/Endgerät (CAN-Eingänge)
  Alle Werte, der als Ausgang definierten Instanz-Variablen, können per CoE an ein Endgerät, welches per CAN-Bus mit der CMI verbunden ist, übertragen werden. Dabei dient die CMI als transparenter Gateway zwischen IPS und Endgerät und sendet alle, per CoE empfangenen, Daten direkt an den CAN-Bus weiter. Daher muss der jeweilige CAN-Eingang nur auf dem Endgerät konfiguriert werden. Dazu wird auf dem Engerät als Knoten der Wert von *Eigene Knoten-Nr* der Modul-Instanz und als Ausgangsnummer die Nr *#* der entsprechenden Instanz-Variable angegeben.
  
  Details zur Konfiguration der CAN-Eingänge auf den Endgeräten, sind in den Handbüchern von Technische Alternative zu finden.

  Wenn auf der IPS-Variable ein Variablen-Profil des Modules (beginnt mit JoTTACoE) konfiguriert ist, wird die entsprechende Einheit ebenfalls per CoE übertragen und auf dem Endgerät ausgewertet, wenn dort die Messgrösse auf automatisch gestellt ist.

  Da die CMI nur als Gateway funktioniert, ist es nicht möglich, Werte von IPS direkt an einen CAN-Eingang der CMI zu senden. Dazu müsste man den Wert an ein Endgerät senden und von dort per Programmierung wieder zurück auf einen CAN-Ausgang, welcher wiederum von der CMI eingelesen wird...

  ### 3. Modul-Funktionen
  Die folgenden Funktionen stehen in IPS-Ereignissen/-Scripts zur Verfügung:
  - IPS_RequestAction(int $InstanzID, string $Ident, float/boolean $Value): steht nur für Instanz-Variabeln, welche als Ausgang definiert sind, zur Verfügung und sendet den entsprechenden Wert per CoE an die CMI.

  ### 5. Fehlersuche
  Die Debug-Funktion der Instanz liefert detaillierte Informationen über empfangenen / gesendeten Daten und die genutzen Datenblöcke. Auch verworfene Werte werden hier ausgegeben.

## 6. Anhang
  ### 1. CoE-Datenblöcke
  CAN over Ethernet (CoE) versendet immer mehrere Werte als Datenblöcke per UDP auf Port 5441. Bei analogen Daten werden jeweils 4 Werte, bei digitalen Daten 16 Werte gleichzeitig miteinander versendet (auch wenn diese nicht definiert oder geändert wurden).
  Jedes CoE-Paket besteht aus 14 Bytes. Die ersten zwei Bytes sind für den Header reserviert und beinhalten die Konten-Nr des Absenders sowie die Block-Nr der Daten. Die restlichen 12 Daten-Bytes enthalten die entsprechenden Werte und die Messgrösse als UnitID.

  **Analoge Daten**
  | **Byte:**  | 1         | 2        | 3+4    | 5+6    | 7+8    | 9+10   | 11       | 12       | 13       | 14       |
  | :--------- | :-------: | :------: | :----: | :----: | :----: | :----: | :------: | :------: | :------: | :------: |
  | **Daten:** | Knoten-Nr | Block-Nr | Wert 1 | Wert 2 | Wert 3 | Wert 4 | UnitID 1 | UnitID 2 | UnitID 3 | UnitID 4 |
  
  Die Werte werden immer dimensionslos als Signed Short (16-Bit Integer von -32768 bis +32767) BigEndian übertragen. Anschliessend bestimmt die UnitID (Messgrösse), um wieviele Stellen das Komma nach links geschoben wird um den effektiven Wert mit Nachkommastellen und die Einheit zu erhalten.

  **Digitale Daten**
  | **Byte:**  | 1         | 2        | 3+4        | 5-10             | 11              | 12-14            |
  | :--------- | :-------: | :------: | :--------: | :--------------: | :-------------: | :--------------: |
  | **Daten:** | Knoten-Nr | Block-Nr | Wert 1-16  | mit 0 aufgefüllt | Messgrösse ???  | mit 0 aufgefüllt |
  
  Im ersten 16-Bit langen Wert-Block entspricht jedes Bit dem Zustand eines digitalen Ausgangs. Die restlichen 6 Wert-Bytes werden mit 0 aufgefüllt. Im ersten Messgrössen-Byte wird zwar etwas übertragen, aber bisher konnte ich noch nicht herausfinden was genau.
  Vermutlich wird zwischen zwei digitalen Einheiten (0 = Ein/Aus oder 1 = Nein/Ja) unterschieden. 

  **Datenblöcke**

  Die einzelnen Netzwerkausgänge sind fixen Datenblöcken zugewiesen. Die Block-Nr teilt dem Empfänger mit, dass er jetzt die folgenden Werte übermittelt bekommt:
  | Block-Nr | Datentyp | Netzwerkausgänge |
  | :------: | :------- |:---------------- |
  | 0        | Digital  | 1-16             |
  | 1        | Analog   | 1-4              |
  | 2        | Analog   | 5-8              |
  | 3        | Analog   | 9-12             |
  | 4        | Analog   | 13-16            |
  | 5        | Analog   | 17-20            |
  | 6        | Analog   | 21-24            |
  | 7        | Analog   | 25-28            |
  | 8        | Analog   | 29-32            |
  | 9        | Digital  | 17-32            |

  Nicht definierte Werte eines Blockes werden immer als 0-Wert gesendet. Daher ist es wichtig, dass bei mehreren CMIs darauf geachtet wird, dass diese entweder eine andere Knoten-Nr oder einen Netzwerkausgang aus einem anderen Block für den CoE-Ausgang verwenden.
  Wird dies nicht beachtet, überscheiben die beiden CMIs die entsprechenden Werte jeweils gegenseitig.

  **Sendebedingungen**

  Da immer der ganze Block mit den aktuellsten Werten gesendet wird, kann es sein, dass neue Werte in IPS ankommen, obwohl die Sendebedingung für einen bestimmten Wert auf der CMI gar nicht erfüllt war.
  Ist die Sendebedingung für einen beliebigen Wert aus demselben Block erfüllt ist somit die Sendebedingung für alle Werte innerhalb desselben Blockes erfüllt.

  ### 2. Modul-Informationen
  | Modul    | Typ    | Hersteller             | Gerät      | Prefix   | GUID                                   |
  | :------- | :----- | :--------------------- | :----------| :------- | :------------------------------------- |
  | JoTTACoE | Device | Technische Alternative | CoE-Knoten | JoTTACoE | {61108236-EBFE-207F-2FEC-55EDB2B4FDFF} |

  ### 3. Changelog
  Version 0.2 (BETA):
  - Fix: ReceiveDataFilter korrigiert, so dass auch Daten von Nodes 11, 14-31 empfangen werden.
  - Modul-Informationen hinzugefügt
  
  Version 0.2 (BETA):
  - Dokumentation aktualisiert
  - Diverse kleine Fixes
  
  Version 0.1 (ALPHA):  
  - Initale Erstellung des Modules.
  - Feedbacks zu Fehlern aber auch funktionierende Geräte & Konfigurationen sind willkommen.

  ### 4. Spenden    
  Das Modul ist für die nicht kommzerielle Nutzung kostenlos. Spenden als Unterstützung für den Autor sind aber willkommen:  
  <p align="center"><a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=9M6W4KM34HWMA&source=url" target="_blank"><img src="https://www.paypalobjects.com/de_DE/CH/i/btn/btn_donateCC_LG.gif" border="0" /></a></p>

## 7. Support
Fragen, Anregungen, Kritik und Fehler zu diesem Modul können im entsprechenden [Thread des IPS-Forums](https://community.symcon.de/t/modul-coe-knoten-jottacoe-technische-alternative-via-can-over-ethernet-coe) deponiert werden.
Da das Modul in der Freizeit entwickelt wird, kann es jedoch eine Weile dauern, bis eine Antwort im Forum verfügbar oder ein entsprechendes Update vorhanden ist. Besten Dank für euer Verständnis :-)

## 8. Lizenz
IPS-Modul: <a href="https://creativecommons.org/licenses/by-nc-sa/4.0/" target="_blank">CC BY-NC-SA 4.0</a>
