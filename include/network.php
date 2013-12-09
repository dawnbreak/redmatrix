<?php /** @file */



function get_capath() {
	return appdirpath() . '/library/cacert.pem';
}

/**
 * @function z_fetch_url
 * @param string $url
 *    URL to fetch
 * @param boolean $binary = false
 *    TRUE if asked to return binary results (file download)
 * @param int $redirects = 0
 *    internal use, recursion counter
 * @param array $opts (optional parameters)
 *    'accept_content' => supply Accept: header with 'accept_content' as the value
 *    'timeout' => int seconds, default system config value or 60 seconds
 *    'http_auth' => username:password
 *    'novalidate' => do not validate SSL certs, default is to validate using our CA list
 *    
 * @returns array
 *    'return_code' => HTTP return code or 0 if timeout or failure
 *    'success' => boolean true (if HTTP 2xx result) or false
 *    'header' => HTTP headers
 *    'body' => fetched content
 */

function z_fetch_url($url, $binary = false, $redirects = 0, $opts = array()) {

	$ret = array('return_code' => 0, 'success' => false, 'header' => "", 'body' => "");

	$a = get_app();

	$ch = @curl_init($url);
	if(($redirects > 8) || (! $ch)) 
		return false;

	@curl_setopt($ch, CURLOPT_HEADER, true);
	@curl_setopt($ch, CURLOPT_CAINFO, get_capath());
	@curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
	@curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
	@curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; Red)");

	if (x($opts,'accept_content')){
		curl_setopt($ch,CURLOPT_HTTPHEADER, array (
			"Accept: " . $opts['accept_content']
		));
	}

	if(x($opts,'timeout') && intval($opts['timeout'])) {
		@curl_setopt($ch, CURLOPT_TIMEOUT, $opts['timeout']);
	}
	else {
		$curl_time = intval(get_config('system','curl_timeout'));
		@curl_setopt($ch, CURLOPT_TIMEOUT, (($curl_time !== false) ? $curl_time : 60));
	}

	if(x($opts,'http_auth')) {
		// "username" . ':' . "password"
		@curl_setopt($ch, CURLOPT_USERPWD, $opts['http_auth']);
	}

	@curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 
		((x($opts,'novalidate') && intval($opts['novalidate'])) ? false : true));


	$prx = get_config('system','proxy');
	if(strlen($prx)) {
		@curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
		@curl_setopt($ch, CURLOPT_PROXY, $prx);
		$prxusr = @get_config('system','proxyuser');
		if(strlen($prxusr))
			@curl_setopt($ch, CURLOPT_PROXYUSERPWD, $prxusr);
	}
	if($binary)
		@curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);


	// don't let curl abort the entire application
	// if it throws any errors.

	$s = @curl_exec($ch);

	$base = $s;
	$curl_info = @curl_getinfo($ch);
	$http_code = $curl_info['http_code'];
//	logger('fetch_url:' . $http_code . ' data: ' . $s);
	$header = '';

	// Pull out multiple headers, e.g. proxy and continuation headers
	// allow for HTTP/2.x without fixing code

	while(preg_match('/^HTTP\/[1-2].+? [1-5][0-9][0-9]/',$base)) {
		$chunk = substr($base,0,strpos($base,"\r\n\r\n")+4);
		$header .= $chunk;
		$base = substr($base,strlen($chunk));
	}

	if($http_code == 301 || $http_code == 302 || $http_code == 303 || $http_code == 307 || $http_code == 308) {
		$matches = array();
		preg_match('/(Location:|URI:)(.*?)\n/', $header, $matches);
		$newurl = trim(array_pop($matches));
		if(strpos($newurl,'/') === 0)
			$newurl = $url . $newurl;
		$url_parsed = @parse_url($newurl);
		if (isset($url_parsed)) {
			@curl_close($ch);
			return z_fetch_url($newurl,$binary,$redirects++,$opts);
		}
	}

	$rc = intval($http_code);
	$ret['return_code'] = $rc;
	$ret['success'] = (($rc >= 200 && $rc <= 299) ? true : false);
	if(! $ret['success']) {
		$ret['debug'] = $curl_info;
		logger('z_fetch_url: debug:' . print_r($curl_info,true), LOGGER_DATA);
	}
	$ret['body'] = substr($s,strlen($header));
	$ret['header'] = $header;
	
	@curl_close($ch);
	return($ret);
}




