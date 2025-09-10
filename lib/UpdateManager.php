<?php

namespace FriendsOfREDAXO\GitHubInstaller;

/**
 * Update-Manager für existierende Module und Templates
 */
class UpdateManager
{
    private GitHubApi $github;
    private RepositoryManager $repoManager;

    public function __construct()
    {
        $this->github = new GitHubApi();
        $this->repoManager = new RepositoryManager();
    }

    /**
     * Prüft ob ein Modul bereits existiert und gibt dessen Status zurück
     */
    public function getModuleStatus(string $moduleKey, string $moduleName): array
    {
        $status = [
            'exists' => false,
            'update_method' => null, // 'key' oder 'name'
            'existing_data' => null
        ];

        // 1. Priorität: Prüfung über `key` Feld (wenn vorhanden)
        if ($moduleKey && $this->hasKeyField('module')) {
            $checkSql = \rex_sql::factory();
            $checkSql->setQuery('SELECT * FROM ' . \rex::getTable('module') . ' WHERE `key` = ?', [$moduleKey]);
            
            if ($checkSql->getRows() > 0) {
                $status['exists'] = true;
                $status['update_method'] = 'key';
                $status['existing_data'] = [
                    'id' => $checkSql->getValue('id'),
                    'name' => $checkSql->getValue('name'),
                    'key' => $checkSql->getValue('key'),
                    'input' => $checkSql->getValue('input'),
                    'output' => $checkSql->getValue('output')
                ];
                return $status;
            }
        }

        // 2. Fallback: Prüfung über Name
        if ($moduleName) {
            $checkSql = \rex_sql::factory();
            $checkSql->setQuery('SELECT * FROM ' . \rex::getTable('module') . ' WHERE name = ?', [$moduleName]);
            
            if ($checkSql->getRows() > 0) {
                $status['exists'] = true;
                $status['update_method'] = 'name';
                $status['existing_data'] = [
                    'id' => $checkSql->getValue('id'),
                    'name' => $checkSql->getValue('name'),
                    'key' => $checkSql->getValue('key'),
                    'input' => $checkSql->getValue('input'),
                    'output' => $checkSql->getValue('output')
                ];
            }
        }

        return $status;
    }

    /**
     * Aktualisiert ein existierendes Modul
     */
    public function updateModule(string $repoKey, string $moduleName, string $key = ''): bool
    {
        $repositories = $this->repoManager->getRepositories();
        
        if (!isset($repositories[$repoKey])) {
            throw new \Exception('Repository not found');
        }

        $repo = $repositories[$repoKey];
        
        // Module-Daten laden
        $modules = $this->repoManager->getModules($repoKey);
        $moduleData = [];
        foreach ($modules as $module) {
            if ($module['name'] === $moduleName) {
                $moduleData = $module;
                break;
            }
        }

        if (empty($moduleData)) {
            throw new \Exception('Module not found in repository');
        }

        $moduleTitle = $moduleData['title'] ?? $moduleName;
        $moduleKey = $key ?: ($moduleData['key'] ?? '');

        // Status prüfen
        $status = $this->getModuleStatus($moduleKey, $moduleTitle);
        
        if (!$status['exists']) {
            throw new \Exception('Modul existiert nicht - verwenden Sie den InstallManager für neue Installationen');
        }

        // Input und Output laden
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
            // Input ist optional
        }

        try {
            $outputCode = $this->github->getFileContent(
                $repo['owner'], 
                $repo['repo'], 
                "modules/{$moduleName}/output.php", 
                $repo['branch']
            );
        } catch (\Exception $e) {
            // Output ist optional
        }

        // Update durchführen
        $updateSql = \rex_sql::factory();
        $updateSql->setTable(\rex::getTable('module'));
        
        $updateSql->setValue('name', $moduleTitle);
        $updateSql->setValue('input', $inputCode);
        $updateSql->setValue('output', $outputCode);
        
        // Key nur setzen wenn es existiert und einen Wert hat und wir nicht über Key updaten
        if ($moduleKey && $this->hasKeyField('module') && $status['update_method'] !== 'key') {
            $updateSql->setValue('key', $moduleKey);
        }

