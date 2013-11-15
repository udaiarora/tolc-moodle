<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Defines classes used for plugins management
 *
 * This library provides a unified interface to various plugin types in
 * Moodle. It is mainly used by the plugins management admin page and the
 * plugins check page during the upgrade.
 *
 * @package    core
 * @subpackage admin
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Singleton class providing general plugins management functionality
 */
class plugin_manager {

    /** the plugin is shipped with standard Moodle distribution */
    const PLUGIN_SOURCE_STANDARD    = 'std';
    /** the plugin is added extension */
    const PLUGIN_SOURCE_EXTENSION   = 'ext';

    /** the plugin uses neither database nor capabilities, no versions */
    const PLUGIN_STATUS_NODB        = 'nodb';
    /** the plugin is up-to-date */
    const PLUGIN_STATUS_UPTODATE    = 'uptodate';
    /** the plugin is about to be installed */
    const PLUGIN_STATUS_NEW         = 'new';
    /** the plugin is about to be upgraded */
    const PLUGIN_STATUS_UPGRADE     = 'upgrade';
    /** the standard plugin is about to be deleted */
    const PLUGIN_STATUS_DELETE     = 'delete';
    /** the version at the disk is lower than the one already installed */
    const PLUGIN_STATUS_DOWNGRADE   = 'downgrade';
    /** the plugin is installed but missing from disk */
    const PLUGIN_STATUS_MISSING     = 'missing';

    /** @var plugin_manager holds the singleton instance */
    protected static $singletoninstance;
    /** @var array of raw plugins information */
    protected $pluginsinfo = null;
    /** @var array of raw subplugins information */
    protected $subpluginsinfo = null;
    /** @var array list of installed plugins $name=>$version */
    protected $installedplugins = null;
    /** @var array list of all enabled plugins $name=>$name */
    protected $enabledplugins = null;
    /** @var array list of all enabled plugins $name=>$diskversion */
    protected $presentplugins = null;

    /**
     * Direct initiation not allowed, use the factory method {@link self::instance()}
     */
    protected function __construct() {
    }

    /**
     * Sorry, this is singleton
     */
    protected function __clone() {
    }

    /**
     * Factory method for this class
     *
     * @return plugin_manager the singleton instance
     */
    public static function instance() {
        if (is_null(self::$singletoninstance)) {
            self::$singletoninstance = new self();
        }
        return self::$singletoninstance;
    }

    /**
     * Reset all caches.
     * @param bool $phpunitreset
     */
    public static function reset_caches($phpunitreset = false) {
        if ($phpunitreset) {
            self::$singletoninstance = null;
        } else {
            if (self::$singletoninstance) {
                self::$singletoninstance->pluginsinfo = null;
                self::$singletoninstance->subpluginsinfo = null;
                self::$singletoninstance->installedplugins = null;
                self::$singletoninstance->enabledplugins = null;
                self::$singletoninstance->presentplugins = null;
            }
        }
        $cache = cache::make('core', 'plugin_manager');
        $cache->purge();
    }

    /**
     * Returns the result of {@link core_component::get_plugin_types()} ordered for humans
     *
     * @see self::reorder_plugin_types()
     * @return array (string)name => (string)location
     */
    public function get_plugin_types() {
        if (func_num_args() > 0) {
            if (!func_get_arg(0)) {
                throw coding_exception('plugin_manager->get_plugin_types() does not support relative paths.');
            }
        }
        return $this->reorder_plugin_types(core_component::get_plugin_types());
    }

    /**
     * Load list of installed plugins,
     * always call before using $this->installedplugins.
     *
     * This method is caching results for all plugins.
     */
    protected function load_installed_plugins() {
        global $DB, $CFG;

        if ($this->installedplugins) {
            return;
        }

        if (empty($CFG->version)) {
            // Nothing installed yet.
            $this->installedplugins = array();
            return;
        }

        $cache = cache::make('core', 'plugin_manager');
        $installed = $cache->get('installed');

        if (is_array($installed)) {
            $this->installedplugins = $installed;
            return;
        }

        $this->installedplugins = array();

        if ($CFG->version < 2013092001.02) {
            // We did not upgrade the database yet.
            $modules = $DB->get_records('modules', array(), 'name ASC', 'id, name, version');
            foreach ($modules as $module) {
                $this->installedplugins['mod'][$module->name] = $module->version;
            }
            $blocks = $DB->get_records('block', array(), 'name ASC', 'id, name, version');
            foreach ($blocks as $block) {
                $this->installedplugins['block'][$block->name] = $block->version;
            }
        }

        $versions = $DB->get_records('config_plugins', array('name'=>'version'));
        foreach ($versions as $version) {
            $parts = explode('_', $version->plugin, 2);
            if (!isset($parts[1])) {
                // Invalid component, there must be at least one "_".
                continue;
            }
            // Do not verify here if plugin type and name are valid.
            $this->installedplugins[$parts[0]][$parts[1]] = $version->value;
        }

        foreach ($this->installedplugins as $key => $value) {
            ksort($this->installedplugins[$key]);
        }

        $cache->set('installed', $this->installedplugins);
    }

    /**
     * Return list of installed plugins of given type.
     * @param string $type
     * @return array $name=>$version
     */
    public function get_installed_plugins($type) {
        $this->load_installed_plugins();
        if (isset($this->installedplugins[$type])) {
            return $this->installedplugins[$type];
        }
        return array();
    }

    /**
     * Load list of all enabled plugins,
     * call before using $this->enabledplugins.
     *
     * This method is caching results from individual plugin info classes.
     */
    protected function load_enabled_plugins() {
        global $CFG;

        if ($this->enabledplugins) {
            return;
        }

        if (empty($CFG->version)) {
            $this->enabledplugins = array();
            return;
        }

        $cache = cache::make('core', 'plugin_manager');
        $enabled = $cache->get('enabled');

        if (is_array($enabled)) {
            $this->enabledplugins = $enabled;
            return;
        }

        $this->enabledplugins = array();

        require_once($CFG->libdir.'/adminlib.php');

        $plugintypes = core_component::get_plugin_types();
        foreach ($plugintypes as $plugintype => $fulldir) {
            // Hack: include mod and editor subplugin management classes first,
            //       the adminlib.php is supposed to contain extra admin settings too.
            $plugininfoclass = 'plugininfo_' . $plugintype;
            if (!class_exists($plugininfoclass) and file_exists("$fulldir/adminlib.php")) {
                include_once("$fulldir/adminlib.php");
            }
            if (class_exists($plugininfoclass)) {
                $enabled = $plugininfoclass::get_enabled_plugins();
                if (!is_array($enabled)) {
                    continue;
                }
                $this->enabledplugins[$plugintype] = $enabled;
            }
        }

        $cache->set('enabled', $this->enabledplugins);
    }

    /**
     * Get list of enabled plugins of given type,
     * the result may contain missing plugins.
     *
     * @param string $type
     * @return array|null  list of enabled plugins of this type, null if unknown
     */
    public function get_enabled_plugins($type) {
        $this->load_enabled_plugins();
        if (isset($this->enabledplugins[$type])) {
            return $this->enabledplugins[$type];
        }
        return null;
    }

    /**
     * Load list of all present plugins - call before using $this->presentplugins.
     */
    protected function load_present_plugins() {
        if ($this->presentplugins) {
            return;
        }

        $cache = cache::make('core', 'plugin_manager');
        $present = $cache->get('present');

        if (is_array($present)) {
            $this->presentplugins = $present;
            return;
        }

        $this->presentplugins = array();

        $plugintypes = core_component::get_plugin_types();
        foreach ($plugintypes as $type => $typedir) {
            $plugs = core_component::get_plugin_list($type);
            foreach ($plugs as $plug => $fullplug) {
                $plugin = new stdClass();
                $plugin->version = null;
                $module = $plugin;
                @include($fullplug.'/version.php');
                $this->presentplugins[$type][$plug] = $plugin;
            }
        }

        $cache->set('present', $this->presentplugins);
    }

    /**
     * Get list of present plugins of given type.
     *
     * @param string $type
     * @return array|null  list of presnet plugins $name=>$diskversion, null if unknown
     */
    public function get_present_plugins($type) {
        $this->load_present_plugins();
        if (isset($this->presentplugins[$type])) {
            return $this->presentplugins[$type];
        }
        return null;
    }

    /**
     * Returns a tree of known plugins and information about them
     *
     * @return array 2D array. The first keys are plugin type names (e.g. qtype);
     *      the second keys are the plugin local name (e.g. multichoice); and
     *      the values are the corresponding objects extending {@link plugininfo_base}
     */
    public function get_plugins() {
        global $CFG;

        if (is_array($this->pluginsinfo)) {
            return $this->pluginsinfo;
        }

        $this->pluginsinfo = array();

        // Hack: include mod and editor subplugin management classes first,
        //       the adminlib.php is supposed to contain extra admin settings too.
        require_once($CFG->libdir.'/adminlib.php');
        foreach (core_component::get_plugin_types_with_subplugins() as $type => $ignored) {
            foreach (core_component::get_plugin_list($type) as $dir) {
                if (file_exists("$dir/adminlib.php")) {
                    include_once("$dir/adminlib.php");
                }
            }
        }
        $plugintypes = $this->get_plugin_types();
        foreach ($plugintypes as $plugintype => $plugintyperootdir) {
            if (in_array($plugintype, array('base', 'general'))) {
                throw new coding_exception('Illegal usage of reserved word for plugin type');
            }
            if (class_exists('plugininfo_' . $plugintype)) {
                $plugintypeclass = 'plugininfo_' . $plugintype;
            } else {
                $plugintypeclass = 'plugininfo_general';
            }
            if (!in_array('plugininfo_base', class_parents($plugintypeclass))) {
                throw new coding_exception('Class ' . $plugintypeclass . ' must extend plugininfo_base');
            }
            $plugins = $plugintypeclass::get_plugins($plugintype, $plugintyperootdir, $plugintypeclass);
            $this->pluginsinfo[$plugintype] = $plugins;
        }

        if (empty($CFG->disableupdatenotifications) and !during_initial_install()) {
            // append the information about available updates provided by {@link available_update_checker()}
            $provider = available_update_checker::instance();
            foreach ($this->pluginsinfo as $plugintype => $plugins) {
                foreach ($plugins as $plugininfoholder) {
                    $plugininfoholder->check_available_updates($provider);
                }
            }
        }

        return $this->pluginsinfo;
    }

    /**
     * Returns list of known plugins of the given type.
     *
     * This method returns the subset of the tree returned by {@link self::get_plugins()}.
     * If the given type is not known, empty array is returned.
     *
     * @param string $type plugin type, e.g. 'mod' or 'workshopallocation'
     * @return array (string)plugin name (e.g. 'workshop') => corresponding subclass of {@link plugininfo_base}
     */
    public function get_plugins_of_type($type) {

        $plugins = $this->get_plugins();

        if (!isset($plugins[$type])) {
            return array();
        }

        return $plugins[$type];
    }

    /**
     * Returns list of all known subplugins of the given plugin.
     *
     * For plugins that do not provide subplugins (i.e. there is no support for it),
     * empty array is returned.
     *
     * @param string $component full component name, e.g. 'mod_workshop'
     * @return array (string) component name (e.g. 'workshopallocation_random') => subclass of {@link plugininfo_base}
     */
    public function get_subplugins_of_plugin($component) {

        $pluginfo = $this->get_plugin_info($component);

        if (is_null($pluginfo)) {
            return array();
        }

        $subplugins = $this->get_subplugins();

        if (!isset($subplugins[$pluginfo->component])) {
            return array();
        }

        $list = array();

        foreach ($subplugins[$pluginfo->component] as $subdata) {
            foreach ($this->get_plugins_of_type($subdata->type) as $subpluginfo) {
                $list[$subpluginfo->component] = $subpluginfo;
            }
        }

        return $list;
    }

    /**
     * Returns list of plugins that define their subplugins and the information
     * about them from the db/subplugins.php file.
     *
     * @return array with keys like 'mod_quiz', and values the data from the
     *      corresponding db/subplugins.php file.
     */
    public function get_subplugins() {

        if (is_array($this->subpluginsinfo)) {
            return $this->subpluginsinfo;
        }

        $this->subpluginsinfo = array();
        foreach (core_component::get_plugin_types_with_subplugins() as $type => $ignored) {
            foreach (core_component::get_plugin_list($type) as $component => $ownerdir) {
                $componentsubplugins = array();
                if (file_exists($ownerdir . '/db/subplugins.php')) {
                    $subplugins = array();
                    include($ownerdir . '/db/subplugins.php');
                    foreach ($subplugins as $subplugintype => $subplugintyperootdir) {
                        $subplugin = new stdClass();
                        $subplugin->type = $subplugintype;
                        $subplugin->typerootdir = $subplugintyperootdir;
                        $componentsubplugins[$subplugintype] = $subplugin;
                    }
                    $this->subpluginsinfo[$type . '_' . $component] = $componentsubplugins;
                }
            }
        }

        return $this->subpluginsinfo;
    }

    /**
     * Returns the name of the plugin that defines the given subplugin type
     *
     * If the given subplugin type is not actually a subplugin, returns false.
     *
     * @param string $subplugintype the name of subplugin type, eg. workshopform or quiz
     * @return false|string the name of the parent plugin, eg. mod_workshop
     */
    public function get_parent_of_subplugin($subplugintype) {

        $parent = false;
        foreach ($this->get_subplugins() as $pluginname => $subplugintypes) {
            if (isset($subplugintypes[$subplugintype])) {
                $parent = $pluginname;
                break;
            }
        }

        return $parent;
    }

    /**
     * Returns a localized name of a given plugin
     *
     * @param string $component name of the plugin, eg mod_workshop or auth_ldap
     * @return string
     */
    public function plugin_name($component) {

        $pluginfo = $this->get_plugin_info($component);

        if (is_null($pluginfo)) {
            throw new moodle_exception('err_unknown_plugin', 'core_plugin', '', array('plugin' => $component));
        }

        return $pluginfo->displayname;
    }

    /**
     * Returns a localized name of a plugin typed in singular form
     *
     * Most plugin types define their names in core_plugin lang file. In case of subplugins,
     * we try to ask the parent plugin for the name. In the worst case, we will return
     * the value of the passed $type parameter.
     *
     * @param string $type the type of the plugin, e.g. mod or workshopform
     * @return string
     */
    public function plugintype_name($type) {

        if (get_string_manager()->string_exists('type_' . $type, 'core_plugin')) {
            // for most plugin types, their names are defined in core_plugin lang file
            return get_string('type_' . $type, 'core_plugin');

        } else if ($parent = $this->get_parent_of_subplugin($type)) {
            // if this is a subplugin, try to ask the parent plugin for the name
            if (get_string_manager()->string_exists('subplugintype_' . $type, $parent)) {
                return $this->plugin_name($parent) . ' / ' . get_string('subplugintype_' . $type, $parent);
            } else {
                return $this->plugin_name($parent) . ' / ' . $type;
            }

        } else {
            return $type;
        }
    }

