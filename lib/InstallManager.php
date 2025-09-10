<?php

namespace FriendsOfREDAXO\GitHubInstaller;

use rex_sql;
use rex_template;
use rex_module;
use rex_path;
use rex;

/**
 * Installation Manager für Module und Templates
 */
class InstallManager
{
    private GitHubApi $github;
    private RepositoryManager $repoManager;
    
    public function __construct()
    {
        $this->github = new GitHubApi();
        $this->repoManager = new RepositoryManager();
    }
    
    /**
     * Modul installieren
     */
    public function installModule(string $repoKey, string $moduleName, string $key = ''): bool
    {
        $repositories = $this->repoManager->getRepositories();
        
        if (!isset($repositories[$repoKey])) {
            throw new \Exception('Repository not found');
        }
        
        $repo = $repositories[$repoKey];
        
        // Modul-Dateien laden
        $inputCode = '';
        $outputCode = '';
        
        try {
            $inputCode = $this->github->getFileContent(
                $repo['owner'], 
                $repo['repo'], 
                "modules/{$moduleName}/input.php", 
                $repo['branch']
            );
        } catch (\Exception $e) {
            // input.php nicht vorhanden
        }
        
        try {
            $outputCode = $this->github->getFileContent(
                $repo['owner'], 
                $repo['repo'], 
                "modules/{$moduleName}/output.php", 
                $repo['branch']
            );
        } catch (\Exception $e) {
            // output.php nicht vorhanden
        }
        
        // Metadaten laden
        $modules = $this->repoManager->getModules($repoKey);
        $moduleData = [];
        foreach ($modules as $module) {
            if ($module['name'] === $moduleName) {
                $moduleData = $module;
                break;
            }
        }
        $moduleTitle = $moduleData['title'] ?? $moduleName;
        $moduleKey = $key ?: ($moduleData['key'] ?? '');
        
        // SQL-Objekt initialisieren
        $sql = rex_sql::factory();
        
        // Prüfen ob Modul bereits existiert (über Key oder Name)
        $existingModule = null;
        if ($moduleKey) {
            $sql->setQuery('SELECT * FROM ' . rex::getTable('module') . ' WHERE `key` = ?', [$moduleKey]);
            if ($sql->getRows() > 0) {
                $existingModule = $sql->getRow();
            }
        }
        
        if (!$existingModule) {
            $sql->setQuery('SELECT * FROM ' . rex::getTable('module') . ' WHERE name = ?', [$moduleTitle]);
            if ($sql->getRows() > 0) {
                $existingModule = $sql->getRow();
            }
        }
        
        if ($existingModule && isset($existingModule['id'])) {
            // Modul aktualisieren - verwende Key falls vorhanden, sonst ID
            $sql->setTable(rex::getTable('module'));
            if ($moduleKey && $this->hasKeyField('module')) {
                $sql->setWhere(['key' => $moduleKey]);
            } else {
                $sql->setWhere(['id' => $existingModule['id']]);
            }
            $sql->setValue('name', $moduleTitle);
            $sql->setValue('input', $inputCode);
            $sql->setValue('output', $outputCode);
            
            // Key nur setzen wenn das Feld existiert
            if ($moduleKey && $this->hasKeyField('module')) {
                $sql->setValue('key', $moduleKey);
            }
            
            $sql->addGlobalUpdateFields();
            
            try {
                $sql->update();
            } catch (\rex_sql_exception $e) {
                throw new \Exception('Fehler beim Aktualisieren des Moduls: ' . $e->getMessage());
            }
        } else {
            // Neues Modul erstellen
            $sql = rex_sql::factory(); // Neues SQL-Objekt für INSERT
            $sql->setTable(rex::getTable('module'));
            $sql->setValue('name', $moduleTitle);
            $sql->setValue('input', $inputCode);
            $sql->setValue('output', $outputCode);
            
            // Key nur setzen wenn das Feld existiert
            if ($moduleKey && $this->hasKeyField('module')) {
                $sql->setValue('key', $moduleKey);
            }
            
            $sql->addGlobalCreateFields();
            
            try {
                $sql->insert();
            } catch (\rex_sql_exception $e) {
                throw new \Exception('Fehler beim Erstellen des Moduls: ' . $e->getMessage());
            }
        }
        
        // Assets installieren falls vorhanden
        if ($moduleKey) {
            $this->installModuleAssets($repo, $moduleName, $moduleKey);
        }
        
        // Cache löschen
        rex_delete_cache();
        
        return true;
    }
    
