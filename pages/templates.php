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
    $template = rex_request('template', 'string');
    $key = rex_request('key', 'string');
    
    if ($template) {
        try {
            $installManager->installNewTemplate($repo, $template, $key);
            echo rex_view::success($addon->i18n('github_installer_templates_installed_success'));
        } catch (Exception $e) {
            echo rex_view::error($addon->i18n('github_installer_templates_install_error') . ': ' . $e->getMessage());
        }
    }
}

if ($func === 'update' && $repo) {
    $template = rex_request('template', 'string');
    $key = rex_request('key', 'string');
    
    if ($template) {
        try {
            $updateManager->updateTemplate($repo, $template, $key);
            echo rex_view::success($addon->i18n('github_installer_templates_updated_success'));
        } catch (Exception $e) {
            echo rex_view::error($addon->i18n('github_installer_templates_update_error') . ': ' . $e->getMessage());
        }
    }
}

// Repository-Auswahl
$repositories = $repoManager->getRepositories();

if (empty($repositories)) {
    echo rex_view::warning($addon->i18n('github_installer_templates_no_repos'));
    return;
}

// Repository-Auswahlformular
$content = '';
$formElements = [];

$n = [];
$n['label'] = '<label for="templates-repo-select">' . $addon->i18n('github_installer_templates_select_repo') . '</label>';

$select = '<select name="repo" id="templates-repo-select" class="form-control">';
$select .= '<option value="">' . $addon->i18n('github_installer_templates_choose_repo') . '</option>';

foreach ($repositories as $repoKey => $repoData) {
    $selected = ($repo === $repoKey) ? ' selected="selected"' : '';
    $select .= '<option value="' . rex_escape($repoKey) . '"' . $selected . '>' . rex_escape($repoData['display_name']) . ' (' . rex_escape($repoKey) . ')</option>';
}
$select .= '</select>';

$n['field'] = $select;
$formElements[] = $n;

// Submit Button
$n = [];
$n['field'] = '<button class="btn btn-primary" type="submit">' . $addon->i18n('github_installer_templates_loading') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content = $fragment->parse('core/form/container.php');

// Repository-Formular
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit');
$fragment->setVar('title', $addon->i18n('github_installer_templates_select_repo'));
$fragment->setVar('body', $content, false);
$repoForm = $fragment->parse('core/page/section.php');

echo '<form action="' . rex_url::currentBackendPage() . '" method="post">' . $repoForm . '</form>';

