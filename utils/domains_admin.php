<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2024 Aleksey Andreev (liuch)
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
 * This script is designed to manipulate the list of domains in the database.
 * Usage: php domains_admin.php <command> [<args>]
 * The available commands are:
 *   `list`    - Outputs a list of domains sorted by FQDN. No parameters.
 *   `add`     - Adds a new domain to the database. Parameters:
 *               name=<FQDN>                       Required.
 *               active=<true|false>               Optional. The default value is false. The valid values are:
 *                                                 true, false, 1, 0, yes, no. The value is case insensitive.
 *               description=<description>         Optional. The default value is null.
 *   `show`    - Displays domain information. Parameters:
 *               id=<domain ID>|name=<domain name> Required.
 *   `modify`  - Modifies domain. Parameters:
 *               id=<domain ID>|name=<domain name> Required.
 *               active=<true|false>               Optional.
 *               description=<description>         Optional.
 *               Note: The command changes only the specified domain data. The rest of the data remains unchanged.
 *   `delete`  - Deletes a domain from the database. Parameters:
 *               id=<domain ID>|name=<domain name> Required.
 *
 * Some examples:
 *
 * $php utils/domains_admin.php list
 * will display a list of available domains from the database
 *
 * $php utils/domains_admin.php add name=b.net active=yes description=The\ backup\ mail\ domain
 * will create a new domain b.net with description. Do not forget to escape special characters!
 *
 * $php utils/domains_admin.php show id=1
 * will display information about the domain with ID=1
 *
 * $php utils/domains_admin.php show name=b.net
 * will display information about the domain b.net
 *
 * $php utils/domains_admin.php modify id=1 active=no
 * will deactivate the domain with ID=1
 *
 * $php utils/domains_admin.php delete name=b.net
 * will delete the domain named b.net
 *
 * @category Utilities
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Domains\Domain;
use Liuch\DmarcSrg\Domains\DomainList;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\RuntimeException;

require realpath(__DIR__ . '/..') . '/init.php';

if (php_sapi_name() !== 'cli') {
    echo 'Forbidden';
    exit(1);
}

if (!isset($argv)) {
    echo 'Cannot get the script arguments. Probably register_argc_argv is disabled.', PHP_EOL;
    exit(1);
}

$parseArguments = function (array $allowed) use (&$argv): array {
    $res = [];
    if (count($argv) > 2) {
        for ($i = 2; $i < count($argv); ++$i) {
            $av = explode('=', $argv[$i], 2);
            if (count($av) == 1 || !in_array($av[0], $allowed)) {
                throw new SoftException("Incorrect parameter \"{$av[0]}\"");
            }
            $res[$av[0]] = $av[1];
        }
    }
    return $res;
};
$getActiveParameter = function (array $args) {
    $ac = array_search(strtolower($args['active']), [ '', '0', 'false', 'no', '1', 'true', 'yes' ], true);
    if ($ac === false) {
        throw new SoftException('Incorrect value of the "active" paramenter');
    }
    return $ac > 3;
};
$getDomain = function (array $args) {
    if (isset($args['id'])) {
        if (isset($args['name'])) {
            throw new SoftException('To identify a domain you need to specify either its id or name, not both');
        }
        $id = filter_var($args['id'], FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 0 ] ]);
        if (!$id) {
            throw new SoftException('Incorrect domain ID');
        }
        $domain = new Domain($id);
    } elseif (!empty($args['name'])) {
        $domain = new Domain($args['name']);
    } else {
        throw new SoftException('Either the "id" or "name" parameter must be specified');
    }
    return $domain;
};