function z_post_url($url,$params, $redirects = 0, $opts = array()) {

	$ret = array('return_code' => 0, 'success' => false, 'header' => "", 'body' => "");

	$ch = curl_init($url);
	if(($redirects > 8) || (! $ch)) 
		return ret;

	curl_setopt($ch, CURLOPT_HEADER, true);
	@curl_setopt($ch, CURLOPT_CAINFO, get_capath());
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
	curl_setopt($ch, CURLOPT_POST,1);
	curl_setopt($ch, CURLOPT_POSTFIELDS,$params);
	curl_setopt($ch, CURLOPT_USERAGENT, "Red");


	if (x($opts,'accept_content')){
		curl_setopt($ch,CURLOPT_HTTPHEADER, array (
			"Accept: " . $opts['accept_content']
		));
	}
	if(x($opts,'headers'))
		curl_setopt($ch, CURLOPT_HTTPHEADER, $opts['headers']);

	if(x($opts,'timeout') && intval($opts['timeout'])) {
		@curl_setopt($ch, CURLOPT_TIMEOUT, $opts['timeout']);
	}
	else {
		$curl_time = intval(get_config('system','curl_timeout'));
		@curl_setopt($ch, CURLOPT_TIMEOUT, (($curl_time !== false) ? $curl_time : 60));
	}

	if(x($opts,'http_auth')) {
		// "username" . ':' . "password"
		@curl_setopt($ch, CURLOPT_USERPWD, $opts['http_auth']);
	}

	@curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 
		((x($opts,'novalidate') && intval($opts['novalidate'])) ? false : true));

	$prx = get_config('system','proxy');
	if(strlen($prx)) {
		curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
		curl_setopt($ch, CURLOPT_PROXY, $prx);
		$prxusr = get_config('system','proxyuser');
		if(strlen($prxusr))
			curl_setopt($ch, CURLOPT_PROXYUSERPWD, $prxusr);
	}

	// don't let curl abort the entire application
	// if it throws any errors.

	$s = @curl_exec($ch);

	$base = $s;
	$curl_info = curl_getinfo($ch);
	$http_code = $curl_info['http_code'];

	$header = '';

	// Pull out multiple headers, e.g. proxy and continuation headers
	// allow for HTTP/2.x without fixing code

	while(preg_match('/^HTTP\/[1-2].+? [1-5][0-9][0-9]/',$base)) {
		$chunk = substr($base,0,strpos($base,"\r\n\r\n")+4);
		$header .= $chunk;
		$base = substr($base,strlen($chunk));
	}

	if($http_code == 301 || $http_code == 302 || $http_code == 303 || $http_code == 307 || $http_code == 308) {
		$matches = array();
		preg_match('/(Location:|URI:)(.*?)\n/', $header, $matches);
		$newurl = trim(array_pop($matches));
		if(strpos($newurl,'/') === 0)
			$newurl = $url . $newurl;
		$url_parsed = @parse_url($newurl);
		if (isset($url_parsed)) {
			curl_close($ch);
			if($http_code == 303) {
				return z_fetch_url($newurl,false,$redirects++,$opts);
			} else {
				return z_post_url($newurl,$params,$redirects++,$opts);
			}
		}
	}
	$rc = intval($http_code);
	$ret['return_code'] = $rc;
	$ret['success'] = (($rc >= 200 && $rc <= 299) ? true : false);
	if(! $ret['success']) {
		$ret['debug'] = $curl_info;
		logger('z_fetch_url: debug:' . print_r($curl_info,true), LOGGER_DATA);
	}

	$ret['body'] = substr($s,strlen($header));
	$ret['header'] = $header;
	curl_close($ch);
	return($ret);
}



function json_return_and_die($x) {
	header("content-type: application/json");
	echo json_encode($x);
	killme();
}



// Generic XML return
// Outputs a basic dfrn XML status structure to STDOUT, with a <status> variable 
// of $st and an optional text <message> of $message and terminates the current process. 


function xml_status($st, $message = '') {

	$xml_message = ((strlen($message)) ? "\t<message>" . xmlify($message) . "</message>\r\n" : '');

	if($st)
		logger('xml_status returning non_zero: ' . $st . " message=" . $message);

	header( "Content-type: text/xml" );
	echo '<?xml version="1.0" encoding="UTF-8"?>'."\r\n";
	echo "<result>\r\n\t<status>$st</status>\r\n$xml_message</result>\r\n";
	killme();
}

