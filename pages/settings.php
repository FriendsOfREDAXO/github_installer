<?php

$addon = rex_addon::get('github_installer');


// Einstellungen speichern
if (rex_post('formsubmit', 'string') === '1') {
    $config = rex_post('config', 'array');
    
    // GitHub Token
    $addon->setConfig('github_token', $config['github_token'] ?? '');
    
    // Cache Lifetime
    $cacheLifetime = (int) ($config['cache_lifetime'] ?? 3600);
    if ($cacheLifetime < 300) $cacheLifetime = 300; // Minimum 5 Minuten
    $addon->setConfig('cache_lifetime', $cacheLifetime);
    
    // Upload Repository Settings
    $addon->setConfig('upload_owner', trim($config['upload_owner'] ?? ''));
    $addon->setConfig('upload_repo', trim($config['upload_repo'] ?? ''));
    $addon->setConfig('upload_branch', trim($config['upload_branch'] ?? 'main'));
    $addon->setConfig('upload_author', trim($config['upload_author'] ?? ''));
    
    echo rex_view::success($addon->i18n('settings_saved'));
}

// Formular
$content = '';
$formElements = [];

// GitHub Token
$n = [];
$n['label'] = '<label for="github-token">' . $addon->i18n('github_token') . '</label>';
$n['field'] = '<input type="password" id="github-token" name="config[github_token]" class="form-control" 
                      placeholder="' . $addon->i18n('github_token_placeholder') . '" 
                      value="' . rex_escape($addon->getConfig('github_token', '')) . '">
               <p class="help-block">' . $addon->i18n('github_token_help') . '</p>
               <p class="help-block"><small><strong>' . $addon->i18n('github_token_create_info') . '</strong></small></p>';
$formElements[] = $n;

// Cache Lifetime
$n = [];
$n['label'] = '<label for="cache-lifetime">' . $addon->i18n('cache_lifetime') . '</label>';
$n['field'] = '<input type="number" id="cache-lifetime" name="config[cache_lifetime]" class="form-control" 
                      min="300" step="60" value="' . $addon->getConfig('cache_lifetime', 3600) . '">
               <p class="help-block">' . $addon->i18n('cache_lifetime_help') . '</p>';
$formElements[] = $n;

// Upload Repository Settings
$n = [];
$n['header'] = '<h3>Upload-Einstellungen</h3>';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="upload-owner">' . $addon->i18n('upload_owner') . '</label>';
$n['field'] = '<input type="text" id="upload-owner" name="config[upload_owner]" class="form-control" 
                      placeholder="Dein GitHub Username" 
                      value="' . rex_escape($addon->getConfig('upload_owner', '')) . '">
               <p class="help-block">' . $addon->i18n('upload_owner_help') . '</p>';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="upload-repo">' . $addon->i18n('upload_repo') . '</label>';
$n['field'] = '<input type="text" id="upload-repo" name="config[upload_repo]" class="form-control" 
                      placeholder="repository-name" 
                      value="' . rex_escape($addon->getConfig('upload_repo', '')) . '">
               <p class="help-block">' . $addon->i18n('upload_repo_help') . '</p>';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="upload-branch">' . $addon->i18n('upload_branch') . '</label>';
$n['field'] = '<input type="text" id="upload-branch" name="config[upload_branch]" class="form-control" 
                      value="' . rex_escape($addon->getConfig('upload_branch', 'main')) . '">
               <p class="help-block">' . $addon->i18n('upload_branch_help') . '</p>';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="upload-author">' . $addon->i18n('upload_author') . '</label>';
$n['field'] = '<input type="text" id="upload-author" name="config[upload_author]" class="form-control" 
                      placeholder="Dein Name" 
                      value="' . rex_escape($addon->getConfig('upload_author', '')) . '">
               <p class="help-block">' . $addon->i18n('upload_author_help') . '</p>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/container.php');

// Submit Button
$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="save" value="' . $addon->i18n('save_settings') . '">' . $addon->i18n('save_settings') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');
$buttons = '<fieldset class="rex-form-action">' . $buttons . '</fieldset>';

// Formular zusammenbauen
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit');
$fragment->setVar('title', $addon->i18n('settings_title'));
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$output = $fragment->parse('core/page/section.php');

$output = '<form action="' . rex_url::currentBackendPage() . '" method="post">
<input type="hidden" name="formsubmit" value="1" />
' . $output . '
</form>';

echo $output;
