<?php

use FriendsOfREDAXO\GitHubInstaller\RepositoryManager;
use FriendsOfREDAXO\GitHubInstaller\NewInstallManager;
use FriendsOfREDAXO\GitHubInstaller\UpdateManager;

$addon = rex_addon::get('github_installer');

$repoManager = new RepositoryManager();
$installManager = new NewInstallManager();
$updateManager = new UpdateManager();

// Aktionen verarbeiten
$func = rex_request('func', 'string');
$repo = rex_request('repo', 'string');

if ($func === 'install' && $repo) {
    $module = rex_request('module', 'string');
    $key = rex_request('key', 'string');
    
    if ($module) {
        try {
            $installManager->installNewModule($repo, $module, $key);
            echo rex_view::success($addon->i18n('modules_installed_success'));
        } catch (Exception $e) {
            echo rex_view::error($addon->i18n('modules_install_error') . ': ' . $e->getMessage());
        }
    }
}

if ($func === 'update' && $repo) {
    $module = rex_request('module', 'string');
    $key = rex_request('key', 'string');
    
    if ($module) {
        try {
            $updateManager->updateModule($repo, $module, $key);
            echo rex_view::success($addon->i18n('modules_updated_success'));
        } catch (Exception $e) {
            echo rex_view::error($addon->i18n('modules_update_error') . ': ' . $e->getMessage());
        }
    }
}

// Repository-Auswahl
$repositories = $repoManager->getRepositories();

if (empty($repositories)) {
    echo rex_view::warning($addon->i18n('modules_no_repos'));
    return;
}

// Repository-Auswahlformular
$content = '';
$formElements = [];

$n = [];
$n['label'] = '<label for="modules-repo-select">' . $addon->i18n('modules_select_repo') . '</label>';

$select = '<select name="repo" id="modules-repo-select" class="form-control">';
$select .= '<option value="">' . $addon->i18n('modules_choose_repo') . '</option>';

foreach ($repositories as $repoKey => $repoData) {
    $selected = ($repo === $repoKey) ? ' selected="selected"' : '';
    $select .= '<option value="' . rex_escape($repoKey) . '"' . $selected . '>' . rex_escape($repoData['display_name']) . ' (' . rex_escape($repoKey) . ')</option>';
}
$select .= '</select>';

$n['field'] = $select;
$formElements[] = $n;

// Submit Button
$n = [];
$n['field'] = '<button class="btn btn-primary" type="submit">' . $addon->i18n('modules_loading') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content = $fragment->parse('core/form/container.php');

// Repository-Formular
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit');
$fragment->setVar('title', $addon->i18n('modules_select_repo'));
$fragment->setVar('body', $content, false);
$repoForm = $fragment->parse('core/page/section.php');

echo '<form action="' . rex_url::currentBackendPage() . '" method="post">' . $repoForm . '</form>';