/**
 * @function http_status_exit
 * 
 * Send HTTP status header and exit
 * @param int $val
 *    integer HTTP status result value
 * @param string $msg
 *    optional message
 * @returns (does not return, process is terminated)
 */

function http_status_exit($val,$msg = '') {

    $err = '';
	if($val >= 400)
		$msg = (($msg) ? $msg : 'Error');
	if($val >= 200 && $val < 300)
		$msg = (($msg) ? $msg : 'OK');

	logger('http_status_exit ' . $val . ' ' . $msg);	
	header($_SERVER['SERVER_PROTOCOL'] . ' ' . $val . ' ' . $msg);
	killme();
}


// convert an XML document to a normalised, case-corrected array
// used by webfinger


function convert_xml_element_to_array($xml_element, &$recursion_depth=0) {

        // If we're getting too deep, bail out
        if ($recursion_depth > 512) {
                return(null);
        }

        if (!is_string($xml_element) &&
        !is_array($xml_element) &&
        (get_class($xml_element) == 'SimpleXMLElement')) {
                $xml_element_copy = $xml_element;
                $xml_element = get_object_vars($xml_element);
        }

        if (is_array($xml_element)) {
                $result_array = array();
                if (count($xml_element) <= 0) {
                        return (trim(strval($xml_element_copy)));
                }

                foreach($xml_element as $key=>$value) {

                        $recursion_depth++;
                        $result_array[strtolower($key)] =
                convert_xml_element_to_array($value, $recursion_depth);
                        $recursion_depth--;
                }
                if ($recursion_depth == 0) {
                        $temp_array = $result_array;
                        $result_array = array(
                                strtolower($xml_element_copy->getName()) => $temp_array,
                        );
                }

                return ($result_array);

        } else {
                return (trim(strval($xml_element)));
        }
}

// Given an email style address, perform webfinger lookup and 
// return the resulting DFRN profile URL, or if no DFRN profile URL
// is located, returns an OStatus subscription template (prefixed 
// with the string 'stat:' to identify it as on OStatus template).
// If this isn't an email style address just return $s.
// Return an empty string if email-style addresses but webfinger fails,
// or if the resultant personal XRD doesn't contain a supported 
// subscription/friend-request attribute.

// amended 7/9/2011 to return an hcard which could save potentially loading 
// a lengthy content page to scrape dfrn attributes


function webfinger_dfrn($s,&$hcard) {
	if(! strstr($s,'@')) {
		return $s;
	}
	$profile_link = '';

	$links = webfinger($s);
	logger('webfinger_dfrn: ' . $s . ':' . print_r($links,true), LOGGER_DATA);
	if(count($links)) {
		foreach($links as $link) {
			if($link['@attributes']['rel'] === NAMESPACE_DFRN)
				$profile_link = $link['@attributes']['href'];
			if($link['@attributes']['rel'] === NAMESPACE_OSTATUSSUB)
				$profile_link = 'stat:' . $link['@attributes']['template'];	
			if($link['@attributes']['rel'] === 'http://microformats.org/profile/hcard')
				$hcard = $link['@attributes']['href'];				
		}
	}
	return $profile_link;
}

// Given an email style address, perform webfinger lookup and 
// return the array of link attributes from the personal XRD file.
// On error/failure return an empty array.



function webfinger($s, $debug = false) {
	$host = '';
	if(strstr($s,'@')) {
		$host = substr($s,strpos($s,'@') + 1);
	}
	if(strlen($host)) {
		$tpl = fetch_lrdd_template($host);
		logger('webfinger: lrdd template: ' . $tpl);
		if(strlen($tpl)) {
			$pxrd = str_replace('{uri}', urlencode('acct:' . $s), $tpl);
			logger('webfinger: pxrd: ' . $pxrd);
			$links = fetch_xrd_links($pxrd);
			if(! count($links)) {
				// try with double slashes
				$pxrd = str_replace('{uri}', urlencode('acct://' . $s), $tpl);
				logger('webfinger: pxrd: ' . $pxrd);
				$links = fetch_xrd_links($pxrd);
			}
			return $links;
		}
	}
	return array();
}




// Given a host name, locate the LRDD template from that
// host. Returns the LRDD template or an empty string on
// error/failure.


