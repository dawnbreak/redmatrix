<?php

namespace RedMatrix\RedDAV;

use Sabre\DAV;
use Sabre\HTTP\URLUtil;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Sabre\DAV\Auth\Backend\BackendInterface as AuthPlugin;

/**
 * @brief Provides a DAV frontend for the webbrowser.
 *
 * RedBrowser is a SabreDAV server-plugin to provide a view to the DAV storage
 * for the webbrowser.
 *
 * @license http://opensource.org/licenses/mit-license.php The MIT License (MIT)
 */
class RedBrowser extends DAV\Browser\Plugin {

	/**
	 * @see \Sabre\DAV\Auth\Backend\BackendInterface
	 * @var RedDAV\RedBasicAuth
	 */
	private $auth;

	/**
	 * @brief Constructor for RedBrowser class.
	 *
	 * $enablePost will be activated through set_writeable() in a later stage.
	 * At the moment the write_storage permission is only valid for the whole
	 * folder. No file specific permissions yet.
	 *
	 * @param RedDAV\RedBasicAuth &$auth
	 */
	public function __construct(AuthPlugin &$auth) {
		$this->auth = $auth;
		parent::__construct(false);
	}

	/**
	 * Extended from parent to add our own listeners.
	 */
	function initialize(DAV\Server $server) {
		parent::initialize($server);

		/** @todo test if this is working here */
		$which = null;
		if (argc() > 1)
			$which = argv(1);

		$a = get_app();
		$profile = 0;
		if ($which)
			profile_load($a, $which, $profile);

		$this->server->on('beforeMethod', [$this, 'redBeforeMethod'], 50);
	}

	/**
	 * @brief Triggered before any method is handled to check permissions.
	 *
	 * Together with beforeMethod from DAVACL it enables POST actions when
	 * permissions allow it.
	 *
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 * @return void
	 */
	function redBeforeMethod(RequestInterface $request, ResponseInterface $response) {
		$method = $request->getMethod();
		//$path = $request->getPath();
		//logger('redBeforeMethod called path ' . $path, LOGGER_DEBUG);

		// RedBrowser just needs POST and GET
		switch($method) {
			case 'POST' :
			case 'GET' :
				if (! $this->auth->owner_id) {
					break;
				}

				// check write_storage permission and enable POST handlers
				if (perm_is_allowed($this->auth->owner_id, $this->auth->observer, 'write_storage')) {
					$this->enablePost = true;
					$this->server->on('method:POST', [$this,'httpPOST']);
					$this->server->on('onBrowserPostAction', [$this, 'redPostAction']);
				}
				break;
		}
	}

	/**
	 * @brief Handle our events from POST actions.
	 *
	 * Provides delete support for sabreAction.
	 *
	 * @param string $uri
	 * @param string $action
	 * @param array $postVars
	 * @return boolean
	 */
	function redPostAction($uri, $action, $postVars) {
		$ret = true;

		logger('uri: ' . $uri . ' action: ' . $action . ' postVars: ' . print_r($postVars, true));

		switch ($action) {
			case 'del' :
				// @TODO move to top when all forms have security token.
				check_form_security_token_redirectOnErr('/' . $uri);

				if (! isset($postVars['attachId'])) {
					$ret = false;
					logger('No attachId provided in POST variables.');
					notice( t('The request was incomplete. Could not delete the asset.') . EOL);
					break;
				}

				logger('Delete attach: ' . $postVars['attachId'] . ' from channel: ' . $postVars['channel']);

				$observer = get_app()->get_observer();
				$ob_hash = (($observer) ? $observer['xchan_hash'] : '');

				$perms = get_all_perms($this->auth->owner_id, $ob_hash);

				// @todo these checks are already done before, need to catch Exceptions and add feedback to UI still
				if (! $perms['write_storage']) {
					$ret = false;
					logger('Observer has no write_storage, so can not delete this asset.');
					notice( t('Permission denied.') . EOL);
					break;
				}

				$r = q("SELECT hash FROM attach WHERE id = %d AND uid = %d",
					intval($postVars['attachId']),
					intval($this->auth->owner_id)
				);
				if(! $r) {
					$ret = false;
					notice( t('File not found.') . EOL);
					break;
				}

				$f = $r[0];
				attach_delete($this->auth->owner_id, $f['hash']);

				break;
		}

		return $ret;
	}

