<div class="section-content-wrapper-np">
	<table id="cloud-index">
		<tr>
			<th width="1%"></th>
			<th width="92%">{{$name}}</th>
			<th width="1%"></th><th width="1%"></th><th width="1%"></th><th width="1%"></th>
			<th width="1%">{{*{{$type}}*}}</th>
			<th width="1%" class="hidden-xs">{{$size}}</th>
			<th width="1%" class="hidden-xs">{{$lastmod}}</th>
		</tr>
	{{if $parentpath}}
		<tr>
			<td><i class="icon-level-up"></i>{{*{{$parentpath.icon}}*}}</td>
			<td><a href="{{$parentpath.path}}" title="{{$parent}}">..</a></td>
			<td></td><td></td><td></td><td></td>
			<td>{{*[{{$parent}}]*}}</td>
			<td class="hidden-xs"></td>
			<td class="hidden-xs"></td>
		</tr>
	{{/if}}
	{{foreach $entries as $item}}
		<tr id="cloud-index-{{$item.attachId}}">
			<td><i class="{{$item.iconFromType}}" title="{{$item.type}}"></i></td>
			<td><a href="{{$item.fullPath}}">{{$item.displayName}}</a></td>
	{{if $item.is_owner}}
			<td class="cloud-index-tool">{{$item.attachIcon}}</td>
			<td id="file-edit-{{$item.attachId}}" class="cloud-index-tool"></td>
			<td class="cloud-index-tool"><i class="fakelink icon-pencil" onclick="filestorage(event, '{{$nick}}', {{$item.attachId}});"></i></td>
			<td class="cloud-index-tool"><button type="submit" form="form_delete" name="attachId" value="{{$item.attachId}}" title="{{$delete}}" onclick="return confirmDelete();" class="btn btn-xs btn-link icon-trash drop-icons"></td>
	{{else}}
			<td></td><td></td><td></td><td></td>
	{{/if}}
			<td>{{*{{$item.type}}*}}</td>
			<td class="hidden-xs">{{$item.sizeFormatted}}</td>
			<td class="hidden-xs">{{$item.lastmodified}}</td>
		</tr>
		<tr id="cloud-tools-{{$item.attachId}}">
			<td id="perms-panel-{{$item.attachId}}" colspan="9"></td>
		</tr>
	{{/foreach}}
	</table>
</div>
