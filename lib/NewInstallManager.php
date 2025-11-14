<?php

namespace FriendsOfREDAXO\GitHubInstaller;

/**
 * Install-Manager nur für neue Module und Templates
 */
class NewInstallManager
{
    private GitHubApi $github;
    private RepositoryManager $repoManager;
    private InstallationTracker $tracker;

    public function __construct()
    {
        $this->github = new GitHubApi();
        $this->repoManager = new RepositoryManager();
        $this->tracker = new InstallationTracker();
    }

    /**
     * Neues Modul installieren
     */
    public function installNewModule(string $repoKey, string $moduleName, string $key = ''): bool
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

        // Prüfen ob Modul bereits existiert
        if ($this->moduleExists($moduleKey, $moduleTitle)) {
            throw new \Exception('Module already exists - use UpdateManager for updates');
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

        // Neues Modul erstellen
        $sql = \rex_sql::factory();
        $sql->setTable(\rex::getTable('module'));
        
        $sql->setValue('name', $moduleTitle);
        $sql->setValue('input', $inputCode);
        $sql->setValue('output', $outputCode);
        
        // Key nur setzen wenn es existiert und einen Wert hat
        if ($moduleKey && $this->hasKeyField('module')) {
            $sql->setValue('key', $moduleKey);
        }

        try {
            $sql->insert();
            
            // Get last commit information from GitHub
            $commitInfo = $this->github->getLastCommit(
                $repo['owner'],
                $repo['repo'],
                "modules/{$moduleName}",
                $repo['branch']
            );
            
            // Track installation
            if ($moduleKey) {
                $this->tracker->saveInstallation(
                    'module',
                    $moduleKey,
                    $moduleTitle,
                    $repo['owner'],
                    $repo['repo'],
                    $repo['branch'],
                    "modules/{$moduleName}",
                    $commitInfo
                );
            }
            
            // REDAXO Cache löschen
            \rex_delete_cache();
            
            error_log("GitHub Installer - Module '{$moduleTitle}' successfully installed");
            return true;
            
        } catch (\Exception $e) {
            error_log("GitHub Installer - Error installing module '{$moduleTitle}': " . $e->getMessage());
            throw new \Exception('Fehler beim Installieren des Moduls: ' . $e->getMessage());
        }
    }

    /**
     * Neues Template installieren
     */
    public function installNewTemplate(string $repoKey, string $templateName, string $key = ''): bool
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

        // Prüfen ob Template bereits existiert
        if ($this->templateExists($templateKey, $templateTitle)) {
            throw new \Exception('Template already exists - use UpdateManager for updates');
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

        // Neues Template erstellen
        $sql = \rex_sql::factory();
        $sql->setTable(\rex::getTable('template'));
        
        $sql->setValue('name', $templateTitle);
        $sql->setValue('content', $templateCode);
        
        // Key nur setzen wenn es existiert und einen Wert hat
        if ($templateKey && $this->hasKeyField('template')) {
            $sql->setValue('key', $templateKey);
        }

        try {
            $sql->insert();
            
            // Get last commit information from GitHub
            $commitInfo = $this->github->getLastCommit(
                $repo['owner'],
                $repo['repo'],
                "templates/{$templateName}",
                $repo['branch']
            );
            
            // Track installation
            if ($templateKey) {
                $this->tracker->saveInstallation(
                    'template',
                    $templateKey,
                    $templateTitle,
                    $repo['owner'],
                    $repo['repo'],
                    $repo['branch'],
                    "templates/{$templateName}",
                    $commitInfo
                );
            }
            
            // REDAXO Cache löschen
            \rex_delete_cache();
            
            error_log("GitHub Installer - Template '{$templateTitle}' successfully installed");
            return true;
            
        } catch (\Exception $e) {
            error_log("GitHub Installer - Error installing template '{$templateTitle}': " . $e->getMessage());
            throw new \Exception('Fehler beim Installieren des Templates: ' . $e->getMessage());
        }
    }

