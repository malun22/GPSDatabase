<?php
 /*********************************************************\
 *                  GPS Database Plugin                    *
 ***********************************************************
 *                        Features                         *
 * - Adds a chatcommand connects to a google spreadsheet   *
 *   database which compares the tmx id with the ones in   *
 *   database and gives you a link to a GPS Video		   *
 * - /gps                                                  *
 * - Database gets updated every map skip or manually by   *
 *   using /gps update                                     *
 ***********************************************************
 *                    Created by Malun                     *
 ***********************************************************
 *              Dependencies: tmxinfofetcher.php           *
 ***********************************************************
 *                         License                         *
 * LICENSE: This program is free software: you can         *
 * redistribute it and/or modify it under the terms of the *
 * GNU General Public License as published by the Free     *
 * Software Foundation, either version 3 of the License,   *
 * or (at your option) any later version.                  *
 *                                                         *
 * This program is distributed in the hope that it will be *
 * useful, but WITHOUT ANY WARRANTY; without even the      *
 * implied warranty of MERCHANTABILITY or FITNESS FOR A    *
 * PARTICULAR PURPOSE.  See the GNU General Public License *
 * for more details.                                       *
 *                                                         *
 * You should have received a copy of the GNU General      *
 * Public License along with this program.  If not,        *
 * see <http://www.gnu.org/licenses/>.                     *
 ***********************************************************
 *                       Installation                      *
 * - Put this plugin in /Controllers/XASECO/plugins        *
 * - activate the plugin in                                *
 *   /TMF04445/Controllers/XASECO/plugins.xml              *
 \*********************************************************/
global $spreadsheet_url, $gdb_data;

Aseco::registerEvent('onSync', 'gdb_updateData');
Aseco::registerEvent('onNewChallenge','gdb_updateData');

Aseco::addChatCommand('gps','Sets up the gps command environment');

$spreadsheet_url = "https://docs.google.com/spreadsheets/d/e/2PACX-1vRSB-eBE6wYXDgtTjDEuGyOsO7XDuozJfAlV8Oa_BYdlCYACrAmAa1j1YvYKyIvfWrNLqez13YCu95_/pub?gid=0&single=true&output=csv";

function gdb_updateData() {
global $gdb_data, $spreadsheet_url, $aseco;
	$gdb_data = null;
	if(!ini_set('default_socket_timeout', 15)) echo "<!-- unable to change socket timeout -->";

	if (($handle = fopen($spreadsheet_url, "r")) !== FALSE) {
		while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
			$gdb_data[] = $data;
		}
		fclose($handle);
		$msg = '$z$s>> $0F3GPS Database updated.';
		$aseco->client->query('ChatSendServerMessage', $msg);
	}
	else
		die($aseco->console("[chat.gpsdatabase.php] Problem reading csv"));
}

function chat_gps($aseco, $command) {
global $gdb_data;

	$player = $command['author'];
	$login = $player->login;
	
	// split params into arrays & insure optional parameters exist. Cloned from chat.admin.php by Xymph
	$arglist = explode(' ', $command['params'], 2);
	if (!isset($arglist[1])) $arglist[1] = '';
	$command['params'] = explode(' ', preg_replace('/ +/', ' ', $command['params']));
	if (!isset($command['params'][1])) $command['params'][1] = '';
	
	if ($command['params'][0] == 'update') {
		if ($aseco->isMasteradmin($player) || $aseco->isAdmin($player) || $aseco->isOperator($player)) {
			gdb_updateData();
		} else {
			$msg = '$z$s > $b00You don\'t have the requiered permission to do this.';
			$aseco->client->query('ChatSendServerMessageToLogin', $msg, $login);
		}
	} else {
		if($aseco->server->packmask == "Stadium") {
			$firstSection = "TMNF";
			$secondSection = "TMU";
		} else {
			$firstSection = "TMU";
			$secondSection = "TMNF";
		}
		
		$data = new \TMXInfoFetcher($firstSection, $aseco->server->challenge->uid, false);
		
		$tmxid = $data->id;
		unset($data);
		
		foreach ($gdb_data AS &$map) {
			if ($map[0] == $tmxid) {
				$vid = $map[1];
				break;
			}
		}
		
		if ($vid != NULL) {
			$msg = '$z$s> GPS available $L[http://youtu.be/' . $vid . ']here$z$s.';
		} else {
			$msg = '$z$s> There is no GPS Video linked yet. Visit $L[http://docs.google.com/spreadsheets/d/1Q626InvUyYXG3iybBJQ-_EZ-HGb27FCLUPlwQSR1NTM/]this spreadsheet$z$s to fill the database.';
		}
		$aseco->client->query('ChatSendServerMessageToLogin', $msg, $login);
	}
}
?>