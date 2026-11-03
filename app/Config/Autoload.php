<?php

namespace Config;

use CodeIgniter\Config\AutoloadConfig;

/**
 * -------------------------------------------------------------------
 * AUTOLOADER CONFIGURATION
 * -------------------------------------------------------------------
 *
 * This file defines the namespaces and class maps so the Autoloader
 * can find the files as needed.
 *
 * NOTE: If you use an identical key in $psr4 or $classmap, then
 *       the values in this file will overwrite the framework's values.
 *
 * NOTE: This class is required prior to Autoloader instantiation,
 *       and does not extend BaseConfig.
 */
class Autoload extends AutoloadConfig
{
    /**
     * -------------------------------------------------------------------
     * Namespaces
     * -------------------------------------------------------------------
     * This maps the locations of any namespaces in your application to
     * their location on the file system. These are used by the autoloader
     * to locate files the first time they have been instantiated.
     *
     * The 'Config' (APPPATH . 'Config') and 'CodeIgniter' (SYSTEMPATH) are
     * already mapped for you.
     *
     * You may change the name of the 'App' namespace if you wish,
     * but this should be done prior to creating any namespaced classes,
     * else you will need to modify all of those classes for this to work.
     *
     * @var array<string, list<string>|string>
     */
    public $psr4 = [
        APP_NAMESPACE => APPPATH,
    ];

    /**
     * -------------------------------------------------------------------
     * Class Map
     * -------------------------------------------------------------------
     * The class map provides a map of class names and their exact
     * location on the drive. Classes loaded in this manner will have
     * slightly faster performance because they will not have to be
     * searched for within one or more directories as they would if they
     * were being autoloaded through a namespace.
     *
     * Prototype:
     *   $classmap = [
     *       'MyClass'   => '/path/to/class/file.php'
     *   ];
     *
     * @var array<string, string>
     */
    public $classmap = [];

    /**
     * -------------------------------------------------------------------
     * Files
     * -------------------------------------------------------------------
     * The files array provides a list of paths to __non-class__ files
     * that will be autoloaded. This can be useful for bootstrap operations
     * or for loading functions.
     *
     * Prototype:
     *   $files = [
     *       '/path/to/my/file.php',
     *   ];
     *
     * @var list<string>
     */
    public $files = [];

    /**
     * -------------------------------------------------------------------
     * Helpers
     * -------------------------------------------------------------------
     * Prototype:
     *   $helpers = [
     *       'form',
     *   ];
     *
     * @var list<string>
     */
    public $helpers = [];

    public function __construct()
    {
        // Auto-registro de componentes ACTIVOS (arquitectura modular tipo plugin):
        // cada carpeta en components/ con un Manifest.php se registra como el
        // namespace PSR-4 "Components\<Nombre>", y CI4 auto-descubre sus
        // Config/Routes.php, Config/Services.php, Config/Events.php y filtros.
        // El estado activo/inactivo lo gestiona el panel de componentes en
        // writable/components.json; una carpeta SIN registro se considera ACTIVA
        // (drop-in: soltar la carpeta la habilita).
        $root      = defined('ROOTPATH') ? ROOTPATH : __DIR__ . '/../../';
        $dir       = $root . 'components';
        $stateFile = (defined('WRITEPATH') ? WRITEPATH : $root . 'writable' . DIRECTORY_SEPARATOR) . 'components.json';
        $state     = is_file($stateFile) ? (json_decode((string) file_get_contents($stateFile), true) ?: []) : [];

        if (is_dir($dir)) {
            foreach (scandir($dir) as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $entry;
                if (!is_dir($path) || !is_file($path . DIRECTORY_SEPARATOR . 'Manifest.php')) {
                    continue;
                }
                $active = !isset($state[$entry]) || ($state[$entry]['active'] ?? true);
                if ($active) {
                    $this->psr4['Components\\' . $entry] = $path;
                }
            }
        }

        parent::__construct();
    }
}
