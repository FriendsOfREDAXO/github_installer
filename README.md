# GitHub Installer

**Bidirektionale GitHub-Integration für REDAXO** - Installiere Module und Templates aus GitHub-Repositories und lade deine eigenen Module/Templates zu GitHub hoch.

## 🚀 Features

### 📥 Installation von GitHub
- Browse und installiere Module/Templates aus GitHub-Repositories
- **Asset-Unterstützung**: CSS/JS-Dateien werden automatisch kopiert nach `/assets/modules/{key}/` bzw. `/assets/templates/{key}/`
- File-basiertes Caching für bessere Performance
- Unterstützung für private Repositories mit GitHub-Tokens
- Multi-Language Support (Deutsch/Englisch)
- Sauberes Repository-Management

### 📤 Upload zu GitHub  
- **Bidirektionale Synchronisation**: Lade deine lokalen REDAXO Module/Templates zu GitHub hoch
- **Settings-Integration**: Einmalige Repository-Konfiguration (Owner, Repository, Branch, Author)
- **Intelligente Ordnernamenerkennung**: Verwendet Modul-Keys (z.B. "gblock") statt IDs
- **Vollständiger Upload**: input.php, output.php, config.yml, README.md werden automatisch generiert
- **Überschreiben**: Vorhandene Module/Templates werden aktualisiert

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
└── templates/
    └── template_key/            # z.B. "main-layout"
        ├── config.yml           # Template-Konfiguration
        ├── template.php         # Template-Inhalt
        ├── README.md            # Dokumentation (optional)  
        └── assets/              # CSS/JS-Dateien (optional)
            ├── template.css
            └── template.js
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

### Module/Templates installieren
1. Backend → Addons → GitHub Installer → **Installieren**
2. Repository hinzufügen (z.B. `username/repository`)
3. Verfügbare Module/Templates durchsuchen
4. **Installieren** klicken
5. Assets werden automatisch nach `/assets/modules/{key}/` kopiert

### Module/Templates hochladen
1. Backend → Addons → GitHub Installer → **Upload**
2. Modul oder Template aus der Liste auswählen
3. **Upload** klicken
4. Beschreibung und Version eingeben
5. Das System erstellt automatisch:
   - `/modules/{key}/config.yml`
   - `/modules/{key}/input.php`
   - `/modules/{key}/output.php`
   - `/modules/{key}/README.md`

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
└── templates/
    └── main-layout/
        ├── config.yml
        ├── template.php
        └── assets/
            ├── layout.css
            └── layout.js
```

## 🆕 Changelog

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
