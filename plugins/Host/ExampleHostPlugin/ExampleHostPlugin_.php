<?php

namespace Liuch\DmarcSrg\Plugins;

/**
 * Example of the `host` group plugin.
 *
 * To activate the plugin, rename this file so that the file name (without extension)
 * matches the name of the parent directory.
 *
 * The plugin must implement the `PluginInterface` interface.
 * Currently `hostInformationStart`, `hostInformationFinish` events are processed.
 * In this example, the `removeFields` method handles the `hostInformationStart` event,
 * and the `modifyData` method handles the `hostInformationFinish` event.
 * Note that in the both methods, the data array is passed by reference.
 *
 * The `removeFields` method can be used to filter the data being processed and displayed.
 * Simply delete the fields you do not need in order to exclude them from processing.
 *
 * The `modifyData` method can be used to add, modify, reorder, and rename data items.
 * If data elements are being replaced, such elements should be removed in the first step
 * to eliminate unnecessary work.
 *
 * The plugin replaces two items and add new one in the Main section.
 * Renames one item in the Statistics section, and adds a new section.
 *
 * ---------- Before -----------
 *
 *       HOST INFORMATION
 *
 * IP address:           1.2.3.4
 * rDNS name:    host.domain.ltd
 * Reverse IP:             match
 *
 *          STATISTICS
 *
 * Total reports:              5
 * Total messages:             6
 * Last report:       Jan 1 2001
 *
 *
 * ---------- After ------------
 *
 *       HOST INFORMATION
 *
 * IP address:           1.2.3.4
 * rDNS name:   host.example.com
 * Reverse IP:             match
 * ISP:              ABC Telecom
 *
 *          STATISTICS
 *
 * Total reports:              5
 * Total messages:             6
 * Last report date:  Jan 1 2001
 *
 *      PLUGIN INFORMATION
 *
 * Name:             ExampleHost
 * Version:                  1.0
 */
class ExampleHostPlugin implements PluginInterface
{
    /**
     * Returns the name of the plugin. Used when displaying errors
     *
     * @return string Any string that identifies the plugin
     */
    public function name(): string
    {
        return 'ExampleHostPlugin';
    }

    /**
     * Associates events with handler method names and returns them as an array
     *
     * Available events are: `hostInformationStart`, `hostInformationFinish`
     *
     * @return array
     */
    public function subscribedEvents(): array
    {
        return [
            'hostInformationStart'  => 'removeFields',
            'hostInformationFinish' => 'modifyData'
        ];
    }

    /**
     * Handles the `hostInformationStart` event (see the subscribedEvents method)
     *
     * To prevent the data item from being processed and displayed on the client,
     * just remove the corresponding field from the array by the `fields` key.
     *
     * @param array &$data Array with the following keys:
     *                     `ip`     - Host IP address
     *                     `event`  - Event name
     *                     `fields` - Array of strings with fields to get information for. Can be the following:
     *                                'main.rdns'         - Reverse DNS name
     *                                'main.rip'          - Reverse IP
     *                                'stats.reports'     - Total reports
     *                                'stats.messages'    - Total messages
     *                                'stats.last_report' - Last report date
     *                                Note that the list of fields may vary depending on the client's needs.
     *
     * @return void
     */
    public function removeFields(array &$data): void
    {
        // Note that if you remove the main.rdns item, the main.rip item will not be defined correctly
        // because it relies on the value of main.rdns. So if you override the first one,
        // you should override the second one as well.
        $old_count = count($data['fields']);
        $data['fields'] = array_filter($data['fields'], function ($fld) {
            return $fld !== 'main.rdns' && $fld != 'main.rip';
        });
        $data['dns_removed'] = ($old_count > count($data['fields']));
    }

    /**
     * Handles the `hostInformationFinish` event (see the subscribedEvents method)
     *
     * @param array &$data Array with the following keys:
     *                     `ip`     - Same as in the `removeFields` method
     *                     `event`  - Same as in the `removeFields` method
     *                     `fields` - Same as in the `removeFields` method
     *                     `result` - Array of [ 'field' => 'value' ] items
     *                                The data from this array will be displayed
     *                                in the same order as here, grouped by section name.
     *                                The main section is displayed always displayed first.
     *
     * @return void
     */
    public function modifyData(array &$data): void
    {
        // The host name, reverse IP and ISP name.
        // The main section is always displayed first,
        // so you can add items to the end of the array.
        if ($data['dns_removed']) {
            $data['result'][] = [ 'main.rdns', 'host.example.com' ];
            $data['result'][] = [ 'main.rip', true ];
            $data['result'][] = [ 'main.ISP', 'ABC Telecom' ];
        }

        // Plugin section
        $data['result'][] = [ 'plugin.Name', 'ExampleHost' ];
        $data['result'][] = [ 'plugin.Version', '1.0' ];

        // Renaming a section
        $data['dictionary']['plugin'] = 'Plugin information';

        // Renaming an item
        $data['dictionary']['stats.last_report'] = 'Last report date';
    }
}
