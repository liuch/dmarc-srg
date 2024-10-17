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
 * This script is designed to manipulate the list of users in the database.
 * Usage: php users_admin.php <command> [<args>]
 * The available commands are:
 *   `list`        - Outputs a list of users sorted by username. No parameters.
 *   `add`         - Adds a new user to the database. Parameters:
 *                   name=<username>              Required.
 *                   level=<user|manager>         Access level. Optional. The default value is user.
 *                   enabled=<true|false>         Optional. The default value is false. The valid values are:
 *                                                true, false, 1, 0, yes, no. The value is case insensitive.
 *   `show`        - Displays user information.   Parameters:
 *                   id=<user ID>|name=<username> Required.
 *   `modify`      - Modifies a user. Parameters:
 *                   id=<user ID>|name=<username> Required.
 *                   level=<user|manager>         Access level. Optional. The default value is user.
 *                   enabled=<true|false>         Optional.
 *                   Note: This command changes only the specified user data. The rest of the data remains unchanged.
 *   `setpassword` - Changes user's password. Parameters:
 *                   id=<user ID>|name=<username> Required.
 *                   password=<user password>     Required.
 *   `domains`     - Assigns or unassigns domains to a user. Paremeters:
 *                   id=<user ID>|name=<username> Required.
 *                   add=<user name>              Optional.
 *                   remove=<user name>           Optional.
 *   `delete`      - Deletes a user from the database. Parameters:
 *                   id=<user ID>|name=<username> Required.
 *
 * Some some examples:
 *
 * $php utils/users_admin.php list
 * will display a list of users from the database
 *
 * $php utils/users_admin.php add name=cooluser enabled=yes level=user
 * will create a new user named cooluser and assign it "user" access level.
 *
 * $php utils/users_admin.php show id=1
 * will display information about the user with ID=1
 *
 * $php utils/users_admin.php show name=cooluser
 * will display information about the user cooluser
 *
 * $php utils/users_admin.php modify id=1 enabled=no
 * will deactivate the user with ID=1
 *
 * $php utils/users_admin.php domains id=1 add=a.net
 * will assign the domain a.net to the user with ID=1
 *
 * $php utils/users_admin.php delete name=baduser
 * will delete the user named baduser
 *
 * @category Utilities
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Users\User;
use Liuch\DmarcSrg\Users\DbUser;
use Liuch\DmarcSrg\Users\UserList;
use Liuch\DmarcSrg\Domains\Domain;
use Liuch\DmarcSrg\Domains\DomainList;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\RuntimeException;

require realpath(__DIR__ . '/..') . '/init.php';

if (Core::isWEB()) {
    echo 'Forbidden';
    exit(1);
}

if (!isset($argv)) {
    echo 'Cannot get the script arguments. Probably register_argc_argv is disabled.', PHP_EOL;
    exit(1);
}

$parseArguments = function (array $allowed) use (&$argv): array {
    $res = [];
    for ($i = 2; $i < count($argv); ++$i) {
        $av = explode('=', $argv[$i], 2);
        if (count($av) == 1 || !in_array($av[0], $allowed)) {
            throw new SoftException("Incorrect parameter \"{$av[0]}\"");
        }
        $res[$av[0]] = $av[1];
    }
    return $res;
};
$getLevelParameter = function (array $args) {
    $lvl = strtolower($args['level']);
    if ($lvl === 'admin') {
        $lvl = '';
    }
    try {
        $nlvl = User::stringToLevel($lvl);
    } catch (SoftException $e) {
        throw new SoftException('Incorrect user level. The valid values are: "user", "manager"');
    }
    return $nlvl;
};
$getEnabledParameter = function (array $args) {
    $ac = array_search(strtolower($args['enabled']), [ '', '0', 'false', 'no', '1', 'true', 'yes' ], true);
    if ($ac === false) {
        throw new SoftException('Incorrect value of the "enabled" paramenter');
    }
    return $ac > 3;
};
$getUser = function (array $args) {
    if (isset($args['id'])) {
        if (isset($args['name'])) {
            throw new SoftException('To identify a user you need to specify either its id or name, not both');
        }
        $id = filter_var($args['id'], FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 0 ] ]);
        if (!$id) {
            throw new SoftException('Incorrect user ID');
        }
        $user = new DbUser($id);
    } elseif (!empty($args['name'])) {
        $user = new DbUser($args['name']);
    } else {
        throw new SoftException('Either the "id" or "name" parameter must be specified');
    }
    return $user;
};