	/**
	 * @brief Creates the directory listing for the given path.
	 *
	 * @param string $path which should be displayed
	 */
	public function generateDirectoryIndex($path) {
		logger('Generate directory index: ' . $path, LOGGER_DEBUG);

		// @todo better check for write_storage permission
		$is_owner = ((local_channel() && $this->auth->owner_id == local_channel()) ? true : false);

		if ($this->auth->getTimezone())
			date_default_timezone_set($this->auth->getTimezone());

		require_once('include/conversation.php');
		require_once('include/text.php');
		if ($this->auth->owner_nick) {
			$html = profile_tabs(get_app(), (($is_owner) ? true : false), $this->auth->owner_nick);
		}

		$files = $this->server->getPropertiesForPath($path, [
			'{DAV:}displayname',
			'{DAV:}resourcetype',
			'{DAV:}getcontenttype',
			'{DAV:}getcontentlength',
			'{DAV:}getlastmodified',
			], 1);


		$parent = $this->server->tree->getNodeForPath($path);

		$parentpath = array();
		// only show parent if not leaving /cloud/
		if ($path && $path != 'cloud') {
			logger('Add parent to directory index', LOGGER_DEBUG);
			list($parentUri) = URLUtil::splitPath($path);
			$fullPath = URLUtil::encodePath($this->server->getBaseUri() . $parentUri);

			$parentpath['icon'] = '';
			$parentpath['path'] = $fullPath;
		}

		$f = array();
		foreach ($files as $file) {
			$ft = array();
			$type = null;

			// This is the current directory, we can skip it
			if (rtrim($file['href'], '/') == $path) continue;

			list(, $name) = URLUtil::splitPath($file['href']);

			if (isset($file[200]['{DAV:}resourcetype'])) {
				$type = $file[200]['{DAV:}resourcetype']->getValue();

				// resourcetype can have multiple values
				if (!is_array($type)) $type = array($type);

				foreach ($type as $k=>$v) {
					// Some name mapping is preferred
					switch ($v) {
						case '{DAV:}collection' :
							$type[$k] = t('Collection');
							break;
						case '{DAV:}principal' :
							$type[$k] = t('Principal');
							break;
						case '{urn:ietf:params:xml:ns:carddav}addressbook' :
							$type[$k] = t('Addressbook');
							break;
						case '{urn:ietf:params:xml:ns:caldav}calendar' :
							$type[$k] = t('Calendar');
							break;
						case '{urn:ietf:params:xml:ns:caldav}schedule-inbox' :
							$type[$k] = t('Schedule Inbox');
							break;
						case '{urn:ietf:params:xml:ns:caldav}schedule-outbox' :
							$type[$k] = t('Schedule Outbox');
							break;
						case '{http://calendarserver.org/ns/}calendar-proxy-read' :
							$type[$k] = 'Proxy-Read';
							break;
						case '{http://calendarserver.org/ns/}calendar-proxy-write' :
							$type[$k] = 'Proxy-Write';
							break;
					}
				}
				$type = implode(', ', $type);
			}

			// If no resourcetype was found, we attempt to use
			// the contenttype property
			if (!$type && isset($file[200]['{DAV:}getcontenttype'])) {
				$type = $file[200]['{DAV:}getcontenttype'];
			}
			if (!$type) $type = t('Unknown');

			$size = isset($file[200]['{DAV:}getcontentlength']) ? (int)$file[200]['{DAV:}getcontentlength'] : '';
			$lastmodified = ((isset($file[200]['{DAV:}getlastmodified'])) ? $file[200]['{DAV:}getlastmodified']->getTime()->format('Y-m-d H:i:s') : '');

			$fullPath = URLUtil::encodePath('/' . trim($this->server->getBaseUri() . ($path ? $path . '/' : '') . $name, '/'));


			$displayName = isset($file[200]['{DAV:}displayname']) ? $file[200]['{DAV:}displayname'] : $name;
			$displayName = $this->escapeHTML($displayName);
			$type = $this->escapeHTML($type);

			$icon = '';
			$parentHash = '';
			$owner = $this->auth->owner_id;
			$splitPath = split('/', $fullPath);
			if (count($splitPath) > 3) {
				for ($i = 3; $i < count($splitPath); $i++) {
					$attachName = urldecode($splitPath[$i]);
					$attachHash = $this->findAttachHash($owner, $parentHash, $attachName);
					$parentHash = $attachHash;
				}
			}

			$attachIcon = ""; // "<a href=\"attach/".$attachHash."\" title=\"".$displayName."\"><i class=\"icon-download\"></i></a>";

			// put the array for this file together
			$ft['attachId'] = $this->findAttachIdByHash($attachHash);
			$ft['fileStorageUrl'] = substr($fullPath, 0, strpos($fullPath, 'cloud/')) . 'filestorage/' . $this->auth->getCurrentUser();
			$ft['icon'] = $icon;
			$ft['attachIcon'] = (($size) ? $attachIcon : '');
			// @todo is_owner is global right now, should check for write_storage permission!
			$ft['is_owner'] = $is_owner;
			$ft['fullPath'] = $fullPath;
			$ft['displayName'] = $displayName;
			$ft['type'] = $type;
			$ft['size'] = $size;
			$ft['sizeFormatted'] = userReadableSize($size);
			$ft['lastmodified'] = (($lastmodified) ? datetime_convert('UTC', date_default_timezone_get(), $lastmodified) : '');
			$ft['iconFromType'] = getIconFromType($type);

			$f[] = $ft;
		}

		// Storage and quota for the account (all channels of the owner of this directory)!
		$limit = service_class_fetch($owner, 'attach_upload_limit');
		$r = q("SELECT SUM(filesize) AS total FROM attach WHERE aid = %d",
			intval($this->auth->channel_account_id)
		);
		$used = $r[0]['total'];
		if ($used) {
			$quotaDesc = t('%1$s used');
			$quotaDesc = sprintf($quotaDesc,
				userReadableSize($used));
		}
		if ($limit && $used) {
			$quotaDesc = t('%1$s used of %2$s (%3$s&#37;)');
			$quotaDesc = sprintf($quotaDesc,
				userReadableSize($used),
				userReadableSize($limit),
				round($used / $limit, 1));
		}

		// prepare quota for template
		$quota = array();
		$quota['used'] = $used;
		$quota['limit'] = $limit;
		$quota['desc'] = $quotaDesc;

		$output = '';
		if ($this->enablePost) {
			$this->server->emit('onHTMLActionsPanel', array($parent, &$output));
		}

		$html .= replace_macros(get_markup_template('cloud_header.tpl'), array(
				'$header' => t('Files') . ': ' . $this->escapeHTML($path) . '/',
				'$quota' => $quota,
				'$total' => t('Total'),
				'$actionspanel' => $output,
				'$shared' => t('Shared'),
				'$create' => t('Create'),
				'$upload' => t('Upload'),
				'$is_owner' => $is_owner,
				'$channel' => $this->auth->getCurrentUser(),
				'$sectoken' => get_form_security_token()
			));

		$html .= replace_macros(get_markup_template('cloud_directory.tpl'), array(
				'$parentpath' => $parentpath,
				'$entries' => $f,
				'$name' => t('Name'),
				'$type' => t('Type'),
				'$size' => t('Size'),
				'$lastmod' => t('Last Modified'),
				'$parent' => t('parent'),
				'$edit' => t('Edit'),
				'$delete' => t('Delete'),
				'$nick' => $this->auth->getCurrentUser()
			));

		$a = get_app();
		$a->page['content'] = $html;
		load_pdl($a);

		$theme_info_file = 'view/theme/' . current_theme() . '/php/theme.php';
		if (file_exists($theme_info_file)){
			require_once($theme_info_file);
			if (function_exists(str_replace('-', '_', current_theme()) . '_init')) {
				$func = str_replace('-', '_', current_theme()) . '_init';
				$func($a);
			}
		}
		construct_page($a);
	}

