<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2025 Aleksey Andreev (liuch)
 *
 * Available at:
 * https://github.com/liuch/dmarc-srg
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of  MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * =========================
 *
 * This file contains the class PluginManager
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Plugins;

use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\RuntimeException;

/**
 * Class for managing the plugin system
 */
class PluginManager
{
    private static $instance = null;

    private $plugins = [];

    /**
     * Sends an event to plugin that are subscribed to it
     *
     * @param string $group Event group
     * @param string $name  Event name
     * @param array  $data  Array of data to be passed to each plugin
     *
     * @return void
     */
    public static function dispatchEvent(string $group, string $name, array &$data): void
    {
        $plist = self::instance()->loadPlugins($group);
        $data['event'] = $name;
        $plugin = null;
        try {
            foreach ($plist as &$it) {
                $handler = $it['handlers'][$name] ?? null;
                if ($handler) {
                    $plugin = $it['plugin'];
                    $plugin->$handler($data);
                }
            }
            unset($it);
        } catch (SoftException $e) {
            if ($plugin) {
                $e = new SoftException('[' . $plugin->name() . '] ' . $e->getMessage(), $e->getCode());
            }
            throw $e;
        }
    }

    /**
     * Searches and loads plugins for the specified type, if necessary.
     *
     * Splitting plug-ins into groups is necessary to save resources,
     * so that only necessary plug-ins are loaded.
     *
     * @param string $group Event group
     *
     * @return array Array of plugins of the specified group sorted by name
     */
    private function loadPlugins(string $group): array
    {
        if (isset($this->plugins[$group])) {
            return $this->plugins[$group];
        }

        $res = [];
        try {
            $groups_it = new \FilesystemIterator(ROOT_PATH . 'plugins/');
            foreach ($groups_it as $group_fi) {
                if ($group_fi->isDir() && $group_fi->getFilename() === $group) {
                    $plugins_it = new \FileSystemIterator($group_fi->getPathname());
                    foreach ($plugins_it as $plugin_fi) {
                        if ($plugin_fi->isDir()) {
                            $plugin_name = $plugin_fi->getFileName();
                            $plugin_path = $plugin_fi->getPathname() . "/{$plugin_name}.php";
                            if (is_file($plugin_path) && is_readable($plugin_path)) {
                                include_once($plugin_path);
                                $plugin_ns_name = __NAMESPACE__ . '\\' . $plugin_name;
                                $plugin = new $plugin_ns_name();
                                if ($plugin instanceof PluginInterface) {
                                    $res[] = [
                                        'plugin'   => $plugin,
                                        'name'     => $plugin_name,
                                        'handlers' => $plugin->subscribedEvents()
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            throw new RuntimeException('Plugin loading error', -1, $e);
        }
        usort($res, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        $this->plugins[$group] = $res;
        return $res;
    }

    /**
     * Returns an instance of the class
     *
     * @return self
     */
    private static function instance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
