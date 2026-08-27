<?php

namespace FriendsOfREDAXO\GitHubInstaller;

/**
 * Install-Manager nur für neue Module und Templates
 */
class NewInstallManager
{
    private GitHubApi $github;
    private RepositoryManager $repoManager;

    public function __construct()
    {
        $this->github = new GitHubApi();
        $this->repoManager = new RepositoryManager();
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
        if ($moduleKey && TableHelper::hasKeyField('module')) {
            $sql->setValue('key', $moduleKey);
        }

        try {
            $sql->insert();
            
            // GitHub-Cache speichern
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
        if ($templateKey && TableHelper::hasKeyField('template')) {
            $sql->setValue('key', $templateKey);
        }

        try {
            $sql->insert();
            
            // GitHub-Cache speichern
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
        return null !== TableHelper::findExisting('module', $moduleKey, $moduleName);
    }

    /**
     * Prüft ob ein Template bereits existiert
     */
    private function templateExists(string $templateKey, string $templateName): bool
    {
        return null !== TableHelper::findExisting('template', $templateKey, $templateName);
    }

    /**
     * Installiert eine Klasse aus einem Repository
     */
    public function installClass(string $repoKey, string $className): bool
    {
        // $className landet unten direkt in einem Dateisystempfad. Er muss zwar auch
        // ein vorhandener Schlüssel in $classes sein, aber diese Liste stammt aus dem
        // (potenziell fremdverwalteten) GitHub-Repo - zusätzlich hart validieren.
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $className)) {
            throw new \Exception('Ungültiger Klassenname');
        }

        $classes = $this->repoManager->getClasses($repoKey);

        if (!isset($classes[$className])) {
            throw new \Exception("Klasse '{$className}' nicht gefunden");
        }
        
        $classData = $classes[$className];
        $targetDirectory = $classData['target_directory'] ?? 'lib';
        // basename(): $classData['filename'] kommt aus dem GitHub-Repo und könnte
        // theoretisch Pfadanteile enthalten - nur den reinen Dateinamen übernehmen.
        $filename = basename($classData['filename'] ?? ($className . '.php'));

        // Ziel-Pfad bestimmen - konfigurierbares Ziel-Addon (Default 'project'), siehe TableHelper
        $basePath = TableHelper::classInstallBasePath();
        
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
        
        error_log("GitHub Installer - Class '{$className}' successfully installed to {$targetFile}");
        return true;
    }
}
