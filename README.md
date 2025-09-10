# GitHub Installer

**Bidirektionale GitHub-Integration für REDAXO** - Installiere Module, Templates und Classes aus GitHub-Repositories und lade deine eigenen Inhalte zu GitHub hoch.

## 🚀 Features

### 📥 Installation von GitHub
- Browse und installiere Module/Templates/Classes aus GitHub-Repositories
- **Asset-Unterstützung**: CSS/JS-Dateien werden automatisch kopiert nach `/assets/modules/{key}/` bzw. `/assets/templates/{key}/`
- **Class-Support**: PHP-Classes werden nach `project/lib/` installiert mit Verzeichnis-Struktur
- File-basiertes Caching für bessere Performance
- Unterstützung für private Repositories mit GitHub-Tokens
- Multi-Language Support (Deutsch/Englisch)
- Sauberes Repository-Management

### 📤 Upload zu GitHub  
- **Bidirektionale Synchronisation**: Lade deine lokalen REDAXO Module/Templates/Classes zu GitHub hoch
- **Settings-Integration**: Einmalige Repository-Konfiguration (Owner, Repository, Branch, Author)
- **Intelligente Ordnernamenerkennung**: Verwendet Modul-Keys (z.B. "gblock") statt IDs
- **Vollständiger Upload**: input.php, output.php, config.yml, README.md werden automatisch generiert
- **Class-Upload**: PHP-Classes aus `project/lib/` mit Verzeichnis-Struktur
- **Überschreiben**: Vorhandene Module/Templates/Classes werden aktualisiert

## 📁 Repository-Struktur

Dein GitHub-Repository sollte folgende Struktur haben:

```
repository/
├── modules/
│   └── module_key/              # z.B. "gblock", "text-simple"
│       ├── config.yml           # Modul-Konfiguration
│       ├── input.php            # Eingabe-Template
│       ├── output.php           # Ausgabe-Template
│       ├── README.md            # Dokumentation (optional)
│       └── assets/              # CSS/JS-Dateien (optional)
│           ├── styles.css
│           └── script.js
├── templates/
│   └── template_key/            # z.B. "main-layout"
│       ├── config.yml           # Template-Konfiguration
│       ├── template.php         # Template-Inhalt
│       ├── README.md            # Dokumentation (optional)  
│       └── assets/              # CSS/JS-Dateien (optional)
│           ├── template.css
│           └── template.js
└── classes/
    ├── SimpleClass.php          # Einzelne Class-Datei
    ├── SimpleClass.md           # Dokumentation (optional)
    └── ComplexClass/            # Class mit Verzeichnis-Struktur
        ├── ComplexClass.php     # Haupt-Class-Datei
        ├── README.md            # Dokumentation (optional)
        └── config.yml           # Class-Konfiguration (optional)
```

## 📝 config.yml Format

### Module Konfiguration
```yaml
title: "01 - Gridblock (gruppierte Blöcke)"
description: "Flexibles Spaltenraster-System für REDAXO"
author: "Falko Müller"
version: "1.0.0"
redaxo_version: "5.13+"
```

### Template Konfiguration
```yaml
title: "Main Layout Template"
description: "Basis-Layout für die Website"
author: "Developer Name"
version: "1.2.0"
redaxo_version: "5.13+"
```

### Class Konfiguration
```yaml
title: "Demo Helper Class"
description: "Hilfsklasse für Demo-Funktionen"
author: "Developer Name"
version: "1.0.0"
redaxo_version: "5.13+"
namespace: "Demo"
```

## 🛠️ Installation & Konfiguration

### 1. Addon installieren
1. GitHub Installer in REDAXO installieren
2. Addon aktivieren

### 2. Upload-Einstellungen konfigurieren (optional)
Für das Hochladen zu GitHub:
1. Backend → Addons → GitHub Installer → **Einstellungen**
2. **Upload-Repository konfigurieren**:
   - **Owner**: Dein GitHub-Username (z.B. `skerbis`)
   - **Repository**: Repository-Name (z.B. `stuff`)
   - **Branch**: Ziel-Branch (z.B. `main`)
   - **Author**: Dein Name für die Metadaten

