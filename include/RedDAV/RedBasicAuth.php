<?php

namespace RedMatrix\RedDAV;

use Sabre\DAV;

/**
 * @brief Authentication backend class for RedDAV.
 *
 * This class also contains some data which is not necessary for authentication
 * like timezone settings.
 *
 * @extends Sabre\DAV\Auth\Backend\AbstractBasic
 *
 * @link http://github.com/friendica/red
 * @license http://opensource.org/licenses/mit-license.php The MIT License (MIT)
 */
class RedBasicAuth extends DAV\Auth\Backend\AbstractBasic {

	/**
	 * @brief The channel_address of the currently locally logged-in channel.
	 *
	 * This variable can be retrieved and manipulated with the methods getCurrentUser()
	 * and setCurrentUser().
	 *
	 * @TODO Need to clean this up and change some names. We should have channel_,
	 * owner_* and observer_*. channel is locally authenticated, owner is where
	 * assets are located and which channel they are associated with, observer is
	 * who is viewing or accessing an asset.
	 *
	 * @var string|null
	 */
	protected $channel_name = null;
	/**
	 * @brief The currently locally logged-in channel's channel_id.
	 *
	 * @var int
	 */
	public $channel_id = 0;
	/**
	 * @brief The currently locally logged-in channel's account_id.
	 *
	 * Used for calculating storage limits per account.
	 *
	 * @var int
	 */
	public $channel_account_id = null;

	/**
	 * @brief The channel_hash of the current visiting channel (observer).
	 *
	 * Set in mod/cloud.php to observer_hash.
	 *
	 * @var string
	 */
	public $observer = '';

	/**
	 * @brief The channel_id of the currently visited path.
	 *
	 * Set in RedDirectory::getDir().
	 *
	 * @var int
	 */
	public $owner_id = 0;
	/**
	 * @brief The channel_address of the currently visited path.
	 *
	 * Set in RedDirectory::getDir().
	 *
	 * Used for creating the path in cloud/
	 *
	 * @var string
	 */
	public $owner_nick = '';

	/**
	 * Timezone from the visiting channel's channel_timezone.
	 *
	 * Used in @ref RedBrowser
	 *
	 * @var string
	 */
	protected $timezone = '';

	/**
	 * @brief Validates a username and password.
	 *
	 * Guest access is granted with the password "+++".
	 *
	 * @see \Sabre\DAV\Auth\Backend\AbstractBasic::validateUserPass
	 * @param string $username
	 * @param string $password
	 * @return bool
	 */
	protected function validateUserPass($username, $password) {
		if (trim($password) === '+++') {
			logger('guest: ' . $username);
			return true;
		}

		require_once('include/auth.php');
		$record = account_verify_password($username, $password);
		if ($record && $record['account_default_channel']) {
			$r = q("SELECT * FROM channel WHERE channel_account_id = %d AND channel_id = %d LIMIT 1",
				intval($record['account_id']),
				intval($record['account_default_channel'])
			);
			if ($r) {
				return $this->setAuthenticated($r[0]);
			}
		}
		$r = q("SELECT * FROM channel WHERE channel_address = '%s' LIMIT 1",
			dbesc($username)
		);
		if ($r) {
			$x = q("SELECT account_flags, account_salt, account_password FROM account WHERE account_id = %d LIMIT 1",
				intval($r[0]['channel_account_id'])
			);
			if ($x) {
				$record = $x[0];
				if (($record['account_flags'] == ACCOUNT_OK) || ($record['account_flags'] == ACCOUNT_UNVERIFIED)
				&& (hash('whirlpool', $record['account_salt'] . $password) === $record['account_password'])) {
					logger('password verified for ' . $username);
					return $this->setAuthenticated($r[0]);
				}
			}
		}

		$error = 'password failed for ' . $username;
		logger($error);
		log_failed_login($error);

		return false;
	}

	/**
	 * @brief Sets variables and session parameters after successfull authentication.
	 *
	 * @param array $r
	 *  Array with the values for the authenticated channel.
	 * @return bool
	 */
	protected function setAuthenticated($r) {
		$this->setCurrentUser($r['channel_address']);
		$this->channel_id = $r['channel_id'];
		$this->observer = $r['channel_hash'];
		$this->channel_account_id = $r['channel_account_id'];
		$_SESSION['uid'] = $r['channel_id'];
		$_SESSION['account_id'] = $r['channel_account_id'];
		$_SESSION['authenticated'] = true;
		return true;
	}

	/**
	 * @brief Sets the channel_name from the currently locally logged-in channel.
	 *
	 * This is the channel_address which is visible in URLs.
	 *
	 * @param string $name The channel_address of the current channel
	 */
	public function setCurrentUser($name) {
		$this->channel_name = $name;
	}
	/**
	 * @brief Returns information about the currently locally logged-in channel.
	 *
	 * If nobody is currently logged in, this method should return null.
	 *
	 * @see \Sabre\DAV\Auth\Backend\AbstractBasic::getCurrentUser
	 * @return string|null the current channel's channel_address
	 */
	public function getCurrentUser() {
		return $this->channel_name;
	}

	/**
	 * @brief Sets the timezone from the channel in RedBasicAuth.
	 *
	 * Set in mod/cloud.php if the channel has a timezone set.
	 *
	 * @param string $timezone
	 *  The channel's timezone.
	 * @return void
	 */
	public function setTimezone($timezone) {
		$this->timezone = $timezone;
	}
	/**
	 * @brief Returns the timezone.
	 *
	 * @return string
	 *  Return the channel's timezone.
	 */
	public function getTimezone() {
		return $this->timezone;
	}

	/**
	 * @brief Prints out all RedBasicAuth variables to logger().
	 *
	 * @return void
	 */
	public function log() {
		logger('channel_name ' . $this->channel_name, LOGGER_DATA);
		logger('channel_id ' . $this->channel_id, LOGGER_DATA);
		logger('observer ' . $this->observer, LOGGER_DATA);
		logger('channel_account_id ' . $this->channel_account_id, LOGGER_DATA);
		logger('owner_id ' . $this->owner_id, LOGGER_DATA);
		logger('owner_nick ' . $this->owner_nick, LOGGER_DATA);
	}
}
