# IPSymcon-EventActiveBridge

Ein PHP-Modul für **IP-Symcon 9.0**, das den Aktivierungszustand eines Symcon-Ereignisses ereignisgesteuert überwacht, optional über eine Boolean-Steuervariable schaltet und den bestätigten Istzustand über eine KNX-DPT-1-Instanz zurückmelden kann.

Das Modul wurde ursprünglich für Wochenplan-Ereignisse entwickelt. Es eignet sich grundsätzlich aber für Symcon-Ereignisse, deren `EventActive`-Zustand überwacht und optional von außen gesteuert werden soll.

## Was macht das Modul?

Eine Instanz von `EventActiveBridge` verbindet genau ein Symcon-Ereignis mit optionalen Ein- und Ausgängen.

Das Modul kann:

- den tatsächlichen `EventActive`-Zustand eines Symcon-Ereignisses überwachen,
- Änderungen ereignisgesteuert über `EM_CHANGEACTIVE` erkennen,
- den Zustand als Boolean-Variable **„Wochenplan aktiv“** bereitstellen,
- eine externe Boolean-Steuervariable überwachen,
- daraus das Symcon-Ereignis mit `IPS_SetEventActive()` aktivieren oder deaktivieren,
- den bestätigten Istzustand über eine KNX-DPT-1-Instanz mit `KNX_WriteDPT1()` senden,
- nach Konfigurationsänderungen bzw. Neustart eine Initialsynchronisierung durchführen,
- unnötige Rückkopplungen vermeiden,
- ohne zyklisches Polling arbeiten.

Wichtig: Die KNX-Statusrückmeldung erzeugt **keine zusätzliche Statusvariable** unterhalb des Moduls. Der tatsächliche Zustand liegt bereits in **„Wochenplan aktiv“** vor und wird direkt über die konfigurierte KNX-Instanz gesendet.

## Funktionsprinzip

### Steuerung von außen nach IP-Symcon

```text
externe Boolean-Steuervariable
        │
        │ VM_UPDATE
        ▼
EventActiveBridge
        │
        │ IPS_SetEventActive()
        ▼
Symcon-Ereignis
```

### Rückmeldung des tatsächlichen Zustands

```text
Symcon-Ereignis
        │
        │ EM_CHANGEACTIVE
        ▼
EventActiveBridge
        ├──────────────► Boolean-Variable "Wochenplan aktiv"
        │
        └──────────────► KNX_WriteDPT1()
                         │
                         ▼
                    KNX-Statusadresse
```

Für die Rückmeldung wird **nicht einfach der zuletzt empfangene Schaltbefehl gespiegelt**. Das Modul liest den tatsächlichen `EventActive`-Zustand des Ereignisses und verwendet diesen als führende Information.

Dadurch werden auch Änderungen erkannt, die direkt über die native IP-Symcon-Visualisierung ausgelöst werden.

## Voraussetzungen

- IP-Symcon **9.0 oder neuer**
- Zugriff auf die Modulverwaltung von IP-Symcon
- ein vorhandenes Symcon-Ereignis
- optional eine Boolean-Steuervariable
- optional eine KNX-DPT-1-Instanz für die Statusrückmeldung

Entwickelt und praktisch getestet wurde das Modul mit **IP-Symcon 9.0 auf einer SymBox**.

## Installation

1. In IP-Symcon die Kerninstanz **Module** öffnen.
2. Über **+** ein neues Repository hinzufügen.
3. Folgende Repository-URL eintragen:

   ```text
   https://github.com/Abracoor/IPSymcon-EventActiveBridge.git
   ```

4. Nach erfolgreichem Laden steht das Modul **EventActiveBridge** als neue Instanz zur Verfügung.

## Konfiguration

Die Instanz stellt drei Konfigurationsfelder bereit.

### Zu überwachendes Ereignis

Hier wird das Symcon-Ereignis ausgewählt, dessen `EventActive`-Zustand überwacht und gegebenenfalls gesteuert werden soll.

### Steuervariable (Boolean)

Optional.

Wird hier eine Boolean-Variable gewählt, reagiert das Modul auf deren Aktualisierung:

- `false` → Ereignis deaktivieren
- `true` → Ereignis aktivieren