### 3. GitHub-Token für private Repositories (optional)
1. GitHub → Settings → Developer settings → Personal access tokens
2. Token mit Repository-Berechtigung erstellen
3. Token in den Addon-Einstellungen hinterlegen

## 🎯 Verwendung

### Module/Templates/Classes installieren
1. Backend → Addons → GitHub Installer → **Installieren** (Module/Templates/Classes)
2. Repository hinzufügen (z.B. `username/repository`)
3. Verfügbare Module/Templates/Classes durchsuchen
4. **Installieren** oder **Neu laden** klicken
5. Assets werden automatisch nach `/assets/modules/{key}/` kopiert
6. Classes werden nach `project/lib/` installiert (mit Verzeichnis-Struktur)

### Module/Templates/Classes hochladen
1. Backend → Addons → GitHub Installer → **Upload**
2. Modul, Template oder Class aus der Liste auswählen
3. **Upload** klicken
4. Beschreibung und Version eingeben
5. Das System erstellt automatisch:
   - **Module**: `/modules/{key}/config.yml`, `input.php`, `output.php`, `README.md`
   - **Templates**: `/templates/{key}/config.yml`, `template.php`, `README.md`
   - **Classes**: `/classes/{classname}.php` oder `/classes/{classname}/` mit Struktur

## 🔧 Asset-Management

Das Addon unterstützt automatisches Asset-Management:

- **Bei Installation**: CSS/JS-Dateien werden von `{repository}/modules/{key}/assets/` nach `/assets/modules/{key}/` kopiert
- **Bei Upload**: Lokale Assets werden automatisch mit hochgeladen
- **Unterstützte Dateien**: .css, .js, .scss, .less, .jpg, .png, .gif, .svg, etc.

## 🎨 Beispiel-Repository

Siehe: [https://github.com/skerbis/stuff](https://github.com/skerbis/stuff)

```
stuff/
├── modules/
│   ├── gblock/
│   │   ├── config.yml
│   │   ├── input.php
│   │   ├── output.php
│   │   └── assets/
│   │       └── gblock.css
│   └── text-simple/
│       ├── config.yml
│       ├── input.php
│       └── output.php
├── templates/
│   └── main-layout/
│       ├── config.yml
│       ├── template.php
│       └── assets/
│           ├── layout.css
│           └── layout.js
└── classes/
    ├── SimpleHelper.php
    └── DemoHelper/
        ├── DemoHelper.php
        ├── README.md
        └── config.yml
```

## 🆕 Changelog

### Version 1.3.0
- ✅ **Class-Support**: Vollständige Unterstützung für PHP-Classes
- ✅ **Verzeichnis-Struktur**: Classes werden mit korrekter Ordner-Struktur installiert
- ✅ **Architecture-Refactor**: Getrennte Install- und Update-Manager
- ✅ **UI-Verbesserungen**: Konsistente Benutzeroberfläche für alle Typen
- ✅ **Terminologie**: "Neu laden" statt "Aktualisieren" für bessere Klarheit

### Version 1.2.0
- ✅ **Bidirektionale Synchronisation**: Upload-Funktionalität hinzugefügt
- ✅ **Asset-Unterstützung**: Automatisches Kopieren von CSS/JS-Dateien
- ✅ **Settings-Integration**: Repository-Konfiguration im Backend
- ✅ **Intelligente Ordnernamen**: Verwendet Modul-Keys statt IDs
- ✅ **Vollständiger Upload**: Automatische Generierung von config.yml und README.md

### Version 1.1.0
- ✅ **Asset-Installation**: CSS/JS-Dateien werden automatisch kopiert
- ✅ **Verbesserte UI**: Bessere Darstellung von Modulen mit Assets
- ✅ **Cache-Optimierung**: Schnellere Repository-Browsing

### Version 1.0.0
- ✅ **Basis-Installation**: Module und Templates aus GitHub installieren
- ✅ **Repository-Management**: GitHub-Repositories verwalten
- ✅ **Multi-Language**: Deutsch/Englisch Support
