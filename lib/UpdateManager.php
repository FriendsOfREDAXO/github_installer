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
        return $this->getUpdateStatus('module', $moduleKey, $moduleName);
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
            throw new \Exception('Modul existiert nicht - bitte über "Installieren" neu anlegen statt zu aktualisieren');
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
        if ($moduleKey && TableHelper::hasKeyField('module') && $status['update_method'] !== 'key') {
            $updateSql->setValue('key', $moduleKey);
        }

        // Update basierend auf der besten verfügbaren Methode
        if ($status['update_method'] === 'key' && $moduleKey) {
            $updateSql->setWhere('`key` = :wherekey', ['wherekey' => $moduleKey]);
        } else {
            $updateSql->setWhere('id = :whereid', ['whereid' => $status['existing_data']['id']]);
        }
        
        // WICHTIG: Update-Datum setzen für GitHub-Sync
        $updateSql->addGlobalUpdateFields();

        try {
            $updateSql->update();
            
            // GitHub-Cache aktualisieren
            if ($moduleKey) {
                $githubDate = $this->github->getLastCommitDate(
                    $repo['owner'],
                    $repo['repo'],
                    "modules/{$moduleName}",
                    $repo['branch']
                );
                
                GitHubItemCache::save(
                    'module',
                    $moduleKey,
                    $moduleTitle,
                    $repo['owner'],
                    $repo['repo'],
                    $repo['branch'],
                    "modules/{$moduleName}",
                    $githubDate
                );
            }
            
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
        return $this->getUpdateStatus('template', $templateKey, $templateName);
    }

    /**
     * Prüft ob ein Modul/Template bereits existiert und liefert dessen Status +
     * Update-Methode (key/name). Gemeinsame Implementierung für Module und
     * Templates - siehe TableHelper.
     */
    private function getUpdateStatus(string $table, string $key, string $name): array
    {
        $found = TableHelper::findExisting($table, $key, $name);

        if (!$found) {
            return ['exists' => false, 'update_method' => null, 'existing_data' => null];
        }

        return [
            'exists' => true,
            'update_method' => $found['matched_by'],
            'existing_data' => [
                'id' => $found['row']['id'],
                'name' => $found['row']['name'],
                'key' => $found['row']['key'] ?? '',
            ],
        ];
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
            throw new \Exception('Template existiert nicht - bitte über "Installieren" neu anlegen statt zu aktualisieren');
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
        if ($templateKey && TableHelper::hasKeyField('template') && $status['update_method'] !== 'key') {
            $updateSql->setValue('key', $templateKey);
        }

        // Update basierend auf der besten verfügbaren Methode
        if ($status['update_method'] === 'key' && $templateKey) {
            $updateSql->setWhere('`key` = :wherekey', ['wherekey' => $templateKey]);
        } else {
            $updateSql->setWhere('id = :whereid', ['whereid' => $status['existing_data']['id']]);
        }
        
        // WICHTIG: Update-Datum setzen für GitHub-Sync
        $updateSql->addGlobalUpdateFields();

        try {
            $updateSql->update();
            
            // GitHub-Cache aktualisieren
            if ($templateKey) {
                $githubDate = $this->github->getLastCommitDate(
                    $repo['owner'],
                    $repo['repo'],
                    "templates/{$templateName}",
                    $repo['branch']
                );
                
                GitHubItemCache::save(
                    'template',
                    $templateKey,
                    $templateTitle,
                    $repo['owner'],
                    $repo['repo'],
                    $repo['branch'],
                    "templates/{$templateName}",
                    $githubDate
                );
            }
            
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
        // Siehe NewInstallManager::installClass() - $className fließt unten in einen
        // Dateisystempfad ein und wird hart validiert, bevor die Repo-Liste geprüft wird.
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $className)) {
            throw new \Exception('Ungültiger Klassenname');
        }

        $classes = $this->repoManager->getClasses($repoKey);

        if (!isset($classes[$className])) {
            throw new \Exception("Klasse '{$className}' nicht gefunden");
        }

        $classData = $classes[$className];
        $targetDirectory = $classData['target_directory'] ?? 'lib';
        // basename(): siehe NewInstallManager::installClass()
        $filename = basename($classData['filename'] ?? ($className . '.php'));
        
        // Ziel-Pfad bestimmen - konfigurierbares Ziel-Addon (Default 'project'), siehe TableHelper
        $basePath = TableHelper::classInstallBasePath();
        
        // Jede Klasse bekommt ihren eigenen Ordner im gitClasses Verzeichnis
        $targetPath = $basePath . $className . '/';
        
        $targetFile = $targetPath . $filename;
        
        // Prüfen ob Datei existiert (zum Neu-Laden muss sie vorhanden sein)
        if (!file_exists($targetFile)) {
            throw new \Exception("Klasse '{$className}' ist nicht installiert - bitte über \"Installieren\" neu anlegen statt zu aktualisieren");
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
}
