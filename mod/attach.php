<?php

require_once('include/security.php');
require_once('include/attach.php');

function attach_init(&$a) {

	if(argc() < 2) {
		notice( t('Item not available.') . EOL);
		return;
	}

	$r = attach_by_hash(argv(1),((argc() > 2) ? intval(argv(2)) : 0));

	if(! $r['success']) {
		notice( $r['message'] . EOL);
		return;
	}

	header('Content-type: ' . $r['data']['filetype']);
	header('Content-disposition: attachment; filename=' . $r['data']['filename']);
	if($r['data']['flags'] & ATTACH_FLAG_OS ) {
		$stream = fopen($r['data']['data'],'rb');
		if($stream) {
			pipe_stream($stream,STDOUT);
			fclose($stream);
		}
	}
	else
		echo $r['data']['data'];
	killme();

}