<?php
/**
 * @file include/reddav.php
 * @brief some DAV related functions for RedMatrix.
 *
 * This file contains some functions which did not fit into one of the RedDAV
 * classes.
 *
 * The extended SabreDAV classes you will find in the RedDAV namespace under
 * @ref includes/RedDAV/.
 * The original SabreDAV classes you can find under @ref vendor/sabre/dav/.
 * We need to use SabreDAV 1.8.x for PHP5.3 compatibility. SabreDAV >= 2.0
 * requires PHP >= 5.4.
 *
 * @link http://github.com/friendica/red
 * @license http://opensource.org/licenses/mit-license.php The MIT License (MIT)
 */

use Sabre\DAV;
use RedMatrix\RedDAV;

require_once('include/attach.php');

/**
 * @brief TODO What exactly is this function for?
 *
 * @fixme function name looks like a class name, should we rename it?
 * @todo get rid of this function
 *
 * @param string $file
 *  path to file or directory
 * @param RedBasicAuth &$auth
 * @param boolean $test (optional) enable test mode
 * @return RedFile|RedDirectory|boolean|null
 * @throws \Sabre\DAV\Exception\Forbidden
 */
function RedFileData($file, &$auth, $test = false) {
	logger($file . (($test) ? ' (test mode) ' : ''), LOGGER_DATA);

	if ((! $file) || ($file === '/')) {
		logger('KW4a');
		return new RedDAV\RedDirectory('/', $auth);
	}
logger('KW4b');
	$file = trim($file, '/');

	$path_arr = explode('/', $file);

	if (! $path_arr)
		return null;

	$channel_name = $path_arr[0];

	$r = q("select channel_id from channel where channel_address = '%s' limit 1",
		dbesc($channel_name)
	);

	if (! $r)
		return null;

	$channel_id = $r[0]['channel_id'];

	$path = '/' . $channel_name;

	// @todo what? and why here?
	$auth->owner_id = $channel_id;

	$permission_error = false;

	$folder = '';

	require_once('include/security.php');
	$perms = permissions_sql($channel_id);

	$errors = false;

	for ($x = 1; $x < count($path_arr); $x++) {
		$r = q("select id, hash, filename, flags from attach where folder = '%s' and filename = '%s' and uid = %d and (flags & %d)>0 $perms",
			dbesc($folder),
			dbesc($path_arr[$x]),
			intval($channel_id),
			intval(ATTACH_FLAG_DIR)
		);

		if ($r && ( $r[0]['flags'] & ATTACH_FLAG_DIR)) {
			$folder = $r[0]['hash'];
			$path .= '/' . $r[0]['filename'];
		}
		if (! $r) {
			$r = q("select id, uid, hash, filename, filetype, filesize, revision, folder, flags, created, edited from attach 
				where folder = '%s' and filename = '%s' and uid = %d $perms order by filename limit 1",
				dbesc($folder),
				dbesc(basename($file)),
				intval($channel_id)
			);
		}
		if (! $r) {
			$errors = true;
			$r = q("select id, uid, hash, filename, filetype, filesize, revision, folder, flags, created, edited from attach 
				where folder = '%s' and filename = '%s' and uid = %d order by filename limit 1",
				dbesc($folder),
				dbesc(basename($file)),
				intval($channel_id)
			);
			if ($r)
				$permission_error = true;
		}
	}

	if ($path === '/' . $file) {
		if ($test)
			return true;
		// final component was a directory.
		return new RedDAV\RedDirectory('/' . $file, $auth);
	}

	if ($errors) {
		logger('not found ' . $file);
		if ($test)
			return false;

		if ($permission_error) {
			logger('permission error ' . $file);
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		return;
	}

	if ($r) {
		if ($test)
			return true;

		if ($r[0]['flags'] & ATTACH_FLAG_DIR) {
			return new RedDAV\RedDirectory($path . '/' . $r[0]['filename'], $auth);
		} else {
			return new RedDAV\RedFile($r[0]['filename'], $r[0], $auth);
		}
	}

	return false;
}