    /**
     * Prüft ob ein Modul bereits existiert
     */
    private function moduleExists(string $moduleKey, string $moduleName): bool
    {
        // 1. Priorität: Prüfung über `key` Feld (wenn vorhanden)
        if ($moduleKey && $this->hasKeyField('module')) {
            $checkSql = \rex_sql::factory();
            $checkSql->setQuery('SELECT id FROM ' . \rex::getTable('module') . ' WHERE `key` = ?', [$moduleKey]);
            
            if ($checkSql->getRows() > 0) {
                return true;
            }
        }

        // 2. Fallback: Prüfung über Name
        if ($moduleName) {
            $checkSql = \rex_sql::factory();
            $checkSql->setQuery('SELECT id FROM ' . \rex::getTable('module') . ' WHERE name = ?', [$moduleName]);
            
            if ($checkSql->getRows() > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prüft ob ein Template bereits existiert
     */
    private function templateExists(string $templateKey, string $templateName): bool
    {
        // 1. Priorität: Prüfung über `key` Feld (wenn vorhanden)
        if ($templateKey && $this->hasKeyField('template')) {
            $checkSql = \rex_sql::factory();
            $checkSql->setQuery('SELECT id FROM ' . \rex::getTable('template') . ' WHERE `key` = ?', [$templateKey]);
            
            if ($checkSql->getRows() > 0) {
                return true;
            }
        }

        // 2. Fallback: Prüfung über Name
        if ($templateName) {
            $checkSql = \rex_sql::factory();
            $checkSql->setQuery('SELECT id FROM ' . \rex::getTable('template') . ' WHERE name = ?', [$templateName]);
            
            if ($checkSql->getRows() > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Installiert eine Klasse aus einem Repository
     */
    public function installClass(string $repoKey, string $className): bool
    {
        $repositoryManager = new RepositoryManager();
        $classes = $repositoryManager->getClasses($repoKey);
        
        if (!isset($classes[$className])) {
            throw new \Exception("Klasse '{$className}' nicht gefunden");
        }
        
        $classData = $classes[$className];
        $targetDirectory = $classData['target_directory'] ?? 'lib';
        $filename = $classData['filename'] ?? $className . '.php';
        
        // Ziel-Pfad bestimmen - IMMER in gitClasses Unterordner
        $basePath = \rex_path::addon('project') . 'lib/gitClasses/';
        
        // Jede Klasse bekommt ihren eigenen Ordner im gitClasses Verzeichnis
        $targetPath = $basePath . $className . '/';
        
        // Verzeichnis erstellen falls nicht vorhanden
        if (!is_dir($targetPath)) {
            if (!\rex_dir::create($targetPath)) {
                throw new \Exception("Konnte Ziel-Verzeichnis nicht erstellen: {$targetPath}");
            }
        }
        
        $targetFile = $targetPath . $filename;
        
        // Prüfen ob Datei bereits existiert
        if (file_exists($targetFile)) {
            throw new \Exception("Klasse '{$className}' ist bereits installiert");
        }
        
        // Datei schreiben
        if (!\rex_file::put($targetFile, $classData['content'])) {
            throw new \Exception("Konnte Klasse-Datei nicht schreiben: {$targetFile}");
        }
        
        // Get repository info for tracking
        $repositories = $this->repoManager->getRepositories();
        if (isset($repositories[$repoKey])) {
            $repo = $repositories[$repoKey];
            
            // Get last commit information from GitHub
            $commitInfo = $this->github->getLastCommit(
                $repo['owner'],
                $repo['repo'],
                "classes/{$className}",
                $repo['branch']
            );
            
            // Track installation
            $this->tracker->saveInstallation(
                'class',
                $className,
                $classData['title'] ?? $className,
                $repo['owner'],
                $repo['repo'],
                $repo['branch'],
                "classes/{$className}",
                $commitInfo
            );
        }
        
        error_log("GitHub Installer - Class '{$className}' successfully installed to {$targetFile}");
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
