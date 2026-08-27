<?php

use FriendsOfREDAXO\GitHubInstaller\RepositoryManager;

$addon = rex_addon::get('github_installer');
$repoManager = new RepositoryManager();
$csrfToken = rex_csrf_token::factory('github_installer_classes');

// Konfigurierte Repositories laden
$repositories = $addon->getConfig('repositories', []);

if (empty($repositories)) {
    echo rex_view::warning($addon->i18n('github_installer_classes_no_repos') . ' <a href="' . rex_url::currentBackendPage(['page' => 'github_installer/repositories']) . '">' . $addon->i18n('github_installer_repositories') . '</a>');
    return;
}

// Repository auswählen
$repo = rex_request('repo', 'string');

if (!$repo) {
    // Repository-Auswahlformular
    $content = '';
    $formElements = [];

    $n = [];
    $n['label'] = '<label for="classes-repo-select">' . $addon->i18n('github_installer_classes_select_repo') . '</label>';

    $select = '<select name="repo" id="classes-repo-select" class="form-control">';
    $select .= '<option value="">' . $addon->i18n('github_installer_classes_choose_repo') . '</option>';

    foreach ($repositories as $repoKey => $repoData) {
        $selected = ($repo === $repoKey) ? ' selected="selected"' : '';
        $select .= '<option value="' . rex_escape($repoKey) . '"' . $selected . '>' . rex_escape($repoData['display_name']) . ' (' . rex_escape($repoKey) . ')</option>';
    }
    $select .= '</select>';

    $n['field'] = $select;
    $formElements[] = $n;

    // Submit Button
    $n = [];
    $n['field'] = '<button class="btn btn-primary" type="submit">' . $addon->i18n('github_installer_classes_loading') . '</button>';
    $formElements[] = $n;

    $fragment = new rex_fragment();
    $fragment->setVar('elements', $formElements, false);
    $content = $fragment->parse('core/form/container.php');

    // Repository-Formular
    $fragment = new rex_fragment();
    $fragment->setVar('class', 'edit');
    $fragment->setVar('title', $addon->i18n('github_installer_classes_select_repo'));
    $fragment->setVar('body', $content, false);
    $repoForm = $fragment->parse('core/page/section.php');

    echo '<form action="' . rex_url::currentBackendPage() . '" method="post">' . $repoForm . '</form>';
    return;
}

