<?php

namespace RedMatrix\RedDAV;

use Sabre\DAV;

/**
 * @brief Basic Authentication Backend class for RedDAV.
 *
 * Checks authentication and provides HTTP basic auth fallback.
 *
 * This class also contains some data which is not necessary for authentication
 * like timezone settings.
 *
 * @license http://opensource.org/licenses/mit-license.php The MIT License (MIT)
 */
class RedBasicAuthBackend extends DAV\Auth\Backend\AbstractBasic {

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
	 * Used for creating the path in cloud/
	 *
	 * @var string
	 */
	public $owner_nick = '';
	/**
	 * @brief The channel_hash of the currently visited path.
	 *
	 * Used for setting DAVACL's owner attribute.
	 *
	 * @var string
	 */
	public $owner_hash = '';

	/**
	 * Timezone from the visiting channel's channel_timezone.
	 *
	 * Used in @ref RedBrowser
	 *
	 * @var string
	 */
	protected $timezone = '';


	/**
	 * @brief Authenticates the user based on the current request.
	 *
	 * If authentication is successful, true must be returned.
	 * If authentication fails, an exception must be thrown.
	 *
	 * When the user it not already authenticated provide a HTTP basic auth.
	 * This is required for WebDAV clients and guest access.
	 *
	 * This method has an index of 10 on beforeMethod.
	 *
	 * @param \Sabre\DAV\Server $server
	 * @param string $realm
	 * @throws \Sabre\DAV\Exception\NotAuthenticated
	 * @throws \Sabre\DAV\Exception\Forbidden
	 * @return boolean true on success, otherwise exception
	 */
	function authenticate(DAV\Server $server, $realm) {
		$ob_hash = get_observer_hash();
		if ($ob_hash) {
			if (local_channel()) {
				$channel = get_app()->get_channel();
				$this->setCurrentUser($channel['channel_address']);
				$this->channel_id = $channel['channel_id'];
				$this->channel_account_id = $channel['channel_account_id'];
				if ($channel['channel_timezone'])
					$this->setTimezone($channel['channel_timezone']);
			}
			$this->observer = $ob_hash;

			// Check if public access is blocked and if it is blocked check
			// if all values (channel_id and observer) are present to be able
			// to check for permissions.
			if (get_config('system', 'block_public') && (! $this->channel_id)) {
				throw new DAV\Exception\Forbidden('Permission denied.');
			}

			// we are logged-in already through local login, ZOT, snakebite?
			return true;
		}

		// The next section of code allows us to bypass prompting for http-auth if a
		// FILE is being accessed anonymously and permissions allow this. This way
		// one can create hotlinks to public media files in their cloud and anonymous
		// viewers won't get asked to login.
		// If a DIRECTORY is accessed or there are permission issues accessing the
		// file and we aren't previously authenticated via zot, prompt for HTTP-auth.
		// This will be the default case for mounting a DAV directory.
		// In order to avoid prompting for passwords for viewing a DIRECTORY, add
		// the URL query parameter 'davguest=1'.

		//$isapublic_file = false;
		$davguest = ((x($_SESSION, 'davguest')) ? true : false);

		if ($server->httpRequest->getMethod() === 'GET') {
			$path = $server->httpRequest->getPath();
			try {
				/** @todo get rid of RedFileData() */
				$x = RedFileData($path, $this);
				if ($x instanceof \RedMatrix\RedDAV\RedFile) {
					// anonymous hotlinking public file without login
					return true;
				}
				if ($x instanceof \RedMatrix\RedDAV\RedDirectory) {
					if ($davguest) {
						// anonymous access to directories when ?davguest parameter set
						return true;
					}
				}
			} catch (Exception $e) {
				// RedFileData() threw a Forbidden exception
				//$isapublic_file = false;
			}
		}

		// If not authenticated and no hotlinking or davguest access matched
		// try a HTTP basic auth. Allows guest logon.
		// This should also be the entry point for most WebDAV clients.
		try {
			parent::authenticate($server, $realm);
			// @FIXME cleanup $this->currentUser from authenticate() is not what we use in getCurrentUser()
		} catch (Exception $e) {
			logger('auth exception: ' . $e->getMessage());
			http_status_exit($e->getHTTPCode(), $e->getMessage());
		}

		// successfull authentication
		return true;
	}

	/**
	 * @brief Validates a username and password.
	 *
	 * Guest access is granted with the password "+++".
	 *
	 * @see \\Sabre\\DAV\\Auth\\Backend\\AbstractBasic::validateUserPass()
	 * @param string $username
	 * @param string $password
	 * @return boolean
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
				if (($record['account_flags'] == ACCOUNT_OK)
						|| ($record['account_flags'] == ACCOUNT_UNVERIFIED)
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
	 * @see \\Sabre\\DAV\\Auth\\Backend\\AbstractBasic::getCurrentUser()
	 * @return string|null the current channel's channel_address
	 */
	public function getCurrentUser() {
		return $this->channel_name;
	}

	/**
	 * @brief Sets the timezone from the channel in RedBasicAuthBackend.
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
	 * @brief Prints out all RedBasicAuthBackend variables to logger().
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
		logger('owner_hash ' . $this->owner_hash, LOGGER_DATA);
	}
}