function fetch_lrdd_template($host) {
	$tpl = '';

	$url1 = 'https://' . $host . '/.well-known/host-meta' ;
	$url2 = 'http://' . $host . '/.well-known/host-meta' ;
	$links = fetch_xrd_links($url1);
	logger('fetch_lrdd_template from: ' . $url1);
	logger('template (https): ' . print_r($links,true));
	if(! count($links)) {
		logger('fetch_lrdd_template from: ' . $url2);
		$links = fetch_xrd_links($url2);
		logger('template (http): ' . print_r($links,true));
	}
	if(count($links)) {
		foreach($links as $link)
			if($link['@attributes']['rel'] && $link['@attributes']['rel'] === 'lrdd')
				$tpl = $link['@attributes']['template'];
	}
	if(! strpos($tpl,'{uri}'))
		$tpl = '';
	return $tpl;
}

// Take a URL from the wild, prepend http:// if necessary
// and check DNS to see if it's real (or check if is a valid IP address)
// return true if it's OK, false if something is wrong with it


function validate_url(&$url) {
	
	// no naked subdomains (allow localhost for tests)
	if(strpos($url,'.') === false && strpos($url,'/localhost/') === false)
		return false;
	if(substr($url,0,4) != 'http')
		$url = 'http://' . $url;
	$h = @parse_url($url);
	
	if(($h) && (dns_get_record($h['host'], DNS_A + DNS_CNAME + DNS_PTR) || filter_var($h['host'], FILTER_VALIDATE_IP) )) {
		return true;
	}
	return false;
}

// checks that email is an actual resolvable internet address


function validate_email($addr) {

	if(get_config('system','disable_email_validation'))
		return true;

	if(! strpos($addr,'@'))
		return false;
	$h = substr($addr,strpos($addr,'@') + 1);

	if(($h) && (dns_get_record($h, DNS_A + DNS_CNAME + DNS_PTR + DNS_MX) || filter_var($h['host'], FILTER_VALIDATE_IP) )) {
		return true;
	}
	return false;
}

// Check $url against our list of allowed sites,
// wildcards allowed. If allowed_sites is unset return true;
// If url is allowed, return true.
// otherwise, return false


function allowed_url($url) {

	$h = @parse_url($url);

	if(! $h) {
		return false;
	}

	$str_allowed = get_config('system','allowed_sites');
	if(! $str_allowed)
		return true;

	$found = false;

	$host = strtolower($h['host']);

	// always allow our own site

	if($host == strtolower($_SERVER['SERVER_NAME']))
		return true;

	$fnmatch = function_exists('fnmatch');
	$allowed = explode(',',$str_allowed);

	if(count($allowed)) {
		foreach($allowed as $a) {
			$pat = strtolower(trim($a));
			if(($fnmatch && fnmatch($pat,$host)) || ($pat == $host)) {
				$found = true; 
				break;
			}
		}
	}
	return $found;
}

// check if email address is allowed to register here.
// Compare against our list (wildcards allowed).
// Returns false if not allowed, true if allowed or if
// allowed list is not configured.


function allowed_email($email) {


	$domain = strtolower(substr($email,strpos($email,'@') + 1));
	if(! $domain)
		return false;

	$str_allowed = get_config('system','allowed_email');
	if(! $str_allowed)
		return true;

	$found = false;

	$fnmatch = function_exists('fnmatch');
	$allowed = explode(',',$str_allowed);

	if(count($allowed)) {
		foreach($allowed as $a) {
			$pat = strtolower(trim($a));
			if(($fnmatch && fnmatch($pat,$domain)) || ($pat == $domain)) {
				$found = true; 
				break;
			}
		}
	}
	return $found;
}



function avatar_img($email) {

	$a = get_app();

	$avatar['size'] = 175;
	$avatar['email'] = $email;
	$avatar['url'] = '';
	$avatar['success'] = false;

	call_hooks('avatar_lookup', $avatar);

	if(! $avatar['success'])
		$avatar['url'] = $a->get_baseurl() . '/images/default_profile_photos/rainbow_man/175.jpg';

	logger('Avatar: ' . $avatar['email'] . ' ' . $avatar['url'], LOGGER_DEBUG);
	return $avatar['url'];
}



