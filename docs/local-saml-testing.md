# Lokale SAML-Teststrategie

Für Integrationstests sollte der lokale Aufbau aus zwei getrennten
SimpleSAMLphp-Instanzen bestehen:

- ein Identity Provider (IdP), der Testidentitäten und Attribute ausliefert;
- ein Service Provider (SP), den RRZE SSO über seine Autoload-Datei verwendet.

Auch wenn beide Instanzen auf demselben Rechner laufen, sollen sie eigene
Konfigurationen, Metadaten, Sitzungen und Cache-Verzeichnisse besitzen. Das
verhindert, dass der Test nur durch gemeinsam genutzten Zustand funktioniert.

## Empfohlene Verzeichnisstruktur

Programmversion und lokale Konfiguration sollten getrennt werden. Stabile
Symlinks vermeiden außerdem, dass bei jedem Patch-Upgrade MAMP und WordPress neu
konfiguriert werden müssen.

```text
simplesaml/
├── idp-current -> simplesamlphp-X.Y.Z
├── sp-current -> simplesamlphp-sp-X.Y.Z
├── environments/
│   ├── local-idp/{config,metadata,cert}
│   └── local-sp/{config,metadata,cert}
├── simplesamlphp-X.Y.Z/
└── simplesamlphp-sp-X.Y.Z/
```

Private Schlüssel, Passwörter und die lokale `environments`-Konfiguration dürfen
nicht in dieses Repository übernommen werden.

## Testprofile

Ein einzelner vollständiger Benutzer prüft nur den Happy Path. Der lokale IdP
sollte zusätzlich Profile für folgende Fälle bereitstellen:

| Profil | Prüft |
| --- | --- |
| vollständiger Student/Mitarbeiter | reguläre Anmeldung und Attributübernahme |
| minimale Attribute | sinnvolle Defaults für fehlende optionale Werte |
| fehlende `uid` | definierte Login-Namen-Fallbacks |
| fehlende E-Mail-Adresse | verständliche und sichere Ablehnung |
| mehrwertige Attribute | korrekte Behandlung von Arrays |
| Unicode | UTF-8 vom IdP bis zur WordPress-Oberfläche |
| ungültige `uid` | Validierung statt stiller Umformung |
| HTML-artige Attributwerte | kontextgerechtes Escaping |
| zwei Subjekte mit gleicher `uid` | Schutz vor Kontenkollisionen |
| stabile ID mit geänderter `uid` | Verhalten bei Account-Umbenennung |

Kollisions- und Provisionierungstests gehören in eine wegwerfbare Datenbank oder
in einen zuvor gesicherten Snapshot.

## Teststufen

1. PHP-Syntax der IdP-/SP-Konfiguration prüfen.
2. SimpleSAMLphp-Konfiguration laden und beide Auth-Sources auflösen.
3. Metadaten-Endpunkte öffnen und Entity IDs sowie Zertifikate prüfen.
4. Den eingebauten SP-Authentifizierungstest mit einem normalen Profil ausführen.
5. RRZE SSO zunächst mit erreichbarem WordPress-Standardlogin testen.
6. Erzwungenes SSO, Rückleitung, Logout und erneute Anmeldung prüfen.
7. Danach jedes Diagnoseprofil einzeln testen und Ergebnis protokollieren.

Browser-Tests sollten zusätzlich in einem privaten Fenster erfolgen. Zwischen
zwei Identitäten sind sowohl IdP- als auch SP-Sitzung zu beenden; andernfalls
kann eine alte SSO-Sitzung das Ergebnis verfälschen.

Abgelaufene Assertions, falsche Audience, ungültige Signaturen und Replay-Fälle
lassen sich mit einem normal arbeitenden IdP nur schwer erzeugen. Diese Fälle
sollten ergänzend durch automatisierte Plugin-Tests abgedeckt werden.

## Patch-Upgrades und Rollback

Eine neue SimpleSAMLphp-Version wird parallel installiert, gegen die bestehende
Umgebungskonfiguration geprüft und erst danach über `idp-current` beziehungsweise
`sp-current` aktiviert. Vorher sind Download-Prüfsumme und Changelog zu prüfen.
Die vorige Version bleibt bestehen, bis SP-Selbsttest und kompletter
WordPress-Browserfluss erfolgreich sind.

Während Infrastrukturänderungen sollte erzwungenes SSO nur aktiv bleiben, wenn
ein getesteter normaler Login- oder Recovery-Weg vorhanden ist. Ein Rollback muss
durch das Zurücksetzen der beiden stabilen Symlinks möglich sein.