Vor dem Schreiben wird geprüft, ob der gewünschte Zustand bereits dem tatsächlichen `EventActive`-Zustand entspricht. Ist das der Fall, erfolgt kein erneuter Schreibvorgang.

### KNX-Statusinstanz

Optional.

Hier kann eine KNX-DPT-1-Instanz ausgewählt werden. Bei jeder tatsächlichen Änderung des überwachten Ereignisses sendet das Modul dessen aktuellen Zustand mit:

```php
KNX_WriteDPT1($instanceID, $active);
```

Die konkrete Gruppenadresse wird ausschließlich durch die Konfiguration der ausgewählten KNX-Instanz bestimmt.

## Warum beim Eingang eine Variable und beim Ausgang eine Instanz?

Für eingehende Änderungen überwacht das Modul eine **Boolean-Variable**, weil Änderungen dort per `VM_UPDATE` erkannt werden können.

Für ausgehende KNX-Telegramme wird dagegen die **KNX-Instanz** benötigt, weil `KNX_WriteDPT1()` auf einer KNX-DPT-1-Instanz arbeitet.

Typischer Aufbau:

```text
KNX-Schaltadresse
      │
      ▼
KNX-DPT-1-Instanz
      │
      ▼
untergeordnete Boolean-Value-Variable
      │
      ▼
EventActiveBridge
      │
      ▼
Symcon-Ereignis
```

Rückweg:

```text
Symcon-Ereignis
      │
      ▼
EventActiveBridge
      │
      ▼
KNX_WriteDPT1(KNX-Instanz, Zustand)
      │
      ▼
KNX-Statusadresse
```

## Empfohlenes KNX-Schema

Für den ursprünglichen Anwendungsfall übernimmt IP-Symcon gegenüber KNX funktional die Rolle eines Aktors.

Dafür werden typischerweise zwei Gruppenadressen verwendet:

- **Schaltadresse:** KNX → IP-Symcon
- **Statusadresse:** IP-Symcon → KNX

Wenn die KNX-DPT-1-Instanz in IP-Symcon so konfiguriert ist, dass die **Statusadresse die Hauptadresse** der Instanz ist und die **Schaltadresse als zusätzliche Rückmelde-/Empfangsadresse** eingetragen ist, ergibt sich:

- Telegramme auf der Schaltadresse aktualisieren die untergeordnete `Value`-Variable.
- `EventActiveBridge` übernimmt diesen Wert als Sollzustand des Symcon-Ereignisses.
- Änderungen des tatsächlichen `EventActive`-Zustands werden über die Statusadresse zurückgesendet.

Dieses Schema erlaubt eine saubere Aktor-ähnliche KNX-Funktion mit getrenntem Schalt- und Statusobjekt.

## Native IP-Symcon-Visualisierung

Der native Ein-/Aus-Schalter eines Wochenplans kann unverändert weiterverwendet werden.

Wird der Wochenplan dort direkt aktiviert oder deaktiviert:

1. IP-Symcon ändert den tatsächlichen `EventActive`-Zustand.
2. `EventActiveBridge` erkennt die Änderung über `EM_CHANGEACTIVE`.
3. Die interne Boolean-Statusvariable wird aktualisiert.
4. Falls konfiguriert, wird der bestätigte Zustand auf KNX gesendet.

Damit bleiben Bedienung in IP-Symcon und externe KNX-Statusanzeigen synchron.

## Verhalten nach Neustart und Konfigurationsänderungen

Beim Anwenden der Konfiguration bzw. nach einem Neustart:

1. wird der aktuelle `EventActive`-Zustand über `IPS_GetEvent()` gelesen,
2. die Variable **„Wochenplan aktiv“** entsprechend gesetzt,
3. die Nachrichtenüberwachung neu registriert,
4. bei konfigurierter KNX-Statusinstanz der aktuelle Zustand einmal auf KNX gesendet.

Damit kann eine externe Anzeige nach einem Neustart wieder auf den tatsächlichen Zustand synchronisiert werden.

## Rückkopplungsschutz

Eine eigene KNX-Statussendung kann abhängig von der KNX-Konfiguration ebenfalls die lokale Value-Variable aktualisieren.

Das Modul vergleicht deshalb bei einem eingehenden Steuerwert zunächst den gewünschten Zustand mit dem aktuellen `EventActive`-Zustand.