// Klassen anzeigen wenn Repository ausgewählt
if ($repo && isset($repositories[$repo])) {
    try {
        $classes = $repoManager->getClassesWithStatus($repo);
        
        if (!empty($classes)) {
            $tableContent = '<div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>' . $addon->i18n('github_installer_class_name') . '</th>
                            <th>' . $addon->i18n('github_installer_class_title') . '</th>
                            <th>' . $addon->i18n('github_installer_class_description') . '</th>
                            <th>' . $addon->i18n('github_installer_class_version') . '</th>
                            <th>' . $addon->i18n('github_installer_class_author') . '</th>
                            <th>' . $addon->i18n('github_installer_info_header') . '</th>
                            <th>' . $addon->i18n('github_installer_status_header') . '</th>
                            <th>' . $addon->i18n('github_installer_class_actions') . '</th>
                        </tr>
                    </thead>
                    <tbody>';

            foreach ($classes as $className => $classData) {
                $isInstalled = $classData['status']['installed'];
                $statusBadge = $isInstalled
                    ? '<span class="badge badge-success">' . $addon->i18n('github_installer_status_installed') . '</span>'
                    : '<span class="badge badge-secondary">' . $addon->i18n('github_installer_status_new') . '</span>';
                
                // Action Buttons
                $actionButtons = '';
                if ($isInstalled) {
                    $actionButtons .= '<button class="btn btn-warning btn-xs update-class-btn" 
                                            data-class="' . rex_escape($className) . '" 
                                            data-repo="' . rex_escape($repo) . '">' . 
                                    $addon->i18n('github_installer_classes_update') . '</button>';
                } else {
                    $actionButtons .= '<button class="btn btn-primary btn-xs install-class-btn" 
                                            data-class="' . rex_escape($className) . '" 
                                            data-repo="' . rex_escape($repo) . '">' . 
                                    $addon->i18n('github_installer_classes_install') . '</button>';
                }
                
                // Info-Links (README)
                $infoLinks = '';
                if (!empty($classData['readme_url'])) {
                    $infoLinks .= '<a href="' . rex_escape($classData['readme_url']) . '" target="_blank" class="btn btn-xs btn-default" title="README auf GitHub öffnen"><i class="rex-icon rex-icon-open-in-new"></i> README</a>';
                }
                
                $tableContent .= '<tr>
                    <td><strong>' . rex_escape($className) . '</strong></td>
                    <td>' . rex_escape($classData['title'] ?? $addon->i18n('github_installer_class_unnamed')) . '</td>
                    <td>' . rex_escape($classData['description'] ?? $addon->i18n('github_installer_no_description')) . '</td>
                    <td>' . rex_escape($classData['version'] ?? '1.0.0') . '</td>
                    <td>' . rex_escape($classData['author'] ?? $addon->i18n('github_installer_unknown')) . '</td>
                    <td>' . $infoLinks . '</td>
                    <td>' . $statusBadge . '</td>
                    <td>' . $actionButtons . '</td>
                </tr>';
            }

            $tableContent .= '</tbody></table></div>';
            
            // Repository Info Header
            $repoInfo = '<div class="alert alert-info">
                <strong>Repository:</strong> ' . rex_escape($repositories[$repo]['display_name']) . ' 
                <small>(' . rex_escape($repo) . ')</small>
            </div>';
            
            echo $repoInfo . $tableContent;
            
        } else {
            echo rex_view::info($addon->i18n('github_installer_classes_no_classes'));
        }
        
    } catch (Exception $e) {
        echo rex_view::error($addon->i18n('github_installer_error_occurred') . ': ' . rex_escape($e->getMessage()));
    }
}
?>

