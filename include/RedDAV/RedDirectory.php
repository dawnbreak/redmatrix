<?php

namespace RedMatrix\RedDAV;

use Sabre\DAV;
use Sabre\DAVACL;
use Sabre\HTTP\URLUtil;
use RedMatrix\RedDAV\RedBasicAuth as RedAuth;
use RedMatrix\RedDAV;

/**
 * @brief This class represents a directory node in DAV.
 *
 * A class that represents a directory.
 *
 * @license http://opensource.org/licenses/mit-license.php The MIT License (MIT)
 */
class RedDirectory extends DAV\Node implements DAV\ICollection, DAV\IQuota /*, DAVACL\IACL */ {

	use ACLTrait;

	/**
	 * @var RedDAV\RedBasicAuth
	 */
	private $auth;

	/**
	 * @var string
	 */
	private $folder_hash;

	/**
	 * @brief The path inside /cloud.
	 *
	 * @var string
	 */
	private $red_path;

	/**
	 * @brief The real path in the filesystem.
	 *
	 * The actual path in store/ with the hashed names.
	 *
	 * @var string
	 */
	private $os_path = '';

	/**
	 * @brief Sets up the directory node, expects a full path.
	 *
	 * @param string $ext_path a full path
	 * @param RedDAV\RedBasicAuth $auth
	 * @throws "\Sabre\DAV\Exception\Forbidden"
	 * @throws "\Sabre\DAV\Exception\NotFound"
	 */
	public function __construct($ext_path, RedAuth $auth) {
		logger('Directory ' . $ext_path, LOGGER_DATA);
		$this->auth = $auth;

		$this->red_path = $ext_path;
		if (! $this->red_path) {
			$this->red_path = '/';
		}

		// DAVACL
		$this->owner = 'principals/channels/' . $this->auth->owner_hash;

		$file = trim($this->red_path, '/');
		$path_arr = explode('/', $file);

		if (! $path_arr)
			return;

		$folder = '';
		$os_path = '';
		$errors = false;
		$permission_error = false;

		$perms = permissions_sql($this->auth->owner_id);
		for ($x = 1; $x < count($path_arr); $x++) {
			$r = q("SELECT id, hash, filename, flags FROM attach WHERE folder = '%s' AND filename = '%s' AND uid = %d AND (flags & %d)>0 $perms LIMIT 1",
					dbesc($folder),
					dbesc($path_arr[$x]),
					intval($this->auth->owner_id),
					intval(ATTACH_FLAG_DIR)
			);
			if (! $r) {
				// path wasn't found. Try without permissions to see if it was the result of permissions.
				$errors = true;
				$r = q("SELECT id, hash, filename, flags FROM attach WHERE folder = '%s' AND filename = '%s' AND uid = %d AND (flags & %d)>0 LIMIT 1",
						dbesc($folder),
						basename($path_arr[$x]),
						intval($this->auth->owner_id),
						intval(ATTACH_FLAG_DIR)
				);
				if ($r) {
					$permission_error = true;
				}
				break;
			}

			if ($r && ($r[0]['flags'] & ATTACH_FLAG_DIR)) {
				$folder = $r[0]['hash'];

				if (strlen($os_path))
					$os_path .= '/';

				$os_path .= $folder;
			}
		}

		if ($errors) {
			if ($permission_error) {
				throw new DAV\Exception\Forbidden('Permission denied.');
			} else {
				throw new DAV\Exception\NotFound('A component of the request path ' . $file . ' could not be found.');
			}
		}

		// set this object's values
		$this->folder_hash = $folder;
		$this->os_path = $os_path;

		$this->log();
	}

	/**
	 * @brief Logging function for this objects values.
	 */
	private function log() {
		//logger('folder_hash ' . $this->folder_hash, LOGGER_DATA);
		logger('os_path  ' . $this->os_path, LOGGER_DATA);
		logger('red_path ' . $this->red_path, LOGGER_DATA);
		// DAV principal, owning channel, not metadata owner who created the file
		logger('owner    ' . $this->owner, LOGGER_DATA);

		//$this->auth->log();
	}

