/* really? wtf! */
window.URL = window.URL || window.webkitURL;

/**
 * @brief Returns a human readable filesize.
 *
 * @todo translate strings
 * @todo do we already have something like this? Can we use it somewhere else, too?
 * @fixme does return nothing for files < 1KiB
 *
 * @param nBytes filesize in bytes
 * @param nPrecision (optional) default 2
 * @returns string
 */
function formatFilesize(nBytes, nPrecision) {
	nPrecision = typeof nPrecision !== 'undefined' ? nPrecision : 2;
	sFormated = "";
	for (var aMultiples = ["KiB", "MiB", "GiB", "TiB", "PiB", "EiB", "ZiB", "YiB"], nMultiple = 0, nApprox = nBytes / 1024; nApprox > 1; nApprox /= 1024, nMultiple++) {
		sFormated = nApprox.toFixed(nPrecision) + " " + aMultiples[nMultiple];
	}
	if (nBytes < 1024) {
		sFormated = nBytes + " B";
	}

	return sFormated;
}

/**
 * @brief Check selected upload files against limits before uploading.
 *
 * This is a client side function to determine the size of an upload before
 * actually transfering it to the server just to realice that it is too big.
 *
 * The relevant limits can be set in php.ini post_max_size and upload_max_filesize.
 *
 * @todo check also against other upload limits like service class, quota, etc.
 * @todo fix information overkill
 * @todo disable Upload button
 * @todo add possibility to remove one file that is too big, but keep the others for upload
 * @todo translate all strings
 *
 * @params oFiles a Files object
 * @params bPreview (optional) show detailed preview of files before uploading
 */
function cloudCheckFiles(oFiles, bPreview) {
	bPreview = typeof bPreview !== 'undefined' ? bPreview : true;
	var nBytes = 0;
	var nFiles = oFiles.length;
	var nFails = 0;
	for (var nFileId = 0; nFileId < nFiles; nFileId++) {
		nBytes += oFiles[nFileId].size;
		if (oFiles[nFileId].size >= cloud_upload_filesize) {
			nFails++;
		}
	}
	var sOutput = '<span title="' + nBytes + ' bytes">';
	sOutput += formatFilesize(nBytes);
	sOutput += "</span>";
	document.getElementById("cloudFiles").innerHTML = nFiles;
	document.getElementById("cloudFileSize").innerHTML = sOutput;

	if (nBytes >= cloud_upload_size) {
		alert(sprintf(aStr.cloud_sizetoobig, formatFilesize(cloud_upload_size)));
	} else if (nFails > 0 && !bPreview) {
		alert(sprintf(aStr.cloud_ntoobig, nFails, formatFilesize(cloud_upload_filesize)));
	}

	if (bPreview) {
		previewFiles(oFiles);
	}
}

/**
 * @brief Create previews of the selected files to upload.
 *
 * @todo merge with cloudCheckFiles()?
 *
 * @param files a Files object
 */
function previewFiles(files) {
	var preview = document.getElementById("cloudFilesPreview");
	if (!files.length) {
		preview.innerHTML = "<p>" + aStr.cloud_noFilesSelected + "</p>";
	} else {
		preview.innerHTML = "<h4>" + aStr.cloud_filePreviews + "</h4>";
		var list = document.createElement("ul");
		preview.appendChild(list);
		for (var i = 0; i < files.length; i++) {
			var imageType = /^image\//;
			var li = document.createElement("li");
			list.appendChild(li);
			// red border when this file is over upload limit
			if (files[i].size >= cloud_upload_filesize) {
				li.setAttribute("style", "border:1px solid red");
			}
			if (imageType.test(files[i].type)) {
				var img = document.createElement("img");
				img.src = window.URL.createObjectURL(files[i]);
				img.height = 50;
				/** @fixme Better no functions in loops. */
				img.onload = function() {
					window.URL.revokeObjectURL(this.src);
				};
				li.appendChild(img);
			}
			var info = document.createElement("span");
			info.innerHTML = aStr.filename + ": " + files[i].name + "<br>" + aStr.filesize + ": " + formatFilesize(files[i].size);
			if (files[i].size >= cloud_upload_filesize) {
				info.innerHTML += " " + sprintf(aStr.cloud_filetoobig, formatFilesize(cloud_upload_filesize));
			}
			info.innerHTML += "<br>" + aStr.mimetype + ": " + files[i].type;
			info.setAttribute("title", files[i].size + " bytes");
			li.appendChild(info);
		}
	}
}