try {
    Core::instance()->user('admin');
    $action = $argv[1] ?? '';
    switch ($action) {
        case 'list':
            $parseArguments([]);
            $list = (new UserList())->getList()['users'];
            $table = new TextTable([ 'ID', 'Name', 'Level', 'Enabled', 'Domains', 'Updated' ]);
            foreach ($list as $user) {
                $ua = $user->toArray();
                $table->appendRow([
                    $user->id(),
                    $ua['name'],
                    User::levelToString($ua['level']),
                    $ua['enabled'] ? '+' : '',
                    $ua['domains'],
                    $ua['updated_time']->format('c')
                ]);
            }
            $table->setMinColumnWidth(1, 10)->sortBy(1)->output();
            break;
        case 'add':
            $args = $parseArguments([ 'name', 'level', 'enabled' ]);
            if (empty($args['name'])) {
                throw new SoftException('Parameter "name" must be specified');
            }
            $ud = [ 'name' => $args['name'] ];
            $ud['level']   = isset($args['level']) ? $getLevelParameter($args) : User::LEVEL_USER;
            $ud['enabled'] = isset($args['enabled']) ? $getEnabledParameter($args) : false;
            $user = new DbUser($ud);
            $user->ensure('nonexist');
            $user->save();
            echo 'Done.', PHP_EOL;
            break;
        case 'show':
            $user = $getUser($parseArguments([ 'id', 'name' ]));
            $ud = $user->toArray();
            $domains = (new DomainList($user))->names();
            echo 'ID:          ', $user->id(), PHP_EOL;
            echo 'Name:        ', $ud['name'], PHP_EOL;
            echo 'Level:       ', User::levelToString($ud['level']), PHP_EOL;
            echo 'Enabled:     ', $ud['enabled'] ? 'Yes' : 'No', PHP_EOL;
            echo 'Password:    ', $ud['password'] ? 'Yes' : 'No', PHP_EOL;
            echo 'Domains:     ', ($domains ? implode(', ', $domains) : 'None'), PHP_EOL;
            echo 'Created:     ', $ud['created_time']->format('c'), PHP_EOL;
            echo 'Updated:     ', $ud['updated_time']->format('c'), PHP_EOL;
            break;
        case 'modify':
            $args = $parseArguments([ 'id', 'name', 'level', 'enabled' ]);
            $user = $getUser($args);
            $user->ensure('exist');
            $mf = false;
            $ud = $user->toArray();
            if (isset($args['enabled'])) {
                $en = $getEnabledParameter($args);
                if ($ud['enabled'] !== $en) {
                    $ud['enabled'] = $en;
                    $mf = true;
                }
            }
            if (isset($args['level'])) {
                $lvl = $getLevelParameter($args);
                if ($lvl !== $ud['level']) {
                    $ud['level'] = $getLevelParameter($args);
                    $mf = true;
                }
            }
            if ($mf) {
                (new DbUser($ud))->save();
                echo 'Done.', PHP_EOL;
            } else {
                echo 'There is nothing to change', PHP_EOL;
            }
            break;
        case 'setpassword':
            $args = $parseArguments([ 'id', 'name', 'password' ]);
            $user = $getUser($args);
            if (empty($args['password'])) {
                throw new SoftException('The password must not be empty');
            }
            $user->ensure('exist');
            $user->setPassword($args['password']);
            echo 'Done.', PHP_EOL;
            break;
        case 'domains':
            $args = $parseArguments([ 'id', 'name', 'add', 'remove' ]);
            $user = $getUser($args);
            $action = null;
            $mf = 0;
            foreach ([ 'add', 'remove' ] as $a) {
                if (isset($args[$a])) {
                    $action = $a;
                    if ($mf++) {
                        break;
                    }
                }
            }
            if ($mf === 1) {
                $user->ensure('exist');
                $domain = new Domain($args[$action]);
                $domain->ensure('exist');
                if (!empty($args['add'])) {
                    $domain->assignUser($user);
                } elseif (!empty($args['remove'])) {
                    $domain->unassignUser($user);
                }
                echo 'Done.', PHP_EOL;
            } elseif ($mf === 0) {
                echo 'There is nothing to change', PHP_EOL;
            } else {
                throw new SoftException('You can specify only one of the actions for this command');
            }
            break;
        case 'delete':
            $user = $getUser($parseArguments([ 'id', 'name' ]));
            $user->ensure('exist');
            $user->delete();
            echo 'Done.', PHP_EOL;
            break;
        default:
            echo 'Unknown command ', $action, PHP_EOL, PHP_EOL;
            // no break needed
        case '':
            echo "Usage: {$argv[0]} <command> [<parameters>]", PHP_EOL, PHP_EOL;
            echo 'Commands:', PHP_EOL;
            echo '  list         Outputs a list of users sorted by username. No parameters.', PHP_EOL;
            echo '  add          Adds a new user to the database.', PHP_EOL;
            echo '               Required parameter: name.', PHP_EOL;
            echo '               Optional parameters: enabled, level.', PHP_EOL;
            echo '  show         Displays user information.', PHP_EOL;
            echo '               Required parameters: id or name.', PHP_EOL;
            echo '  modify       Makes changes to a user record.', PHP_EOL;
            echo '               Required parameters: id or name.', PHP_EOL;
            echo '               Optional parameters: level, enabled.', PHP_EOL;
            echo '  setpassword  Changes user\'s password.', PHP_EOL;
            echo '               Required parameters: id or name and password.', PHP_EOL;
            echo '  domains      Assigns or unassigns domains to a user.', PHP_EOL;
            echo '               Required parameters: id or name.', PHP_EOL;
            echo '               Required parameters: add or remove.', PHP_EOL;
            echo '  delete       Deletes a user record from the database.', PHP_EOL;
            echo '               Required parameters: id or name.', PHP_EOL, PHP_EOL;
            echo 'Parameters:', PHP_EOL;
            echo '  id           User internal ID. Cannot be changed.', PHP_EOL;
            echo '               Used for user identification only.', PHP_EOL;
            echo '  name         User name. Cannot be changed.', PHP_EOL;
            echo '               Used when adding a user and for it identification.', PHP_EOL;
            echo '  level        User access level. It can have one of the following values: user, manager.', PHP_EOL;
            echo '  enabled      Whether the user is active or not. One of the following values:', PHP_EOL;
            echo '               1, 0, yes, no, true, false.', PHP_EOL;
            echo '  password     Password string. Used with the setpassword command.', PHP_EOL;
            echo '  add          String with domain to be assigned. Used with the domains command.', PHP_EOL;
            echo '  remove       String with domain to be unassigned. Used with the domains command.', PHP_EOL;
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
