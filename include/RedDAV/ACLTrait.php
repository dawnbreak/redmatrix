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
	 * This is a principal URL such as \e principals/channels/channelname
	 */
	public $owner;

	/**
	 * @brief Returns the owner principal.
	 *
	 * This must be a url to a principal, or null if there's no owner.
	 *
	 * @return string|null
	 */
	function getOwner() {
		return $this->owner;
	}

	/**
	 * @brief Returns a group principal.
	 *
	 * This must be an URL to a principal, or null if there's no group.
	 *
	 * @return string|null
	 */
	function getGroup() {
		return null;
	}

	/**
	 * @brief Returns a list of ACEs (Access Control Entry) for this node.
	 *
	 * This is the default ACL with full permission for owner only.
	 *
	 * @note This method needs to be adapted in our own classes to return the
	 * appropriate ACEs for each node.
	 *
	 * Each ACE has the following properties:
	 *   * 'privilege', a string such as {DAV:}read or {DAV:}write. These are
	 *      currently the only supported privileges
	 *   * 'principal', a url to the principal who owns the node
	 *   * 'protected' (optional), indicating that this ACE is not allowed to
	 *      be updated.
	 *
	 * @return array of ACEs
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
	 * @brief Updates the ACL.
	 *
	 * This method will receive a list of new ACE's as an array argument.
	 *
	 * @throw DAV\Exception\Forbidden
	 * @param array $acl
	 */
	function setACL(array $acl) {
		throw new DAV\Exception\Forbidden('Not allowed to change ACL');
	}

	/**
	 * @brief Returns the list of supported privileges for this node.
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