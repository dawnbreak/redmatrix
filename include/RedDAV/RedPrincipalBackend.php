<?php

namespace RedMatrix\RedDAV;

use Sabre\DAV,
    Sabre\DAVACL,
    Sabre\HTTP\URLUtil;

/**
 * @brief RedPrincipalBackend implementation.
 *
 * This backend provides principals for DAV from the RedMatrix channels
 * and collections. I am not yet completely sure how this will map with
 * all the other advanced concepts of RedMatrix.
 *
 * We have custom principal URL schemas. All user principals are in a collection
 * under 'principals/channels' and all group principals are in a collection
 * under 'principals/collections'.
 *
 * @todo Right now we use xchan_hash as the URI. Maybe it would be nicer to use xchan_addr?
 *
 * @extends \Sabre\DAVACL\PrincipalBackend\AbstractBackend
 *
 * @link http://github.com/friendica/red
 * @author Klaus Weidenbach
 * @license http://opensource.org/licenses/mit-license.php The MIT License (MIT)
 */
class RedPrincipalBackend extends DAVACL\PrincipalBackend\AbstractBackend {

	/**
	 * @brief Returns a list of principals based on a prefix.
	 *
	 * This prefix will often contain something like 'principals'. You are only
	 * expected to return principals that are in this base path.
	 *
	 * You are expected to return at least a 'uri' for every user, you can
	 * return any additional properties if you wish so. Common properties are:
	 * {DAV:}displayname
	 * {http://sabredav.org/ns}email-address - This is a custom SabreDAV
	 * field that's actually injected in a number of other properties. If
	 * you have an email address, use this property.
	 *
	 * @param string $prefixPath
	 *    principals/channels or principals/collections are available
	 * @throws \Sabre\DAV\Exception\NotFound if another path was requested
	 * @return array
	 */
	function getPrincipalsByPrefix($prefixPath) {
		$principals = [];

		if ($prefixPath === 'principals/channels') {
			// taken from acl_selectors.php:contact_select() and added GROUP BY
			// @TODO make GROUP BY compatible with PostgreSQL
			$r = q("SELECT abook_id, xchan_name, xchan_url, xchan_addr, xchan_hash FROM abook LEFT JOIN xchan ON abook_xchan = xchan_hash
					WHERE abook_flags = 0 OR NOT ( abook_flags & %d )>0 AND abook_channel = %d
					GROUP BY xchan_hash
					ORDER BY xchan_name ASC",
					intval(ABOOK_FLAG_SELF),
					intval(local_channel())
			);

			if($r) {
				foreach($r as $rr) {
					$principals[] = [
							'uri' => 'principals/channels/' . $rr['xchan_hash'],
							'{DAV:}displayname' => $rr['xchan_name'],
							//'vcard' => $rr['xchan_url'] . '.vcf',
					];
				}
			}
			call_hooks('principals_contact_channels', $principals);
		} else if ($prefixPath === 'principals/collections') {
			// taken from acl_selectors.php:group_select() and limited returned fields
			$r = q("SELECT hash,name FROM `groups` WHERE `deleted` = 0 AND `uid` = %d ORDER BY `name` ASC",
					intval(local_channel())
			);

			if($r) {
				foreach($r as $rr) {
					$principals[] = [
							'uri' => 'principals/collections/' . $rr['hash'],
							'{DAV:}displayname' => $rr['name'],
							//'vcard' => $rr['xchan_url'] . '.vcf',
					];
				}
			}
			call_hooks('principals_contact_collections', $principals);
		} else {
			throw new DAV\Exception\NotFound('Invalid principal prefix path ' . $prefixPath);
		}
		call_hooks('principals_by_prefix', $principals);

		return $principals;
	}

	/**
	 * @brief Returns a specific principal, specified by it's path.
	 *
	 * The returned structure should be the exact same as from
	 * getPrincipalsByPrefix.
	 *
	 * @todo this could get optimized for sure!
	 *
	 * @param string $path
	 * @return array
	 */
	function getPrincipalByPath($path) {
		logger('Get Principal: ' . $path, LOGGER_DEBUG);

		list($prefix,) = URLUtil::splitPath($path);

		$principals = $this->getPrincipalsByPrefix($prefix);

		foreach ($principals as $principal) {
			if ($principal['uri'] == $path) {
				return $principal;
			}
		}

		return null;
	}

	/**
	 * @brief Updates one ore more webdav properties on a principal.
	 *
	 * The list of mutations is stored in a Sabre\DAV\PropPatch object.
	 * To do the actual updates, you must tell this object which properties
	 * you're going to process with the handle() method.
	 *
	 * Calling the handle method is like telling the PropPatch object "I
	 * promise I can handle updating this property".
	 *
	 * Read the PropPatch documenation for more info and examples.
	 *
	 * @param string $path
	 * @param \Sabre\DAV\PropPatch $propPatch
	 * @return void
	 */
	function updatePrincipal($path, DAV\PropPatch $propPatch) {
		return false;
	}

	/**
	 * @brief This method is used to search for principals matching a set of
	 * properties.
	 *
	 * This search is specifically used by RFC3744's principal-property-search
	 * REPORT.
	 *
	 * The actual search should be a unicode-non-case-sensitive search. The
	 * keys in searchProperties are the WebDAV property names, while the values
	 * are the property values to search on.
	 *
	 * By default, if multiple properties are submitted to this method, the
	 * various properties should be combined with 'AND'. If $test is set to
	 * 'anyof', it should be combined using 'OR'.
	 *
	 * This method should simply return an array with full principal uri's.
	 *
	 * If somebody attempted to search on a property the backend does not
	 * support, you should simply return 0 results.
	 *
	 * You can also just return 0 results if you choose to not support
	 * searching at all, but keep in mind that this may stop certain features
	 * from working.
	 *
	 * @param string $prefixPath
	 * @param array $searchProperties
	 * @param string $test
	 * @return array
	 */
	function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof') {

	}

	/**
	 * Finds a principal by its URI.
	 *
	 * This method may receive any type of uri, but mailto: addresses will be
	 * the most common.
	 *
	 * Implementation of this API is optional. It is currently used by the
	 * CalDAV system to find principals based on their email addresses. If this
	 * API is not implemented, some features may not work correctly.
	 *
	 * This method must return a relative principal path, or null, if the
	 * principal was not found or you refuse to find it.
	 *
	 * @param string $uri
	 * @param string $principalPrefix
	 * @return string
	 */
//	function findByUri($uri, $principalPrefix);

	/**
	 * @brief Returns the list of groups a principal is a member of.
	 *
	 * @param string $principal
	 * @throws \Sabre\DAV\Exception\NotFound
	 * @return array
	 */
	function getGroupMembership($principal) {
		$principal = $this->getPrincipalByPath($principal);
		if (!$principal) throw new DAV\Exception\NotFound('Principal not found');

		// @TODO implement getGroupMembership()
	}

	/**
	 * @brief Returns the list of members for a group-principal.
	 *
	 * @param string $principal
	 * @throws \Sabre\DAV\Exception\NotFound
	 * @return array
	 */
	function getGroupMemberSet($principal) {
		$principal = $this->getPrincipalByPath($principal);
		if (!$principal) throw new DAV\Exception\NotFound('Principal not found');

		// @TODO implement getGroupMemberSet()
	}

	/**
	 * @brief Updates the list of group members for a group principal.
	 *
	 * The principals should be passed as a list of uri's.
	 *
	 * @param string $principal
	 * @param array $members
	 * @return void
	 */
	function setGroupMemberSet($principal, array $members) {

	}
}
