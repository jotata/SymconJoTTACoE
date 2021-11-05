[![IP-Symcon is awesome!](https://img.shields.io/badge/IP--Symcon-6.0-blue.svg)](https://www.symcon.de)
[![Check Style](https://github.com/jotata/SymconJoTKPP/workflows/Check%20Style/badge.svg)](https://github.com/jotata/SymconJoTKPP/actions?query=workflow%3A%22Check+Style%22)
[![Run Tests](https://github.com/jotata/SymconJoTKPP/workflows/Run%20Tests/badge.svg)](https://github.com/jotata/SymconJoTKPP/actions?query=workflow%3A%22Run+Tests%22)
[![Tested with](https://img.shields.io/badge/tested-UVR16x2-blue)](https://www.ta.co.at/x2-frei-programmierbare-regler/uvr16x2/)
[![Release Version](https://img.shields.io/github/v/release/jotata/SymconJoTTACoE)](https://github.com/jotata/SymconJoTTACoE/releases)

# SymconJoTTACoE
Erweiterung zum Lesen/Schreiben der Werte eines Gerätes von Technische Alternative via CoE/CMI in IP-Symcon.

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
    1. [Modul-Informationen](#1-modul-informationen)
    2. [Changelog](#2-changelog)
    3. [Spenden](#3-spenden)
7. [Support](#7-support)
8. [Lizenz](#8-lizenz)

## 1. Funktionsumfang


## 2. Voraussetzungen
 - IPS 6.0 oder höher  
 - CMI von Technische Alternative mit Konigurierten CoE-Paramatern

## 3. Unterstütze Geräte
Das Modul wird grundsätzlich für eine UVR16x2 programmiert / getestet. Da die CMI von Technische Alternative aber alle Werte via CAN-Bus zur Verfügung stellen kann, ist auch die Steuerung anderer Geräte möglich.

Hersteller: Technische Alternative

## 4. Modul-Installation / Update
Die Installation erfolgt über den IPS Module-Store. In der Suche einfach "JoTTACoE" eingeben und die Installation starten.
Update erfolgt ebenfalls über den Module-Store. Einfach beim installierten Modul auf "Aktualisieren" klicken.

**Das Modul wird für den privaten Gebrauch kostenlos zur Verfügung gestellt.**

**Bei kommerzieller Nutzung (d.h. wenn Sie für die Einrichtung/Installation und/oder den Betrieb von IPS Geld erhalten) wenden Sie sich bitte an den Autor.**

**ACHTUNG: Der Autor übernimmt keine Haftung für irgendwelche Folgen welche durch die Nutzung dieses Modules entstehen!**

## 5. Einrichten der Instanz in IP-Symcon
  ### 1. Erstellen einer neuen Instanz
   1. Neue Instanz hinzufügen
   2. Im Schnellfilter "Technische Alternative" eingeben
   3. Das Gerät "Technische Alternative CMI" auswählen
   4. Name & Ort anpassen (optional)
   5. Falls noch keine UDP-Socket Instanz vorhanden ist, wird eine solche erstellt. Diese entsprechend konfigurieren:
 
  ### 2. Konfiguration der Instanz
   - 

  ### 3. Modul-Funktionen
  Die folgenden Funktionen stehen in IPS-Ereignissen/-Scripts zur Verfügung:
  - 

  ### 5. Fehlersuche
  Die Debug-Funktion der Instanz liefert detaillierte Informationen über die Konvertierung der Werte und von der CMI zurückgegebenen Fehler.

## 6. Anhang
###  1. Modul-Informationen
| Modul    | Typ    | Hersteller             | Gerät       | Prefix   | GUID                                   |
| :------- | :----- | :--------------------- | :-----------| :------- | :------------------------------------- |
| JoTTACoE | Device | Technische Alternative | CMI via CoE | JoTTACoE | {61108236-EBFE-207F-2FEC-55EDB2B4FDFF} |

### 2. Changelog
Version 0.1 (ALPHA):  
- Initale Erstellung des Modules.
- Feedbacks zu Fehlern aber auch funktionierende Geräte & Konfigurationen sind willkommen.

### 3. Spenden    
Das Modul ist für die nicht kommzerielle Nutzung kostenlos. Spenden als Unterstützung für den Autor sind aber willkommen:  
<p align="center"><a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=9M6W4KM34HWMA&source=url" target="_blank"><img src="https://www.paypalobjects.com/de_DE/CH/i/btn/btn_donateCC_LG.gif" border="0" /></a></p>

## 7. Support
Fragen, Anregungen, Kritik und Fehler zu diesem Modul können im entsprechenden [Thread des IPS-Forums]() (noch nicht verfügbar) deponiert werden.
Da das Modul in der Freizeit entwickelt wird, kann es jedoch eine Weile dauern, bis eine Antwort im Forum verfügbar oder ein entsprechendes Update vorhanden ist. Besten Dank für euer Verständnis :-)

## 8. Lizenz
IPS-Modul: <a href="https://creativecommons.org/licenses/by-nc-sa/4.0/" target="_blank">CC BY-NC-SA 4.0</a>