Sind beide bereits identisch, wird das Ereignis nicht erneut gesetzt.

Dadurch werden unnötige Schleifen und doppelte Schreibvorgänge vermieden.

## Mehrere Ereignisse

Eine Modulinstanz verarbeitet genau **ein** Symcon-Ereignis.

Für mehrere Wochenpläne oder andere Ereignisse wird jeweils eine eigene `EventActiveBridge`-Instanz angelegt.

Der aktuelle Stand wurde mit mehreren unabhängigen Modulinstanzen praktisch getestet.

## Typische Anwendungsfälle

- Wochenplan über KNX aktivieren/deaktivieren
- tatsächlichen Wochenplanstatus an KNX zurückmelden
- native IP-Symcon-Wochenplan-Kachel und KNX synchron halten
- Aktivierungszustand eines Ereignisses als Boolean-Variable verfügbar machen
- Ereignissteuerung über eine andere Boolean-Variable
- ereignisgesteuerte Synchronisierung mit externen Systemen
- Vermeidung zyklischer Polling-Skripte

## Debugging

Die Instanz schreibt Informationen in das Symcon-Debug-Protokoll.

Beispiele für Ereignisänderungen:

```text
EventID 12345 changed: EventActive=false
```

```text
EventID 12345 changed: EventActive=true
```

Bei eingehenden Steuerwerten wird protokolliert, welcher Zustand für das konfigurierte Ereignis angefordert wurde.

Für KNX-Statussendungen wird protokolliert, welche KNX-Instanz mit welchem Boolean-Wert über `KNX_WriteDPT1()` beschrieben wurde.

## Technischer Hintergrund

Für Änderungen der Ereignisaktivierung nutzt das Modul:

```text
EM_CHANGEACTIVE = 10804
```

Für Änderungen der Steuervariable wird auf `VM_UPDATE` reagiert.

Nach einer Ereignisänderung wird der tatsächliche Zustand zusätzlich mit `IPS_GetEvent()` gelesen. Die Statusrückmeldung basiert dadurch auf dem realen `EventActive`-Zustand und nicht lediglich auf einem zuvor empfangenen Sollwert.

Die eigentliche Aktivierung bzw. Deaktivierung erfolgt mit:

```php
IPS_SetEventActive($eventID, $active);
```

Die KNX-Rückmeldung erfolgt mit:

```php
KNX_WriteDPT1($instanceID, $active);
```

## Datenschutz und Sicherheit

Das Repository enthält ausschließlich generischen Modulcode.

Konkrete:

- Symcon-Objekt-IDs
- KNX-Gruppenadressen
- Netzwerkadressen
- Zugangsdaten
- projektspezifische Bezeichnungen

werden nicht im Repository gespeichert.

Alle installationsspezifischen Zuordnungen werden ausschließlich lokal in der jeweiligen IP-Symcon-Instanz konfiguriert.

## Getesteter Stand

Der aktuelle Stand wurde mit IP-Symcon 9.0 praktisch getestet für:

- direkte Änderung des Ereignisses über die native Kachelvisualisierung,
- Erkennung von `EventActive=false`,
- Erkennung von `EventActive=true`,
- Aktualisierung der internen Boolean-Statusvariable,
- Steuerung des Ereignisses über eine externe Boolean-Variable,
- KNX-Schalttelegramm → Symcon-Ereignis,
- Symcon-Ereignis → KNX-Statusrückmeldung,
- KNX-DPT-1-Rückmeldung für `false` und `true`,
- bidirektionale Synchronisierung mit einem KNX-Bedienelement,
- mehrere unabhängige Modulinstanzen,
- Initialsynchronisierung,
- ereignisgesteuerten Betrieb ohne zyklische Abfrage.

## Lizenz

Aktuell ist noch keine Lizenzdatei hinterlegt. Vor einer breiteren Weiterverwendung oder Veröffentlichung sollte eine passende Open-Source-Lizenz ergänzt werden.

## Status

Frühe, funktionsfähige Version.

Der bidirektionale Kernmechanismus ist praktisch getestet. Weitere Verallgemeinerungen und Komfortfunktionen können in späteren Versionen ergänzt werden.
