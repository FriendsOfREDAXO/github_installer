<?php

/**
 * Stuff AddOn - GitHub Integration.
 *
 * @author Friends Of REDAXO
 */

$addon = rex_addon::get('github_installer');

echo rex_view::title($addon->i18n('title'));

rex_be_controller::includeCurrentPageSubPath();
