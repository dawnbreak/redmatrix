<?php

namespace RedMatrix\RedDAV;

use Sabre\DAV;
use Sabre\DAVACL;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Sabre\HTTP\URLUtil;
use RedMatrix\RedDAV\RedBasicAuthBackend;

/**
 * @brief DAVACL implementation of \\Sabre\\DAVACL\\Plugin.
 *
 * This plugin provides functionality to enforce ACL permissions.
 * ACL is defined in RFC3744.
 *
 * In addition it also provides support for the {DAV:}current-user-principal
 * property, defined in RFC5397 and the {DAV:}expand-property report, as
 * defined in RFC3253.
 *
 * It will map RedMatrix's roles view_storage to {DAV:}read and write_storage
 * to {DAV:}write.
 *
 * A beforeMethod will hook into every communication and enforce these permissions
 * together with some other methods that hook into creation, deletion, etc.
 * operations.
 *
 * @see RedPrincipalBackend
 *
 * @author Klaus Weidenbach
 * @license http://opensource.org/licenses/mit-license.php The MIT License (MIT)
 */
class RedDAVACL extends DAVACL\Plugin {

	/**
	 * This string is prepended to the username of the currently logged in
	 * user. This allows the plugin to determine the principal path based on
	 * the username.
	 *
	 * @var string
	 */
	public $defaultUsernamePath = 'principals/channels';

	/**
	 * @var \RedMatrix\RedDAV\RedBasicAuthBackend
	 */
	private $auth;

	/**
	 * @brief Constructor for RedDAVACL.
	 *
	 * @param[in,out] \RedMatrix\RedDAV\RedBasicAuthBackend &$auth
	 */
	public function __construct(RedBasicAuthBackend &$auth) {
		$this->auth = $auth;
	}

	/**
	 * @brief Triggered before any method is handled to check permissions.
	 *
	 * This is also used to set the current owner_ variables.
	 *
	 * This method has an index of 20 on beforeMethod.
	 *
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 * @return void
	 */
	function beforeMethod(RequestInterface $request, ResponseInterface $response) {
		$method = $request->getMethod();
		$path = $request->getPath();
		logger('Request method: ' . $method . ' path: ' . $path, LOGGER_DATA);

		/** @todo Is this necessary? It's not in Sabre's default. A bug? */
		// the node can not exist when the WebDAV client PUT it first time. So check if the folder exists.
		if ($method === 'PUT') {
			list($parent_path, ) = URLUtil::splitPath($path);
			$path = $parent_path;
		}

		// Make sure childExists() will work without a complete
		// RedDAV\RedBasicAuthBackend object which will get completed after this
		// check.
		$exists = $this->server->tree->nodeExists($path);

		// If the node does not exists, none of these checks apply
		if (! $exists) return;

		$channel_name = $this->checkForChannel($path);
		if ($channel_name) {
			$r = q("SELECT channel_id, channel_hash FROM channel WHERE channel_address = '%s' AND NOT ( channel_pageflags & %d )>0 LIMIT 1",
					dbesc($channel_name),
					intval(PAGE_REMOVED)
			);
			if (! $r) {
				throw new DAV\Exception\NotFound('The channel: ' . $channel_name . ' could not be found.');
			}
			$this->auth->owner_id = $r[0]['channel_id'];
			$this->auth->owner_hash = $r[0]['channel_hash'];
			$this->auth->owner_nick = $channel_name;
		}

		switch ($method) {
			case 'POST' :
				//$this->checkPrivileges($path,'{DAV:}write');
				logger('POST');
				break;

			case 'GET' :
			case 'HEAD' :
			case 'OPTIONS' :
				// For these 3 we only need to know if the node is readable.
				//$this->checkPrivileges($path,'{DAV:}read');
				logger('POST/GET/HEAD/OPTIONS');
				break;

			case 'PUT' :
			case 'LOCK' :
			case 'UNLOCK' :
				// This method requires the write-content priv if the node
				// already exists, and bind on the parent if the node is being
				// created.
				// The bind privilege is handled in the beforeBind event.
				//$this->checkPrivileges($path,'{DAV:}write-content');
				logger('PUT/LOCK/UNLOCK');
				break;

			case 'PROPPATCH' :
				//$this->checkPrivileges($path,'{DAV:}write-properties');
				logger('PROPPATCH');
				break;

			case 'COPY' :
			case 'MOVE' :
				// Copy requires read privileges on the entire source tree.
				// If the target exists write-content normally needs to be
				// checked, however, we're deleting the node beforehand and
				// creating a new one after, so this is handled by the
				// beforeUnbind event.
				//
				// The creation of the new node is handled by the beforeBind
				// event.
				//
				// If MOVE is used beforeUnbind will also be used to check if
				// the sourcenode can be deleted.
				//$this->checkPrivileges($path,'{DAV:}read',self::R_RECURSIVE);
				logger('COPY/MOVE');

				break;
		}
	}

	/**
	 * @brief Returns the standard users' principal.
	 *
	 * This is one authorative principal url for the current user.
	 * This method will return null if the user wasn't logged in.
	 *
	 * @return string|null
	 */
	function getCurrentUserPrincipal() {
		$userName = $this->auth->observer;
		if (!$userName) return null;

		return $this->defaultUsernamePath . '/' . $userName;
	}

	/**
	 * @brief Checks if channel name is required for this path and returns name.
	 *
	 * @param array $path_arr
	 * @return boolean|string boolean false if not channel in path, otherwise channel name
	 */
	protected function checkForChannel($path) {
		// node can exists, but path can be empty still e.g. /cloud (baseUri)
		if (! isset($path[0]))
			return false;

		// isn't this redundant with the check before?
		$path_arr = explode('/', $path);
		if (! $path_arr)
			return false;

		if ($path_arr[0] === 'principals')
			return false;

		// /dav has another structure
		if ($path_arr[0] === 'channelsCollection')
			return $path_arr[1];

		// old /cloud
		return $path_arr[0];
	}
}
