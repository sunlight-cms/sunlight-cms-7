<?php

/**
 * [ADMIN] Plugin common API - ulozit konfiguraci
 * @param string $plugin nazev pluginu
 * @param array $cfg nova konfigurace
 * @param string $fname nazev PHP souboru (bez pripony)
 * @return int|bool
 */
function _pluginSaveConfig($plugin, $cfg, $fname = 'config')
{
    return file_put_contents(_plugin_dir . $plugin . '/' . $fname . '.php', '<?php return ' . var_export($cfg, true) . ';');
}

/**
 * [ADMIN] Plugin common API - nacist konfiguraci
 * @param string $plugin nazev pluginu
 * @param string $fname nazev PHP souboru (bez pripony)
 * @return mixed
 */
function _pluginLoadConfig($plugin, $fname = 'config')
{
    return @include(_plugin_dir . $plugin . '/' . $fname . '.php');
}