        // Update basierend auf der besten verfügbaren Methode
        if ($status['update_method'] === 'key' && $moduleKey) {
            $updateSql->setWhere('`key` = :wherekey', ['wherekey' => $moduleKey]);
        } else {
            $updateSql->setWhere('id = :whereid', ['whereid' => $status['existing_data']['id']]);
        }

        try {
            $updateSql->update();
            
            // REDAXO Cache löschen
            \rex_delete_cache();
            
            error_log("GitHub Installer - Module '{$moduleTitle}' successfully reloaded via {$status['update_method']}");
            return true;
            
        } catch (\Exception $e) {
            error_log("GitHub Installer - Error updating module '{$moduleTitle}': " . $e->getMessage());
            throw new \Exception('Fehler beim Aktualisieren des Moduls: ' . $e->getMessage());
        }
    }

    /**
     * Prüft ob ein Template bereits existiert und gibt dessen Status zurück
     */
    public function getTemplateStatus(string $templateKey, string $templateName): array
    {
        $status = [
            'exists' => false,
            'update_method' => null,
            'existing_data' => null
        ];

        // 1. Priorität: Prüfung über `key` Feld (wenn vorhanden)
        if ($templateKey && $this->hasKeyField('template')) {
            $checkSql = \rex_sql::factory();
            $checkSql->setQuery('SELECT * FROM ' . \rex::getTable('template') . ' WHERE `key` = ?', [$templateKey]);
            
            if ($checkSql->getRows() > 0) {
                $status['exists'] = true;
                $status['update_method'] = 'key';
                $status['existing_data'] = [
                    'id' => $checkSql->getValue('id'),
                    'name' => $checkSql->getValue('name'),
                    'key' => $checkSql->getValue('key'),
                    'content' => $checkSql->getValue('content')
                ];
                return $status;
            }
        }

        // 2. Fallback: Prüfung über Name
        if ($templateName) {
            $checkSql = \rex_sql::factory();
            $checkSql->setQuery('SELECT * FROM ' . \rex::getTable('template') . ' WHERE name = ?', [$templateName]);
            
            if ($checkSql->getRows() > 0) {
                $status['exists'] = true;
                $status['update_method'] = 'name';
                $status['existing_data'] = [
                    'id' => $checkSql->getValue('id'),
                    'name' => $checkSql->getValue('name'),
                    'key' => $checkSql->getValue('key'),
                    'content' => $checkSql->getValue('content')
                ];
            }
        }

        return $status;
    }

    /**
     * Aktualisiert ein existierendes Template
     */
    public function updateTemplate(string $repoKey, string $templateName, string $key = ''): bool
    {
        $repositories = $this->repoManager->getRepositories();
        
        if (!isset($repositories[$repoKey])) {
            throw new \Exception('Repository not found');
        }

        $repo = $repositories[$repoKey];
        
        // Template-Daten laden
        $templates = $this->repoManager->getTemplates($repoKey);
        $templateData = [];
        foreach ($templates as $template) {
            if ($template['name'] === $templateName) {
                $templateData = $template;
                break;
            }
        }

        if (empty($templateData)) {
            throw new \Exception('Template not found in repository');
        }

        $templateTitle = $templateData['title'] ?? $templateName;
        $templateKey = $key ?: ($templateData['key'] ?? '');

        // Status prüfen
        $status = $this->getTemplateStatus($templateKey, $templateTitle);
        
        if (!$status['exists']) {
            throw new \Exception('Template existiert nicht - verwenden Sie den InstallManager für neue Installationen');
        }

        // Template-Code laden
        $templateCode = '';
        try {
            $templateCode = $this->github->getFileContent(
                $repo['owner'], 
                $repo['repo'], 
                "templates/{$templateName}/template.php", 
                $repo['branch']
            );
        } catch (\Exception $e) {
            throw new \Exception('Template file not found');
        }

        // Update durchführen
        $updateSql = \rex_sql::factory();
        $updateSql->setTable(\rex::getTable('template'));
        
        $updateSql->setValue('name', $templateTitle);
        $updateSql->setValue('content', $templateCode);
        
        // Key nur setzen wenn es existiert und einen Wert hat und wir nicht über Key updaten
        if ($templateKey && $this->hasKeyField('template') && $status['update_method'] !== 'key') {
            $updateSql->setValue('key', $templateKey);
        }

        // Update basierend auf der besten verfügbaren Methode
        if ($status['update_method'] === 'key' && $templateKey) {
            $updateSql->setWhere('`key` = :wherekey', ['wherekey' => $templateKey]);
        } else {
            $updateSql->setWhere('id = :whereid', ['whereid' => $status['existing_data']['id']]);
        }

        try {
            $updateSql->update();
            
            // REDAXO Cache löschen
            \rex_delete_cache();
            
            error_log("GitHub Installer - Template '{$templateTitle}' successfully reloaded via {$status['update_method']}");
            return true;
            
        } catch (\Exception $e) {
            error_log("GitHub Installer - Error updating template '{$templateTitle}': " . $e->getMessage());
            throw new \Exception('Fehler beim Aktualisieren des Templates: ' . $e->getMessage());
        }
    }

    /**
     * Aktualisiert eine Klasse aus einem Repository
     */
    public function updateClass(string $repoKey, string $className): bool
    {
        $repositoryManager = new RepositoryManager();
        $classes = $repositoryManager->getClasses($repoKey);
        
        if (!isset($classes[$className])) {
            throw new \Exception("Klasse '{$className}' nicht gefunden");
        }
        
        $classData = $classes[$className];
        $targetDirectory = $classData['target_directory'] ?? 'lib';
        $filename = $classData['filename'] ?? $className . '.php';
        
        // Ziel-Pfad bestimmen mit Verzeichnis-Struktur
        if ($targetDirectory === 'lib') {
            $basePath = \rex_path::addon('project') . 'lib/';
        } else {
            $basePath = \rex_path::addon('project') . $targetDirectory . '/';
        }
        
        // Wenn die Klasse aus einem Verzeichnis stammt, Verzeichnis-Struktur beibehalten
        if (str_contains($classData['path'], '/')) {
            // z.B. "classes/DemoHelper" -> "DemoHelper/"
            $classDirName = basename($classData['path']);
            $targetPath = $basePath . $classDirName . '/';
        } else {
            // Einzelne Datei direkt in lib/
            $targetPath = $basePath;
        }
        
        $targetFile = $targetPath . $filename;
        
        // Prüfen ob Datei existiert (zum Neu-Laden muss sie vorhanden sein)
        if (!file_exists($targetFile)) {
            throw new \Exception("Klasse '{$className}' ist nicht installiert - verwenden Sie den InstallManager für neue Installationen");
        }
        
        // Backup der alten Datei erstellen
        $backupFile = $targetFile . '.backup.' . date('Y-m-d_H-i-s');
        if (!copy($targetFile, $backupFile)) {
            throw new \Exception("Konnte Backup nicht erstellen");
        }
        
        // Neue Datei schreiben
        if (!\rex_file::put($targetFile, $classData['content'])) {
            // Backup wiederherstellen bei Fehler
            copy($backupFile, $targetFile);
            unlink($backupFile);
            throw new \Exception("Konnte Klasse-Datei nicht neu laden: {$targetFile}");
        }
        
        // Backup löschen bei erfolgreichem Neu-Laden
        unlink($backupFile);
        
        error_log("GitHub Installer - Class '{$className}' successfully reloaded at {$targetFile}");
        return true;
    }

    /**
     * Prüft ob eine Tabelle ein `key` Feld hat
     */
    private function hasKeyField(string $table = 'module'): bool
    {
        $sql = \rex_sql::factory();
        $sql->setQuery('SHOW COLUMNS FROM ' . \rex::getTable($table) . ' LIKE "key"');
        return $sql->getRows() > 0;
    }
}
