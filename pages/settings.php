<?php

$addon = rex_addon::get('github_installer');

$csrfToken = rex_csrf_token::factory('github_installer_settings');

// Einstellungen speichern
if (rex_post('formsubmit', 'string') === '1' && !$csrfToken->isValid()) {
    echo rex_view::error($addon->i18n('github_installer_csrf_invalid'));
} elseif (rex_post('formsubmit', 'string') === '1') {
    $config = rex_post('config', 'array');

    // GitHub Token: Feld bleibt beim Rendern leer (siehe unten), damit der Token nicht
    // im Klartext im Seiten-Quelltext steht. Leer gelassen = Token unverändert lassen;
    // nur bei explizitem "Token entfernen" oder neuer Eingabe wird der Wert geändert.
    if (!empty($config['github_token_clear'])) {
        $addon->setConfig('github_token', '');
    } elseif (($config['github_token'] ?? '') !== '') {
        $addon->setConfig('github_token', $config['github_token']);
    }

    // Cache Lifetime
    $cacheLifetime = (int) ($config['cache_lifetime'] ?? 3600);
    if ($cacheLifetime < 300) $cacheLifetime = 300; // Minimum 5 Minuten
    $addon->setConfig('cache_lifetime', $cacheLifetime);

    // Upload Repository Settings
    $addon->setConfig('upload_owner', trim($config['upload_owner'] ?? ''));
    $addon->setConfig('upload_repo', trim($config['upload_repo'] ?? ''));
    $addon->setConfig('upload_branch', trim($config['upload_branch'] ?? 'main'));
    $addon->setConfig('upload_author', trim($config['upload_author'] ?? ''));

    // Klassen-Ziel-Addon
    $addon->setConfig('class_install_addon', trim($config['class_install_addon'] ?? '') ?: 'project');

    echo rex_view::success($addon->i18n('github_installer_settings_saved'));
}

// Formular
$content = '';
$formElements = [];

// GitHub Token
// Der gespeicherte Token wird NICHT ins value-Attribut geschrieben (stünde sonst im
// Klartext im Seiten-Quelltext) - Platzhalter zeigt nur, ob bereits einer gesetzt ist.
$hasToken = '' !== $addon->getConfig('github_token', '');
$tokenPlaceholder = $hasToken
    ? $addon->i18n('github_installer_github_token_set_placeholder')
    : $addon->i18n('github_installer_github_token_placeholder');

$n = [];
$n['label'] = '<label for="github-token">' . $addon->i18n('github_installer_github_token') . '</label>';
$n['field'] = '<input type="password" id="github-token" name="config[github_token]" class="form-control"
                      autocomplete="off"
                      placeholder="' . rex_escape($tokenPlaceholder) . '"
                      value="">
               <p class="help-block">' . $addon->i18n('github_installer_github_token_help') . '</p>
               <p class="help-block"><small><strong>' . $addon->i18n('github_installer_github_token_create_info') . '</strong></small></p>'
             . ($hasToken ? '<div class="checkbox"><label><input type="checkbox" name="config[github_token_clear]" value="1"> ' . $addon->i18n('github_installer_github_token_clear') . '</label></div>' : '');
$formElements[] = $n;

// Cache Lifetime
$n = [];
$n['label'] = '<label for="cache-lifetime">' . $addon->i18n('github_installer_cache_lifetime') . '</label>';
$n['field'] = '<input type="number" id="cache-lifetime" name="config[cache_lifetime]" class="form-control" 
                      min="300" step="60" value="' . $addon->getConfig('cache_lifetime', 3600) . '">
               <p class="help-block">' . $addon->i18n('github_installer_cache_lifetime_help') . '</p>';
$formElements[] = $n;

// Upload Repository Settings
$n = [];
$n['header'] = '<h3>Upload-Einstellungen</h3>';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="upload-owner">' . $addon->i18n('github_installer_upload_owner') . '</label>';
$n['field'] = '<input type="text" id="upload-owner" name="config[upload_owner]" class="form-control" 
                      placeholder="Dein GitHub Username" 
                      value="' . rex_escape($addon->getConfig('upload_owner', '')) . '">
               <p class="help-block">' . $addon->i18n('github_installer_upload_owner_help') . '</p>';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="upload-repo">' . $addon->i18n('github_installer_upload_repo') . '</label>';
$n['field'] = '<input type="text" id="upload-repo" name="config[upload_repo]" class="form-control" 
                      placeholder="repository-name" 
                      value="' . rex_escape($addon->getConfig('upload_repo', '')) . '">
               <p class="help-block">' . $addon->i18n('github_installer_upload_repo_help') . '</p>';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="upload-branch">' . $addon->i18n('github_installer_upload_branch') . '</label>';
$n['field'] = '<input type="text" id="upload-branch" name="config[upload_branch]" class="form-control" 
                      value="' . rex_escape($addon->getConfig('upload_branch', 'main')) . '">
               <p class="help-block">' . $addon->i18n('github_installer_upload_branch_help') . '</p>';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="upload-author">' . $addon->i18n('github_installer_upload_author') . '</label>';
$n['field'] = '<input type="text" id="upload-author" name="config[upload_author]" class="form-control" 
                      placeholder="Dein Name" 
                      value="' . rex_escape($addon->getConfig('upload_author', '')) . '">
               <p class="help-block">' . $addon->i18n('github_installer_upload_author_help') . '</p>';
$formElements[] = $n;

// Klassen-Installation
$n = [];
$n['header'] = '<h3>' . $addon->i18n('github_installer_class_settings_header') . '</h3>';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="class-install-addon">' . $addon->i18n('github_installer_class_install_addon') . '</label>';
$n['field'] = '<input type="text" id="class-install-addon" name="config[class_install_addon]" class="form-control"
                      value="' . rex_escape($addon->getConfig('class_install_addon', 'project')) . '">
               <p class="help-block">' . $addon->i18n('github_installer_class_install_addon_help') . '</p>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/container.php');

// Submit Button
$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="save" value="' . $addon->i18n('github_installer_save_settings') . '">' . $addon->i18n('github_installer_save_settings') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');
$buttons = '<fieldset class="rex-form-action">' . $buttons . '</fieldset>';

// Formular zusammenbauen
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit');
$fragment->setVar('title', $addon->i18n('github_installer_settings_title'));
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$output = $fragment->parse('core/page/section.php');

$output = '<form action="' . rex_url::currentBackendPage() . '" method="post">
<input type="hidden" name="formsubmit" value="1" />
' . $csrfToken->getHiddenField() . $output . '
</form>';

echo $output;
