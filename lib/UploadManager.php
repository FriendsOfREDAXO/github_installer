<?php

namespace FriendsOfREDAXO\GitHubInstaller;

/**
 * Vereinfachter UploadManager für das Hochladen von Modulen und Templates zu GitHub
 */
class UploadManager
{
    private GitHubApi $githubApi;
    
    public function __construct(GitHubApi $githubApi)
    {
        $this->githubApi = $githubApi;
    }
    
    /**
     * Laden des Moduls in GitHub-Repository hoch
     */
    public function uploadModuleToGitHub(string $owner, string $repo, string $branch, string $author, int $moduleId): array
    {
        // Modul-Daten laden
        $sql = \rex_sql::factory();
        $sql->setQuery('SELECT * FROM rex_module WHERE id = ?', [$moduleId]);
        
        if ($sql->getRows() === 0) {
            return ['success' => false, 'message' => 'Modul nicht gefunden'];
        }
        
        $moduleData = $sql->getRow();
        $moduleKey = $moduleData['key'] ?: 'module_' . $moduleId;
        
        try {
            // Ordnerstruktur sicherstellen (falls Repository leer ist)
            $this->ensureRepositoryStructure($owner, $repo, $branch);
            
            // Hauptverzeichnis erstellen
            $this->githubApi->createOrUpdateFile(
                $owner,
                $repo,
                "modules/{$moduleKey}/README.md",
                "# Modul: {$moduleData['name']}\n\n{$moduleData['name']}",
                "Modul {$moduleKey} hinzugefügt",
                $branch
            );
            
            // Input-Template
            if (!empty($moduleData['input'])) {
                $this->githubApi->createOrUpdateFile(
                    $owner,
                    $repo,
                    "modules/{$moduleKey}/input.php",
                    $moduleData['input'],
                    "Modul {$moduleKey}: input.php aktualisiert",
                    $branch
                );
            }
            
            // Output-Template
            if (!empty($moduleData['output'])) {
                $this->githubApi->createOrUpdateFile(
                    $owner,
                    $repo,
                    "modules/{$moduleKey}/output.php",
                    $moduleData['output'],
                    "Modul {$moduleKey}: output.php aktualisiert",
                    $branch
                );
            }
            
            // Config-Datei
            $config = $this->generateModuleConfig($moduleData, $author);
            $this->githubApi->createOrUpdateFile(
                $owner,
                $repo,
                "modules/{$moduleKey}/config.yml",
                $config,
                "Modul {$moduleKey}: config.yml aktualisiert",
                $branch
            );
            
            // Assets hochladen
            $this->uploadAssets($owner, $repo, $branch, 'modules', $moduleKey);
            
            return ['success' => true, 'message' => "Modul '{$moduleKey}' erfolgreich hochgeladen"];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Fehler beim Upload: ' . $e->getMessage()];
        }
    }
    
    /**
     * Template in GitHub-Repository hochladen
     */
    public function uploadTemplateToGitHub(string $owner, string $repo, string $branch, string $author, int $templateId): array
    {
        // Template-Daten laden
        $sql = \rex_sql::factory();
        $sql->setQuery('SELECT * FROM rex_template WHERE id = ?', [$templateId]);
        
        if ($sql->getRows() === 0) {
            return ['success' => false, 'message' => 'Template nicht gefunden'];
        }
        
        $templateData = $sql->getRow();
        $templateKey = $templateData['key'] ?: 'template_' . $templateId;
        
        try {
            // Ordnerstruktur sicherstellen (falls Repository leer ist)
            $this->ensureRepositoryStructure($owner, $repo, $branch);
            
            // Hauptverzeichnis erstellen
            $this->githubApi->createOrUpdateFile(
                $owner,
                $repo,
                "templates/{$templateKey}/README.md",
                "# Template: {$templateData['name']}\n\n{$templateData['name']}",
                "Template {$templateKey} hinzugefügt",
                $branch
            );
            
            // Template-Datei
            if (!empty($templateData['content'])) {
                $this->githubApi->createOrUpdateFile(
                    $owner,
                    $repo,
                    "templates/{$templateKey}/template.php",
                    $templateData['content'],
                    "Template {$templateKey}: template.php aktualisiert",
                    $branch
                );
            }
            
            // Config-Datei
            $config = $this->generateTemplateConfig($templateData, $author);
            $this->githubApi->createOrUpdateFile(
                $owner,
                $repo,
                "templates/{$templateKey}/config.yml",
                $config,
                "Template {$templateKey}: config.yml aktualisiert",
                $branch
            );
            
            // Assets hochladen
            $this->uploadAssets($owner, $repo, $branch, 'templates', $templateKey);
            
            return ['success' => true, 'message' => "Template '{$templateKey}' erfolgreich hochgeladen"];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Fehler beim Upload: ' . $e->getMessage()];
        }
    }
    
