<?php

namespace RedMatrix\RedDAV;

use Sabre\DAV;

/**
 * @brief Trait for implementing ACL in our classes.
 *
 * Use this trait in our classes that implement \\Sabre\\DAVACL\\IACL.
 *
 * @author Klaus Weidenbach
 * @license http://opensource.org/licenses/mit-license.php The MIT License (MIT)
 */
trait ACLTrait {

	/**
	 * This is a principal URL such as principals/channels/channelname
	 */
	public $owner;

	/**
	 * Returns the owner principal
	 *
	 * This must be a url to a principal, or null if there's no owner
	 *
	 * @return string|null
	 */
	function getOwner() {
		return $this->owner;
	}

	/**
	 * Returns a group principal
	 *
	 * This must be a url to a principal, or null if there's no owner
	 *
	 * @return string|null
	 */
	function getGroup() {

		return null;

	}

	/**
	 * Returns a list of ACE's for this node.
	 *
	 * Each ACE has the following properties:
	 *   * 'privilege', a string such as {DAV:}read or {DAV:}write. These are
	 *     currently the only supported privileges
	 *   * 'principal', a url to the principal who owns the node
	 *   * 'protected' (optional), indicating that this ACE is not allowed to
	 *      be updated.
	 *
	 * @return array
	 */
	function getACL() {
		return [
			[
				'privilege' => '{DAV:}all',
				'principal' => '{DAV:}owner',
				'protected' => true,
			]
		];
	}

	/**
	 * Updates the ACL
	 *
	 * This method will receive a list of new ACE's as an array argument.
	 *
	 * @param array $acl
	 * @return void
	 */
	function setACL(array $acl) {
		throw new DAV\Exception\Forbidden('Not allowed to change ACL\'s');
	}

	/**
	 * Returns the list of supported privileges for this node.
	 *
	 * The returned data structure is a list of nested privileges.
	 * See Sabre\\DAVACL\\Plugin::getDefaultSupportedPrivilegeSet for a simple
	 * standard structure.
	 *
	 * If null is returned from this method, the default privilege set is used,
	 * which is fine for most common usecases.
	 *
	 * @return array|null
	 */
	function getSupportedPrivilegeSet() {
		return null;
	}

}