	/**
	 * @brief Returns an array with all the child nodes.
	 *
	 * Array with all RedDirectory and RedFile Sabre\\DAV\\Node items for the
	 * given path.
	 *
	 * @throw "\Sabre\DAV\Exception\Forbidden"
	 * @return array \\Sabre\\DAV\\INode[]
	 */
	public function getChildren() {
		logger('Children for ' . $this->red_path, LOGGER_DATA);

		if (($this->auth->owner_id) && (! perm_is_allowed($this->auth->owner_id, $this->auth->observer, 'view_storage'))) {
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		$ret = [];

		$file = trim($this->red_path, '/');
		$path_arr = explode('/', $file);

		if (! $path_arr)
			return null;

		$channel_name = $path_arr[0];

		$r = q("SELECT channel_id, channel_hash FROM channel WHERE channel_address = '%s' AND NOT ( channel_pageflags & %d )>0 LIMIT 1",
				dbesc($channel_name),
				intval(PAGE_REMOVED)
		);

		if (! $r)
			return null;

		$channel_id = $r[0]['channel_id'];
		$channel_hash = $r[0]['channel_hash'];

		// set DAVACL principal for this folder
		$this->owner = 'principals/channels/' . $channel_hash;

		// finally get the children
		if (ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
			$prefix = 'DISTINCT ON (filename)';
			$suffix = 'ORDER BY filename';
		} else {
			$prefix = '';
			$suffix = 'GROUP BY filename';
		}
		$perms = permissions_sql($channel_id);
		$r = q("SELECT $prefix id, uid, hash, filename, filetype, filesize, revision, folder, flags, created, edited FROM attach WHERE folder = '%s' AND uid = %d $perms $suffix",
				dbesc($this->folder_hash),
				intval($channel_id)
		);

		foreach ($r as $rr) {
			//logger('Found node: ' . $rr['filename'], LOGGER_DEBUG);
			if ($rr['flags'] & ATTACH_FLAG_DIR) {
				$ret[] = new RedDAV\RedDirectory($this->red_path . '/' . $rr['filename'], $this->auth);
			} else {
				$ret[] = new RedDAV\RedFile($this->red_path . '/' . $rr['filename'], $rr, $this->auth);
			}
		}

		return $ret;
	}

	/**
	 * @brief Returns a child by name.
	 *
	 * @throw "\Sabre\DAV\Exception\Forbidden"
	 * @throw "\Sabre\DAV\Exception\NotFound"
	 * @param string $name
	 */
	public function getChild($name) {
		logger('Child ' . $name, LOGGER_DATA);

		if (($this->auth->owner_id) && (! perm_is_allowed($this->auth->owner_id, $this->auth->observer, 'view_storage'))) {
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		$x = RedFileData($this->red_path . '/' . $name, $this->auth);
		if ($x) {
			return $x;
		}

		throw new DAV\Exception\NotFound('The file with name: ' . $name . ' could not be found.');
	}

	/**
	 * @brief Returns the name of the directory.
	 *
	 * @return string
	 */
	public function getName() {
		return (basename($this->red_path));
	}

	/**
	 * @brief Renames the directory.
	 *
	 * @todo handle duplicate directory name
	 *
	 * @throw "\Sabre\DAV\Exception\Forbidden"
	 * @param string $name The new name of the directory.
	 * @return void
	 */
	public function setName($name) {
		logger('Rename ' . basename($this->red_path) . ' -> ' . $name, LOGGER_DATA);

		// @todo add childExists() if new name already exists

		// This should have been checked already in RedDAVACL.
		if (! perm_is_allowed($this->auth->owner_id, $this->auth->observer, 'write_storage')) {
			logger('Permission denied '. $name);
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		list($parent_path, ) = URLUtil::splitPath($this->red_path);
		$new_path = $parent_path . '/' . $name;

		q("UPDATE attach SET filename = '%s' WHERE hash = '%s' AND uid = %d",
			dbesc($name),
			dbesc($this->folder_hash),
			intval($this->auth->owner_id)
		);

		$this->red_path = $new_path;
	}

	/**
	 * @brief Creates a new file in the directory.
	 *
	 * Data will either be supplied as a stream resource, or in certain cases
	 * as a string. Keep in mind that you may have to support either.
	 *
	 * After successful creation of the file, you may choose to return the ETag
	 * of the new file here.
	 *
	 * @throw "\Sabre\DAV\Exception\Forbidden"
	 * @param string $name Name of the file
	 * @param resource|string $data Initial payload
	 * @return null|string ETag
	 */
	public function createFile($name, $data = null) {
		logger('New file ' . $name, LOGGER_DEBUG);

		// This should have been checked already in RedDAVACL.
		if (! perm_is_allowed($this->auth->owner_id, $this->auth->observer, 'write_storage')) {
			logger('Permission denied ' . $name);
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		$mimetype = z_mime_content_type($name);

		$c = q("SELECT * FROM channel WHERE channel_id = %d AND NOT (channel_pageflags & %d)>0 LIMIT 1",
			intval($this->auth->owner_id),
			intval(PAGE_REMOVED)
		);

		if (! $c) {
			logger('No channel');
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		$filesize = 0;
		$hash = random_string();

		q("INSERT INTO attach ( aid, uid, hash, creator, filename, folder, flags, filetype, filesize, revision, data, created, edited, allow_cid, allow_gid, deny_cid, deny_gid )
			VALUES ( %d, %d, '%s', '%s', '%s', '%s', '%s', '%s', %d, %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) ",
			intval($c[0]['channel_account_id']),
			intval($c[0]['channel_id']),
			dbesc($hash),
			dbesc($this->auth->observer),
			dbesc($name),
			dbesc($this->folder_hash),
			dbesc(ATTACH_FLAG_OS),
			dbesc($mimetype),
			intval($filesize),
			intval(0),
			dbesc($this->os_path . '/' . $hash),
			dbesc(datetime_convert()),
			dbesc(datetime_convert()),
			dbesc($c[0]['channel_allow_cid']),
			dbesc($c[0]['channel_allow_gid']),
			dbesc($c[0]['channel_deny_cid']),
			dbesc($c[0]['channel_deny_gid'])
		);

		$f = 'store/' . $this->auth->owner_nick . '/' . (($this->os_path) ? $this->os_path . '/' : '') . $hash;

		// returns the number of bytes that were written to the file, or FALSE on failure
		$size = file_put_contents($f, $data);
		// delete attach entry if file_put_contents() failed
		if ($size === false) {
			logger('file_put_contents() failed to ' . $f);
			attach_delete($c[0]['channel_id'], $hash);
			return;
		}

		// returns now
		$edited = datetime_convert();

		// updates entry with filesize and timestamp
		q("UPDATE attach SET filesize = '%s', edited = '%s' WHERE hash = '%s' AND uid = %d",
			dbesc($size),
			dbesc($edited),
			dbesc($hash),
			intval($c[0]['channel_id'])
		);

		// update the folder's lastmodified timestamp
		q("UPDATE attach SET edited = '%s' WHERE hash = '%s' AND uid = %d",
			dbesc($edited),
			dbesc($this->folder_hash),
			intval($c[0]['channel_id'])
		);

		// @todo move this to own plugin
		$maxfilesize = get_config('system', 'maxfilesize');
		if (($maxfilesize) && ($size > $maxfilesize)) {
			attach_delete($c[0]['channel_id'], $hash);
			return;
		}

		// check against service class quota
		$limit = service_class_fetch($c[0]['channel_id'], 'attach_upload_limit');
		if ($limit !== false) {
			$x = q("SELECT SUM(filesize) AS total FROM attach WHERE aid = %d ",
				intval($c[0]['channel_account_id'])
			);
			if (($x) && ($x[0]['total'] + $size > $limit)) {
				logger('service class limit exceeded for ' . $c[0]['channel_name'] . ' total usage is ' . $x[0]['total'] . ' limit is ' . $limit);
				attach_delete($c[0]['channel_id'], $hash);
				return;
			}
		}
	}

	/**
	 * @brief Creates a new subdirectory.
	 *
	 * @param string $name the directory to create
	 * @return void
	 */
	public function createDirectory($name) {
		logger('New directory ' . $name, LOGGER_DEBUG);

		// This should have been checked already in RedDAVACL.
		if ((! $this->auth->owner_id > 0) || (! perm_is_allowed($this->auth->owner_id, $this->auth->observer, 'write_storage'))) {
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		$r = q("SELECT * FROM channel WHERE channel_id = %d AND NOT (channel_pageflags & %d)>0 LIMIT 1",
			intval($this->auth->owner_id),
			intval(PAGE_REMOVED)
		);

		if ($r) {
			$result = attach_mkdir($r[0], $this->auth->observer, array('filename' => $name, 'folder' => $this->folder_hash));
			if (! $result['success']) {
				logger('error ' . print_r($result, true), LOGGER_DATA);
			}
		}
	}

	/**
	 * @brief Checks if a child exists.
	 *
	 * @param string $name
	 *  The name to check if it exists.
	 * @return boolean
	 */
	public function childExists($name) {
		logger('Checking child: ' . $name, LOGGER_DEBUG);

		$x = RedFileData($this->red_path . '/' . $name, $this->auth, true);
		//logger('RedFileData returns: ' . print_r($x, true), LOGGER_DATA);
		if ($x)
			return true;

		return false;
	}

	/**
	 * @brief Returns the last modification time for the directory, as a UNIX
	 * timestamp.
	 *
	 * It looks for the last edited file in the folder. If it is an empty folder
	 * it returns the lastmodified time of the folder itself, to prevent zero
	 * timestamps.
	 *
	 * @return int last modification time in UNIX timestamp
	 */
	public function getLastModified() {
		$r = q("SELECT edited FROM attach WHERE folder = '%s' AND uid = %d ORDER BY edited DESC LIMIT 1",
			dbesc($this->folder_hash),
			intval($this->auth->owner_id)
		);
		if (! $r) {
			$r = q("SELECT edited FROM attach WHERE hash = '%s' AND uid = %d LIMIT 1",
				dbesc($this->folder_hash),
				intval($this->auth->owner_id)
			);
			if (! $r)
				return '';
		}

		return datetime_convert('UTC', 'UTC', $r[0]['edited'], 'U');
	}

	/**
	 * @brief Return quota usage.
	 *
	 * @fixme Should guests relly see the used/free values from filesystem of the
	 * complete store directory?
	 *
	 * @return array with used and free values in bytes.
	 */
	public function getQuotaInfo() {
		// values from the filesystem of the complete <i>store/</i> directory
		$limit = disk_total_space('store');
		$free = disk_free_space('store');

		if ($this->auth->owner_id) {
			$c = q("select * from channel where channel_id = %d and not (channel_pageflags & %d)>0 limit 1",
				intval($this->auth->owner_id),
				intval(PAGE_REMOVED)
			);

			$ulimit = service_class_fetch($c[0]['channel_id'], 'attach_upload_limit');
			$limit = (($ulimit) ? $ulimit : $limit);

			$x = q("select sum(filesize) as total from attach where aid = %d",
				intval($c[0]['channel_account_id'])
			);
			$free = (($x) ? $limit - $x[0]['total'] : 0);
		}

		return [
			$limit - $free,
			$free,
		];
	}
}