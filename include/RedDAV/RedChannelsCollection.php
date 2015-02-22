<?php

namespace RedMatrix\RedDAV;

use Sabre\DAV;
use Sabre\DAV\Auth\Backend\BackendInterface as AuthPlugin;
use RedMatrix\RedDAV;

/**
 * @brief Collection of RedMatrix channels.
 *
 * A class that creates a list of accessible channels as an collection to be
 * used as a node in a Sabre server node tree.
 *
 * @author Klaus Weidenbach
 * @license http://opensource.org/licenses/mit-license.php The MIT License (MIT)
 */
class RedChannelsCollection extends DAV\Collection {

	/**
	 * @var RedDAV\RedBasicAuth
	 */
	protected $auth;

	/**
	 * @brief Constructor for RedChannelsCollection
	 *
	 * @param AuthPlugin $auth_plugin
	 */
	public function __construct(AuthPlugin $auth_plugin) {
		$this->auth = $auth_plugin;
	}

	/**
	 * @brief Returns an array with all viewable the channels.
	 *
	 * Get a list of RedDAV\\RedDirectory objects with all the channels where
	 * the visitor (observer) has <b>view_storage</b> perms.
	 *
	 * @return \\Sabre\\DAV\\INode[]
	 */
	function getChildren() {
		logger('Children for channels collection.', LOGGER_DEBUG);

		$ret = [];
		$r = q("SELECT channel_id, channel_address FROM channel WHERE NOT (channel_pageflags & %d)>0 AND NOT (channel_pageflags & %d)>0",
				intval(PAGE_REMOVED),
				intval(PAGE_HIDDEN)
		);

		if ($r) {
			foreach ($r as $rr) {
				if (perm_is_allowed($rr['channel_id'], $this->auth->observer, 'view_storage')) {
					logger('Found storage for channel: ' . $rr['channel_address'], LOGGER_DATA);
					$ret[] = new RedDAV\RedDirectory($rr['channel_address'], $this->auth);
				}
			}
		}

		return $ret;
	}

	/**
	 * @brief Returns a specific child node, referenced by its name.
	 *
	 * This method must throw \\Sabre\\DAV\\Exception\\NotFound if the node does
	 * not exist.
	 *
	 * @param string $name
	 * @throws "\Sabre\DAV\Exception\NotFound"
	 * @throws "\Sabre\DAV\Exception\Forbidden"
	 * @return \\Sabre\\DAV\\INode in our case it is always a RedDAV\\RedDirectory
	 */
	function getChild($name) {
		// generic implementation of getChild(), but too much overhead
		//foreach($this->getChildren() as $child) {
		//	if ($child->getName()==$name) return $child;
		//}

		// Is this still needed here?
		if (($this->auth->owner_id > 0) && (! perm_is_allowed($this->auth->owner_id, $this->auth->observer, 'view_storage'))) {
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		$x = new RedDAV\RedDirectory($name, $this->auth);
		if ($x) {
			return $x;
		}
	}

	/**
	 * @brief Checks if a child-node exists.
	 *
	 * childExists() gets called when we don't have yet a complete
	 * RedDAV\\RedBasicAuth object. Especially owner_ is not yet available. This
	 * is also the reason why we can not create RedDAV\\RedDirectory entries here.
	 *
	 * @param string $name Name of a channel to check.
	 * @throws "\Sabre\DAV\Exception\Forbidden"
	 * @return bool
	 */
	function childExists($name) {
		$name = trim($name, '/');
		$path_arr = explode('/', $name);
		if (! $path_arr)
			return false;

		$channel_name = $path_arr[0];

		$r = q("SELECT channel_id FROM channel WHERE channel_address = '%s' AND NOT (channel_pageflags & %d)>0 AND NOT (channel_pageflags & %d)>0",
				dbesc($channel_name),
				intval(PAGE_REMOVED),
				intval(PAGE_HIDDEN)
		);
		if ($r) {
			if (perm_is_allowed($r[0]['channel_id'], $this->auth->observer, 'view_storage')) {
				return true;
			}
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		//throw new DAV\Exception\NotFound('Channel ' . $name . ' not found.');
		return false;
	}

	/**
	 * @brief Returns the name of the collection.
	 *
	 * This name will be viewed when we are the tree's root of the WebDAV server.
	 *
	 * @return string
	 */
	public function getName() {
		return 'channelsCollection';
	}
}