    /**
     * Template installieren
     */
    public function installTemplate(string $repoKey, string $templateName, string $key = ''): bool
    {
        $repositories = $this->repoManager->getRepositories();
        
        if (!isset($repositories[$repoKey])) {
            throw new \Exception('Repository not found');
        }
        
        $repo = $repositories[$repoKey];
        
        // Template-Datei laden
        $templateCode = '';
        
        try {
            $templateCode = $this->github->getFileContent(
                $repo['owner'], 
                $repo['repo'], 
                "templates/{$templateName}/template.php", 
                $repo['branch']
            );
        } catch (\Exception $e) {
            throw new \Exception('Template file not found: template.php');
        }
        
        // Metadaten laden
        $templates = $this->repoManager->getTemplates($repoKey);
        $templateData = [];
        foreach ($templates as $template) {
            if ($template['name'] === $templateName) {
                $templateData = $template;
                break;
            }
        }
        $templateTitle = $templateData['title'] ?? $templateName;
        $templateKey = $key ?: ($templateData['key'] ?? '');
        
        // SQL-Objekt initialisieren
        $sql = rex_sql::factory();
        
        // Prüfen ob Template bereits existiert (über Key oder Name)
        $existingTemplate = null;
        if ($templateKey) {
            $sql->setQuery('SELECT * FROM ' . rex::getTable('template') . ' WHERE `key` = ?', [$templateKey]);
            if ($sql->getRows() > 0) {
                $existingTemplate = $sql->getRow();
            }
        }
        
        if (!$existingTemplate) {
            $sql->setQuery('SELECT * FROM ' . rex::getTable('template') . ' WHERE name = ?', [$templateTitle]);
            if ($sql->getRows() > 0) {
                $existingTemplate = $sql->getRow();
            }
        }
        
        if ($existingTemplate && isset($existingTemplate['id'])) {
            // Template aktualisieren - verwende Key falls vorhanden, sonst ID
            $sql->setTable(rex::getTable('template'));
            if ($templateKey && $this->hasKeyField('template')) {
                $sql->setWhere(['key' => $templateKey]);
            } else {
                $sql->setWhere(['id' => $existingTemplate['id']]);
            }
            $sql->setValue('name', $templateTitle);
            $sql->setValue('content', $templateCode);
            
            // Key nur setzen wenn das Feld existiert
            if ($templateKey && $this->hasKeyField('template')) {
                $sql->setValue('key', $templateKey);
            }
            
            $sql->addGlobalUpdateFields();
            
            try {
                $sql->update();
            } catch (\rex_sql_exception $e) {
                throw new \Exception('Fehler beim Aktualisieren des Templates: ' . $e->getMessage());
            }
        } else {
            // Neues Template erstellen
            $sql = rex_sql::factory(); // Neues SQL-Objekt für INSERT
            $sql->setTable(rex::getTable('template'));
            $sql->setValue('name', $templateTitle);
            $sql->setValue('content', $templateCode);
            
            // Key nur setzen wenn das Feld existiert
            if ($templateKey && $this->hasKeyField('template')) {
                $sql->setValue('key', $templateKey);
            }
            
            $sql->addGlobalCreateFields();
            
            try {
                $sql->insert();
            } catch (\rex_sql_exception $e) {
                throw new \Exception('Fehler beim Erstellen des Templates: ' . $e->getMessage());
            }
        }
        
        // Assets installieren falls vorhanden
        if ($templateKey) {
            $this->installTemplateAssets($repo, $templateName, $templateKey);
        }
        
        // Cache löschen
        rex_delete_cache();
        
        return true;
    }
    