    /**
     * Returns a localized name of a plugin type in plural form
     *
     * Most plugin types define their names in core_plugin lang file. In case of subplugins,
     * we try to ask the parent plugin for the name. In the worst case, we will return
     * the value of the passed $type parameter.
     *
     * @param string $type the type of the plugin, e.g. mod or workshopform
     * @return string
     */
    public function plugintype_name_plural($type) {

        if (get_string_manager()->string_exists('type_' . $type . '_plural', 'core_plugin')) {
            // for most plugin types, their names are defined in core_plugin lang file
            return get_string('type_' . $type . '_plural', 'core_plugin');

        } else if ($parent = $this->get_parent_of_subplugin($type)) {
            // if this is a subplugin, try to ask the parent plugin for the name
            if (get_string_manager()->string_exists('subplugintype_' . $type . '_plural', $parent)) {
                return $this->plugin_name($parent) . ' / ' . get_string('subplugintype_' . $type . '_plural', $parent);
            } else {
                return $this->plugin_name($parent) . ' / ' . $type;
            }

        } else {
            return $type;
        }
    }

    /**
     * Returns information about the known plugin, or null
     *
     * @param string $component frankenstyle component name.
     * @return plugininfo_base|null the corresponding plugin information.
     */
    public function get_plugin_info($component) {
        list($type, $name) = core_component::normalize_component($component);
        $plugins = $this->get_plugins();
        if (isset($plugins[$type][$name])) {
            return $plugins[$type][$name];
        } else {
            return null;
        }
    }

    /**
     * Check to see if the current version of the plugin seems to be a checkout of an external repository.
     *
     * @see available_update_deployer::plugin_external_source()
     * @param string $component frankenstyle component name
     * @return false|string
     */
    public function plugin_external_source($component) {

        $plugininfo = $this->get_plugin_info($component);

        if (is_null($plugininfo)) {
            return false;
        }

        $pluginroot = $plugininfo->rootdir;

        if (is_dir($pluginroot.'/.git')) {
            return 'git';
        }

        if (is_dir($pluginroot.'/CVS')) {
            return 'cvs';
        }

        if (is_dir($pluginroot.'/.svn')) {
            return 'svn';
        }

        return false;
    }

    /**
     * Get a list of any other plugins that require this one.
     * @param string $component frankenstyle component name.
     * @return array of frankensyle component names that require this one.
     */
    public function other_plugins_that_require($component) {
        $others = array();
        foreach ($this->get_plugins() as $type => $plugins) {
            foreach ($plugins as $plugin) {
                $required = $plugin->get_other_required_plugins();
                if (isset($required[$component])) {
                    $others[] = $plugin->component;
                }
            }
        }
        return $others;
    }

