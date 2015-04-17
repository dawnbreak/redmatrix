<div id="files-mkdir-tools" class="section-content-tools-wrapper form-group">
	<label for="files-mkdir">{{$folder_header}}</label>
	<form method="post" action="">
		<input type="hidden" name="sabreAction" value="mkcol">
		<input id="files-mkdir" type="text" name="name" class="form-control form-group">
		<button class="btn btn-primary btn-sm pull-right" type="submit" value="{{$folder_submit}}">{{$folder_submit}}</button>
	</form>
	<div class="clear"></div>
</div>
<div id="files-upload-tools" class="section-content-tools-wrapper form-group">
	<label for="files-upload">{{$upload_header}}</label>
	<br>{{$limit_info}}
	<form method="post" action="" enctype="multipart/form-data">
		<input type="hidden" name="sabreAction" value="put">
		<input class="form-group" id="files-upload" type="file" name="file" onchange="cloudCheckFiles(this.files);">
		<input type="checkbox" name="overwrite" id="files_overwrite"><label for="files_overwrite">{{$overwrite}}</label>
		<button class="btn btn-primary btn-sm pull-right" type="submit" value="{{$upload_submit}}">{{$upload_submit}}</button>
		<div id="cloudUpload"><h3>Selected uploads summary</h3>
			<output name="cloudFiles" id="cloudFiles" for="files-upload">0</output> selected files
			with total filesize: <output name="cloudFileSize" id="cloudFileSize" for="files-upload">0</output><br>
			<div id="cloudFilesPreview"></div>
		</div>
	</form>
	<div class="clear"></div>
</div>
