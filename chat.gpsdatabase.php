<?php
 /*********************************************************\
 *                  GPS Database Plugin                    *
 ***********************************************************
 *                        Features                         *
 * - Adds a chatcommand connected to a google spreadsheet  *
 *   database which compares the tmx id with the one	   *
 *   currently played and gives a link to a GPS Video	   *
 * - /gps                                                  *
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
 * - Put this plugin in /XASECO/plugins				       *
 * - activate the plugin in                                *
 *   XASECO/plugins.xml						               *
 \*********************************************************/
global $GPSDB;

Aseco::registerEvent("onStartup", "gpsdb_onStartup");
Aseco::registerEvent('onNewChallenge','gpsdb_onNewTrack');

Aseco::addChatCommand('gps','Sets up the gps command environment');

function gpsdb_onStartup($aseco) {
	global $GPSDB;
	$GPSDB = new GPSDB();
	$GPSDB->onStartup();
}

function gpsdb_onNewTrack($aseco,$challenge) {
	// Get the link if available
	global $GPSDB;
	$GPSDB->onNewTrack($challenge);
}

function chat_gps($aseco,$command) {
	global $GPSDB;
	$GPSDB->onCommand($command);
}

class GPSDB {
	private $config;

	public function onStartup() {
		$this->config['URL'] = "https://docs.google.com/spreadsheets/d/e/2PACX-1vRSB-eBE6wYXDgtTjDEuGyOsO7XDuozJfAlV8Oa_BYdlCYACrAmAa1j1YvYKyIvfWrNLqez13YCu95_/pub?gid=0&single=true&output=csv";
		
		$this->config['TMXRETRYTIME'] = 1; // in seconds | set 0 to only try once

		$this->msgConsole('Plugin GPS Database by malun initialized.');
	}

	public function onCommand($command) {
	global $aseco;
		
		$player = $command['author'];
		$login = $player->login;

		// split params into arrays & insure optional parameters exist cloned from chat.admin.php by Xymph
		$arglist = explode(' ', $command['params'], 2);
		if (!isset($arglist[1])) $arglist[1] = '';
		$command['params'] = explode(' ', preg_replace('/ +/', ' ', $command['params']));
		if (!isset($command['params'][1])) $command['params'][1] = '';

		if ($command['params'][0] == '') {
			if (isset($this->config['VIDEOURL'])) {
				if ($this->config['VIDEOURL']) {
						$this->msgPlayer($login,'GPS available $L[http://youtu.be/' . $this->config['VIDEOURL'] . ']here$z$s.');
				} else {
					$this->msgPlayer($login,'{#error}Could not fetch TMX ID.');
				}
			} else {
				$this->msgPlayer($login,'{#error}This map doesn\'t have a GPS linked yet. Visit $L[http://docs.google.com/spreadsheets/d/1Q626InvUyYXG3iybBJQ-_EZ-HGb27FCLUPlwQSR1NTM/]this spreadsheet$z$s{#error} for further information.');
			}
		} elseif ($command['params'][0] == 'update') {
			if ($aseco->isAnyAdmin($player) || $aseco->isAnyAdmin($player->login)) {
				$this->updateGPSLink($aseco->server->challenge->uid);
				$this->msgPlayer($login,'GPS Link updated.');
			} else {
				$this->msgPlayer($login,'{#error}You don\'t habe the required permissions.');
			}
		} else {
			$this->msgPlayer($login,'{#error}Use /gps to receive a link to a GPS Video of the running map.');
		}
	}

	public function onNewTrack($challenge) {
		if (!isset($this->config['UID']) || $this->config['UID'] != $challenge->uid) {
			$this->updateGPSLink($challenge->uid);
		}
	}

	private function updateGPSLink($uid) {
		unset($this->config['VIDEOURL']);

		$sheetdata = $this->getCSVData($this->config['URL']);

		$this->config['UID'] = $uid;

		$tmxid = $this->getTMXId($this->config['UID']);

		if ($tmxid) {
			foreach ($sheetdata AS &$map) {
				if (strpos($map,$tmxid) !== false) {
					$mapinfo = explode(",",$map);
					$this->config['VIDEOURL'] = $mapinfo[1];
					break;
				}
			}
		} else {
			$this->config['VIDEOURL'] = false;
		}
	}

	private function getCSVData($url) {
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		$gpsdb_data = explode("\n",curl_exec($curl));
		curl_close($curl);
		unset($gpsdb_data[0]);
		return $gpsdb_data;
	}

	private function getTMXId($uid) {
	global $aseco;

		if($aseco->server->packmask == "Stadium") {
			$firstSection = "TMNF";
			$secondSection = "TMU";
		} else {
			$firstSection = "TMU";
			$secondSection = "TMNF";
		}
		
		$timestamp = time();

		if ($this->config['TMXRETRYTIME'] > 0) {
			while(!isset($data->id) AND time() <= $timestamp + $this->config['TMXRETRYTIME']) {
				$data = new \TMXInfoFetcher($firstSection, $uid, false);
			}
		} else {
			$data = new \TMXInfoFetcher($firstSection, $uid, false);
		}

		if (isset($data)) {
			return $data->id;
		} else {
			return false;
		}
	}

	private function msgPlayer($login,$msg) {
		global $aseco;

		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> ' . $msg), $login);
	}

	private function msgAll($msg) {
		global $aseco;

		$aseco->client->query('ChatSendServerMessage', $aseco->formatColors('{#server}> ' . $msg));
	}

	private function msgConsole($msg) {
		global $aseco;

		$aseco->console('[chat.gpsdatabase.php] ' . $msg);
	}
}
?>