	/**
	 * @brief Creates a form to add new folders and upload files.
	 *
	 * @param \Sabre\DAV\INode $node
	 * @param string &$output
	 */
	public function htmlActionsPanel(DAV\INode $node, &$output) {
		if (! $node instanceof DAV\ICollection)
			return;

		// These two checks are a bit redundant because this method should
		// not be called anyway when $enablePost is not set which is taken care
		// of already with write_storage permission check in redBeforeMethod().

		// We also know fairly certain that if an object is a non-extended
		// SimpleCollection, we won't need to show the panel either.
		if (get_class($node) === 'Sabre\\DAV\\SimpleCollection')
			return;
		// the same for RedChannelsCollection
		if (get_class($node) === 'RedMatrix\\RedDAV\\RedChannelsCollection')
			return;

		$output .= replace_macros(get_markup_template('cloud_actionspanel.tpl'), array(
				'$folder_header' => t('Create new folder'),
				'$folder_submit' => t('Create'),
				'$upload_header' => t('Upload file'),
				'$upload_submit' => t('Upload')
			));
	}

	/**
	 * This method takes a path/name of an asset and turns it into url
	 * suiteable for http access.
	 *
	 * @param string $assetName
	 * @return string
	 */
	protected function getAssetUrl($assetName) {
		return z_root() . '/cloud/?sabreAction=asset&assetName=' . urlencode($assetName);
	}

	/**
	 * @brief Return the hash of an attachment.
	 *
	 * Given the owner, the parent folder and and attach name get the attachment
	 * hash.
	 *
	 * @param int $owner
	 *  The owner_id
	 * @param string $parentHash
	 *  The parent's folder hash
	 * @param string $attachName
	 *  The name of the attachment
	 * @return string
	 */
	protected function findAttachHash($owner, $parentHash, $attachName) {
		$hash = '';

		$r = q("SELECT hash FROM attach WHERE uid = %d AND folder = '%s' AND filename = '%s' ORDER BY edited DESC LIMIT 1",
			intval($owner),
			dbesc($parentHash),
			dbesc($attachName)
		);

		if ($r) {
			$hash = $r[0]['hash'];
		}

		return $hash;
	}

	/**
	 * @brief Returns an attachment's id for a given hash.
	 *
	 * This id is used to access the attachment in filestorage/
	 *
	 * @param string $attachHash
	 *  The hash of an attachment
	 * @return string
	 */
	protected function findAttachIdByHash($attachHash) {
		$id = '';

		$r = q("SELECT id FROM attach WHERE hash = '%s' ORDER BY edited DESC LIMIT 1",
			dbesc($attachHash)
		);

		if ($r) {
			$id = $r[0]['id'];
		}

		return $id;
	}
}
