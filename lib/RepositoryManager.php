<?php

namespace FriendsOfREDAXO\GitHubInstaller;

use rex_config;

/**
 * Repository-Manager
 */
class RepositoryManager
{
    private GitHubApi $github;
    
    public function __construct()
    {
        $this->github = new GitHubApi();
    }
    
    /**
     * Alle Repositories abrufen
     */
    public function getRepositories(): array
    {
        return rex_config::get('github_installer', 'repositories', []);
    }
    
    /**
     * Repository hinzufügen
     */
    public function addRepository(string $owner, string $repo, string $displayName = '', string $branch = 'main'): bool
    {
        $repositories = $this->getRepositories();
        $key = $owner . '/' . $repo;
        
        // Prüfen ob bereits vorhanden
        if (isset($repositories[$key])) {
            throw new \Exception('Repository already exists');
        }
        
        // GitHub-Verbindung testen
        if (!$this->github->testRepository($owner, $repo, $branch)) {
            throw new \Exception('Repository not found on GitHub');
        }
        
        $repositories[$key] = [
            'owner' => $owner,
            'repo' => $repo,
            'display_name' => $displayName ?: $repo,
            'branch' => $branch,
            'added' => time(),
        ];
        
        return rex_config::set('github_installer', 'repositories', $repositories);
    }
    
    /**
     * Repository aktualisieren
     */
    public function updateRepository(string $key, string $displayName = '', string $branch = 'main'): bool
    {
        $repositories = $this->getRepositories();
        
        if (!isset($repositories[$key])) {
            throw new \Exception('Repository not found');
        }
        
        // GitHub-Verbindung testen
        [$owner, $repo] = explode('/', $key, 2);
        if (!$this->github->testRepository($owner, $repo, $branch)) {
            throw new \Exception('Repository not found on GitHub');
        }
        
        $repositories[$key]['display_name'] = $displayName ?: $repositories[$key]['repo'];
        $repositories[$key]['branch'] = $branch;
        
        return rex_config::set('github_installer', 'repositories', $repositories);
    }
    
    /**
     * Repository entfernen
     */
    public function removeRepository(string $key): bool
    {
        $repositories = $this->getRepositories();
        
        if (!isset($repositories[$key])) {
            return false;
        }
        
        // Cache für dieses Repository löschen
        [$owner, $repo] = explode('/', $key, 2);
        $this->github->clearRepositoryCache($owner, $repo);
        
        unset($repositories[$key]);
        return rex_config::set('github_installer', 'repositories', $repositories);
    }
    
    /**
     * Module aus Repository abrufen
     */
    public function getModules(string $key): array
    {
        $repositories = $this->getRepositories();
        
        if (!isset($repositories[$key])) {
            return [];
        }
        
        $repo = $repositories[$key];
        $modules = [];
        
        try {
            $contents = $this->github->getRepositoryContents($repo['owner'], $repo['repo'], 'modules', $repo['branch']);
            
            foreach ($contents as $item) {
                if ($item['type'] !== 'dir') continue;
                
                $moduleName = $item['name'];
                $moduleData = [
                    'name' => $moduleName,
                    'title' => $moduleName,
                    'description' => '',
                    'version' => '1.0.0',
                    'author' => '',
                    'key' => '',
                ];
                
                // Metadaten aus verschiedenen Quellen sammeln
                $moduleData = $this->extractModuleMetadata($repo, $moduleName);
                
                $modules[] = $moduleData;
            }
        } catch (\Exception $e) {
            // modules-Ordner nicht vorhanden
        }
        
        return $modules;
    }
    
    /**
     * Templates aus Repository abrufen
     */
    public function getTemplates(string $key): array
    {
        $repositories = $this->getRepositories();
        
        if (!isset($repositories[$key])) {
            return [];
        }
        
        $repo = $repositories[$key];
        $templates = [];
        
        try {
            $contents = $this->github->getRepositoryContents($repo['owner'], $repo['repo'], 'templates', $repo['branch']);
            
            foreach ($contents as $item) {
                if ($item['type'] !== 'dir') continue;
                
                $templateName = $item['name'];
                
                // Metadaten aus verschiedenen Quellen sammeln
                $templateData = $this->extractTemplateMetadata($repo, $templateName);
                
                $templates[] = $templateData;
            }
        } catch (\Exception $e) {
            // templates-Ordner nicht vorhanden
        }
        
        return $templates;
    }
    