    /**
     * Check a dependencies list against the list of installed plugins.
     * @param array $dependencies compenent name to required version or ANY_VERSION.
     * @return bool true if all the dependencies are satisfied.
     */
    public function are_dependencies_satisfied($dependencies) {
        foreach ($dependencies as $component => $requiredversion) {
            $otherplugin = $this->get_plugin_info($component);
            if (is_null($otherplugin)) {
                return false;
            }

            if ($requiredversion != ANY_VERSION and $otherplugin->versiondisk < $requiredversion) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks all dependencies for all installed plugins
     *
     * This is used by install and upgrade. The array passed by reference as the second
     * argument is populated with the list of plugins that have failed dependencies (note that
     * a single plugin can appear multiple times in the $failedplugins).
     *
     * @param int $moodleversion the version from version.php.
     * @param array $failedplugins to return the list of plugins with non-satisfied dependencies
     * @return bool true if all the dependencies are satisfied for all plugins.
     */
    public function all_plugins_ok($moodleversion, &$failedplugins = array()) {

        $return = true;
        foreach ($this->get_plugins() as $type => $plugins) {
            foreach ($plugins as $plugin) {

                if (!$plugin->is_core_dependency_satisfied($moodleversion)) {
                    $return = false;
                    $failedplugins[] = $plugin->component;
                }

                if (!$this->are_dependencies_satisfied($plugin->get_other_required_plugins())) {
                    $return = false;
                    $failedplugins[] = $plugin->component;
                }
            }
        }

        return $return;
    }

    /**
     * Is it possible to uninstall the given plugin?
     *
     * False is returned if the plugininfo subclass declares the uninstall should
     * not be allowed via {@link plugininfo_base::is_uninstall_allowed()} or if the
     * core vetoes it (e.g. becase the plugin or some of its subplugins is required
     * by some other installed plugin).
     *
     * @param string $component full frankenstyle name, e.g. mod_foobar
     * @return bool
     */
    public function can_uninstall_plugin($component) {

        $pluginfo = $this->get_plugin_info($component);

        if (is_null($pluginfo)) {
            return false;
        }

        if (!$this->common_uninstall_check($pluginfo)) {
            return false;
        }

        // If it has subplugins, check they can be uninstalled too.
        $subplugins = $this->get_subplugins_of_plugin($pluginfo->component);
        foreach ($subplugins as $subpluginfo) {
            if (!$this->common_uninstall_check($subpluginfo)) {
                return false;
            }
            // Check if there are some other plugins requiring this subplugin
            // (but the parent and siblings).
            foreach ($this->other_plugins_that_require($subpluginfo->component) as $requiresme) {
                $ismyparent = ($pluginfo->component === $requiresme);
                $ismysibling = in_array($requiresme, array_keys($subplugins));
                if (!$ismyparent and !$ismysibling) {
                    return false;
                }
            }
        }

        // Check if there are some other plugins requiring this plugin
        // (but its subplugins).
        foreach ($this->other_plugins_that_require($pluginfo->component) as $requiresme) {
            $ismysubplugin = in_array($requiresme, array_keys($subplugins));
            if (!$ismysubplugin) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns uninstall URL if exists.
     *
     * @param string $component
     * @return moodle_url uninstall URL, null if uninstall not supported
     */
    public function get_uninstall_url($component) {
        if (!$this->can_uninstall_plugin($component)) {
            return null;
        }

        $pluginfo = $this->get_plugin_info($component);

        if (is_null($pluginfo)) {
            return null;
        }

        return $pluginfo->get_uninstall_url();
    }

    /**
     * Uninstall the given plugin.
     *
     * Automatically cleans-up all remaining configuration data, log records, events,
     * files from the file pool etc.
     *
     * In the future, the functionality of {@link uninstall_plugin()} function may be moved
     * into this method and all the code should be refactored to use it. At the moment, we
     * mimic this future behaviour by wrapping that function call.
     *
     * @param string $component
     * @param progress_trace $progress traces the process
     * @return bool true on success, false on errors/problems
     */
    public function uninstall_plugin($component, progress_trace $progress) {

        $pluginfo = $this->get_plugin_info($component);

        if (is_null($pluginfo)) {
            return false;
        }

        // Give the pluginfo class a chance to execute some steps.
        $result = $pluginfo->uninstall($progress);
        if (!$result) {
            return false;
        }

        // Call the legacy core function to uninstall the plugin.
        ob_start();
        uninstall_plugin($pluginfo->type, $pluginfo->name);
        $progress->output(ob_get_clean());

        return true;
    }

    /**
     * Checks if there are some plugins with a known available update
     *
     * @return bool true if there is at least one available update
     */
    public function some_plugins_updatable() {
        foreach ($this->get_plugins() as $type => $plugins) {
            foreach ($plugins as $plugin) {
                if ($plugin->available_updates()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check to see if the given plugin folder can be removed by the web server process.
     *
     * @param string $component full frankenstyle component
     * @return bool
     */
    public function is_plugin_folder_removable($component) {

        $pluginfo = $this->get_plugin_info($component);

        if (is_null($pluginfo)) {
            return false;
        }

        // To be able to remove the plugin folder, its parent must be writable, too.
        if (!is_writable(dirname($pluginfo->rootdir))) {
            return false;
        }

        // Check that the folder and all its content is writable (thence removable).
        return $this->is_directory_removable($pluginfo->rootdir);
    }

    /**
     * Defines a list of all plugins that were originally shipped in the standard Moodle distribution,
     * but are not anymore and are deleted during upgrades.
     *
     * The main purpose of this list is to hide missing plugins during upgrade.
     *
     * @param string $type plugin type
     * @param string $name plugin name
     * @return bool
     */
    public static function is_deleted_standard_plugin($type, $name) {

        // Example of the array structure:
        // $plugins = array(
        //     'block' => array('admin', 'admin_tree'),
        //     'mod' => array('assignment'),
        // );
        // Do not include plugins that were removed during upgrades to versions that are
        // not supported as source versions for upgrade any more. For example, at MOODLE_23_STABLE
        // branch, listed should be no plugins that were removed at 1.9.x - 2.1.x versions as
        // Moodle 2.3 supports upgrades from 2.2.x only.
        $plugins = array(
            'qformat' => array('blackboard'),
            'enrol' => array('authorize'),
            'tool' => array('bloglevelupgrade'),
        );

        if (!isset($plugins[$type])) {
            return false;
        }
        return in_array($name, $plugins[$type]);
    }

    /**
     * Defines a white list of all plugins shipped in the standard Moodle distribution
     *
     * @param string $type
     * @return false|array array of standard plugins or false if the type is unknown
     */
    public static function standard_plugins_list($type) {

        $standard_plugins = array(

            'assignment' => array(
                'offline', 'online', 'upload', 'uploadsingle'
            ),

            'assignsubmission' => array(
                'comments', 'file', 'onlinetext'
            ),

            'assignfeedback' => array(
                'comments', 'file', 'offline'
            ),

            'atto' => array(
                'bold', 'clear', 'html', 'image', 'indent', 'italic', 'link',
                'media', 'orderedlist', 'outdent', 'strike', 'title',
                'underline', 'unlink', 'unorderedlist'
            ),

            'auth' => array(
                'cas', 'db', 'email', 'fc', 'imap', 'ldap', 'manual', 'mnet',
                'nntp', 'nologin', 'none', 'pam', 'pop3', 'radius',
                'shibboleth', 'webservice'
            ),

            'block' => array(
                'activity_modules', 'admin_bookmarks', 'badges', 'blog_menu',
                'blog_recent', 'blog_tags', 'calendar_month',
                'calendar_upcoming', 'comments', 'community',
                'completionstatus', 'course_list', 'course_overview',
                'course_summary', 'feedback', 'glossary_random', 'html',
                'login', 'mentees', 'messages', 'mnet_hosts', 'myprofile',
                'navigation', 'news_items', 'online_users', 'participants',
                'private_files', 'quiz_results', 'recent_activity',
                'rss_client', 'search_forums', 'section_links',
                'selfcompletion', 'settings', 'site_main_menu',
                'social_activities', 'tag_flickr', 'tag_youtube', 'tags'
            ),

            'booktool' => array(
                'exportimscp', 'importhtml', 'print'
            ),

            'cachelock' => array(
                'file'
            ),

            'cachestore' => array(
                'file', 'memcache', 'memcached', 'mongodb', 'session', 'static'
            ),

            'calendartype' => array(
                'gregorian'
            ),

            'coursereport' => array(
                //deprecated!
            ),

            'datafield' => array(
                'checkbox', 'date', 'file', 'latlong', 'menu', 'multimenu',
                'number', 'picture', 'radiobutton', 'text', 'textarea', 'url'
            ),

            'datapreset' => array(
                'imagegallery'
            ),

            'editor' => array(
                'textarea', 'tinymce', 'atto'
            ),

            'enrol' => array(
                'category', 'cohort', 'database', 'flatfile',
                'guest', 'imsenterprise', 'ldap', 'manual', 'meta', 'mnet',
                'paypal', 'self'
            ),

            'filter' => array(
                'activitynames', 'algebra', 'censor', 'emailprotect',
                'emoticon', 'mediaplugin', 'multilang', 'tex', 'tidy',
                'urltolink', 'data', 'glossary'
            ),

            'format' => array(
                'singleactivity', 'social', 'topics', 'weeks'
            ),

            'gradeexport' => array(
                'ods', 'txt', 'xls', 'xml'
            ),

            'gradeimport' => array(
                'csv', 'xml'
            ),

            'gradereport' => array(
                'grader', 'outcomes', 'overview', 'user'
            ),

            'gradingform' => array(
                'rubric', 'guide'
            ),

            'local' => array(
            ),

            'message' => array(
                'email', 'jabber', 'popup'
            ),

            'mnetservice' => array(
                'enrol'
            ),

            'mod' => array(
                'assign', 'assignment', 'book', 'chat', 'choice', 'data', 'feedback', 'folder',
                'forum', 'glossary', 'imscp', 'label', 'lesson', 'lti', 'page',
                'quiz', 'resource', 'scorm', 'survey', 'url', 'wiki', 'workshop'
            ),

            'plagiarism' => array(
            ),

            'portfolio' => array(
                'boxnet', 'download', 'flickr', 'googledocs', 'mahara', 'picasa'
            ),

            'profilefield' => array(
                'checkbox', 'datetime', 'menu', 'text', 'textarea'
            ),

            'qbehaviour' => array(
                'adaptive', 'adaptivenopenalty', 'deferredcbm',
                'deferredfeedback', 'immediatecbm', 'immediatefeedback',
                'informationitem', 'interactive', 'interactivecountback',
                'manualgraded', 'missing'
            ),

            'qformat' => array(
                'aiken', 'blackboard_six', 'examview', 'gift',
                'learnwise', 'missingword', 'multianswer', 'webct',
                'xhtml', 'xml'
            ),

            'qtype' => array(
                'calculated', 'calculatedmulti', 'calculatedsimple',
                'description', 'essay', 'match', 'missingtype', 'multianswer',
                'multichoice', 'numerical', 'random', 'randomsamatch',
                'shortanswer', 'truefalse'
            ),

            'quiz' => array(
                'grading', 'overview', 'responses', 'statistics'
            ),

            'quizaccess' => array(
                'delaybetweenattempts', 'ipaddress', 'numattempts', 'openclosedate',
                'password', 'safebrowser', 'securewindow', 'timelimit'
            ),

            'report' => array(
                'backups', 'completion', 'configlog', 'courseoverview',
                'log', 'loglive', 'outline', 'participation', 'progress', 'questioninstances', 'security', 'stats', 'performance'
            ),

            'repository' => array(
                'alfresco', 'areafiles', 'boxnet', 'coursefiles', 'dropbox', 'equella', 'filesystem',
                'flickr', 'flickr_public', 'googledocs', 'local', 'merlot',
                'picasa', 'recent', 'skydrive', 's3', 'upload', 'url', 'user', 'webdav',
                'wikimedia', 'youtube'
            ),

            'scormreport' => array(
                'basic',
                'interactions',
                'graphs',
                'objectives'
            ),

            'tinymce' => array(
                'ctrlhelp', 'dragmath', 'managefiles', 'moodleemoticon', 'moodleimage',
                'moodlemedia', 'moodlenolink', 'pdw', 'spellchecker', 'wrap'
            ),

            'theme' => array(
                'afterburner', 'anomaly', 'arialist', 'base', 'binarius', 'bootstrapbase',
                'boxxie', 'brick', 'canvas', 'clean', 'formal_white', 'formfactor',
                'fusion', 'leatherbound', 'magazine', 'mymobile', 'nimble',
                'nonzero', 'overlay', 'serenity', 'sky_high', 'splash',
                'standard', 'standardold'
            ),

            'tool' => array(
                'assignmentupgrade', 'behat', 'capability', 'customlang',
                'dbtransfer', 'generator', 'health', 'innodb', 'installaddon',
                'langimport', 'multilangupgrade', 'phpunit', 'profiling',
                'qeupgradehelper', 'replace', 'spamcleaner', 'timezoneimport',
                'unittest', 'uploadcourse', 'uploaduser', 'unsuproles', 'xmldb'
            ),

            'webservice' => array(
                'amf', 'rest', 'soap', 'xmlrpc'
            ),

            'workshopallocation' => array(
                'manual', 'random', 'scheduled'
            ),

            'workshopeval' => array(
                'best'
            ),

            'workshopform' => array(
                'accumulative', 'comments', 'numerrors', 'rubric'
            )
        );

        if (isset($standard_plugins[$type])) {
            return $standard_plugins[$type];
        } else {
            return false;
        }
    }

    /**
     * Reorders plugin types into a sequence to be displayed
     *
     * For technical reasons, plugin types returned by {@link core_component::get_plugin_types()} are
     * in a certain order that does not need to fit the expected order for the display.
     * Particularly, activity modules should be displayed first as they represent the
     * real heart of Moodle. They should be followed by other plugin types that are
     * used to build the courses (as that is what one expects from LMS). After that,
     * other supportive plugin types follow.
     *
     * @param array $types associative array
     * @return array same array with altered order of items
     */
    protected function reorder_plugin_types(array $types) {
        $fix = array(
            'mod'        => $types['mod'],
            'block'      => $types['block'],
            'qtype'      => $types['qtype'],
            'qbehaviour' => $types['qbehaviour'],
            'qformat'    => $types['qformat'],
            'filter'     => $types['filter'],
            'enrol'      => $types['enrol'],
        );
        foreach ($types as $type => $path) {
            if (!isset($fix[$type])) {
                $fix[$type] = $path;
            }
        }
        return $fix;
    }

    /**
     * Check if the given directory can be removed by the web server process.
     *
     * This recursively checks that the given directory and all its contents
     * it writable.
     *
     * @param string $fullpath
     * @return boolean
     */
    protected function is_directory_removable($fullpath) {

        if (!is_writable($fullpath)) {
            return false;
        }

        if (is_dir($fullpath)) {
            $handle = opendir($fullpath);
        } else {
            return false;
        }

        $result = true;

        while ($filename = readdir($handle)) {

            if ($filename === '.' or $filename === '..') {
                continue;
            }

            $subfilepath = $fullpath.'/'.$filename;

            if (is_dir($subfilepath)) {
                $result = $result && $this->is_directory_removable($subfilepath);

            } else {
                $result = $result && is_writable($subfilepath);
            }
        }

        closedir($handle);

        return $result;
    }

    /**
     * Helper method that implements common uninstall prerequisities
     *
     * @param plugininfo_base $pluginfo
     * @return bool
     */
    protected function common_uninstall_check(plugininfo_base $pluginfo) {

        if (!$pluginfo->is_uninstall_allowed()) {
            // The plugin's plugininfo class declares it should not be uninstalled.
            return false;
        }

        if ($pluginfo->get_status() === plugin_manager::PLUGIN_STATUS_NEW) {
            // The plugin is not installed. It should be either installed or removed from the disk.
            // Relying on this temporary state may be tricky.
            return false;
        }

        if (is_null($pluginfo->get_uninstall_url())) {
            // Backwards compatibility.
            debugging('plugininfo_base subclasses should use is_uninstall_allowed() instead of returning null in get_uninstall_url()',
                DEBUG_DEVELOPER);
            return false;
        }

        return true;
    }
}


/**
 * General exception thrown by the {@link available_update_checker} class
 */
class available_update_checker_exception extends moodle_exception {

    /**
     * @param string $errorcode exception description identifier
     * @param mixed $debuginfo debugging data to display
     */
    public function __construct($errorcode, $debuginfo=null) {
        parent::__construct($errorcode, 'core_plugin', '', null, print_r($debuginfo, true));
    }
}


/**
 * Singleton class that handles checking for available updates
 */
class available_update_checker {

    /** @var available_update_checker holds the singleton instance */
    protected static $singletoninstance;
    /** @var null|int the timestamp of when the most recent response was fetched */
    protected $recentfetch = null;
    /** @var null|array the recent response from the update notification provider */
    protected $recentresponse = null;
    /** @var null|string the numerical version of the local Moodle code */
    protected $currentversion = null;
    /** @var null|string the release info of the local Moodle code */
    protected $currentrelease = null;
    /** @var null|string branch of the local Moodle code */
    protected $currentbranch = null;
    /** @var array of (string)frankestyle => (string)version list of additional plugins deployed at this site */
    protected $currentplugins = array();

    /**
     * Direct initiation not allowed, use the factory method {@link self::instance()}
     */
    protected function __construct() {
    }

    /**
     * Sorry, this is singleton
     */
    protected function __clone() {
    }

    /**
     * Factory method for this class
     *
     * @return available_update_checker the singleton instance
     */
    public static function instance() {
        if (is_null(self::$singletoninstance)) {
            self::$singletoninstance = new self();
        }
        return self::$singletoninstance;
    }

    /**
     * Reset any caches
     * @param bool $phpunitreset
     */
    public static function reset_caches($phpunitreset = false) {
        if ($phpunitreset) {
            self::$singletoninstance = null;
        }
    }

    /**
     * Returns the timestamp of the last execution of {@link fetch()}
     *
     * @return int|null null if it has never been executed or we don't known
     */
    public function get_last_timefetched() {

        $this->restore_response();

        if (!empty($this->recentfetch)) {
            return $this->recentfetch;

        } else {
            return null;
        }
    }

    /**
     * Fetches the available update status from the remote site
     *
     * @throws available_update_checker_exception
     */
    public function fetch() {
        $response = $this->get_response();
        $this->validate_response($response);
        $this->store_response($response);
    }

    /**
     * Returns the available update information for the given component
     *
     * This method returns null if the most recent response does not contain any information
     * about it. The returned structure is an array of available updates for the given
     * component. Each update info is an object with at least one property called
     * 'version'. Other possible properties are 'release', 'maturity', 'url' and 'downloadurl'.
     *
     * For the 'core' component, the method returns real updates only (those with higher version).
     * For all other components, the list of all known remote updates is returned and the caller
     * (usually the {@link plugin_manager}) is supposed to make the actual comparison of versions.
     *
     * @param string $component frankenstyle
     * @param array $options with supported keys 'minmaturity' and/or 'notifybuilds'
     * @return null|array null or array of available_update_info objects
     */
    public function get_update_info($component, array $options = array()) {

        if (!isset($options['minmaturity'])) {
            $options['minmaturity'] = 0;
        }

        if (!isset($options['notifybuilds'])) {
            $options['notifybuilds'] = false;
        }

        if ($component == 'core') {
            $this->load_current_environment();
        }

        $this->restore_response();

        if (empty($this->recentresponse['updates'][$component])) {
            return null;
        }

        $updates = array();
        foreach ($this->recentresponse['updates'][$component] as $info) {
            $update = new available_update_info($component, $info);
            if (isset($update->maturity) and ($update->maturity < $options['minmaturity'])) {
                continue;
            }
            if ($component == 'core') {
                if ($update->version <= $this->currentversion) {
                    continue;
                }
                if (empty($options['notifybuilds']) and $this->is_same_release($update->release)) {
                    continue;
                }
            }
            $updates[] = $update;
        }

        if (empty($updates)) {
            return null;
        }

        return $updates;
    }

    /**
     * The method being run via cron.php
     */
    public function cron() {
        global $CFG;

        if (!$this->cron_autocheck_enabled()) {
            $this->cron_mtrace('Automatic check for available updates not enabled, skipping.');
            return;
        }

        $now = $this->cron_current_timestamp();

        if ($this->cron_has_fresh_fetch($now)) {
            $this->cron_mtrace('Recently fetched info about available updates is still fresh enough, skipping.');
            return;
        }

        if ($this->cron_has_outdated_fetch($now)) {
            $this->cron_mtrace('Outdated or missing info about available updates, forced fetching ... ', '');
            $this->cron_execute();
            return;
        }

        $offset = $this->cron_execution_offset();
        $start = mktime(1, 0, 0, date('n', $now), date('j', $now), date('Y', $now)); // 01:00 AM today local time
        if ($now > $start + $offset) {
            $this->cron_mtrace('Regular daily check for available updates ... ', '');
            $this->cron_execute();
            return;
        }
    }

    /// end of public API //////////////////////////////////////////////////////

    /**
     * Makes cURL request to get data from the remote site
     *
     * @return string raw request result
     * @throws available_update_checker_exception
     */
    protected function get_response() {
        global $CFG;
        require_once($CFG->libdir.'/filelib.php');

        $curl = new curl(array('proxy' => true));
        $response = $curl->post($this->prepare_request_url(), $this->prepare_request_params(), $this->prepare_request_options());
        $curlerrno = $curl->get_errno();
        if (!empty($curlerrno)) {
            throw new available_update_checker_exception('err_response_curl', 'cURL error '.$curlerrno.': '.$curl->error);
        }
        $curlinfo = $curl->get_info();
        if ($curlinfo['http_code'] != 200) {
            throw new available_update_checker_exception('err_response_http_code', $curlinfo['http_code']);
        }
        return $response;
    }

    /**
     * Makes sure the response is valid, has correct API format etc.
     *
     * @param string $response raw response as returned by the {@link self::get_response()}
     * @throws available_update_checker_exception
     */
    protected function validate_response($response) {

        $response = $this->decode_response($response);

        if (empty($response)) {
            throw new available_update_checker_exception('err_response_empty');
        }

        if (empty($response['status']) or $response['status'] !== 'OK') {
            throw new available_update_checker_exception('err_response_status', $response['status']);
        }

        if (empty($response['apiver']) or $response['apiver'] !== '1.2') {
            throw new available_update_checker_exception('err_response_format_version', $response['apiver']);
        }

        if (empty($response['forbranch']) or $response['forbranch'] !== moodle_major_version(true)) {
            throw new available_update_checker_exception('err_response_target_version', $response['forbranch']);
        }
    }

    /**
     * Decodes the raw string response from the update notifications provider
     *
     * @param string $response as returned by {@link self::get_response()}
     * @return array decoded response structure
     */
    protected function decode_response($response) {
        return json_decode($response, true);
    }

    /**
     * Stores the valid fetched response for later usage
     *
     * This implementation uses the config_plugins table as the permanent storage.
     *
     * @param string $response raw valid data returned by {@link self::get_response()}
     */
    protected function store_response($response) {

        set_config('recentfetch', time(), 'core_plugin');
        set_config('recentresponse', $response, 'core_plugin');

        $this->restore_response(true);
    }

    /**
     * Loads the most recent raw response record we have fetched
     *
     * After this method is called, $this->recentresponse is set to an array. If the
     * array is empty, then either no data have been fetched yet or the fetched data
     * do not have expected format (and thence they are ignored and a debugging
     * message is displayed).
     *
     * This implementation uses the config_plugins table as the permanent storage.
     *
     * @param bool $forcereload reload even if it was already loaded
     */
    protected function restore_response($forcereload = false) {

        if (!$forcereload and !is_null($this->recentresponse)) {
            // we already have it, nothing to do
            return;
        }

        $config = get_config('core_plugin');

        if (!empty($config->recentresponse) and !empty($config->recentfetch)) {
            try {
                $this->validate_response($config->recentresponse);
                $this->recentfetch = $config->recentfetch;
                $this->recentresponse = $this->decode_response($config->recentresponse);
            } catch (available_update_checker_exception $e) {
                // The server response is not valid. Behave as if no data were fetched yet.
                // This may happen when the most recent update info (cached locally) has been
                // fetched with the previous branch of Moodle (like during an upgrade from 2.x
                // to 2.y) or when the API of the response has changed.
                $this->recentresponse = array();
            }

        } else {
            $this->recentresponse = array();
        }
    }

    /**
     * Compares two raw {@link $recentresponse} records and returns the list of changed updates
     *
     * This method is used to populate potential update info to be sent to site admins.
     *
     * @param array $old
     * @param array $new
     * @throws available_update_checker_exception
     * @return array parts of $new['updates'] that have changed
     */
    protected function compare_responses(array $old, array $new) {

        if (empty($new)) {
            return array();
        }

        if (!array_key_exists('updates', $new)) {
            throw new available_update_checker_exception('err_response_format');
        }

        if (empty($old)) {
            return $new['updates'];
        }

        if (!array_key_exists('updates', $old)) {
            throw new available_update_checker_exception('err_response_format');
        }

        $changes = array();

        foreach ($new['updates'] as $newcomponent => $newcomponentupdates) {
            if (empty($old['updates'][$newcomponent])) {
                $changes[$newcomponent] = $newcomponentupdates;
                continue;
            }
            foreach ($newcomponentupdates as $newcomponentupdate) {
                $inold = false;
                foreach ($old['updates'][$newcomponent] as $oldcomponentupdate) {
                    if ($newcomponentupdate['version'] == $oldcomponentupdate['version']) {
                        $inold = true;
                    }
                }
                if (!$inold) {
                    if (!isset($changes[$newcomponent])) {
                        $changes[$newcomponent] = array();
                    }
                    $changes[$newcomponent][] = $newcomponentupdate;
                }
            }
        }

        return $changes;
    }

    /**
     * Returns the URL to send update requests to
     *
     * During the development or testing, you can set $CFG->alternativeupdateproviderurl
     * to a custom URL that will be used. Otherwise the standard URL will be returned.
     *
     * @return string URL
     */
    protected function prepare_request_url() {
        global $CFG;

        if (!empty($CFG->config_php_settings['alternativeupdateproviderurl'])) {
            return $CFG->config_php_settings['alternativeupdateproviderurl'];
        } else {
            return 'https://download.moodle.org/api/1.2/updates.php';
        }
    }

    /**
     * Sets the properties currentversion, currentrelease, currentbranch and currentplugins
     *
     * @param bool $forcereload
     */
    protected function load_current_environment($forcereload=false) {
        global $CFG;

        if (!is_null($this->currentversion) and !$forcereload) {
            // nothing to do
            return;
        }

        $version = null;
        $release = null;

        require($CFG->dirroot.'/version.php');
        $this->currentversion = $version;
        $this->currentrelease = $release;
        $this->currentbranch = moodle_major_version(true);

        $pluginman = plugin_manager::instance();
        foreach ($pluginman->get_plugins() as $type => $plugins) {
            foreach ($plugins as $plugin) {
                if (!$plugin->is_standard()) {
                    $this->currentplugins[$plugin->component] = $plugin->versiondisk;
                }
            }
        }
    }

    /**
     * Returns the list of HTTP params to be sent to the updates provider URL
     *
     * @return array of (string)param => (string)value
     */
    protected function prepare_request_params() {
        global $CFG;

        $this->load_current_environment();
        $this->restore_response();

        $params = array();
        $params['format'] = 'json';

        if (isset($this->recentresponse['ticket'])) {
            $params['ticket'] = $this->recentresponse['ticket'];
        }

        if (isset($this->currentversion)) {
            $params['version'] = $this->currentversion;
        } else {
            throw new coding_exception('Main Moodle version must be already known here');
        }

        if (isset($this->currentbranch)) {
            $params['branch'] = $this->currentbranch;
        } else {
            throw new coding_exception('Moodle release must be already known here');
        }

        $plugins = array();
        foreach ($this->currentplugins as $plugin => $version) {
            $plugins[] = $plugin.'@'.$version;
        }
        if (!empty($plugins)) {
            $params['plugins'] = implode(',', $plugins);
        }

        return $params;
    }

    /**
     * Returns the list of cURL options to use when fetching available updates data
     *
     * @return array of (string)param => (string)value
     */
    protected function prepare_request_options() {
        global $CFG;

        $options = array(
            'CURLOPT_SSL_VERIFYHOST' => 2,      // this is the default in {@link curl} class but just in case
            'CURLOPT_SSL_VERIFYPEER' => true,
        );

        return $options;
    }

    /**
     * Returns the current timestamp
     *
     * @return int the timestamp
     */
    protected function cron_current_timestamp() {
        return time();
    }

    /**
     * Output cron debugging info
     *
     * @see mtrace()
     * @param string $msg output message
     * @param string $eol end of line
     */
    protected function cron_mtrace($msg, $eol = PHP_EOL) {
        mtrace($msg, $eol);
    }

    /**
     * Decide if the autocheck feature is disabled in the server setting
     *
     * @return bool true if autocheck enabled, false if disabled
     */
    protected function cron_autocheck_enabled() {
        global $CFG;

        if (empty($CFG->updateautocheck)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Decide if the recently fetched data are still fresh enough
     *
     * @param int $now current timestamp
     * @return bool true if no need to re-fetch, false otherwise
     */
    protected function cron_has_fresh_fetch($now) {
        $recent = $this->get_last_timefetched();

        if (empty($recent)) {
            return false;
        }

        if ($now < $recent) {
            $this->cron_mtrace('The most recent fetch is reported to be in the future, this is weird!');
            return true;
        }

        if ($now - $recent > 24 * HOURSECS) {
            return false;
        }

        return true;
    }

    /**
     * Decide if the fetch is outadated or even missing
     *
     * @param int $now current timestamp
     * @return bool false if no need to re-fetch, true otherwise
     */
    protected function cron_has_outdated_fetch($now) {
        $recent = $this->get_last_timefetched();

        if (empty($recent)) {
            return true;
        }

        if ($now < $recent) {
            $this->cron_mtrace('The most recent fetch is reported to be in the future, this is weird!');
            return false;
        }

        if ($now - $recent > 48 * HOURSECS) {
            return true;
        }

        return false;
    }

    /**
     * Returns the cron execution offset for this site
     *
     * The main {@link self::cron()} is supposed to run every night in some random time
     * between 01:00 and 06:00 AM (local time). The exact moment is defined by so called
     * execution offset, that is the amount of time after 01:00 AM. The offset value is
     * initially generated randomly and then used consistently at the site. This way, the
     * regular checks against the download.moodle.org server are spread in time.
     *
     * @return int the offset number of seconds from range 1 sec to 5 hours
     */
    protected function cron_execution_offset() {
        global $CFG;

        if (empty($CFG->updatecronoffset)) {
            set_config('updatecronoffset', rand(1, 5 * HOURSECS));
        }

        return $CFG->updatecronoffset;
    }

    /**
     * Fetch available updates info and eventually send notification to site admins
     */
    protected function cron_execute() {

        try {
            $this->restore_response();
            $previous = $this->recentresponse;
            $this->fetch();
            $this->restore_response(true);
            $current = $this->recentresponse;
            $changes = $this->compare_responses($previous, $current);
            $notifications = $this->cron_notifications($changes);
            $this->cron_notify($notifications);
            $this->cron_mtrace('done');
        } catch (available_update_checker_exception $e) {
            $this->cron_mtrace('FAILED!');
        }
    }

    /**
     * Given the list of changes in available updates, pick those to send to site admins
     *
     * @param array $changes as returned by {@link self::compare_responses()}
     * @return array of available_update_info objects to send to site admins
     */
    protected function cron_notifications(array $changes) {
        global $CFG;

        $notifications = array();
        $pluginman = plugin_manager::instance();
        $plugins = $pluginman->get_plugins(true);

        foreach ($changes as $component => $componentchanges) {
            if (empty($componentchanges)) {
                continue;
            }
            $componentupdates = $this->get_update_info($component,
                array('minmaturity' => $CFG->updateminmaturity, 'notifybuilds' => $CFG->updatenotifybuilds));
            if (empty($componentupdates)) {
                continue;
            }
            // notify only about those $componentchanges that are present in $componentupdates
            // to respect the preferences
            foreach ($componentchanges as $componentchange) {
                foreach ($componentupdates as $componentupdate) {
                    if ($componentupdate->version == $componentchange['version']) {
                        if ($component == 'core') {
                            // In case of 'core', we already know that the $componentupdate
                            // is a real update with higher version ({@see self::get_update_info()}).
                            // We just perform additional check for the release property as there
                            // can be two Moodle releases having the same version (e.g. 2.4.0 and 2.5dev shortly
                            // after the release). We can do that because we have the release info
                            // always available for the core.
                            if ((string)$componentupdate->release === (string)$componentchange['release']) {
                                $notifications[] = $componentupdate;
                            }
                        } else {
                            // Use the plugin_manager to check if the detected $componentchange
                            // is a real update with higher version. That is, the $componentchange
                            // is present in the array of {@link available_update_info} objects
                            // returned by the plugin's available_updates() method.
                            list($plugintype, $pluginname) = core_component::normalize_component($component);
                            if (!empty($plugins[$plugintype][$pluginname])) {
                                $availableupdates = $plugins[$plugintype][$pluginname]->available_updates();
                                if (!empty($availableupdates)) {
                                    foreach ($availableupdates as $availableupdate) {
                                        if ($availableupdate->version == $componentchange['version']) {
                                            $notifications[] = $componentupdate;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $notifications;
    }

    /**
     * Sends the given notifications to site admins via messaging API
     *
     * @param array $notifications array of available_update_info objects to send
     */
    protected function cron_notify(array $notifications) {
        global $CFG;

        if (empty($notifications)) {
            return;
        }

        $admins = get_admins();

        if (empty($admins)) {
            return;
        }

        $this->cron_mtrace('sending notifications ... ', '');

        $text = get_string('updatenotifications', 'core_admin') . PHP_EOL;
        $html = html_writer::tag('h1', get_string('updatenotifications', 'core_admin')) . PHP_EOL;

        $coreupdates = array();
        $pluginupdates = array();

        foreach ($notifications as $notification) {
            if ($notification->component == 'core') {
                $coreupdates[] = $notification;
            } else {
                $pluginupdates[] = $notification;
            }
        }

        if (!empty($coreupdates)) {
            $text .= PHP_EOL . get_string('updateavailable', 'core_admin') . PHP_EOL;
            $html .= html_writer::tag('h2', get_string('updateavailable', 'core_admin')) . PHP_EOL;
            $html .= html_writer::start_tag('ul') . PHP_EOL;
            foreach ($coreupdates as $coreupdate) {
                $html .= html_writer::start_tag('li');
                if (isset($coreupdate->release)) {
                    $text .= get_string('updateavailable_release', 'core_admin', $coreupdate->release);
                    $html .= html_writer::tag('strong', get_string('updateavailable_release', 'core_admin', $coreupdate->release));
                }
                if (isset($coreupdate->version)) {
                    $text .= ' '.get_string('updateavailable_version', 'core_admin', $coreupdate->version);
                    $html .= ' '.get_string('updateavailable_version', 'core_admin', $coreupdate->version);
                }
                if (isset($coreupdate->maturity)) {
                    $text .= ' ('.get_string('maturity'.$coreupdate->maturity, 'core_admin').')';
                    $html .= ' ('.get_string('maturity'.$coreupdate->maturity, 'core_admin').')';
                }
                $text .= PHP_EOL;
                $html .= html_writer::end_tag('li') . PHP_EOL;
            }
            $text .= PHP_EOL;
            $html .= html_writer::end_tag('ul') . PHP_EOL;

            $a = array('url' => $CFG->wwwroot.'/'.$CFG->admin.'/index.php');
            $text .= get_string('updateavailabledetailslink', 'core_admin', $a) . PHP_EOL;
            $a = array('url' => html_writer::link($CFG->wwwroot.'/'.$CFG->admin.'/index.php', $CFG->wwwroot.'/'.$CFG->admin.'/index.php'));
            $html .= html_writer::tag('p', get_string('updateavailabledetailslink', 'core_admin', $a)) . PHP_EOL;
        }

        if (!empty($pluginupdates)) {
            $text .= PHP_EOL . get_string('updateavailableforplugin', 'core_admin') . PHP_EOL;
            $html .= html_writer::tag('h2', get_string('updateavailableforplugin', 'core_admin')) . PHP_EOL;

            $html .= html_writer::start_tag('ul') . PHP_EOL;
            foreach ($pluginupdates as $pluginupdate) {
                $html .= html_writer::start_tag('li');
                $text .= get_string('pluginname', $pluginupdate->component);
                $html .= html_writer::tag('strong', get_string('pluginname', $pluginupdate->component));

                $text .= ' ('.$pluginupdate->component.')';
                $html .= ' ('.$pluginupdate->component.')';

                $text .= ' '.get_string('updateavailable', 'core_plugin', $pluginupdate->version);
                $html .= ' '.get_string('updateavailable', 'core_plugin', $pluginupdate->version);

                $text .= PHP_EOL;
                $html .= html_writer::end_tag('li') . PHP_EOL;
            }
            $text .= PHP_EOL;
            $html .= html_writer::end_tag('ul') . PHP_EOL;

            $a = array('url' => $CFG->wwwroot.'/'.$CFG->admin.'/plugins.php');
            $text .= get_string('updateavailabledetailslink', 'core_admin', $a) . PHP_EOL;
            $a = array('url' => html_writer::link($CFG->wwwroot.'/'.$CFG->admin.'/plugins.php', $CFG->wwwroot.'/'.$CFG->admin.'/plugins.php'));
            $html .= html_writer::tag('p', get_string('updateavailabledetailslink', 'core_admin', $a)) . PHP_EOL;
        }

        $a = array('siteurl' => $CFG->wwwroot);
        $text .= get_string('updatenotificationfooter', 'core_admin', $a) . PHP_EOL;
        $a = array('siteurl' => html_writer::link($CFG->wwwroot, $CFG->wwwroot));
        $html .= html_writer::tag('footer', html_writer::tag('p', get_string('updatenotificationfooter', 'core_admin', $a),
            array('style' => 'font-size:smaller; color:#333;')));

        foreach ($admins as $admin) {
            $message = new stdClass();
            $message->component         = 'moodle';
            $message->name              = 'availableupdate';
            $message->userfrom          = get_admin();
            $message->userto            = $admin;
            $message->subject           = get_string('updatenotificationsubject', 'core_admin', array('siteurl' => $CFG->wwwroot));
            $message->fullmessage       = $text;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml   = $html;
            $message->smallmessage      = get_string('updatenotifications', 'core_admin');
            $message->notification      = 1;
            message_send($message);
        }
    }

    /**
     * Compare two release labels and decide if they are the same
     *
     * @param string $remote release info of the available update
     * @param null|string $local release info of the local code, defaults to $release defined in version.php
     * @return boolean true if the releases declare the same minor+major version
     */
    protected function is_same_release($remote, $local=null) {

        if (is_null($local)) {
            $this->load_current_environment();
            $local = $this->currentrelease;
        }

        $pattern = '/^([0-9\.\+]+)([^(]*)/';

        preg_match($pattern, $remote, $remotematches);
        preg_match($pattern, $local, $localmatches);

        $remotematches[1] = str_replace('+', '', $remotematches[1]);
        $localmatches[1] = str_replace('+', '', $localmatches[1]);

        if ($remotematches[1] === $localmatches[1] and rtrim($remotematches[2]) === rtrim($localmatches[2])) {
            return true;
        } else {
            return false;
        }
    }
}


/**
 * Defines the structure of objects returned by {@link available_update_checker::get_update_info()}
 */
class available_update_info {

    /** @var string frankenstyle component name */
    public $component;
    /** @var int the available version of the component */
    public $version;
    /** @var string|null optional release name */
    public $release = null;
    /** @var int|null optional maturity info, eg {@link MATURITY_STABLE} */
    public $maturity = null;
    /** @var string|null optional URL of a page with more info about the update */
    public $url = null;
    /** @var string|null optional URL of a ZIP package that can be downloaded and installed */
    public $download = null;
    /** @var string|null of self::download is set, then this must be the MD5 hash of the ZIP */
    public $downloadmd5 = null;

    /**
     * Creates new instance of the class
     *
     * The $info array must provide at least the 'version' value and optionally all other
     * values to populate the object's properties.
     *
     * @param string $name the frankenstyle component name
     * @param array $info associative array with other properties
     */
    public function __construct($name, array $info) {
        $this->component = $name;
        foreach ($info as $k => $v) {
            if (property_exists('available_update_info', $k) and $k != 'component') {
                $this->$k = $v;
            }
        }
    }
}


/**
 * Implements a communication bridge to the mdeploy.php utility
 */
class available_update_deployer {

    const HTTP_PARAM_PREFIX     = 'updteautodpldata_';  // Hey, even Google has not heard of such a prefix! So it MUST be safe :-p
    const HTTP_PARAM_CHECKER    = 'datapackagesize';    // Name of the parameter that holds the number of items in the received data items

    /** @var available_update_deployer holds the singleton instance */
    protected static $singletoninstance;
    /** @var moodle_url URL of a page that includes the deployer UI */
    protected $callerurl;
    /** @var moodle_url URL to return after the deployment */
    protected $returnurl;

    /**
     * Direct instantiation not allowed, use the factory method {@link self::instance()}
     */
    protected function __construct() {
    }

    /**
     * Sorry, this is singleton
     */
    protected function __clone() {
    }

    /**
     * Factory method for this class
     *
     * @return available_update_deployer the singleton instance
     */
    public static function instance() {
        if (is_null(self::$singletoninstance)) {
            self::$singletoninstance = new self();
        }
        return self::$singletoninstance;
    }

    /**
     * Reset caches used by this script
     *
     * @param bool $phpunitreset is this called as a part of PHPUnit reset?
     */
    public static function reset_caches($phpunitreset = false) {
        if ($phpunitreset) {
            self::$singletoninstance = null;
        }
    }

    /**
     * Is automatic deployment enabled?
     *
     * @return bool
     */
    public function enabled() {
        global $CFG;

        if (!empty($CFG->disableupdateautodeploy)) {
            // The feature is prohibited via config.php
            return false;
        }

        return get_config('updateautodeploy');
    }

    /**
     * Sets some base properties of the class to make it usable.
     *
     * @param moodle_url $callerurl the base URL of a script that will handle the class'es form data
     * @param moodle_url $returnurl the final URL to return to when the deployment is finished
     */
    public function initialize(moodle_url $callerurl, moodle_url $returnurl) {

        if (!$this->enabled()) {
            throw new coding_exception('Unable to initialize the deployer, the feature is not enabled.');
        }

        $this->callerurl = $callerurl;
        $this->returnurl = $returnurl;
    }

    /**
     * Has the deployer been initialized?
     *
     * Initialized deployer means that the following properties were set:
     * callerurl, returnurl
     *
     * @return bool
     */
    public function initialized() {

        if (!$this->enabled()) {
            return false;
        }

        if (empty($this->callerurl)) {
            return false;
        }

        if (empty($this->returnurl)) {
            return false;
        }

        return true;
    }

    /**
     * Returns a list of reasons why the deployment can not happen
     *
     * If the returned array is empty, the deployment seems to be possible. The returned
     * structure is an associative array with keys representing individual impediments.
     * Possible keys are: missingdownloadurl, missingdownloadmd5, notwritable.
     *
     * @param available_update_info $info
     * @return array
     */
    public function deployment_impediments(available_update_info $info) {

        $impediments = array();

        if (empty($info->download)) {
            $impediments['missingdownloadurl'] = true;
        }

        if (empty($info->downloadmd5)) {
            $impediments['missingdownloadmd5'] = true;
        }

        if (!empty($info->download) and !$this->update_downloadable($info->download)) {
            $impediments['notdownloadable'] = true;
        }

        if (!$this->component_writable($info->component)) {
            $impediments['notwritable'] = true;
        }

        return $impediments;
    }

    /**
     * Check to see if the current version of the plugin seems to be a checkout of an external repository.
     *
     * @see plugin_manager::plugin_external_source()
     * @param available_update_info $info
     * @return false|string
     */
    public function plugin_external_source(available_update_info $info) {

        $paths = core_component::get_plugin_types();
        list($plugintype, $pluginname) = core_component::normalize_component($info->component);
        $pluginroot = $paths[$plugintype].'/'.$pluginname;

        if (is_dir($pluginroot.'/.git')) {
            return 'git';
        }

        if (is_dir($pluginroot.'/CVS')) {
            return 'cvs';
        }

        if (is_dir($pluginroot.'/.svn')) {
            return 'svn';
        }

        return false;
    }

    /**
     * Prepares a renderable widget to confirm installation of an available update.
     *
     * @param available_update_info $info component version to deploy
     * @return renderable
     */
    public function make_confirm_widget(available_update_info $info) {

        if (!$this->initialized()) {
            throw new coding_exception('Illegal method call - deployer not initialized.');
        }

        $params = $this->data_to_params(array(
            'updateinfo' => (array)$info,   // see http://www.php.net/manual/en/language.types.array.php#language.types.array.casting
        ));

        $widget = new single_button(
            new moodle_url($this->callerurl, $params),
            get_string('updateavailableinstall', 'core_admin'),
            'post'
        );

        return $widget;
    }

    /**
     * Prepares a renderable widget to execute installation of an available update.
     *
     * @param available_update_info $info component version to deploy
     * @param moodle_url $returnurl URL to return after the installation execution
     * @return renderable
     */
    public function make_execution_widget(available_update_info $info, moodle_url $returnurl = null) {
        global $CFG;

        if (!$this->initialized()) {
            throw new coding_exception('Illegal method call - deployer not initialized.');
        }

        $pluginrootpaths = core_component::get_plugin_types();

        list($plugintype, $pluginname) = core_component::normalize_component($info->component);

        if (empty($pluginrootpaths[$plugintype])) {
            throw new coding_exception('Unknown plugin type root location', $plugintype);
        }

        list($passfile, $password) = $this->prepare_authorization();

        if (is_null($returnurl)) {
            $returnurl = new moodle_url('/admin');
        } else {
            $returnurl = $returnurl;
        }

        $params = array(
            'upgrade' => true,
            'type' => $plugintype,
            'name' => $pluginname,
            'typeroot' => $pluginrootpaths[$plugintype],
            'package' => $info->download,
            'md5' => $info->downloadmd5,
            'dataroot' => $CFG->dataroot,
            'dirroot' => $CFG->dirroot,
            'passfile' => $passfile,
            'password' => $password,
            'returnurl' => $returnurl->out(false),
        );

        if (!empty($CFG->proxyhost)) {
            // MDL-36973 - Beware - we should call just !is_proxybypass() here. But currently, our
            // cURL wrapper class does not do it. So, to have consistent behaviour, we pass proxy
            // setting regardless the $CFG->proxybypass setting. Once the {@link curl} class is
            // fixed, the condition should be amended.
            if (true or !is_proxybypass($info->download)) {
                if (empty($CFG->proxyport)) {
                    $params['proxy'] = $CFG->proxyhost;
                } else {
                    $params['proxy'] = $CFG->proxyhost.':'.$CFG->proxyport;
                }

                if (!empty($CFG->proxyuser) and !empty($CFG->proxypassword)) {
                    $params['proxyuserpwd'] = $CFG->proxyuser.':'.$CFG->proxypassword;
                }

                if (!empty($CFG->proxytype)) {
                    $params['proxytype'] = $CFG->proxytype;
                }
            }
        }

        $widget = new single_button(
            new moodle_url('/mdeploy.php', $params),
            get_string('updateavailableinstall', 'core_admin'),
            'post'
        );

        return $widget;
    }

    /**
     * Returns array of data objects passed to this tool.
     *
     * @return array
     */
    public function submitted_data() {

        $data = $this->params_to_data($_POST);

        if (empty($data) or empty($data[self::HTTP_PARAM_CHECKER])) {
            return false;
        }

        if (!empty($data['updateinfo']) and is_object($data['updateinfo'])) {
            $updateinfo = $data['updateinfo'];
            if (!empty($updateinfo->component) and !empty($updateinfo->version)) {
                $data['updateinfo'] = new available_update_info($updateinfo->component, (array)$updateinfo);
            }
        }

        if (!empty($data['callerurl'])) {
            $data['callerurl'] = new moodle_url($data['callerurl']);
        }

        if (!empty($data['returnurl'])) {
            $data['returnurl'] = new moodle_url($data['returnurl']);
        }

        return $data;
    }

    /**
     * Handles magic getters and setters for protected properties.
     *
     * @param string $name method name, e.g. set_returnurl()
     * @param array $arguments arguments to be passed to the array
     */
    public function __call($name, array $arguments = array()) {

        if (substr($name, 0, 4) === 'set_') {
            $property = substr($name, 4);
            if (empty($property)) {
                throw new coding_exception('Invalid property name (empty)');
            }
            if (empty($arguments)) {
                $arguments = array(true); // Default value for flag-like properties.
            }
            // Make sure it is a protected property.
            $isprotected = false;
            $reflection = new ReflectionObject($this);
            foreach ($reflection->getProperties(ReflectionProperty::IS_PROTECTED) as $reflectionproperty) {
                if ($reflectionproperty->getName() === $property) {
                    $isprotected = true;
                    break;
                }
            }
            if (!$isprotected) {
                throw new coding_exception('Unable to set property - it does not exist or it is not protected');
            }
            $value = reset($arguments);
            $this->$property = $value;
            return;
        }

        if (substr($name, 0, 4) === 'get_') {
            $property = substr($name, 4);
            if (empty($property)) {
                throw new coding_exception('Invalid property name (empty)');
            }
            if (!empty($arguments)) {
                throw new coding_exception('No parameter expected');
            }
            // Make sure it is a protected property.
            $isprotected = false;
            $reflection = new ReflectionObject($this);
            foreach ($reflection->getProperties(ReflectionProperty::IS_PROTECTED) as $reflectionproperty) {
                if ($reflectionproperty->getName() === $property) {
                    $isprotected = true;
                    break;
                }
            }
            if (!$isprotected) {
                throw new coding_exception('Unable to get property - it does not exist or it is not protected');
            }
            return $this->$property;
        }
    }

    /**
     * Generates a random token and stores it in a file in moodledata directory.
     *
     * @return array of the (string)filename and (string)password in this order
     */
    public function prepare_authorization() {
        global $CFG;

        make_upload_directory('mdeploy/auth/');

        $attempts = 0;
        $success = false;

        while (!$success and $attempts < 5) {
            $attempts++;

            $passfile = $this->generate_passfile();
            $password = $this->generate_password();
            $now = time();

            $filepath = $CFG->dataroot.'/mdeploy/auth/'.$passfile;

            if (!file_exists($filepath)) {
                $success = file_put_contents($filepath, $password . PHP_EOL . $now . PHP_EOL, LOCK_EX);
                chmod($filepath, $CFG->filepermissions);
            }
        }

        if ($success) {
            return array($passfile, $password);

        } else {
            throw new moodle_exception('unable_prepare_authorization', 'core_plugin');
        }
    }

    // End of external API

    /**
     * Prepares an array of HTTP parameters that can be passed to another page.
     *
     * @param array|object $data associative array or an object holding the data, data JSON-able
     * @return array suitable as a param for moodle_url
     */
    protected function data_to_params($data) {

        // Append some our own data
        if (!empty($this->callerurl)) {
            $data['callerurl'] = $this->callerurl->out(false);
        }
        if (!empty($this->returnurl)) {
            $data['returnurl'] = $this->returnurl->out(false);
        }

        // Finally append the count of items in the package.
        $data[self::HTTP_PARAM_CHECKER] = count($data);

        // Generate params
        $params = array();
        foreach ($data as $name => $value) {
            $transname = self::HTTP_PARAM_PREFIX.$name;
            $transvalue = json_encode($value);
            $params[$transname] = $transvalue;
        }

        return $params;
    }

    /**
     * Converts HTTP parameters passed to the script into native PHP data
     *
     * @param array $params such as $_REQUEST or $_POST
     * @return array data passed for this class
     */
    protected function params_to_data(array $params) {

        if (empty($params)) {
            return array();
        }

        $data = array();
        foreach ($params as $name => $value) {
            if (strpos($name, self::HTTP_PARAM_PREFIX) === 0) {
                $realname = substr($name, strlen(self::HTTP_PARAM_PREFIX));
                $realvalue = json_decode($value);
                $data[$realname] = $realvalue;
            }
        }

        return $data;
    }

    /**
     * Returns a random string to be used as a filename of the password storage.
     *
     * @return string
     */
    protected function generate_passfile() {
        return clean_param(uniqid('mdeploy_', true), PARAM_FILE);
    }

    /**
     * Returns a random string to be used as the authorization token
     *
     * @return string
     */
    protected function generate_password() {
        return complex_random_string();
    }

    /**
     * Checks if the given component's directory is writable
     *
     * For the purpose of the deployment, the web server process has to have
     * write access to all files in the component's directory (recursively) and for the
     * directory itself.
     *
     * @see worker::move_directory_source_precheck()
     * @param string $component normalized component name
     * @return boolean
     */
    protected function component_writable($component) {

        list($plugintype, $pluginname) = core_component::normalize_component($component);

        $directory = core_component::get_plugin_directory($plugintype, $pluginname);

        if (is_null($directory)) {
            throw new coding_exception('Unknown component location', $component);
        }

        return $this->directory_writable($directory);
    }

    /**
     * Checks if the mdeploy.php will be able to fetch the ZIP from the given URL
     *
     * This is mainly supposed to check if the transmission over HTTPS would
     * work. That is, if the CA certificates are present at the server.
     *
     * @param string $downloadurl the URL of the ZIP package to download
     * @return bool
     */
    protected function update_downloadable($downloadurl) {
        global $CFG;

        $curloptions = array(
            'CURLOPT_SSL_VERIFYHOST' => 2,      // this is the default in {@link curl} class but just in case
            'CURLOPT_SSL_VERIFYPEER' => true,
        );

        $curl = new curl(array('proxy' => true));
        $result = $curl->head($downloadurl, $curloptions);
        $errno = $curl->get_errno();
        if (empty($errno)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks if the directory and all its contents (recursively) is writable
     *
     * @param string $path full path to a directory
     * @return boolean
     */
    private function directory_writable($path) {

        if (!is_writable($path)) {
            return false;
        }

        if (is_dir($path)) {
            $handle = opendir($path);
        } else {
            return false;
        }

        $result = true;

        while ($filename = readdir($handle)) {
            $filepath = $path.'/'.$filename;

            if ($filename === '.' or $filename === '..') {
                continue;
            }

            if (is_dir($filepath)) {
                $result = $result && $this->directory_writable($filepath);

            } else {
                $result = $result && is_writable($filepath);
            }
        }

        closedir($handle);

        return $result;
    }
}


/**
 * Factory class producing required subclasses of {@link plugininfo_base}
 */
class plugininfo_default_factory {

    /**
     * Makes a new instance of the plugininfo class
     *
     * @param string $type the plugin type, eg. 'mod'
     * @param string $typerootdir full path to the location of all the plugins of this type
     * @param string $name the plugin name, eg. 'workshop'
     * @param string $namerootdir full path to the location of the plugin
     * @param string $typeclass the name of class that holds the info about the plugin
     * @return plugininfo_base the instance of $typeclass
     */
    public static function make($type, $typerootdir, $name, $namerootdir, $typeclass) {
        $plugin              = new $typeclass();
        $plugin->type        = $type;
        $plugin->typerootdir = $typerootdir;
        $plugin->name        = $name;
        $plugin->rootdir     = $namerootdir;

        $plugin->init_display_name();
        $plugin->load_disk_version();
        $plugin->load_db_version();
        $plugin->init_is_standard();

        return $plugin;
    }
}


/**
 * Base class providing access to the information about a plugin
 *
 * @property-read string component the component name, type_name
 */
abstract class plugininfo_base {

    /** @var string the plugintype name, eg. mod, auth or workshopform */
    public $type;
    /** @var string full path to the location of all the plugins of this type */
    public $typerootdir;
    /** @var string the plugin name, eg. assignment, ldap */
    public $name;
    /** @var string the localized plugin name */
    public $displayname;
    /** @var string the plugin source, one of plugin_manager::PLUGIN_SOURCE_xxx constants */
    public $source;
    /** @var string fullpath to the location of this plugin */
    public $rootdir;
    /** @var int|string the version of the plugin's source code */
    public $versiondisk;
    /** @var int|string the version of the installed plugin */
    public $versiondb;
    /** @var int|float|string required version of Moodle core  */
    public $versionrequires;
    /** @var array other plugins that this one depends on, lazy-loaded by {@link get_other_required_plugins()} */
    public $dependencies;
    /** @var int number of instances of the plugin - not supported yet */
    public $instances;
    /** @var int order of the plugin among other plugins of the same type - not supported yet */
    public $sortorder;
    /** @var array|null array of {@link available_update_info} for this plugin */
    public $availableupdates;

    /**
     * Finds all enabled plugins, the result may include missing plugins.
     * @return array|null of enabled plugins $pluginname=>$pluginname, null means unknown
     */
    public static function get_enabled_plugins() {
        return null;
    }

    /**
     * Gathers and returns the information about all plugins of the given type,
     * either on disk or previously installed.
     *
     * @param string $type the name of the plugintype, eg. mod, auth or workshopform
     * @param string $typerootdir full path to the location of the plugin dir
     * @param string $typeclass the name of the actually called class
     * @return array of plugintype classes, indexed by the plugin name
     */
    public static function get_plugins($type, $typerootdir, $typeclass) {
        // Get the information about plugins at the disk.
        $plugins = core_component::get_plugin_list($type);
        $return = array();
        foreach ($plugins as $pluginname => $pluginrootdir) {
            $return[$pluginname] = plugininfo_default_factory::make($type, $typerootdir,
                $pluginname, $pluginrootdir, $typeclass);
        }

        // Fetch missing incorrectly uninstalled plugins.
        $manager = plugin_manager::instance();
        $plugins = $manager->get_installed_plugins($type);

        foreach ($plugins as $name => $version) {
            if (isset($return[$name])) {
                continue;
            }
            $plugin              = new $typeclass();
            $plugin->type        = $type;
            $plugin->typerootdir = $typerootdir;
            $plugin->name        = $name;
            $plugin->rootdir     = null;
            $plugin->displayname = $name;
            $plugin->versiondb   = $version;
            $plugin->init_is_standard();

            $return[$name] = $plugin;
        }

        return $return;
    }

    /**
     * Is this plugin already installed and updated?
     * @return bool true if plugin installed and upgraded.
     */
    public function is_updated() {
        if (!$this->rootdir) {
            return false;
        }
        if ($this->versiondb === null and $this->versiondisk === null) {
            // There is no version.php or version info inside,
            // for now let's pretend it is ok.
            // TODO: return false once we require version in each plugin.
            return true;
        }

        return ((float)$this->versiondb === (float)$this->versiondisk);
    }

    /**
     * Sets {@link $displayname} property to a localized name of the plugin
     */
    public function init_display_name() {
        if (!get_string_manager()->string_exists('pluginname', $this->component)) {
            $this->displayname = '[pluginname,' . $this->component . ']';
        } else {
            $this->displayname = get_string('pluginname', $this->component);
        }
    }

    /**
     * Magic method getter, redirects to read only values.
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        switch ($name) {
            case 'component': return $this->type . '_' . $this->name;

            default:
                debugging('Invalid plugin property accessed! '.$name);
                return null;
        }
    }

    /**
     * Return the full path name of a file within the plugin.
     *
     * No check is made to see if the file exists.
     *
     * @param string $relativepath e.g. 'version.php'.
     * @return string e.g. $CFG->dirroot . '/mod/quiz/version.php'.
     */
    public function full_path($relativepath) {
        if (empty($this->rootdir)) {
            return '';
        }
        return $this->rootdir . '/' . $relativepath;
    }

    /**
     * Sets {@link $versiondisk} property to a numerical value representing the
     * version of the plugin's source code.
     *
     * If the value is null after calling this method, either the plugin
     * does not use versioning (typically does not have any database
     * data) or is missing from disk.
     */
    public function load_disk_version() {
        $versions = plugin_manager::instance()->get_present_plugins($this->type);

        $this->versiondisk = null;
        $this->versionrequires = null;
        $this->dependencies = array();

        if (!isset($versions[$this->name])) {
            return;
        }

        $plugin = $versions[$this->name];

        if (isset($plugin->version)) {
            $this->versiondisk = $plugin->version;
        }
        if (isset($plugin->requires)) {
            $this->versionrequires = $plugin->requires;
        }
        if (isset($plugin->dependencies)) {
            $this->dependencies = $plugin->dependencies;
        }
    }

    /**
     * Get the list of other plugins that this plugin requires to be installed.
     *
     * @return array with keys the frankenstyle plugin name, and values either
     *      a version string (like '2011101700') or the constant ANY_VERSION.
     */
    public function get_other_required_plugins() {
        if (is_null($this->dependencies)) {
            $this->load_disk_version();
        }
        return $this->dependencies;
    }

    /**
     * Is this is a subplugin?
     *
     * @return boolean
     */
    public function is_subplugin() {
        return ($this->get_parent_plugin() !== false);
    }

    /**
     * If I am a subplugin, return the name of my parent plugin.
     *
     * @return string|bool false if not a subplugin, name of the parent otherwise
     */
    public function get_parent_plugin() {
        return $this->get_plugin_manager()->get_parent_of_subplugin($this->type);
    }

    /**
     * Sets {@link $versiondb} property to a numerical value representing the
     * currently installed version of the plugin.
     *
     * If the value is null after calling this method, either the plugin
     * does not use versioning (typically does not have any database
     * data) or has not been installed yet.
     */
    public function load_db_version() {
        $versions = plugin_manager::instance()->get_installed_plugins($this->type);

        if (isset($versions[$this->name])) {
            $this->versiondb = $versions[$this->name];
        } else {
            $this->versiondb = null;
        }
    }

    /**
     * Sets {@link $source} property to one of plugin_manager::PLUGIN_SOURCE_xxx
     * constants.
     *
     * If the property's value is null after calling this method, then
     * the type of the plugin has not been recognized and you should throw
     * an exception.
     */
    public function init_is_standard() {

        $standard = plugin_manager::standard_plugins_list($this->type);

        if ($standard !== false) {
            $standard = array_flip($standard);
            if (isset($standard[$this->name])) {
                $this->source = plugin_manager::PLUGIN_SOURCE_STANDARD;
            } else if (!is_null($this->versiondb) and is_null($this->versiondisk)
                    and plugin_manager::is_deleted_standard_plugin($this->type, $this->name)) {
                $this->source = plugin_manager::PLUGIN_SOURCE_STANDARD; // to be deleted
            } else {
                $this->source = plugin_manager::PLUGIN_SOURCE_EXTENSION;
            }
        }
    }

    /**
     * Returns true if the plugin is shipped with the official distribution
     * of the current Moodle version, false otherwise.
     *
     * @return bool
     */
    public function is_standard() {
        return $this->source === plugin_manager::PLUGIN_SOURCE_STANDARD;
    }

    /**
     * Returns true if the the given Moodle version is enough to run this plugin
     *
     * @param string|int|double $moodleversion
     * @return bool
     */
    public function is_core_dependency_satisfied($moodleversion) {

        if (empty($this->versionrequires)) {
            return true;

        } else {
            return (double)$this->versionrequires <= (double)$moodleversion;
        }
    }

    /**
     * Returns the status of the plugin
     *
     * @return string one of plugin_manager::PLUGIN_STATUS_xxx constants
     */
    public function get_status() {

        if (is_null($this->versiondb) and is_null($this->versiondisk)) {
            return plugin_manager::PLUGIN_STATUS_NODB;

        } else if (is_null($this->versiondb) and !is_null($this->versiondisk)) {
            return plugin_manager::PLUGIN_STATUS_NEW;

        } else if (!is_null($this->versiondb) and is_null($this->versiondisk)) {
            if (plugin_manager::is_deleted_standard_plugin($this->type, $this->name)) {
                return plugin_manager::PLUGIN_STATUS_DELETE;
            } else {
                return plugin_manager::PLUGIN_STATUS_MISSING;
            }

        } else if ((float)$this->versiondb === (float)$this->versiondisk) {
            // Note: the float comparison should work fine here
            //       because there are no arithmetic operations with the numbers.
            return plugin_manager::PLUGIN_STATUS_UPTODATE;

        } else if ($this->versiondb < $this->versiondisk) {
            return plugin_manager::PLUGIN_STATUS_UPGRADE;

        } else if ($this->versiondb > $this->versiondisk) {
            return plugin_manager::PLUGIN_STATUS_DOWNGRADE;

        } else {
            // $version = pi(); and similar funny jokes - hopefully Donald E. Knuth will never contribute to Moodle ;-)
            throw new coding_exception('Unable to determine plugin state, check the plugin versions');
        }
    }

    /**
     * Returns the information about plugin availability
     *
     * True means that the plugin is enabled. False means that the plugin is
     * disabled. Null means that the information is not available, or the
     * plugin does not support configurable availability or the availability
     * can not be changed.
     *
     * @return null|bool
     */
    public function is_enabled() {
        if (!$this->rootdir) {
            // Plugin missing.
            return false;
        }

        $enabled = plugin_manager::instance()->get_enabled_plugins($this->type);

        if (!is_array($enabled)) {
            return null;
        }

        return isset($enabled[$this->name]);
    }

    /**
     * Populates the property {@link $availableupdates} with the information provided by
     * available update checker
     *
     * @param available_update_checker $provider the class providing the available update info
     */
    public function check_available_updates(available_update_checker $provider) {
        global $CFG;

        if (isset($CFG->updateminmaturity)) {
            $minmaturity = $CFG->updateminmaturity;
        } else {
            // this can happen during the very first upgrade to 2.3
            $minmaturity = MATURITY_STABLE;
        }

        $this->availableupdates = $provider->get_update_info($this->component,
            array('minmaturity' => $minmaturity));
    }

    /**
     * If there are updates for this plugin available, returns them.
     *
     * Returns array of {@link available_update_info} objects, if some update
     * is available. Returns null if there is no update available or if the update
     * availability is unknown.
     *
     * @return array|null
     */
    public function available_updates() {

        if (empty($this->availableupdates) or !is_array($this->availableupdates)) {
            return null;
        }

        $updates = array();

        foreach ($this->availableupdates as $availableupdate) {
            if ($availableupdate->version > $this->versiondisk) {
                $updates[] = $availableupdate;
            }
        }

        if (empty($updates)) {
            return null;
        }

        return $updates;
    }

    /**
     * Returns the node name used in admin settings menu for this plugin settings (if applicable)
     *
     * @return null|string node name or null if plugin does not create settings node (default)
     */
    public function get_settings_section_name() {
        return null;
    }

    /**
     * Returns the URL of the plugin settings screen
     *
     * Null value means that the plugin either does not have the settings screen
     * or its location is not available via this library.
     *
     * @return null|moodle_url
     */
    public function get_settings_url() {
        $section = $this->get_settings_section_name();
        if ($section === null) {
            return null;
        }
        $settings = admin_get_root()->locate($section);
        if ($settings && $settings instanceof admin_settingpage) {
            return new moodle_url('/admin/settings.php', array('section' => $section));
        } else if ($settings && $settings instanceof admin_externalpage) {
            return new moodle_url($settings->url);
        } else {
            return null;
        }
    }

    /**
     * Loads plugin settings to the settings tree
     *
     * This function usually includes settings.php file in plugins folder.
     * Alternatively it can create a link to some settings page (instance of admin_externalpage)
     *
     * @param part_of_admin_tree $adminroot
     * @param string $parentnodename
     * @param bool $hassiteconfig whether the current user has moodle/site:config capability
     */
    public function load_settings(part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig) {
    }

    /**
     * Should there be a way to uninstall the plugin via the administration UI
     *
     * By default, uninstallation is allowed for all non-standard add-ons. Subclasses
     * may want to override this to allow uninstallation of all plugins (simply by
     * returning true unconditionally). Subplugins follow their parent plugin's
     * decision by default.
     *
     * Note that even if true is returned, the core may still prohibit the uninstallation,
     * e.g. in case there are other plugins that depend on this one.
     *
     * @return bool
     */
    public function is_uninstall_allowed() {

        if ($this->is_subplugin()) {
            return $this->get_plugin_manager()->get_plugin_info($this->get_parent_plugin())->is_uninstall_allowed();
        }

        if ($this->is_standard()) {
            return false;
        }

        return true;
    }

    /**
     * Optional extra warning before uninstallation, for example number of uses in courses.
     *
     * @return string
     */
    public function get_uninstall_extra_warning() {
        return '';
    }

    /**
     * Returns the URL of the screen where this plugin can be uninstalled
     *
     * Visiting that URL must be safe, that is a manual confirmation is needed
     * for actual uninstallation of the plugin. By default, URL to a common
     * uninstalling tool is returned.
     *
     * @return moodle_url
     */
    public function get_uninstall_url() {
        return $this->get_default_uninstall_url();
    }

    /**
     * Pre-uninstall hook.
     *
     * This is intended for disabling of plugin, some DB table purging, etc.
     *
     * NOTE: to be called from uninstall_plugin() only.
     * @private
     */
    public function uninstall_cleanup() {
        // Override when extending class,
        // do not forget to call parent::pre_uninstall_cleanup() at the end.
    }

    /**
     * Returns relative directory of the plugin with heading '/'
     *
     * @return string
     */
    public function get_dir() {
        global $CFG;

        return substr($this->rootdir, strlen($CFG->dirroot));
    }

    /**
     * Hook method to implement certain steps when uninstalling the plugin.
     *
     * This hook is called by {@link plugin_manager::uninstall_plugin()} so
     * it is basically usable only for those plugin types that use the default
     * uninstall tool provided by {@link self::get_default_uninstall_url()}.
     *
     * @param progress_trace $progress traces the process
     * @return bool true on success, false on failure
     */
    public function uninstall(progress_trace $progress) {
        return true;
    }

    /**
     * Returns URL to a script that handles common plugin uninstall procedure.
     *
     * This URL is suitable for plugins that do not have their own UI
     * for uninstalling.
     *
     * @return moodle_url
     */
    protected final function get_default_uninstall_url() {
        return new moodle_url('/admin/plugins.php', array(
            'sesskey' => sesskey(),
            'uninstall' => $this->component,
            'confirm' => 0,
        ));
    }

    /**
     * Provides access to the plugin_manager singleton.
     *
     * @return plugin_manager
     */
    protected function get_plugin_manager() {
        return plugin_manager::instance();
    }
}


/**
 * General class for all plugin types that do not have their own class
 */
class plugininfo_general extends plugininfo_base {
}


/**
 * Class for page side blocks
 */
class plugininfo_block extends plugininfo_base {
    /**
     * Finds all enabled plugins, the result may include missing plugins.
     * @return array|null of enabled plugins $pluginname=>$pluginname, null means unknown
     */
    public static function get_enabled_plugins() {
        global $DB;

        return $DB->get_records_menu('block', array('visible'=>1), 'name ASC', 'name, name AS val');
    }

    /**
     * Magic method getter, redirects to read only values.
     *
     * For block plugins pretends the object has 'visible' property for compatibility
     * with plugins developed for Moodle version below 2.4
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        if ($name === 'visible') {
            debugging('This is now an instance of plugininfo_block, please use $block->is_enabled() instead of $block->visible', DEBUG_DEVELOPER);
            return ($this->is_enabled() !== false);
        }
        return parent::__get($name);
    }

    public function init_display_name() {

        if (get_string_manager()->string_exists('pluginname', 'block_' . $this->name)) {
            $this->displayname = get_string('pluginname', 'block_' . $this->name);

        } else if (($block = block_instance($this->name)) !== false) {
            $this->displayname = $block->get_title();

        } else {
            parent::init_display_name();
        }
    }

    public function get_settings_section_name() {
        return 'blocksetting' . $this->name;
    }

    public function load_settings(part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig) {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE; // in case settings.php wants to refer to them
        $ADMIN = $adminroot; // may be used in settings.php
        $block = $this; // also can be used inside settings.php

        if (!$this->rootdir) {
            // Plugin missing.
            return;
        }

        $section = $this->get_settings_section_name();

        if (!$hassiteconfig || (($blockinstance = block_instance($this->name)) === false)) {
            return;
        }

        $settings = null;
        if ($blockinstance->has_config()) {
            if (file_exists($this->full_path('settings.php'))) {
                $settings = new admin_settingpage($section, $this->displayname,
                        'moodle/site:config', $this->is_enabled() === false);
                include($this->full_path('settings.php')); // this may also set $settings to null
            }
        }
        if ($settings) {
            $ADMIN->add($parentnodename, $settings);
        }
    }

    public function is_uninstall_allowed() {
        return true;
    }

    /**
     * Warnign with number of block instances.
     *
     * @return string
     */
    public function get_uninstall_extra_warning() {
        global $DB;

        if (!$count = $DB->count_records('block_instances', array('blockname'=>$this->name))) {
            return '';
        }

        return '<p>'.get_string('uninstallextraconfirmblock', 'core_plugin', array('instances'=>$count)).'</p>';
    }

    /**
     * Pre-uninstall hook.
     *
     * This is intended for disabling of plugin, some DB table purging, etc.
     *
     * NOTE: to be called from uninstall_plugin() only.
     * @private
     */
    public function uninstall_cleanup() {
        global $DB, $CFG;

        if ($block = $DB->get_record('block', array('name'=>$this->name))) {
            // Inform block it's about to be deleted
            if (file_exists("$CFG->dirroot/blocks/$block->name/block_$block->name.php")) {
                $blockobject = block_instance($block->name);
                if ($blockobject) {
                    $blockobject->before_delete();  //only if we can create instance, block might have been already removed
                }
            }

            // First delete instances and related contexts
            $instances = $DB->get_records('block_instances', array('blockname' => $block->name));
            foreach($instances as $instance) {
                blocks_delete_instance($instance);
            }

            // Delete block
            $DB->delete_records('block', array('id'=>$block->id));
        }

        parent::uninstall_cleanup();
    }
}


/**
 * Class for text filters
 */
class plugininfo_filter extends plugininfo_base {

    public function init_display_name() {
        if (!get_string_manager()->string_exists('filtername', $this->component)) {
            $this->displayname = '[filtername,' . $this->component . ']';
        } else {
            $this->displayname = get_string('filtername', $this->component);
        }
    }

    /**
     * Finds all enabled plugins, the result may include missing plugins.
     * @return array|null of enabled plugins $pluginname=>$pluginname, null means unknown
     */
    public static function get_enabled_plugins() {
        global $DB, $CFG;
        require_once("$CFG->libdir/filterlib.php");

        $enabled = array();
        $filters = $DB->get_records_select('filter_active', "active <> :disabled", array('disabled'=>TEXTFILTER_DISABLED), 'filter ASC', 'id, filter');
        foreach ($filters as $filter) {
            $enabled[$filter->filter] = $filter->filter;
        }

        return $enabled;
    }

    public function get_settings_section_name() {
        return 'filtersetting' . $this->name;
    }

    public function load_settings(part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig) {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE; // in case settings.php wants to refer to them
        $ADMIN = $adminroot; // may be used in settings.php
        $filter = $this; // also can be used inside settings.php

        if (!$this->rootdir) {
            // Plugin missing.
            return;
        }

        $settings = null;
        if ($hassiteconfig && file_exists($this->full_path('filtersettings.php'))) {
            $section = $this->get_settings_section_name();
            $settings = new admin_settingpage($section, $this->displayname,
                    'moodle/site:config', $this->is_enabled() === false);
            include($this->full_path('filtersettings.php')); // this may also set $settings to null
        }
        if ($settings) {
            $ADMIN->add($parentnodename, $settings);
        }
    }

    public function is_uninstall_allowed() {
        return true;
    }

    /**
     * Pre-uninstall hook.
     *
     * This is intended for disabling of plugin, some DB table purging, etc.
     *
     * NOTE: to be called from uninstall_plugin() only.
     * @private
     */
    public function uninstall_cleanup() {
        global $DB;

        $DB->delete_records('filter_active', array('filter' => $this->name));
        $DB->delete_records('filter_config', array('filter' => $this->name));

        parent::uninstall_cleanup();
    }
}


/**
 * Class for activity modules
 */
class plugininfo_mod extends plugininfo_base {
    /**
     * Finds all enabled plugins, the result may include missing plugins.
     * @return array|null of enabled plugins $pluginname=>$pluginname, null means unknown
     */
    public static function get_enabled_plugins() {
        global $DB;
        return $DB->get_records_menu('modules', array('visible'=>1), 'name ASC', 'name, name AS val');
    }

    /**
     * Magic method getter, redirects to read only values.
     *
     * For module plugins we pretend the object has 'visible' property for compatibility
     * with plugins developed for Moodle version below 2.4
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        if ($name === 'visible') {
            debugging('This is now an instance of plugininfo_mod, please use $module->is_enabled() instead of $module->visible', DEBUG_DEVELOPER);
            return ($this->is_enabled() !== false);
        }
        return parent::__get($name);
    }

    public function init_display_name() {
        if (get_string_manager()->string_exists('pluginname', $this->component)) {
            $this->displayname = get_string('pluginname', $this->component);
        } else {
            $this->displayname = get_string('modulename', $this->component);
        }
    }

    public function get_settings_section_name() {
        return 'modsetting' . $this->name;
    }

    public function load_settings(part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig) {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE; // in case settings.php wants to refer to them
        $ADMIN = $adminroot; // may be used in settings.php
        $module = $this; // also can be used inside settings.php

        if (!$this->rootdir) {
            // Plugin missing.
            return;
        }

        $section = $this->get_settings_section_name();

        $settings = null;
        if ($hassiteconfig && file_exists($this->full_path('settings.php'))) {
            $settings = new admin_settingpage($section, $this->displayname,
                    'moodle/site:config', $this->is_enabled() === false);
            include($this->full_path('settings.php')); // this may also set $settings to null
        }
        if ($settings) {
            $ADMIN->add($parentnodename, $settings);
        }
    }

    /**
     * Allow all activity modules but Forum to be uninstalled.

     * This exception for the Forum has been hard-coded in Moodle since ages,
     * we may want to re-think it one day.
     */
    public function is_uninstall_allowed() {
        if ($this->name === 'forum') {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Return warning with number of activities and number of affected courses.
     *
     * @return string
     */
    public function get_uninstall_extra_warning() {
        global $DB;

        if (!$module = $DB->get_record('modules', array('name'=>$this->name))) {
            return '';
        }

        if (!$count = $DB->count_records('course_modules', array('module'=>$module->id))) {
            return '';
        }

        $sql = "SELECT COUNT('x')
                  FROM (
                    SELECT course
                      FROM {course_modules}
                     WHERE module = :mid
                  GROUP BY course
                  ) c";
        $courses = $DB->count_records_sql($sql, array('mid'=>$module->id));

        return '<p>'.get_string('uninstallextraconfirmmod', 'core_plugin', array('instances'=>$count, 'courses'=>$courses)).'</p>';
    }

    /**
     * Pre-uninstall hook.
     *
     * This is intended for disabling of plugin, some DB table purging, etc.
     *
     * NOTE: to be called from uninstall_plugin() only.
     * @private
     */
    public function uninstall_cleanup() {
        global $DB, $CFG;

        if (!$module = $DB->get_record('modules', array('name' => $this->name))) {
            parent::uninstall_cleanup();
            return;
        }

        // Delete all the relevant instances from all course sections.
        if ($coursemods = $DB->get_records('course_modules', array('module' => $module->id))) {
            foreach ($coursemods as $coursemod) {
                // Do not verify results, there is not much we can do anyway.
                delete_mod_from_section($coursemod->id, $coursemod->section);
            }
        }

        // Increment course.cacherev for courses that used this module.
        // This will force cache rebuilding on the next request.
        increment_revision_number('course', 'cacherev',
            "id IN (SELECT DISTINCT course
                      FROM {course_modules}
                     WHERE module=?)",
            array($module->id));

        // Delete all the course module records.
        $DB->delete_records('course_modules', array('module' => $module->id));

        // Delete module contexts.
        if ($coursemods) {
            foreach ($coursemods as $coursemod) {
                context_helper::delete_instance(CONTEXT_MODULE, $coursemod->id);
            }
        }

        // Delete the module entry itself.
        $DB->delete_records('modules', array('name' => $module->name));

        // Cleanup the gradebook.
        require_once($CFG->libdir.'/gradelib.php');
        grade_uninstalled_module($module->name);

        // Do not look for legacy $module->name . '_uninstall any more,
        // they should have migrated to db/uninstall.php by now.

        parent::uninstall_cleanup();
    }
}


/**
 * Class for question behaviours.
 */
class plugininfo_qbehaviour extends plugininfo_base {

    public function is_uninstall_allowed() {
        return true;
    }

    public function get_uninstall_url() {
        return new moodle_url('/admin/qbehaviours.php',
                array('delete' => $this->name, 'sesskey' => sesskey()));
    }
}


/**
 * Class for question types
 */
class plugininfo_qtype extends plugininfo_base {

    public function is_uninstall_allowed() {
        return true;
    }

    public function get_uninstall_url() {
        return new moodle_url('/admin/qtypes.php',
                array('delete' => $this->name, 'sesskey' => sesskey()));
    }

    public function get_settings_section_name() {
        return 'qtypesetting' . $this->name;
    }

    public function load_settings(part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig) {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE; // in case settings.php wants to refer to them
        $ADMIN = $adminroot; // may be used in settings.php
        $qtype = $this; // also can be used inside settings.php

        if (!$this->rootdir) {
            // Most probably somebody deleted dir without proper uninstall.
            return;
        }
        $section = $this->get_settings_section_name();

        $settings = null;
        $systemcontext = context_system::instance();
        if (($hassiteconfig || has_capability('moodle/question:config', $systemcontext)) &&
                file_exists($this->full_path('settings.php'))) {
            $settings = new admin_settingpage($section, $this->displayname,
                    'moodle/question:config', $this->is_enabled() === false);
            include($this->full_path('settings.php')); // this may also set $settings to null
        }
        if ($settings) {
            $ADMIN->add($parentnodename, $settings);
        }
    }
}


/**
 * Class for authentication plugins
 */
class plugininfo_auth extends plugininfo_base {
    /**
     * Finds all enabled plugins, the result may include missing plugins.
     * @return array|null of enabled plugins $pluginname=>$pluginname, null means unknown
     */
    public static function get_enabled_plugins() {
        global $CFG;

        // These two are always enabled and can't be disabled.
        $enabled = array('nologin'=>'nologin', 'manual'=>'manual');
        foreach (explode(',', $CFG->auth) as $auth) {
            $enabled[$auth] = $auth;
        }

        return $enabled;
    }

    public function get_settings_section_name() {
        return 'authsetting' . $this->name;
    }

    public function load_settings(part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig) {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE; // in case settings.php wants to refer to them
        $ADMIN = $adminroot; // may be used in settings.php
        $auth = $this; // also to be used inside settings.php

        if (!$this->rootdir) {
            // Plugin missing.
            return;
        }

        $section = $this->get_settings_section_name();

        $settings = null;
        if ($hassiteconfig) {
            if (file_exists($this->full_path('settings.php'))) {
                // TODO: finish implementation of common settings - locking, etc.
                $settings = new admin_settingpage($section, $this->displayname,
                        'moodle/site:config', $this->is_enabled() === false);
                include($this->full_path('settings.php')); // this may also set $settings to null
            } else {
                $settingsurl = new moodle_url('/admin/auth_config.php', array('auth' => $this->name));
                $settings = new admin_externalpage($section, $this->displayname,
                        $settingsurl, 'moodle/site:config', $this->is_enabled() === false);
            }
        }
        if ($settings) {
            $ADMIN->add($parentnodename, $settings);
        }
    }
}


/**
 * Class for enrolment plugins
 */
class plugininfo_enrol extends plugininfo_base {
    /**
     * Finds all enabled plugins, the result may include missing plugins.
     * @return array|null of enabled plugins $pluginname=>$pluginname, null means unknown
     */
    public static function get_enabled_plugins() {
        global $CFG;

        $enabled = array();
        foreach (explode(',', $CFG->enrol_plugins_enabled) as $enrol) {
            $enabled[$enrol] = $enrol;
        }

        return $enabled;
    }

    public function get_settings_section_name() {
        if (file_exists($this->full_path('settings.php'))) {
            return 'enrolsettings' . $this->name;
        } else {
            return null;
        }
    }

    public function load_settings(part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig) {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE; // in case settings.php wants to refer to them

        if (!$this->rootdir) {
            // Plugin missing.
            return;
        }

        if (!$hassiteconfig or !file_exists($this->full_path('settings.php'))) {
            return;
        }
        $section = $this->get_settings_section_name();

        $ADMIN = $adminroot; // may be used in settings.php
        $enrol = $this; // also can be used inside settings.php
        $settings = new admin_settingpage($section, $this->displayname,
                'moodle/site:config', $this->is_enabled() === false);

        include($this->full_path('settings.php')); // This may also set $settings to null!

        if ($settings) {
            $ADMIN->add($parentnodename, $settings);
        }
    }

    public function is_uninstall_allowed() {
        if ($this->name === 'manual') {
            return false;
        }
        return true;
    }

    /**
     * Return warning with number of activities and number of affected courses.
     *
     * @return string
     */
    public function get_uninstall_extra_warning() {
        global $DB, $OUTPUT;

        $sql = "SELECT COUNT('x')
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.enrol = :plugin";
        $count = $DB->count_records_sql($sql, array('plugin'=>$this->name));

        if (!$count) {
            return '';
        }

        $migrateurl = new moodle_url('/admin/enrol.php', array('action'=>'migrate', 'enrol'=>$this->name, 'sesskey'=>sesskey()));
        $migrate = new single_button($migrateurl, get_string('migratetomanual', 'core_enrol'));
        $button = $OUTPUT->render($migrate);

        $result = '<p>'.get_string('uninstallextraconfirmenrol', 'core_plugin', array('enrolments'=>$count)).'</p>';
        $result .= $button;

        return $result;
    }

    /**
     * Pre-uninstall hook.
     *
     * This is intended for disabling of plugin, some DB table purging, etc.
     *
     * NOTE: to be called from uninstall_plugin() only.
     * @private
     */
    public function uninstall_cleanup() {
        global $DB, $CFG;

        // NOTE: this is a bit brute force way - it will not trigger events and hooks properly.

        // Nuke all role assignments.
        role_unassign_all(array('component'=>'enrol_'.$this->name));

        // Purge participants.
        $DB->delete_records_select('user_enrolments', "enrolid IN (SELECT id FROM {enrol} WHERE enrol = ?)", array($this->name));

        // Purge enrol instances.
        $DB->delete_records('enrol', array('enrol'=>$this->name));

        // Tweak enrol settings.
        if (!empty($CFG->enrol_plugins_enabled)) {
            $enabledenrols = explode(',', $CFG->enrol_plugins_enabled);
            $enabledenrols = array_unique($enabledenrols);
            $enabledenrols = array_flip($enabledenrols);
            unset($enabledenrols[$this->name]);
            $enabledenrols = array_flip($enabledenrols);
            if (is_array($enabledenrols)) {
                set_config('enrol_plugins_enabled', implode(',', $enabledenrols));
            }
        }

        parent::uninstall_cleanup();
    }
}


/**
 * Class for messaging processors
 */
class plugininfo_message extends plugininfo_base {
    /**
     * Finds all enabled plugins, the result may include missing plugins.
     * @return array|null of enabled plugins $pluginname=>$pluginname, null means unknown
     */
    public static function get_enabled_plugins() {
        global $DB;
        return $DB->get_records_menu('message_processors', array('enabled'=>1), 'name ASC', 'name, name AS val');
    }

    public function get_settings_section_name() {
        return 'messagesetting' . $this->name;
    }

    public function load_settings(part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig) {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE; // in case settings.php wants to refer to them
        $ADMIN = $adminroot; // may be used in settings.php

        if (!$this->rootdir) {
            // Plugin missing.
            return;
        }

        if (!$hassiteconfig) {
            return;
        }
        $section = $this->get_settings_section_name();

        $settings = null;
        $processors = get_message_processors();
        if (isset($processors[$this->name])) {
            $processor = $processors[$this->name];
            if ($processor->available && $processor->hassettings) {
                $settings = new admin_settingpage($section, $this->displayname,
                        'moodle/site:config', $this->is_enabled() === false);
                include($this->full_path('settings.php')); // this may also set $settings to null
            }
        }
        if ($settings) {
            $ADMIN->add($parentnodename, $settings);
        }
    }

    public function is_uninstall_allowed() {
        return true;
    }

    /**
     * Pre-uninstall hook.
     *
     * This is intended for disabling of plugin, some DB table purging, etc.
     *
     * NOTE: to be called from uninstall_plugin() only.
     * @private
     */
    public function uninstall_cleanup() {
        global $CFG;

        require_once($CFG->libdir.'/messagelib.php');
        message_processor_uninstall($this->name);

        parent::uninstall_cleanup();
    }
}


/**
 * Class for repositories
 */
class plugininfo_repository extends plugininfo_base {
    /**
     * Finds all enabled plugins, the result may include missing plugins.
     * @return array|null of enabled plugins $pluginname=>$pluginname, null means unknown
     */
    public static function get_enabled_plugins() {
        global $DB;
        return $DB->get_records_menu('repository', array('visible'=>1), 'type ASC', 'type, type AS val');
    }

    public function get_settings_section_name() {
        return 'repositorysettings'.$this->name;
    }

    public function load_settings(part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig) {
        if (!$this->rootdir) {
            // Plugin missing.
            return;
        }

        if ($hassiteconfig && $this->is_enabled()) {
            // completely no access to repository setting when it is not enabled
            $sectionname = $this->get_settings_section_name();
            $settingsurl = new moodle_url('/admin/repository.php',
                    array('sesskey' => sesskey(), 'action' => 'edit', 'repos' => $this->name));
            $settings = new admin_externalpage($sectionname, $this->displayname,
                    $settingsurl, 'moodle/site:config', false);
            $adminroot->add($parentnodename, $settings);
        }
    }
}


/**
 * Class for portfolios
 */
class plugininfo_portfolio extends plugininfo_base {
    /**
     * Finds all enabled plugins, the result may include missing plugins.
     * @return array|null of enabled plugins $pluginname=>$pluginname, null means unknown
     */
    public static function get_enabled_plugins() {
        global $DB;

        $enabled = array();
        $rs = $DB->get_recordset('portfolio_instance', array('visible'=>1), 'plugin ASC', 'plugin');
        foreach ($rs as $repository) {
            $enabled[$repository->plugin] = $repository->plugin;
        }

        return $enabled;
    }
}


/**
 * Class for themes
 */
class plugininfo_theme extends plugininfo_base {

    public function is_enabled() {
        global $CFG;

        if ((!empty($CFG->theme) and $CFG->theme === $this->name) or
            (!empty($CFG->themelegacy) and $CFG->themelegacy === $this->name)) {
            return true;
        } else {
            return parent::is_enabled();
        }
    }
}


/**
 * Class representing an MNet service
 */
class plugininfo_mnetservice extends plugininfo_base {

    public function is_enabled() {
        global $CFG;

        if (empty($CFG->mnet_dispatcher_mode) || $CFG->mnet_dispatcher_mode !== 'strict') {
            return false;
        } else {
            return parent::is_enabled();
        }
    }
}


/**
 * Class for admin tool plugins
 */
class plugininfo_tool extends plugininfo_base {

    public function is_uninstall_allowed() {
        return true;
    }
}


/**
 * Class for admin tool plugins
 */
class plugininfo_report extends plugininfo_base {

    public function is_uninstall_allowed() {
        return true;
    }
}


/**
 * Class for local plugins
 */
class plugininfo_local extends plugininfo_base {

    public function is_uninstall_allowed() {
        return true;
    }
}

/**
 * Class for HTML editors
 */
class plugininfo_editor extends plugininfo_base {
    /**
     * Finds all enabled plugins, the result may include missing plugins.
     * @return array|null of enabled plugins $pluginname=>$pluginname, null means unknown
     */
    public static function get_enabled_plugins() {
        global $CFG;

        if (empty($CFG->texteditors)) {
            return array('tinymce'=>'tinymce', 'textarea'=>'textarea');
        }

        $enabled = array();
        foreach (explode(',', $CFG->texteditors) as $editor) {
            $enabled[$editor] = $editor;
        }

        return $enabled;
    }

    public function get_settings_section_name() {
        return 'editorsettings' . $this->name;
    }

    public function load_settings(part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig) {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE; // in case settings.php wants to refer to them
        $ADMIN = $adminroot; // may be used in settings.php
        $editor = $this; // also can be used inside settings.php

        if (!$this->rootdir) {
            // Plugin missing.
            return;
        }

        $section = $this->get_settings_section_name();

        $settings = null;
        if ($hassiteconfig && file_exists($this->full_path('settings.php'))) {
            $settings = new admin_settingpage($section, $this->displayname,
                    'moodle/site:config', $this->is_enabled() === false);
            include($this->full_path('settings.php')); // this may also set $settings to null
        }
        if ($settings) {
            $ADMIN->add($parentnodename, $settings);
        }
    }

    /**
     * Basic textarea editor can not be uninstalled.
     */
    public function is_uninstall_allowed() {
        if ($this->name === 'textarea') {
            return false;
        } else {
            return true;
        }
    }
}

/**
 * Class for plagiarism plugins
 */
class plugininfo_plagiarism extends plugininfo_base {

    public function get_settings_section_name() {
        return 'plagiarism'. $this->name;
    }

    public function load_settings(part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig) {
        if (!$this->rootdir) {
            // Plugin missing.
            return;
        }

        // plagiarism plugin just redirect to settings.php in the plugins directory
        if ($hassiteconfig && file_exists($this->full_path('settings.php'))) {
            $section = $this->get_settings_section_name();
            $settingsurl = new moodle_url($this->get_dir().'/settings.php');
            $settings = new admin_externalpage($section, $this->displayname,
                    $settingsurl, 'moodle/site:config', $this->is_enabled() === false);
            $adminroot->add($parentnodename, $settings);
        }
    }

    public function is_uninstall_allowed() {
        return true;
    }
}

/**
 * Class for webservice protocols
 */
class plugininfo_webservice extends plugininfo_base {
    /**
     * Finds all enabled plugins, the result may include missing plugins.
     * @return array|null of enabled plugins $pluginname=>$pluginname, null means unknown
     */
    public static function get_enabled_plugins() {
        global $CFG;

        if (empty($CFG->enablewebservices) or empty($CFG->webserviceprotocols)) {
            return array();
        }

        $enabled = array();
        foreach (explode(',', $CFG->webserviceprotocols) as $protocol) {
            $enabled[$protocol] = $protocol;
        }

        return $enabled;
    }

    public function get_settings_section_name() {
        return 'webservicesetting' . $this->name;
    }

    public function load_settings(part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig) {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE; // in case settings.php wants to refer to them
        $ADMIN = $adminroot; // may be used in settings.php
        $webservice = $this; // also can be used inside settings.php

        if (!$this->rootdir) {
            // Plugin missing.
            return;
        }

        $section = $this->get_settings_section_name();

        $settings = null;
        if ($hassiteconfig && file_exists($this->full_path('settings.php'))) {
            $settings = new admin_settingpage($section, $this->displayname,
                    'moodle/site:config', $this->is_enabled() === false);
            include($this->full_path('settings.php')); // this may also set $settings to null
        }
        if ($settings) {
            $ADMIN->add($parentnodename, $settings);
        }
    }

    public function is_uninstall_allowed() {
        return false;
    }
}

/**
 * Class for course formats
 */
class plugininfo_format extends plugininfo_base {
    /**
     * Finds all enabled plugins, the result may include missing plugins.
     * @return array|null of enabled plugins $pluginname=>$pluginname, null means unknown
     */
    public static function get_enabled_plugins() {
        global $DB;

        $plugins = plugin_manager::instance()->get_installed_plugins('format');
        if (!$plugins) {
            return array();
        }
        $installed = array();
        foreach ($plugins as $plugin => $version) {
            $installed[] = 'format_'.$plugin;
        }

        list($installed, $params) = $DB->get_in_or_equal($installed, SQL_PARAMS_NAMED);
        $disabled = $DB->get_recordset_select('config_plugins', "plugin $installed AND name = 'disabled'", $params, 'plugin ASC');
        foreach ($disabled as $conf) {
            if (empty($conf->value)) {
                continue;
            }
            list($type, $name) = explode('_', $conf->plugin, 2);
            unset($plugins[$name]);
        }

        $enabled = array();
        foreach ($plugins as $plugin => $version) {
            $enabled[$plugin] = $plugin;
        }

        return $enabled;
    }

    /**
     * Gathers and returns the information about all plugins of the given type
     *
     * @param string $type the name of the plugintype, eg. mod, auth or workshopform
     * @param string $typerootdir full path to the location of the plugin dir
     * @param string $typeclass the name of the actually called class
     * @return array of plugintype classes, indexed by the plugin name
     */
    public static function get_plugins($type, $typerootdir, $typeclass) {
        global $CFG;
        $formats = parent::get_plugins($type, $typerootdir, $typeclass);
        require_once($CFG->dirroot.'/course/lib.php');
        $order = get_sorted_course_formats();
        $sortedformats = array();
        foreach ($order as $formatname) {
            $sortedformats[$formatname] = $formats[$formatname];
        }
        return $sortedformats;
    }

    public function get_settings_section_name() {
        return 'formatsetting' . $this->name;
    }

    public function load_settings(part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig) {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE; // in case settings.php wants to refer to them
        $ADMIN = $adminroot; // also may be used in settings.php

        if (!$this->rootdir) {
            // Plugin missing.
            return;
        }

        $section = $this->get_settings_section_name();

        $settings = null;
        if ($hassiteconfig && file_exists($this->full_path('settings.php'))) {
            $settings = new admin_settingpage($section, $this->displayname,
                    'moodle/site:config', $this->is_enabled() === false);
            include($this->full_path('settings.php')); // this may also set $settings to null
        }
        if ($settings) {
            $ADMIN->add($parentnodename, $settings);
        }
    }

    public function is_uninstall_allowed() {
        if ($this->name !== get_config('moodlecourse', 'format') && $this->name !== 'site') {
            return true;
        } else {
            return false;
        }
    }

    public function get_uninstall_extra_warning() {
        global $DB;

        $coursecount = $DB->count_records('course', array('format' => $this->name));

        if (!$coursecount) {
            return '';
        }

        $defaultformat = $this->get_plugin_manager()->plugin_name('format_'.get_config('moodlecourse', 'format'));
        $message = get_string(
            'formatuninstallwithcourses', 'core_admin',
            (object)array('count' => $coursecount, 'format' => $this->displayname,
            'defaultformat' => $defaultformat));

        return $message;
    }

    /**
     * Pre-uninstall hook.
     *
     * This is intended for disabling of plugin, some DB table purging, etc.
     *
     * NOTE: to be called from uninstall_plugin() only.
     * @private
     */
    public function uninstall_cleanup() {
        global $DB;

        if (($defaultformat = get_config('moodlecourse', 'format')) && $defaultformat !== $this->name) {
            $courses = $DB->get_records('course', array('format' => $this->name), 'id');
            $data = (object)array('id' => null, 'format' => $defaultformat);
            foreach ($courses as $record) {
                $data->id = $record->id;
                update_course($data);
            }
        }

        $DB->delete_records('course_format_options', array('format' => $this->name));

        parent::uninstall_cleanup();
    }
}