// Module anzeigen wenn Repository ausgewählt
if ($repo && isset($repositories[$repo])) {
    try {
        $modules = $repoManager->getModulesWithStatus($repo);
        
        if (!empty($modules)) {
            $tableContent = '<div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>' . $addon->i18n('module_name') . '</th>
                            <th>' . $addon->i18n('module_title') . '</th>
                            <th>' . $addon->i18n('module_description') . '</th>
                            <th>' . $addon->i18n('module_version') . '</th>
                            <th>' . $addon->i18n('module_author') . '</th>
                            <th>' . $addon->i18n('module_sync_status') . '</th>
                            <th>' . $addon->i18n('module_assets') . '</th>
                            <th class="rex-table-action">' . $addon->i18n('module_actions') . '</th>
                        </tr>
                    </thead>
                    <tbody>';
            
            foreach ($modules as $module) {
                // URL und Button basierend auf Status
                $actionUrl = '';
                $actionButton = '';
                $statusBadge = '';
                $syncStatus = '';
                
                if ($module['status'] === 'installed') {
                    // Status-Badge
                    $statusBadge = '<span class="label label-success">Installiert</span>';
                    
                    // Sync-Status mit Datums-Vergleich
                    if ($module['update_available']) {
                        $syncStatus = '<span class="label label-warning" title="Update verfügbar"><i class="rex-icon fa-cloud-download"></i> Update</span><br>';
                        if ($module['github_date']) {
                            $githubTimestamp = strtotime($module['github_date']);
                            if ($githubTimestamp > 0) {
                                $syncStatus .= '<small class="text-muted">GitHub: ' . date('d.m.Y H:i', $githubTimestamp) . '</small><br>';
                            }
                        }
                        if ($module['db_date']) {
                            $dbTimestamp = strtotime($module['db_date']);
                            if ($dbTimestamp > 0) {
                                $syncStatus .= '<small class="text-muted">REDAXO: ' . date('d.m.Y H:i', $dbTimestamp) . '</small>';
                            }
                        }
                        
                        // Update-Button
                        $actionUrl = rex_url::currentBackendPage([
                            'repo' => $repo,
                            'func' => 'update',
                            'module' => $module['name'],
                            'key' => $module['key']
                        ]);
                        $actionButton = '<a href="' . $actionUrl . '" 
                               class="btn btn-warning btn-xs"
                               onclick="return confirm(\'' . $addon->i18n('modules_update_confirm', rex_escape($module['name'])) . '\')">
                                <i class="rex-icon rex-icon-refresh"></i> ' . $addon->i18n('modules_update') . '
                            </a>';
                    } else {
                        $syncStatus = '<span class="label label-success"><i class="rex-icon fa-check"></i> Aktuell</span><br>';
                        if ($module['db_date']) {
                            $dbTimestamp = strtotime($module['db_date']);
                            if ($dbTimestamp > 0) {
                                $syncStatus .= '<small class="text-muted">' . date('d.m.Y H:i', $dbTimestamp) . '</small>';
                            }
                        }
                        
                        // Neu laden Button
                        $actionUrl = rex_url::currentBackendPage([
                            'repo' => $repo,
                            'func' => 'update',
                            'module' => $module['name'],
                            'key' => $module['key']
                        ]);
                        $actionButton = '<a href="' . $actionUrl . '" 
                               class="btn btn-default btn-xs"
                               onclick="return confirm(\'' . $addon->i18n('modules_update_confirm', rex_escape($module['name'])) . '\')">
                                <i class="rex-icon rex-icon-refresh"></i> ' . $addon->i18n('modules_reload') . '
                            </a>';
                    }
                } else {
                    // Install-Button für neue Module
                    $actionUrl = rex_url::currentBackendPage([
                        'repo' => $repo,
                        'func' => 'install',
                        'module' => $module['name'],
                        'key' => $module['key']
                    ]);
                    $actionButton = '<a href="' . $actionUrl . '" 
                           class="btn btn-primary btn-xs"
                           onclick="return confirm(\'' . $addon->i18n('modules_install_confirm', rex_escape($module['name'])) . '\')">
                            <i class="rex-icon rex-icon-download"></i> ' . $addon->i18n('modules_install') . '
                        </a>';
                    $statusBadge = '<span class="label label-default">Neu</span>';
                    
                    // Sync-Status für neue Module
                    if ($module['github_date']) {
                        $githubTimestamp = strtotime($module['github_date']);
                        if ($githubTimestamp > 0) {
                            $syncStatus = '<small class="text-muted">GitHub: ' . date('d.m.Y H:i', $githubTimestamp) . '</small>';
                        } else {
                            $syncStatus = '<small class="text-muted">-</small>';
                        }
                    } else {
                        $syncStatus = '<small class="text-muted">-</small>';
                    }
                }
                
                // Assets und README-Info
                $assetsInfo = '';
                if (!empty($module['has_assets'])) {
                    $assetsInfo .= '<span class="label label-success"><i class="rex-icon rex-icon-package"></i> Assets</span> ';
                }
                if (!empty($module['readme_url'])) {
                    $assetsInfo .= '<a href="' . rex_escape($module['readme_url']) . '" target="_blank" class="btn btn-xs btn-default" title="README auf GitHub öffnen"><i class="rex-icon rex-icon-open-in-new"></i> README</a>';
                }
                
                $tableContent .= '<tr>
                    <td><strong>' . rex_escape($module['name']) . '</strong><br>' . $statusBadge . '</td>
                    <td>' . rex_escape($module['title']) . '</td>
                    <td>' . rex_escape($module['description'] ?: $addon->i18n('no_description')) . '</td>
                    <td>' . rex_escape($module['version']) . '</td>
                    <td>' . rex_escape($module['author'] ?: $addon->i18n('unknown')) . '</td>
                    <td>' . $syncStatus . '</td>
                    <td>' . $assetsInfo . '</td>
                    <td class="rex-table-action">' . $actionButton . '</td>
                </tr>';
                
                // Details-Bereich falls Key vorhanden
                if ($module['key']) {
                    $tableContent .= '<tr class="collapse" id="module-details-' . rex_escape($module['name']) . '">
                        <td colspan="6">
                            <div class="panel panel-default">
                                <div class="panel-body">
                                    <h5>' . $addon->i18n('module_info') . '</h5>
                                    <dl class="dl-horizontal">
                                        <dt>' . $addon->i18n('module_key') . ':</dt>
                                        <dd>' . rex_escape($module['key']) . '</dd>
                                        <dt>' . $addon->i18n('module_assets_target') . ':</dt>
                                        <dd>/assets/modules/' . rex_escape($module['key']) . '/</dd>
                                    </dl>
                                </div>
                            </div>
                        </td>
                    </tr>';
                }
            }
            
            $tableContent .= '</tbody></table></div>';
            
            $fragment = new rex_fragment();
            $fragment->setVar('title', $addon->i18n('modules_title') . ' (' . count($modules) . ')');
            $fragment->setVar('body', $tableContent, false);
            echo $fragment->parse('core/page/section.php');
        } else {
            echo rex_view::info($addon->i18n('modules_no_modules'));
        }
        
    } catch (Exception $e) {
        echo rex_view::error($addon->i18n('error_occurred') . ': ' . $e->getMessage());
    }
}