    /**
     * Einfacher YAML-Parser für Basis-Konfigurationen
     */
    public function parseSimpleYaml(string $content): array
    {
        $result = [];
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $key = trim($key);
                $value = trim($value, ' "\'');
                
                // Basis-Typen erkennen
                if ($value === 'true') {
                    $value = true;
                } elseif ($value === 'false') {
                    $value = false;
                } elseif (is_numeric($value)) {
                    $value = is_float($value) ? (float)$value : (int)$value;
                }
                
                $result[$key] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Modul-Metadaten aus verschiedenen Quellen extrahieren
     */
    private function extractModuleMetadata(array $repo, string $moduleName): array
    {
        $moduleData = [
            'name' => $moduleName,
            'title' => $moduleName,
            'description' => '',
            'version' => '1.0.0',
            'author' => '',
            'key' => '',
        ];
        
        // 1. config.yml laden falls vorhanden (primär für stuffx-Repositories)
        if ($this->github->fileExists($repo['owner'], $repo['repo'], "modules/{$moduleName}/config.yml", $repo['branch'])) {
            try {
                $configContent = $this->github->getFileContent(
                    $repo['owner'], 
                    $repo['repo'], 
                    "modules/{$moduleName}/config.yml", 
                    $repo['branch']
                );
                $metadata = $this->parseSimpleYaml($configContent);
                $moduleData = array_merge($moduleData, $metadata);
            } catch (\Exception $e) {
                // config.yml nicht lesbar
            }
        }
        
        // 1b. package.yml als Fallback laden falls config.yml nicht vorhanden
        if (empty($moduleData['description']) && $this->github->fileExists($repo['owner'], $repo['repo'], "modules/{$moduleName}/package.yml", $repo['branch'])) {
            try {
                $packageContent = $this->github->getFileContent(
                    $repo['owner'], 
                    $repo['repo'], 
                    "modules/{$moduleName}/package.yml", 
                    $repo['branch']
                );
                $metadata = $this->parseSimpleYaml($packageContent);
                $moduleData = array_merge($moduleData, $metadata);
            } catch (\Exception $e) {
                // package.yml nicht lesbar
            }
        }
        
        // 2. README.md für Beschreibung nutzen falls package.yml keine hat
        if (empty($moduleData['description']) && $this->github->fileExists($repo['owner'], $repo['repo'], "modules/{$moduleName}/README.md", $repo['branch'])) {
            try {
                $readmeContent = $this->github->getFileContent(
                    $repo['owner'], 
                    $repo['repo'], 
                    "modules/{$moduleName}/README.md", 
                    $repo['branch']
                );
                $description = $this->extractDescriptionFromReadme($readmeContent);
                if ($description) {
                    $moduleData['description'] = $description;
                }
            } catch (\Exception $e) {
                // README.md nicht lesbar
            }
        }
        
        // 3. Input.php nach Kommentaren durchsuchen
        if (empty($moduleData['description'])) {
            try {
                $inputContent = $this->github->getFileContent(
                    $repo['owner'], 
                    $repo['repo'], 
                    "modules/{$moduleName}/input.php", 
                    $repo['branch']
                );
                $description = $this->extractDescriptionFromPhp($inputContent);
                if ($description) {
                    $moduleData['description'] = $description;
                }
            } catch (\Exception $e) {
                // input.php nicht vorhanden
            }
        }
        
        // 4. Fallback: Hübscher Name aus Ordnername
        $moduleData['title'] = $this->beautifyName($moduleName);
        
        // 5. Assets-Information hinzufügen
        $moduleData['has_assets'] = $this->github->fileExists($repo['owner'], $repo['repo'], "modules/{$moduleName}/assets", $repo['branch']);
        if ($moduleData['has_assets']) {
            $moduleData['readme_url'] = "https://github.com/{$repo['owner']}/{$repo['repo']}/blob/{$repo['branch']}/modules/{$moduleName}/README.md";
        }
        
        return $moduleData;
    }
    
    /**
     * Template-Metadaten aus verschiedenen Quellen extrahieren
     */
    private function extractTemplateMetadata(array $repo, string $templateName): array
    {
        $templateData = [
            'name' => $templateName,
            'title' => $templateName,
            'description' => '',
            'version' => '1.0.0',
            'author' => '',
            'key' => '',
        ];
        
        // 1. config.yml laden falls vorhanden (primär für stuffx-Repositories)
        if ($this->github->fileExists($repo['owner'], $repo['repo'], "templates/{$templateName}/config.yml", $repo['branch'])) {
            try {
                $configContent = $this->github->getFileContent(
                    $repo['owner'], 
                    $repo['repo'], 
                    "templates/{$templateName}/config.yml", 
                    $repo['branch']
                );
                $metadata = $this->parseSimpleYaml($configContent);
                $templateData = array_merge($templateData, $metadata);
            } catch (\Exception $e) {
                // config.yml nicht lesbar
            }
        }
        
        // 1b. package.yml als Fallback laden falls config.yml nicht vorhanden
        if (empty($templateData['description']) && $this->github->fileExists($repo['owner'], $repo['repo'], "templates/{$templateName}/package.yml", $repo['branch'])) {
            try {
                $packageContent = $this->github->getFileContent(
                    $repo['owner'], 
                    $repo['repo'], 
                    "templates/{$templateName}/package.yml", 
                    $repo['branch']
                );
                $metadata = $this->parseSimpleYaml($packageContent);
                $templateData = array_merge($templateData, $metadata);
            } catch (\Exception $e) {
                // package.yml nicht lesbar
            }
        }
        
        // 2. README.md für Beschreibung nutzen
        if (empty($templateData['description']) && $this->github->fileExists($repo['owner'], $repo['repo'], "templates/{$templateName}/README.md", $repo['branch'])) {
            try {
                $readmeContent = $this->github->getFileContent(
                    $repo['owner'], 
                    $repo['repo'], 
                    "templates/{$templateName}/README.md", 
                    $repo['branch']
                );
                $description = $this->extractDescriptionFromReadme($readmeContent);
                if ($description) {
                    $templateData['description'] = $description;
                }
            } catch (\Exception $e) {
                // README.md nicht lesbar
            }
        }
        
        // 3. template.php nach Kommentaren durchsuchen
        if (empty($templateData['description'])) {
            try {
                $templateContent = $this->github->getFileContent(
                    $repo['owner'], 
                    $repo['repo'], 
                    "templates/{$templateName}/template.php", 
                    $repo['branch']
                );
                $description = $this->extractDescriptionFromPhp($templateContent);
                if ($description) {
                    $templateData['description'] = $description;
                }
            } catch (\Exception $e) {
                // template.php nicht vorhanden
            }
        }
        
        // 4. Fallback: Hübscher Name aus Ordnername
        $templateData['title'] = $this->beautifyName($templateName);
        
        // 5. Assets-Information hinzufügen
        $templateData['has_assets'] = $this->github->fileExists($repo['owner'], $repo['repo'], "templates/{$templateName}/assets", $repo['branch']);
        if ($templateData['has_assets']) {
            $templateData['readme_url'] = "https://github.com/{$repo['owner']}/{$repo['repo']}/blob/{$repo['branch']}/templates/{$templateName}/README.md";
        }
        
        return $templateData;
    }
    
    /**
     * Beschreibung aus README.md extrahieren
     */
    private function extractDescriptionFromReadme(string $content): string
    {
        $lines = explode("\n", $content);
        
        // Erste nicht-leere Zeile nach dem Titel suchen
        $foundTitle = false;
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Titel überspringen (beginnt mit #)
            if (strpos($line, '#') === 0) {
                $foundTitle = true;
                continue;
            }
            
            // Erste inhaltliche Zeile nach dem Titel
            if ($foundTitle && !empty($line) && strpos($line, '#') !== 0) {
                return substr($line, 0, 200); // Maximal 200 Zeichen
            }
        }
        
        return '';
    }
    
    /**
     * Beschreibung aus PHP-Kommentaren extrahieren
     */
    private function extractDescriptionFromPhp(string $content): string
    {
        // Nach Dokumentationskommentaren suchen
        if (preg_match('/\/\*\*(.*?)\*\//s', $content, $matches)) {
            $comment = $matches[1];
            $lines = explode("\n", $comment);
            
            foreach ($lines as $line) {
                $line = trim($line, " \t\n\r\0\x0B*");
                if (!empty($line) && strpos($line, '@') !== 0) {
                    return substr($line, 0, 200);
                }
            }
        }
        
        // Nach einfachen Kommentaren am Anfang suchen
        if (preg_match('/^<\?php\s*\/\/\s*(.+)/m', $content, $matches)) {
            return substr(trim($matches[1]), 0, 200);
        }
        
        return '';
    }
    
    /**
     * Namen verschönern (text-simple -> Text Simple)
     */
    private function beautifyName(string $name): string
    {
        // Bindestriche und Unterstriche durch Leerzeichen ersetzen
        $name = str_replace(['-', '_'], ' ', $name);
        
        // Jeden Wortanfang groß schreiben
        return ucwords($name);
    }
}