function parse_xml_string($s,$strict = true) {
	if($strict) {
		if(! strstr($s,'<?xml'))
			return false;
		$s2 = substr($s,strpos($s,'<?xml'));
	}
	else
		$s2 = $s;
	libxml_use_internal_errors(true);

	$x = @simplexml_load_string($s2);
	if(! $x) {
		logger('libxml: parse: error: ' . $s2, LOGGER_DATA);
		foreach(libxml_get_errors() as $err)
			logger('libxml: parse: ' . $err->code." at ".$err->line.":".$err->column." : ".$err->message, LOGGER_DATA);
		libxml_clear_errors();
	}
	return $x;
}


function scale_external_images($s, $include_link = true, $scale_replace = false) {

	$a = get_app();

	// Picture addresses can contain special characters
	$s = htmlspecialchars_decode($s);

	$matches = null;
	$c = preg_match_all('/\[img(.*?)\](.*?)\[\/img\]/ism',$s,$matches,PREG_SET_ORDER);
	if($c) {
		require_once('include/photo/photo_driver.php');

		foreach($matches as $mtch) {
			logger('scale_external_image: ' . $mtch[1] . ' ' . $mtch[2]);
			
			if(substr($mtch[1],0,1) == '=') {
				$owidth = intval(substr($mtch[1],1));
				if(intval($owidth) > 0 && intval($owidth) < 640)
					continue;
			}

			$hostname = str_replace('www.','',substr($a->get_baseurl(),strpos($a->get_baseurl(),'://')+3));
			if(stristr($mtch[2],$hostname))
				continue;

			// $scale_replace, if passed, is an array of two elements. The
			// first is the name of the full-size image. The second is the
			// name of a remote, scaled-down version of the full size image.
			// This allows Friendica to display the smaller remote image if
			// one exists, while still linking to the full-size image
			if($scale_replace)
				$scaled = str_replace($scale_replace[0], $scale_replace[1], $mtch[2]);
			else
				$scaled = $mtch[2];
			$i = z_fetch_url($scaled);


			$cache = get_config('system','itemcache');
			if (($cache != '') and is_dir($cache)) {
				$cachefile = $cache."/".hash("md5", $scaled);
				file_put_contents($cachefile, $i['body']);
			}

			// guess mimetype from headers or filename
			$type = guess_image_type($mtch[2],$i['header']);
			
			if($i['success']) {
				$ph = photo_factory($i['body'], $type);
				if($ph->is_valid()) {
					$orig_width = $ph->getWidth();
					$orig_height = $ph->getHeight();

					if($orig_width > 640 || $orig_height > 640) {

						$ph->scaleImage(640);
						$new_width = $ph->getWidth();
						$new_height = $ph->getHeight();
						logger('scale_external_images: ' . $orig_width . '->' . $new_width . 'w ' . $orig_height . '->' . $new_height . 'h' . ' match: ' . $mtch[0], LOGGER_DEBUG);
						$s = str_replace($mtch[0],'[img=' . $new_width . 'x' . $new_height. ']' . $scaled . '[/img]'
							. "\n" . (($include_link) 
								? '[zrl=' . $mtch[2] . ']' . t('view full size') . '[/zrl]' . "\n"
								: ''),$s);
						logger('scale_external_images: new string: ' . $s, LOGGER_DEBUG);
					}
				}
			}
		}
	}

	// replace the special char encoding

	$s = htmlspecialchars($s,ENT_COMPAT,'UTF-8');

	return $s;
}

/**
 * xml2array() will convert the given XML text to an array in the XML structure.
 * Link: http://www.bin-co.com/php/scripts/xml2array/
 * Portions significantly re-written by mike@macgirvin.com for Friendica (namespaces, lowercase tags, get_attribute default changed, more...)
 * Arguments : $contents - The XML text
 *                $namespaces - true or false include namespace information in the returned array as array elements.
 *                $get_attributes - 1 or 0. If this is 1 the function will get the attributes as well as the tag values - this results in a different array structure in the return value.
 *                $priority - Can be 'tag' or 'attribute'. This will change the way the resulting array sturcture. For 'tag', the tags are given more importance.
 * Return: The parsed XML in an array form. Use print_r() to see the resulting array structure.
 * Examples: $array =  xml2array(file_get_contents('feed.xml'));
 *              $array =  xml2array(file_get_contents('feed.xml', true, 1, 'attribute'));
 */ 