    /**
     * Prüft ob eine Tabelle das 'key' Feld hat
     */
    private function hasKeyField(string $table): bool
    {
        static $cache = [];
        
        if (isset($cache[$table])) {
            return $cache[$table];
        }
        
        try {
            $sql = rex_sql::factory();
            $sql->setQuery("SHOW COLUMNS FROM " . rex::getTable($table) . " LIKE 'key'");
            $cache[$table] = $sql->getRows() > 0;
        } catch (\Exception $e) {
            $cache[$table] = false;
        }
        
        return $cache[$table];
    }
    
    /**
     * Assets für Modul installieren
     */
    private function installModuleAssets(array $repo, string $moduleName, string $moduleKey): bool
    {
        $assetsPath = "modules/{$moduleName}/assets";
        $targetPath = rex_path::assets("modules/{$moduleKey}/");
        
        return $this->installAssets($repo, $assetsPath, $targetPath);
    }
    
    /**
     * Assets für Template installieren
     */
    private function installTemplateAssets(array $repo, string $templateName, string $templateKey): bool
    {
        $assetsPath = "templates/{$templateName}/assets";
        $targetPath = rex_path::assets("templates/{$templateKey}/");
        
        return $this->installAssets($repo, $assetsPath, $targetPath);
    }
    
    /**
     * Assets von GitHub in lokales Verzeichnis kopieren
     */
    private function installAssets(array $repo, string $sourcePath, string $targetPath): bool
    {
        try {
            // Prüfen ob Assets-Verzeichnis existiert
            $contents = $this->github->getRepositoryContents($repo['owner'], $repo['repo'], $sourcePath, $repo['branch']);
            
            if (empty($contents)) {
                return true; // Keine Assets vorhanden, aber kein Fehler
            }
            
            // Zielverzeichnis erstellen
            if (!is_dir($targetPath)) {
                if (!mkdir($targetPath, 0755, true)) {
                    throw new \Exception("Cannot create assets directory: {$targetPath}");
                }
            }
            
            // Assets rekursiv kopieren
            $this->copyAssetsRecursive($repo, $sourcePath, $targetPath);
            
            return true;
            
        } catch (\Exception $e) {
            // Assets-Verzeichnis nicht vorhanden oder Fehler beim Kopieren
            // Das ist kein kritischer Fehler, Module/Templates können auch ohne Assets funktionieren
            return false;
        }
    }
    
    /**
     * Assets rekursiv kopieren
     */
    private function copyAssetsRecursive(array $repo, string $sourcePath, string $targetPath): void
    {
        $contents = $this->github->getRepositoryContents($repo['owner'], $repo['repo'], $sourcePath, $repo['branch']);
        
        foreach ($contents as $item) {
            $itemPath = $sourcePath . '/' . $item['name'];
            $localPath = $targetPath . $item['name'];
            
            if ($item['type'] === 'dir') {
                // Verzeichnis erstellen und rekursiv kopieren
                if (!is_dir($localPath)) {
                    mkdir($localPath, 0755, true);
                }
                $this->copyAssetsRecursive($repo, $itemPath, $localPath . '/');
                
            } else {
                // Datei kopieren
                try {
                    $content = $this->github->getFileContent(
                        $repo['owner'], 
                        $repo['repo'], 
                        $itemPath, 
                        $repo['branch']
                    );
                    
                    if (file_put_contents($localPath, $content) === false) {
                        throw new \Exception("Cannot write file: {$localPath}");
                    }
                    
                } catch (\Exception $e) {
                    // Einzelne Datei konnte nicht kopiert werden - Warnung ausgeben aber weitermachen
                    error_log("GitHub Installer: Could not copy asset {$itemPath}: " . $e->getMessage());
                }
            }
        }
    }
}
