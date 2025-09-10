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
        
        // Debug-Info sammeln
        $debug = [];
        $debug['moduleKey'] = $moduleKey;
        $debug['moduleTitle'] = $moduleTitle;
        $debug['hasKeyField'] = $this->hasKeyField('module');
        
        // Prüfen ob Modul bereits existiert (über Key oder Name)
        $existingModule = null;
        $updateByKey = false;
        
        // 1. Prüfung über Key (falls Key vorhanden und Key-Feld existiert)
        if ($moduleKey && $this->hasKeyField('module')) {
            $checkSql = rex_sql::factory();
            $checkSql->setQuery('SELECT * FROM ' . rex::getTable('module') . ' WHERE `key` = ?', [$moduleKey]);
            $debug['keyQuery'] = 'SELECT * FROM ' . rex::getTable('module') . ' WHERE `key` = "' . $moduleKey . '"';
            $debug['keyRows'] = $checkSql->getRows();
            
            if ($checkSql->getRows() > 0) {
                $existingModule = $checkSql->getRow();
                $updateByKey = true;
                $debug['foundByKey'] = true;
            } else {
                $debug['foundByKey'] = false;
            }
        }
        
        // 2. Fallback: Prüfung über Name
        if (!$existingModule) {
            $checkSql = rex_sql::factory();
            $checkSql->setQuery('SELECT * FROM ' . rex::getTable('module') . ' WHERE name = ?', [$moduleTitle]);
            $debug['nameQuery'] = 'SELECT * FROM ' . rex::getTable('module') . ' WHERE name = "' . $moduleTitle . '"';
            $debug['nameRows'] = $checkSql->getRows();
            
            if ($checkSql->getRows() > 0) {
                $existingModule = $checkSql->getRow();
                $updateByKey = false;
                $debug['foundByName'] = true;
            } else {
                $debug['foundByName'] = false;
            }
        }
        
        $debug['existingModuleFound'] = $existingModule ? true : false;
        $debug['existingModuleId'] = $existingModule ? $existingModule['id'] ?? 'NO_ID' : 'NULL';
        
        // IMMER Debug-Info loggen (auch bei gefundenen Modulen)
        error_log('GitHub Installer Debug (' . $moduleTitle . '): ' . json_encode($debug, JSON_PRETTY_PRINT));
        
        // Auch alle existierenden Module loggen zur Analyse
        $allModulesSql = rex_sql::factory();
        $allModulesSql->setQuery('SELECT id, name, `key` FROM ' . rex::getTable('module') . ' ORDER BY id');
        $allModules = [];
        for ($i = 0; $i < $allModulesSql->getRows(); $i++) {
            $row = $allModulesSql->getRow($i);
            $allModules[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'key' => $row['key'] ?? 'NO_KEY'
            ];
        }
        error_log('GitHub Installer - All existing modules: ' . json_encode($allModules, JSON_PRETTY_PRINT));
        
        if ($existingModule && isset($existingModule['id'])) {
            // Modul aktualisieren
            $updateSql = rex_sql::factory();
            $updateSql->setTable(rex::getTable('module'));
            
            if ($updateByKey && $moduleKey) {
                $updateSql->setWhere(['key' => $moduleKey]);
            } else {
                $updateSql->setWhere(['id' => $existingModule['id']]);
            }
            
            $updateSql->setValue('name', $moduleTitle);
            $updateSql->setValue('input', $inputCode);
            $updateSql->setValue('output', $outputCode);
            
            // Key nur setzen wenn das Feld existiert
            if ($moduleKey && $this->hasKeyField('module')) {
                $updateSql->setValue('key', $moduleKey);
            }
            
            $updateSql->addGlobalUpdateFields();
            
            try {
                $updateSql->update();
                // Erfolgreich aktualisiert - Assets installieren und beenden
                if ($moduleKey) {
                    $this->installModuleAssets($repo, $moduleName, $moduleKey);
                }
                rex_delete_cache();
                return true;
            } catch (\rex_sql_exception $e) {
                throw new \Exception('Fehler beim Aktualisieren des Moduls: ' . $e->getMessage());
            }
        }
        
        // Wenn wir hier ankommen, Modul neu erstellen
        $insertSql = rex_sql::factory();
        $insertSql->setTable(rex::getTable('module'));
        $insertSql->setValue('name', $moduleTitle);
        $insertSql->setValue('input', $inputCode);
        $insertSql->setValue('output', $outputCode);
        
        // Key nur setzen wenn das Feld existiert
        if ($moduleKey && $this->hasKeyField('module')) {
            $insertSql->setValue('key', $moduleKey);
        }
        
        $insertSql->addGlobalCreateFields();
        
        try {
            $insertSql->insert();
        } catch (\rex_sql_exception $e) {
            // Debug-Info in Fehlermeldung einbauen
            $debugInfo = json_encode($debug, JSON_PRETTY_PRINT);
            throw new \Exception('Fehler beim Erstellen des Moduls: ' . $e->getMessage() . "\n\nDebug-Info:\n" . $debugInfo);
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
        $updateByKey = false;
        
        if ($templateKey && $this->hasKeyField('template')) {
            $checkSql = rex_sql::factory();
            $checkSql->setQuery('SELECT * FROM ' . rex::getTable('template') . ' WHERE `key` = ?', [$templateKey]);
            if ($checkSql->getRows() > 0) {
                $existingTemplate = $checkSql->getRow();
                $updateByKey = true;
            }
        }
        
        if (!$existingTemplate) {
            $checkSql = rex_sql::factory();
            $checkSql->setQuery('SELECT * FROM ' . rex::getTable('template') . ' WHERE name = ?', [$templateTitle]);
            if ($checkSql->getRows() > 0) {
                $existingTemplate = $checkSql->getRow();
                $updateByKey = false;
            }
        }
        
        if ($existingTemplate && isset($existingTemplate['id'])) {
            // Template aktualisieren
            $updateSql = rex_sql::factory();
            $updateSql->setTable(rex::getTable('template'));
            
            if ($updateByKey && $templateKey) {
                $updateSql->setWhere(['key' => $templateKey]);
            } else {
                $updateSql->setWhere(['id' => $existingTemplate['id']]);
            }
            
            $updateSql->setValue('name', $templateTitle);
            $updateSql->setValue('content', $templateCode);
            
            // Key nur setzen wenn das Feld existiert
            if ($templateKey && $this->hasKeyField('template')) {
                $updateSql->setValue('key', $templateKey);
            }
            
            $updateSql->addGlobalUpdateFields();
            
            try {
                $updateSql->update();
                // Erfolgreich aktualisiert - Assets installieren und beenden
                if ($templateKey) {
                    $this->installTemplateAssets($repo, $templateName, $templateKey);
                }
                rex_delete_cache();
                return true;
            } catch (\rex_sql_exception $e) {
                throw new \Exception('Fehler beim Aktualisieren des Templates: ' . $e->getMessage());
            }
        }
        
        // Wenn wir hier ankommen, Template neu erstellen
        $insertSql = rex_sql::factory();
        $insertSql->setTable(rex::getTable('template'));
        $insertSql->setValue('name', $templateTitle);
        $insertSql->setValue('content', $templateCode);
        
        // Key nur setzen wenn das Feld existiert
        if ($templateKey && $this->hasKeyField('template')) {
            $insertSql->setValue('key', $templateKey);
        }
        
        $insertSql->addGlobalCreateFields();
        
        try {
            $insertSql->insert();
        } catch (\rex_sql_exception $e) {
            throw new \Exception('Fehler beim Erstellen des Templates: ' . $e->getMessage());
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