try {
    Core::instance()->user('admin');
    $action = $argv[1] ?? '';
    switch ($action) {
        case 'list':
            $parseArguments([]);
            $list = (new DomainList($core->user()))->getList()['domains'];
            $table = new TextTable([ 'ID', 'FQDN', 'Active', 'Updated', 'Description' ]);
            foreach ($list as $dom) {
                $da = $dom->toArray();
                $table->appendRow([
                    $dom->id(),
                    $da['fqdn'],
                    $da['active'] ? '+' : '',
                    $da['updated_time']->format('c'),
                    empty($da['description']) ? '' : '+'
                ]);
            }
            $table->setMinColumnWidth(1, 15)->sortBy(1)->output();
            break;
        case 'add':
            $args = $parseArguments([ 'name', 'active', 'description' ]);
            if (empty($args['name'])) {
                throw new SoftException('Parameter "name" must be specified');
            }
            $dd = [ 'fqdn' => $args['name'] ];
            $dd['active'] = isset($args['active']) ? $getActiveParameter($args) : false;
            if (!empty($args['description'])) {
                $dd['description'] = $args['description'];
            }
            $domain = new Domain($dd);
            $domain->ensure('nonexist');
            $domain->save();
            echo 'Done.', PHP_EOL;
            break;
        case 'show':
            $domain = $getDomain($parseArguments([ 'id', 'name' ]));
            $dd = $domain->toArray();
            echo 'ID:          ', $domain->id(), PHP_EOL;
            echo 'FQDN:        ', $dd['fqdn'], PHP_EOL;
            echo 'Acitve:      ', ($dd['active'] ? 'Yes' : 'No'), PHP_EOL;
            echo 'Created:     ', $dd['created_time']->format('c'), PHP_EOL;
            echo 'Updated:     ', $dd['updated_time']->format('c'), PHP_EOL;
            if (!empty($dd['description'])) {
                echo 'Description: ', $dd['description'], PHP_EOL;
            }
            break;
        case 'modify':
            $args = $parseArguments([ 'id', 'name', 'active', 'description' ]);
            $domain = $getDomain($args);
            $domain->ensure('exist');
            $mf = false;
            $dd = $domain->toArray();
            if (isset($args['active'])) {
                $ac = $getActiveParameter($args);
                if ($dd['active'] != $ac) {
                    $dd['active'] = $ac;
                    $mf = true;
                }
            }
            if (isset($args['description'])) {
                $ds = $args['description'];
                if ($dd['description'] != $ds) {
                    $dd['description'] = $ds;
                    $mf = true;
                }
            }
            if ($mf) {
                (new Domain($dd))->save();
                echo 'Done.', PHP_EOL;
            } else {
                echo 'There is nothing to change', PHP_EOL;
            }
            break;
        case 'delete':
            $domain = $getDomain($parseArguments([ 'id', 'name' ]));
            $domain->ensure('exist');
            $domain->delete();
            echo 'Done.', PHP_EOL;
            break;
        default:
            echo 'Unknown command ', $action, PHP_EOL, PHP_EOL;
            // no break needed
        case '':
            echo "Usage: {$argv[0]} <command> [<parameters>]", PHP_EOL, PHP_EOL;
            echo 'Commands:', PHP_EOL;
            echo '  list         Outputs a list of domains sorted by FQDN. No parameters.', PHP_EOL;
            echo '  add          Adds a new domain to the database.', PHP_EOL;
            echo '               Required parameter: name.', PHP_EOL;
            echo '               Optional parameters: active, description.', PHP_EOL;
            echo '  show         Displays domain information.', PHP_EOL;
            echo '               Required parameters: id or name.', PHP_EOL;
            echo '  modify       Makes changes to a domain record.', PHP_EOL;
            echo '               Required parameters: id or name.', PHP_EOL;
            echo '               Optional parameters: active, description.', PHP_EOL;
            echo '  delete       Deletes a domain record from the database.', PHP_EOL;
            echo '               Required parameters: id or name.', PHP_EOL, PHP_EOL;
            echo 'Parameters:', PHP_EOL;
            echo '  id           Domain internal ID. Cannot be changed.', PHP_EOL;
            echo '               Used for domain identification only.', PHP_EOL;
            echo '  name         Domain name. Cannot be changed.', PHP_EOL;
            echo '               Used when adding a domain and for it identification.', PHP_EOL;
            echo '  active       Whether the domain is active or not. ', PHP_EOL;
            echo '               Incoming reports for inactive domains are not processed.', PHP_EOL;
            echo '  description  Domain description.', PHP_EOL;
            exit(1);
    }
} catch (SoftException $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
} catch (RuntimeException $e) {
    echo ErrorHandler::exceptionText($e);
    exit(1);
}

exit(0);