function xml2array($contents, $namespaces = true, $get_attributes=1, $priority = 'attribute') {
    if(!$contents) return array();

    if(!function_exists('xml_parser_create')) {
        logger('xml2array: parser function missing');
        return array();
    }


	libxml_use_internal_errors(true);
	libxml_clear_errors();

	if($namespaces)
	    $parser = @xml_parser_create_ns("UTF-8",':');
	else
	    $parser = @xml_parser_create();

	if(! $parser) {
		logger('xml2array: xml_parser_create: no resource');
		return array();
	}

    xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8"); 
	// http://minutillo.com/steve/weblog/2004/6/17/php-xml-and-character-encodings-a-tale-of-sadness-rage-and-data-loss
    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
    xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
    @xml_parse_into_struct($parser, trim($contents), $xml_values);
    @xml_parser_free($parser);

    if(! $xml_values) {
		logger('xml2array: libxml: parse error: ' . $contents, LOGGER_DATA);
		foreach(libxml_get_errors() as $err)
			logger('libxml: parse: ' . $err->code . " at " . $err->line . ":" . $err->column . " : " . $err->message, LOGGER_DATA);
		libxml_clear_errors();
		return;
	}

    //Initializations
    $xml_array = array();
    $parents = array();
    $opened_tags = array();
    $arr = array();

    $current = &$xml_array; // Reference

    // Go through the tags.
    $repeated_tag_index = array(); // Multiple tags with same name will be turned into an array
    foreach($xml_values as $data) {
        unset($attributes,$value); // Remove existing values, or there will be trouble

        // This command will extract these variables into the foreach scope
        // tag(string), type(string), level(int), attributes(array).
        extract($data); // We could use the array by itself, but this cooler.

        $result = array();
        $attributes_data = array();
        
        if(isset($value)) {
            if($priority == 'tag') $result = $value;
            else $result['value'] = $value; // Put the value in a assoc array if we are in the 'Attribute' mode
        }

        //Set the attributes too.
        if(isset($attributes) and $get_attributes) {
            foreach($attributes as $attr => $val) {
                if($priority == 'tag') $attributes_data[$attr] = $val;
                else $result['@attributes'][$attr] = $val; // Set all the attributes in a array called 'attr'
            }
        }

        // See tag status and do the needed.
		if($namespaces && strpos($tag,':')) {
			$namespc = substr($tag,0,strrpos($tag,':')); 
			$tag = strtolower(substr($tag,strlen($namespc)+1));
			$result['@namespace'] = $namespc;
		}
		$tag = strtolower($tag);

		if($type == "open") {   // The starting of the tag '<tag>'
            $parent[$level-1] = &$current;
            if(!is_array($current) or (!in_array($tag, array_keys($current)))) { // Insert New tag
                $current[$tag] = $result;
                if($attributes_data) $current[$tag. '_attr'] = $attributes_data;
                $repeated_tag_index[$tag.'_'.$level] = 1;

                $current = &$current[$tag];

            } else { // There was another element with the same tag name

                if(isset($current[$tag][0])) { // If there is a 0th element it is already an array
                    $current[$tag][$repeated_tag_index[$tag.'_'.$level]] = $result;
                    $repeated_tag_index[$tag.'_'.$level]++;
                } else { // This section will make the value an array if multiple tags with the same name appear together
                    $current[$tag] = array($current[$tag],$result); // This will combine the existing item and the new item together to make an array
                    $repeated_tag_index[$tag.'_'.$level] = 2;
                    
                    if(isset($current[$tag.'_attr'])) { // The attribute of the last(0th) tag must be moved as well
                        $current[$tag]['0_attr'] = $current[$tag.'_attr'];
                        unset($current[$tag.'_attr']);
                    }

                }
                $last_item_index = $repeated_tag_index[$tag.'_'.$level]-1;
                $current = &$current[$tag][$last_item_index];
            }

        } elseif($type == "complete") { // Tags that ends in 1 line '<tag />'
            //See if the key is already taken.
            if(!isset($current[$tag])) { //New Key
                $current[$tag] = $result;
                $repeated_tag_index[$tag.'_'.$level] = 1;
                if($priority == 'tag' and $attributes_data) $current[$tag. '_attr'] = $attributes_data;

            } else { // If taken, put all things inside a list(array)
                if(isset($current[$tag][0]) and is_array($current[$tag])) { // If it is already an array...

                    // ...push the new element into that array.
                    $current[$tag][$repeated_tag_index[$tag.'_'.$level]] = $result;
                    
                    if($priority == 'tag' and $get_attributes and $attributes_data) {
                        $current[$tag][$repeated_tag_index[$tag.'_'.$level] . '_attr'] = $attributes_data;
                    }
                    $repeated_tag_index[$tag.'_'.$level]++;

                } else { // If it is not an array...
                    $current[$tag] = array($current[$tag],$result); //...Make it an array using using the existing value and the new value
                    $repeated_tag_index[$tag.'_'.$level] = 1;
                    if($priority == 'tag' and $get_attributes) {
                        if(isset($current[$tag.'_attr'])) { // The attribute of the last(0th) tag must be moved as well
                            
                            $current[$tag]['0_attr'] = $current[$tag.'_attr'];
                            unset($current[$tag.'_attr']);
                        }
                        
                        if($attributes_data) {
                            $current[$tag][$repeated_tag_index[$tag.'_'.$level] . '_attr'] = $attributes_data;
                        }
                    }
                    $repeated_tag_index[$tag.'_'.$level]++; // 0 and 1 indexes are already taken
                }
            }

        } elseif($type == 'close') { // End of tag '</tag>'
            $current = &$parent[$level-1];
        }
    }
    
    return($xml_array);
}  