// Templates anzeigen wenn Repository ausgewählt
if ($repo && isset($repositories[$repo])) {
    try {
        $templates = $repoManager->getTemplatesWithStatus($repo);
        
        if (!empty($templates)) {
            $tableContent = '<div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>' . $addon->i18n('github_installer_template_name') . '</th>
                            <th>' . $addon->i18n('github_installer_template_title') . '</th>
                            <th>' . $addon->i18n('github_installer_template_description') . '</th>
                            <th>' . $addon->i18n('github_installer_template_version') . '</th>
                            <th>' . $addon->i18n('github_installer_template_author') . '</th>
                            <th>' . $addon->i18n('github_installer_template_sync_status') . '</th>
                            <th>' . $addon->i18n('github_installer_template_assets') . '</th>
                            <th class="rex-table-action">' . $addon->i18n('github_installer_template_actions') . '</th>
                        </tr>
                    </thead>
                    <tbody>';
            
            foreach ($templates as $template) {
                // URL und Button basierend auf Status
                $actionUrl = '';
                $actionButton = '';
                $statusBadge = '';
                $syncStatus = '';
                
                if ($template['status'] === 'installed') {
                    // Status-Badge
                    $statusBadge = '<span class="label label-success">Installiert</span>';
                    
                    // Sync-Status mit Datums-Vergleich
                    if ($template['update_available']) {
                        $syncStatus = '<span class="label label-warning" title="Update verfügbar"><i class="rex-icon fa-cloud-download"></i> Update</span><br>';
                        if ($template['github_date']) {
                            $githubTimestamp = strtotime($template['github_date']);
                            if ($githubTimestamp > 0) {
                                $syncStatus .= '<small class="text-muted">GitHub: ' . date('d.m.Y H:i', $githubTimestamp) . '</small><br>';
                            }
                        }
                        if ($template['db_date']) {
                            $dbTimestamp = strtotime($template['db_date']);
                            if ($dbTimestamp > 0) {
                                $syncStatus .= '<small class="text-muted">REDAXO: ' . date('d.m.Y H:i', $dbTimestamp) . '</small>';
                            }
                        }
                        
                        // Update-Button
                        $actionUrl = rex_url::currentBackendPage([
                            'repo' => $repo,
                            'func' => 'update',
                            'template' => $template['name'],
                            'key' => $template['key']
                        ]);
                        $actionButton = '<a href="' . $actionUrl . '" 
                               class="btn btn-warning btn-xs"
                               onclick="return confirm(\'' . $addon->i18n('github_installer_templates_update_confirm', rex_escape($template['name'])) . '\')">
                                <i class="rex-icon rex-icon-refresh"></i> ' . $addon->i18n('github_installer_templates_update') . '
                            </a>';
                    } else {
                        $syncStatus = '<span class="label label-success"><i class="rex-icon fa-check"></i> Aktuell</span><br>';
                        if ($template['db_date']) {
                            $dbTimestamp = strtotime($template['db_date']);
                            if ($dbTimestamp > 0) {
                                $syncStatus .= '<small class="text-muted">' . date('d.m.Y H:i', $dbTimestamp) . '</small>';
                            }
                        }
                        
                        // Neu laden Button
                        $actionUrl = rex_url::currentBackendPage([
                            'repo' => $repo,
                            'func' => 'update',
                            'template' => $template['name'],
                            'key' => $template['key']
                        ]);
                        $actionButton = '<a href="' . $actionUrl . '" 
                               class="btn btn-default btn-xs"
                               onclick="return confirm(\'' . $addon->i18n('github_installer_templates_update_confirm', rex_escape($template['name'])) . '\')">
                                <i class="rex-icon rex-icon-refresh"></i> ' . $addon->i18n('github_installer_templates_reload') . '
                            </a>';
                    }
                } else {
                    // Install-Button für neue Templates
                    $actionUrl = rex_url::currentBackendPage([
                        'repo' => $repo,
                        'func' => 'install',
                        'template' => $template['name'],
                        'key' => $template['key']
                    ]);
                    $actionButton = '<a href="' . $actionUrl . '" 
                           class="btn btn-primary btn-xs"
                           onclick="return confirm(\'' . $addon->i18n('github_installer_templates_install_confirm', rex_escape($template['name'])) . '\')">
                            <i class="rex-icon rex-icon-download"></i> ' . $addon->i18n('github_installer_templates_install') . '
                        </a>';
                    $statusBadge = '<span class="label label-default">Neu</span>';
                    
                    // Sync-Status für neue Templates
                    if ($template['github_date']) {
                        $githubTimestamp = strtotime($template['github_date']);
                        if ($githubTimestamp > 0) {
                            $syncStatus = '<small class="text-muted">GitHub: ' . date('d.m.Y H:i', $githubTimestamp) . '</small>';
                        }
                    }
                }
                
                // Assets und README-Info
                $assetsInfo = '';
                if (!empty($template['has_assets'])) {
                    $assetsInfo .= '<span class="label label-success"><i class="rex-icon rex-icon-package"></i> Assets</span> ';
                }
                if (!empty($template['readme_url'])) {
                    $assetsInfo .= '<a href="' . rex_escape($template['readme_url']) . '" target="_blank" class="btn btn-xs btn-default" title="README auf GitHub öffnen"><i class="rex-icon rex-icon-open-in-new"></i> README</a>';
                }
                
                $tableContent .= '<tr>
                    <td><strong>' . rex_escape($template['name']) . '</strong><br>' . $statusBadge . '</td>
                    <td>' . rex_escape($template['title']) . '</td>
                    <td>' . rex_escape($template['description'] ?: $addon->i18n('github_installer_no_description')) . '</td>
                    <td>' . rex_escape($template['version']) . '</td>
                    <td>' . rex_escape($template['author'] ?: $addon->i18n('github_installer_unknown')) . '</td>
                    <td>' . $syncStatus . '</td>
                    <td>' . $assetsInfo . '</td>
                    <td class="rex-table-action">' . $actionButton . '</td>
                </tr>';
                
                // Details-Bereich falls Key vorhanden
                if ($template['key']) {
                    $tableContent .= '<tr class="collapse" id="template-details-' . rex_escape($template['name']) . '">
                        <td colspan="6">
                            <div class="panel panel-default">
                                <div class="panel-body">
                                    <h5>' . $addon->i18n('github_installer_template_info') . '</h5>
                                    <dl class="dl-horizontal">
                                        <dt>' . $addon->i18n('github_installer_template_key') . ':</dt>
                                        <dd>' . rex_escape($template['key']) . '</dd>
                                        <dt>' . $addon->i18n('github_installer_template_assets_target') . ':</dt>
                                        <dd>/assets/templates/' . rex_escape($template['key']) . '/</dd>
                                    </dl>
                                </div>
                            </div>
                        </td>
                    </tr>';
                }
            }
            
            $tableContent .= '</tbody></table></div>';
            
            $fragment = new rex_fragment();
            $fragment->setVar('title', $addon->i18n('github_installer_templates_title') . ' (' . count($templates) . ')');
            $fragment->setVar('body', $tableContent, false);
            echo $fragment->parse('core/page/section.php');
        } else {
            echo rex_view::info($addon->i18n('github_installer_templates_no_templates'));
        }
        
    } catch (Exception $e) {
        echo rex_view::error($addon->i18n('github_installer_error_occurred') . ': ' . $e->getMessage());
    }
}
