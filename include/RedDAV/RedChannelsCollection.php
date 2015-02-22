<?php

namespace RedMatrix\RedDAV;

use Sabre\DAV,
    Sabre\DAV\Auth\Backend\BackendInterface as AuthPlugin,
    RedMatrix\RedDAV;

/**
 * @brief Collection of RedMatrix channels.
 *
 * A class that creates a list of accessible channels as an collection to be
 * used as a node in a Sabre server node tree.
 *
 * @extends \Sabre\DAV\Collection
 *
 * @link http://github.com/friendica/red
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
	 * Get a list of RedDirectory objects with all the channels where the
	 * visitor (observer) has <b>view_storage</b> perms.
	 *
	 * @return DAV\INode[]
	 */
	function getChildren() {
		logger('KW: getChildren');
		logger('Getting children for channels collection.', LOGGER_DEBUG);
//		logger('current channel_id: ' . $this->auth->channel_id . ' current owner_id: ' . $this->auth->owner_id, LOGGER_DEBUG);

		if (get_config('system', 'block_public') && (! $this->auth->channel_id) && (! $this->auth->observer)) {
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		if ($this->auth->owner_id && (! perm_is_allowed($this->auth->owner_id, $this->auth->observer, 'view_storage'))) {
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

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
	 * Returns a specific child node, referenced by its name
	 *
	 * This method must throw Sabre\DAV\Exception\NotFound if the node does not
	 * exist.
	 *
	 * @param string $name
	 * @throws DAV\Exception\NotFound
	 * @throws DAV\Exception\Forbidden
	 * @return DAV\INode
	 */
	function getChild($name) {
		logger('KW: getChild: ' .$name);
		//		foreach($this->getChildren() as $child) {
		//			if ($child->getName()==$name) return $child;
		//		}
		if (get_config('system', 'block_public') && (! $this->auth->channel_id) && (! $this->auth->observer)) {
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		if (($this->auth->owner_id) && (! perm_is_allowed($this->auth->owner_id, $this->auth->observer, 'view_storage'))) {
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		$x = new RedDAV\RedDirectory($name, $this->auth);
		if ($x) {
			return $x;
		}

		throw new DAV\Exception\NotFound('Channel not found: ' . $name);
	}

	/**
	 * @brief Returns the name of the collection.
	 *
	 * @return string
	 */
	public function getName() {
		return 'channelsCollection';
	}
}