function email_header_encode($in_str, $charset = 'UTF-8') {
    $out_str = $in_str;
	$need_to_convert = false;

	for($x = 0; $x < strlen($in_str); $x ++) {
		if((ord($in_str[$x]) == 0) || ((ord($in_str[$x]) > 128))) {
			$need_to_convert = true;
		}
	}

	if(! $need_to_convert)
		return $in_str;

    if ($out_str && $charset) {

        // define start delimimter, end delimiter and spacer
        $end = "?=";
        $start = "=?" . $charset . "?B?";
        $spacer = $end . "\r\n " . $start;

        // determine length of encoded text within chunks
        // and ensure length is even
        $length = 75 - strlen($start) - strlen($end);

        /*
            [EDIT BY danbrown AT php DOT net: The following
            is a bugfix provided by (gardan AT gmx DOT de)
            on 31-MAR-2005 with the following note:
            "This means: $length should not be even,
            but divisible by 4. The reason is that in
            base64-encoding 3 8-bit-chars are represented
            by 4 6-bit-chars. These 4 chars must not be
            split between two encoded words, according
            to RFC-2047.
        */
        $length = $length - ($length % 4);

        // encode the string and split it into chunks
        // with spacers after each chunk
        $out_str = base64_encode($out_str);
        $out_str = chunk_split($out_str, $length, $spacer);

        // remove trailing spacer and
        // add start and end delimiters
        $spacer = preg_quote($spacer,'/');
        $out_str = preg_replace("/" . $spacer . "$/", "", $out_str);
        $out_str = $start . $out_str . $end;
    }
    return $out_str;
}

function email_send($addr, $subject, $headers, $item) {
	//$headers .= 'MIME-Version: 1.0' . "\n";
	//$headers .= 'Content-Type: text/html; charset=UTF-8' . "\n";
	//$headers .= 'Content-Type: text/plain; charset=UTF-8' . "\n";
	//$headers .= 'Content-Transfer-Encoding: 8bit' . "\n\n";

	$part = uniqid("", true);

	$html    = prepare_body($item);

	$headers .= "Mime-Version: 1.0\n";
	$headers .= 'Content-Type: multipart/alternative; boundary="=_'.$part.'"'."\n\n";

	$body = "\n--=_".$part."\n";
	$body .= "Content-Transfer-Encoding: 8bit\n";
	$body .= "Content-Type: text/plain; charset=utf-8; format=flowed\n\n";

	$body .= html2plain($html)."\n";

	$body .= "--=_".$part."\n";
	$body .= "Content-Transfer-Encoding: 8bit\n";
	$body .= "Content-Type: text/html; charset=utf-8\n\n";

	$body .= '<html><head></head><body style="word-wrap: break-word; -webkit-nbsp-mode: space; -webkit-line-break: after-white-space; ">'.$html."</body></html>\n";

	$body .= "--=_".$part."--";

	//$message = '<html><body>' . $html . '</body></html>';
	//$message = html2plain($html);
	logger('notifier: email delivery to ' . $addr);
	mail($addr, $subject, $body, $headers);
}