    /**
     * Assets hochladen (CSS/JS-Dateien)
     */
    private function uploadAssets(string $owner, string $repo, string $branch, string $type, string $key): void
    {
        $assetPath = \rex_path::assets("addons/github_installer/{$type}/{$key}/");
        
        if (!is_dir($assetPath)) {
            return; // Keine Assets vorhanden
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($assetPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($assetPath, '', $file->getPathname());
                $githubPath = "{$type}/{$key}/assets/" . str_replace('\\', '/', $relativePath);
                
                $content = file_get_contents($file->getPathname());
                $this->githubApi->createOrUpdateFile(
                    $owner,
                    $repo,
                    $githubPath,
                    $content,
                    "Assets für {$type}/{$key} aktualisiert",
                    $branch
                );
            }
        }
    }
    
    /**
     * Repository-Struktur sicherstellen
     * Erstellt README-Dateien in den Hauptordnern, falls diese noch nicht existieren
     */
    private function ensureRepositoryStructure(string $owner, string $repo, string $branch): void
    {
        $folders = [
            'modules' => "# Module\n\nDieser Ordner enthält REDAXO-Module.\n\nJedes Modul hat einen eigenen Unterordner mit:\n- `input.php` - Eingabe-Template\n- `output.php` - Ausgabe-Template\n- `config.yml` - Modul-Konfiguration\n",
            'templates' => "# Templates\n\nDieser Ordner enthält REDAXO-Templates.\n\nJedes Template hat einen eigenen Unterordner mit:\n- `template.php` - Template-Code\n- `config.yml` - Template-Konfiguration\n",
            'classes' => "# Classes\n\nDieser Ordner enthält PHP-Klassen.\n\nKlassen werden automatisch von REDAXO geladen.\n",
        ];
        
        foreach ($folders as $folder => $readmeContent) {
            try {
                // Prüfen ob Ordner bereits existiert (indem wir versuchen, die README zu lesen)
                if (!$this->githubApi->fileExists($owner, $repo, "{$folder}/README.md", $branch)) {
                    // README im Hauptordner erstellen
                    $this->githubApi->createOrUpdateFile(
                        $owner,
                        $repo,
                        "{$folder}/README.md",
                        $readmeContent,
                        "Repository-Struktur: {$folder} Ordner erstellt",
                        $branch
                    );
                }
            } catch (\Exception $e) {
                // Fehler ignorieren - wenn es schon existiert ist das OK
            }
        }
    }
    
    /**
     * Modul-Config generieren
     */
    private function generateModuleConfig(array $moduleData, string $author): string
    {
        $date = \rex_formatter::intlDateTime(time());
        
        return "name: '" . addslashes($moduleData['name']) . "'
description: 'Modul exportiert aus REDAXO'
version: '1.0.0'
author: '{$author}'
created: '{$date}'
type: module
key: '" . ($moduleData['key'] ?: 'module_' . $moduleData['id']) . "'
";
    }
    
    /**
     * Template-Config generieren
     */
    private function generateTemplateConfig(array $templateData, string $author): string
    {
        $date = \rex_formatter::intlDateTime(time());
        
        return "name: '" . addslashes($templateData['name']) . "'
description: 'Template exportiert aus REDAXO'
version: '1.0.0'
author: '{$author}'
created: '{$date}'
type: template
key: '" . ($templateData['key'] ?: 'template_' . $templateData['id']) . "'
";
    }
}
