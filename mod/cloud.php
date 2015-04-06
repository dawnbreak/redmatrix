<?php
/**
 * @file mod/cloud.php
 * @brief Initialize RedMatrix's cloud (SabreDAV).
 *
 * Module for accessing the DAV storage area.
 */

use Sabre\DAV;
use Sabre\DAVACL;
use RedMatrix\RedDAV;

// workaround for HTTP-auth in CGI mode
if (x($_SERVER, 'REDIRECT_REMOTE_USER')) {
 	$userpass = base64_decode(substr($_SERVER["REDIRECT_REMOTE_USER"], 6)) ;
	if (strlen($userpass)) {
	 	list($name, $password) = explode(':', $userpass);
		$_SERVER['PHP_AUTH_USER'] = $name;
		$_SERVER['PHP_AUTH_PW'] = $password;
	}
}

if (x($_SERVER, 'HTTP_AUTHORIZATION')) {
	$userpass = base64_decode(substr($_SERVER["HTTP_AUTHORIZATION"], 6)) ;
	if (strlen($userpass)) {
		list($name, $password) = explode(':', $userpass);
		$_SERVER['PHP_AUTH_USER'] = $name;
		$_SERVER['PHP_AUTH_PW'] = $password;
	}
}

/**
 * @brief Fires up the SabreDAV server.
 *
 * @param App &$a
 */
function cloud_init(&$a) {
	require_once('include/reddav.php');

	if (! is_dir('store'))
		os_mkdir('store', STORAGE_DEFAULT_PERMISSIONS, false);

	/** @todo Do we need to check for a true-ish value? */
	if ($_GET['davguest'])
		$_SESSION['davguest'] = true;

	// What are we doing here?
	$_SERVER['QUERY_STRING'] = str_replace(array('?f=', '&f='), array('', ''), $_SERVER['QUERY_STRING']);
	$_SERVER['QUERY_STRING'] = strip_zids($_SERVER['QUERY_STRING']);
	$_SERVER['QUERY_STRING'] = preg_replace('/[\?&]davguest=(.*?)([\?&]|$)/ism', '', $_SERVER['QUERY_STRING']);

	$_SERVER['REQUEST_URI'] = str_replace(array('?f=', '&f='), array('', ''), $_SERVER['REQUEST_URI']);
	$_SERVER['REQUEST_URI'] = strip_zids($_SERVER['REQUEST_URI']);
	$_SERVER['REQUEST_URI'] = preg_replace('/[\?&]davguest=(.*?)([\?&]|$)/ism', '', $_SERVER['REQUEST_URI']);

	// Authentication backend
	$authBackend = new RedDAV\RedBasicAuthBackend();
	// principal backend for DAVACL
	$principalBackend = new RedDAV\RedPrincipalBackend();

	/**
	 * Basically this is an array which contains the 'top-level' directories in
	 * the WebDAV server. Don't think /cloud is the right place for this.
	 */
	$nodes = [
		// /principals
		new DAV\SimpleCollection('principals', [
			new DAVACL\PrincipalCollection($principalBackend, 'principals/channels'),
			new DAVACL\PrincipalCollection($principalBackend, 'principals/collections'),
		]),
		// /webdav root
		new RedDAV\RedChannelsCollection($authBackend),
		// /calendars if we want to put it all under the same path, otherwise we need a new module
		//new DAV\SimpleCollection('calendars', [
		//	new RedDAV\RedCalendarRoot($principalBackend, $caldavBackend, 'principals/channels'),
		//	new RedDAV\RedCalendarRoot($principalBackend, $caldavBackend, 'principals/collections'),
		//]),
		// /photo webdav with photo collection
		//new RedDAV\RedPhotoCollection(),
	];

	// A SabreDAV server-object
	//$server = new DAV\Server($nodes);
	$server = new DAV\Server(new RedDAV\RedChannelsCollection($authBackend));

	// __construct()
	$auth = new DAV\Auth\Plugin($authBackend, t('$Projectname - Guests: Username: {your email address}, Password: +++'));
	// calls initialize() which will call authenticate() from authBackend
	$server->addPlugin($auth);

	// prevent overwriting changes each other with a lock backend
	$lockBackend = new DAV\Locks\Backend\File('store/[data]/locks');
	$lockPlugin = new DAV\Locks\Plugin($lockBackend);
	$server->addPlugin($lockPlugin);

	// include some ACL functionality
	$aclPlugin = new RedDAV\RedDAVACL($authBackend);
	/** @todo Should we add configure options for these options? */
	//$aclPlugin->hideNodesFromListings = true;
	//$aclPlugin->allowAccessToNodesWithoutACL = false;
	$server->addPlugin($aclPlugin);

	// provide a directory view for the cloud in RedMatrix
	$browser = new RedDAV\RedBrowser($authBackend);
	$server->addPlugin($browser);

	// Temporary file filter
	$tmpFilesFilter = new \Sabre\DAV\TemporaryFileFilterPlugin('store/[data]/tmpfiles');
	$server->addPlugin($tmpFilesFilter);

	// Experimental QuotaPlugin
//	$server->addPlugin(new RedDAV\QuotaPlugin($auth));

	$server->setBaseUri('/cloud');

	// All we need to do now, is to fire up the server
	$server->exec();

	killme();
}