<script>
jQuery(document).ready(function($) {

    var csrfTokenParam = <?php echo json_encode(rex_csrf_token::PARAM); ?>;
    var csrfTokenValue = <?php echo json_encode($csrfToken->getValue()); ?>;

    var i18n = {
        reloadConfirm: <?php echo json_encode($addon->i18n('github_installer_classes_update_confirm', '__NAME__')); ?>,
        installConfirm: <?php echo json_encode($addon->i18n('github_installer_classes_install_confirm', '__NAME__')); ?>,
        reloading: <?php echo json_encode($addon->i18n('github_installer_reloading')); ?>,
        installing: <?php echo json_encode($addon->i18n('github_installer_installing')); ?>,
        errorPrefix: <?php echo json_encode($addon->i18n('github_installer_error_prefix')); ?>,
        updateErrorGeneric: <?php echo json_encode($addon->i18n('github_installer_classes_update_error_generic')); ?>,
        installErrorGeneric: <?php echo json_encode($addon->i18n('github_installer_classes_install_error_generic')); ?>,
        statusInstalled: <?php echo json_encode($addon->i18n('github_installer_status_installed')); ?>
    };

    // Serverantworten können GitHub-seitige Strings (Klassenname etc.) enthalten -
    // vor dem Einfügen als HTML escapen, um DOM-based XSS zu vermeiden.
    function escapeHtml(text) {
        return $('<div>').text(text == null ? '' : text).html();
    }

    // Update Class Handler Funktion
    function updateClassHandler(button) {
        var className = button.data('class');
        var repo = button.data('repo');

        if (confirm(i18n.reloadConfirm.replace('__NAME__', className))) {
            button.prop('disabled', true).text(i18n.reloading);

            $.post('<?php echo rex_url::currentBackendPage(); ?>', {
                'update_class': '1',
                'class_name': className,
                'repo': repo,
                [csrfTokenParam]: csrfTokenValue
            }, function(data) {
                if (data.success) {
                    // Erfolgsmeldung anzeigen
                    $('<div class="alert alert-success alert-dismissible" role="alert">' +
                      '<button type="button" class="close" data-dismiss="alert">' +
                      '<span aria-hidden="true">&times;</span></button>' +
                      '<strong>Erfolg!</strong> ' + escapeHtml(data.message) + '</div>')
                      .insertBefore('.table-responsive').hide().fadeIn();

                    button.prop('disabled', false).text('<?php echo $addon->i18n('github_installer_classes_update'); ?>');
                } else {
                    alert(i18n.errorPrefix + ' ' + data.message);
                    button.prop('disabled', false).text('<?php echo $addon->i18n('github_installer_classes_update'); ?>');
                }
            }, 'json').fail(function() {
                alert(i18n.updateErrorGeneric);
                button.prop('disabled', false).text('<?php echo $addon->i18n('github_installer_classes_update'); ?>');
            });
        }
    }

    // Install Class
    $('.install-class-btn').on('click', function() {
        var className = $(this).data('class');
        var repo = $(this).data('repo');
        var button = $(this);

        if (confirm(i18n.installConfirm.replace('__NAME__', className))) {
            button.prop('disabled', true).text(i18n.installing);

            $.post('<?php echo rex_url::currentBackendPage(); ?>', {
                'install_class': '1',
                'class_name': className,
                'repo': repo,
                [csrfTokenParam]: csrfTokenValue
            }, function(data) {
                if (data.success) {
                    // Erfolgsmeldung anzeigen
                    $('<div class="alert alert-success alert-dismissible" role="alert">' +
                      '<button type="button" class="close" data-dismiss="alert">' +
                      '<span aria-hidden="true">&times;</span></button>' +
                      '<strong>Erfolg!</strong> ' + escapeHtml(data.message) + '</div>')
                      .insertBefore('.table-responsive').hide().fadeIn();

                    // Button zu "Neu laden" ändern
                    button.removeClass('btn-primary install-class-btn')
                          .addClass('btn-warning update-class-btn')
                          .text('<?php echo $addon->i18n('github_installer_classes_update'); ?>')
                          .prop('disabled', false);

                    // Status-Badge ändern
                    button.closest('tr').find('.badge-secondary')
                          .removeClass('badge-secondary')
                          .addClass('badge-success')
                          .text(i18n.statusInstalled);

                    // Event-Handler für Update-Button setzen
                    button.off('click').on('click', function() {
                        updateClassHandler($(this));
                    });
                } else {
                    alert(i18n.errorPrefix + ' ' + data.message);
                    button.prop('disabled', false).text('<?php echo $addon->i18n('github_installer_classes_install'); ?>');
                }
            }, 'json').fail(function() {
                alert(i18n.installErrorGeneric);
                button.prop('disabled', false).text('<?php echo $addon->i18n('github_installer_classes_install'); ?>');
            });
        }
    });
    
    // Update Class - Initiale Handler
    $('.update-class-btn').on('click', function() {
        updateClassHandler($(this));
    });
    

});
</script>

<?php
// AJAX Handler für Installation/Update
if (rex_post('install_class', 'bool') || rex_post('update_class', 'bool')) {
    try {
        if (!$csrfToken->isValid()) {
            throw new Exception($addon->i18n('github_installer_csrf_invalid'));
        }

        $className = rex_post('class_name', 'string');
        $repo = rex_post('repo', 'string');

        if (empty($className) || empty($repo)) {
            throw new Exception($addon->i18n('github_installer_missing_parameters'));
        }

        $isUpdate = rex_post('update_class', 'bool');

        if ($isUpdate) {
            $manager = new \FriendsOfREDAXO\GitHubInstaller\UpdateManager();
            $result = $manager->updateClass($repo, $className);
            $message = $addon->i18n('github_installer_classes_updated_success');
        } else {
            $manager = new \FriendsOfREDAXO\GitHubInstaller\NewInstallManager();
            $result = $manager->installClass($repo, $className);
            $message = $addon->i18n('github_installer_classes_installed_success');
        }
        
        rex_response::sendJson(['success' => true, 'message' => $message]);
        
    } catch (Exception $e) {
        rex_response::sendJson(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>
