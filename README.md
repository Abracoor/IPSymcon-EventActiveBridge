# IPSymcon-EventActiveBridge

Ein kleines PHP-Modul für **IP-Symcon 9.0**, das den Aktivierungszustand eines Symcon-Ereignisses ereignisgesteuert überwacht und als Boolean-Variable bereitstellt.

Das Modul wurde ursprünglich für den Anwendungsfall entwickelt, den Ein-/Aus-Zustand eines Wochenplan-Ereignisses zuverlässig für weitere Automationen verfügbar zu machen. Es ist jedoch nicht auf Wochenpläne beschränkt und kann grundsätzlich für Symcon-Ereignisse verwendet werden, deren Aktivierungszustand überwacht werden soll.

## Was macht das Modul?

Für jede Instanz von `EventActiveBridge` wird genau ein Symcon-Ereignis ausgewählt.

Das Modul:

- liest beim Start bzw. nach einer Konfigurationsänderung den aktuellen `EventActive`-Zustand des gewählten Ereignisses,
- legt die Boolean-Variable **„Wochenplan aktiv“** an,
- hält diese Variable synchron zum tatsächlichen Aktivierungszustand des Ereignisses,
- reagiert ereignisgesteuert auf Änderungen über die Symcon-Nachricht `EM_CHANGEACTIVE` (`10804`),
- benötigt daher **keine zyklische Abfrage**.

Die Statusvariable kann anschließend z. B. für KNX-Rückmeldungen, Visualisierungen, Skripte oder andere Automationen verwendet werden.

## Funktionsprinzip

```text
Symcon-Ereignis
      │
      │ EventActive geändert
      ▼
EM_CHANGEACTIVE (10804)
      │
      ▼
EventActiveBridge
      │
      ▼
Boolean-Variable "Wochenplan aktiv"
```

Wird das überwachte Ereignis beispielsweise direkt in der IP-Symcon-Visualisierung aktiviert oder deaktiviert, erkennt das Modul die Änderung und aktualisiert die Boolean-Variable unmittelbar.

## Voraussetzungen

- IP-Symcon **9.0 oder neuer**
- Zugriff auf die Modulverwaltung von IP-Symcon
- ein vorhandenes Symcon-Ereignis, das überwacht werden soll

Entwickelt und praktisch getestet wurde das Modul mit **IP-Symcon 9.0 auf einer SymBox**.

## Installation

1. In IP-Symcon die Kerninstanz **Module** öffnen.
2. Über **+** ein neues Repository hinzufügen.
3. Folgende Repository-URL eintragen:

   ```text
   https://github.com/Abracoor/IPSymcon-EventActiveBridge.git
   ```

4. Nach erfolgreichem Laden steht das Modul **EventActiveBridge** als neue Instanz zur Verfügung.

## Einrichtung

1. Eine neue Instanz vom Typ **EventActiveBridge** anlegen.
2. In der Instanzkonfiguration unter **„Zu überwachendes Ereignis“** das gewünschte Symcon-Ereignis auswählen.
3. Konfiguration übernehmen.

Unterhalb der Instanz wird automatisch die Boolean-Variable **„Wochenplan aktiv“** angelegt.

Der Name der Variable stammt aus dem ursprünglichen Wochenplan-Anwendungsfall. Technisch bildet sie den allgemeinen `EventActive`-Status des ausgewählten Symcon-Ereignisses ab:

- `false` / Aus = Ereignis deaktiviert
- `true` / An = Ereignis aktiviert

## Verhalten nach Neustart

Beim Anwenden der Konfiguration sowie nach einem Neustart von IP-Symcon wird der aktuelle Zustand des gewählten Ereignisses neu eingelesen.

Dadurch wird die Statusvariable nicht nur bei zukünftigen Änderungen aktualisiert, sondern erhält auch beim Start den tatsächlichen aktuellen Zustand.

## Mehrere Ereignisse überwachen

Eine Modulinstanz überwacht derzeit **genau ein Ereignis**.

Sollen mehrere Ereignisse überwacht werden, wird für jedes Ereignis eine eigene `EventActiveBridge`-Instanz angelegt.

## Typische Anwendungsfälle

- Aktivierungsstatus eines Wochenplans an KNX zurückmelden
- Status eines Symcon-Ereignisses in eigener Logik weiterverarbeiten
- ereignisgesteuerte Synchronisierung mit externen Systemen
- Statusanzeige außerhalb der nativen Ereignis-/Wochenplan-Kachel
- Vermeidung zyklischer Polling-Skripte

## Debugging

Die Instanz schreibt Informationen in das Symcon-Debug-Protokoll.

Bei einer Zustandsänderung erscheinen beispielsweise Meldungen wie:

```text
EventID 12345 changed: EventActive=false
```

bzw.

```text
EventID 12345 changed: EventActive=true
```

Zusätzlich werden die von Symcon gelieferten Message-Daten im Debug ausgegeben.

## Aktueller Funktionsumfang

Der derzeitige Stand des Moduls dient ausschließlich der **Überwachung und Spiegelung** des Aktivierungszustands.

Das Modul:

- überwacht `EventActive`,
- stellt den Zustand als Boolean-Variable bereit,
- synchronisiert den Zustand beim Start,
- arbeitet ereignisgesteuert.

Das Modul schaltet das überwachte Ereignis derzeit **nicht selbst**. Eine bidirektionale Steuerung bzw. zusätzliche Schnittstellen können in späteren Versionen ergänzt werden.

## Datenschutz und Sicherheit

Das Repository enthält nur generischen Modulcode.

Konkrete Symcon-Objekt-IDs, KNX-Gruppenadressen, Netzwerkadressen oder andere installationsspezifische Daten werden **nicht im Repository gespeichert**. Die ID des zu überwachenden Ereignisses wird ausschließlich in der jeweiligen lokalen Modulinstanz von IP-Symcon konfiguriert.

## Technischer Hintergrund

IP-Symcon stellt für Änderungen an der Aktivierung eines Ereignisses die interne Nachricht `EM_CHANGEACTIVE` bereit. Das Modul registriert sich für diese Nachricht am ausgewählten Ereignis und verarbeitet sie über den `MessageSink()` des PHP-Moduls.

Anschließend wird der tatsächliche Zustand noch einmal über `IPS_GetEvent()` gelesen. Damit basiert die ausgegebene Statusvariable auf dem tatsächlichen `EventActive`-Zustand und nicht lediglich auf dem zuletzt empfangenen Änderungswert.

## Lizenz

Aktuell ist noch keine Lizenzdatei hinterlegt. Vor einer Weiterverwendung oder Veröffentlichung in größerem Umfang sollte eine passende Open-Source-Lizenz ergänzt werden.

## Status

Frühe Version / Funktionsprototyp.

Der Kernmechanismus wurde erfolgreich mit IP-Symcon 9.0 getestet:

- Erkennung von Aktivieren → `EventActive=true`
- Erkennung von Deaktivieren → `EventActive=false`
- Aktualisierung der Boolean-Statusvariable
- initiale Synchronisierung nach Modulaktualisierung / Konfigurationsanwendung
- keine zyklische Statusabfrage erforderlich
