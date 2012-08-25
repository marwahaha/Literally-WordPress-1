<?php
	/* @var $this LWP_Post */
	
	$file = false;
	$uploading = false;
	$uploaded = false;
	$updating = false;
	$updated = false;
	$deleted = false;
	$error = false;
	$message = array();
	$devices_registered = array();
	//POSTが設定されていたら、アップロードアクション
	if(
		isset($_POST["_wpnonce"]) && wp_verify_nonce($_REQUEST["_wpnonce"], "lwp_upload")
		&& isset($_REQUEST["post_id"]) && $_GET["tab"] == "ebook"
	){
		//更新状態を変更
		$uploaded = true;
		//タイトルチェック
		if(empty($_REQUEST["title"])){
			$message[] = $this->_("Title is empty.");
			$error = true;
		}
		//公開状態のチェック
		if(!is_numeric($_REQUEST["public"])){
			$message[] = $this->_ ('Select public status.');
			$error = true;
		}
		//ファイルのチェック
		if(empty($_FILES["file"])){
			$message[] = $this->_('No file is specified.');
			$error = true;
		//ファイルのエラーチェック
		}elseif($this->file_has_error($_FILES["file"])){
			$message[] = $this->file_has_error($_FILES["file"]);
			$error = true;
		}
		//対応端末のチェック
		if(empty($_POST['devices'])){
			$message[] = $this->_ ('No device is specified.');
			$error = true;
		}
		//エラー状態のチェック
		if(empty($message)){
			$this->upload_file($_REQUEST["post_id"], $_REQUEST["title"], $_FILES["file"]["name"], $_FILES["file"]["tmp_name"], $_REQUEST['devices'], $_REQUEST["desc"], $_REQUEST["public"], $_REQUEST["free"]);
		}else{
			$error = true;
		}
	}
	//更新用アクション
	elseif(
		isset($_POST["_wpnonce"]) && wp_verify_nonce($_REQUEST["_wpnonce"], "lwp_update")
		&& isset($_REQUEST["post_id"]) && $_GET["tab"] == "ebook" && isset($_REQUEST["file_id"])
	){
		$updating = true;
	}
	//更新完了アクション
	elseif(
		isset($_POST["_wpnonce"]) && wp_verify_nonce($_REQUEST["_wpnonce"], "lwp_updated")
		&& isset($_REQUEST["post_id"]) && $_GET["tab"] == "ebook" && isset($_REQUEST["file_id"])
	){
		$updated = true;
		if($this->update_file($_REQUEST["file_id"], $_REQUEST["title"], $_REQUEST['devices'], $_REQUEST["desc"], $_REQUEST["public"], $_REQUEST["free"])){
			$message[] = $this->_ ('File is successfully updated.');
		}else{
			$message[] = $this->_('Failed to update file.');
			$error = true;
		}
	}
	//削除完了アクション
	elseif(
		isset($_POST["_wpnonce"]) && wp_verify_nonce($_REQUEST["_wpnonce"], "lwp_deleted")
		&& isset($_REQUEST["post_id"]) && $_GET["tab"] == "ebook" && isset($_REQUEST["file_id"])
	){
		$deleted = true;
		if($this->delete_file($_REQUEST["file_id"])){
			$message[] = $this->_('File deleted.');
		}else{
			$message[] = $this->_('Failed to delete file.');
			$error = true;
		}
	}else
		$uploading = true;
	
	//更新のとき
	if($updating || $updated){
		$file = $this->get_files(null, $_REQUEST["file_id"]);
		if($req = $this->get_devices($_REQUEST['file_id'])){
			foreach($req as $r){
				$devices_registered[] = $r->device_id;
			}
		};
	}
	$files = $this->get_files($_GET["post_id"]);
	$devices = $this->get_devices();
?>
<form method="post" class="media-upload-form type-form validate" enctype="multipart/form-data" onsubmit="jQuery(this).find('input[type=submit]').val('<?php $this->e('Processing&hellip;'); ?>').attr('disabled', true); ">
	<?php
		if($updating || $updated){
			wp_nonce_field("lwp_updated");
			echo '<input type="hidden" name="file_id" value="'.$_REQUEST["file_id"].'" />';
		}else{
			wp_nonce_field("lwp_upload");
		}
	?>
	<h3 class="media-title"><?php $this->e('Manage files')?></h3>
	<div class="media-items">
		<h4 class="media-sub-title">
		<?php if($updating || $updated): ?>
			<?php $this->e('Please update file information'); ?>
		<?php else: ?>
			<?php $this->e('Please upload file'); ?>
		<?php endif; ?>
		</h4>
		<?php if(!empty($message)): ?>
		<div class="<?php echo ($error) ?  'error' : 'updated'; ?>">
			<p>
			<?php foreach($message as $m): ?>
			<?php echo $m; ?><br />
			<?php endforeach; ?>
			</p>
		</div>
		<?php endif; ?>
		<table class="describe">
			<tbody>
				<tr>
					<th scope="row" valign="top" class="label">
						<label for="title"><?php $this->e('Title'); ?></label>
					</th>
					<td class="field">
						<input id="title" name="title" type="text" value="<?php if($updating || $updated) echo esc_html($file->name); ?>"/>
						<p class="help"><?php $this->e('Name of this file. ex. ePub for iBooks(2nd Edition)'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top" class="label">
						<label><?php $this->e('Public Status'); ?></label>
					</th>
					<td class="field">
						<label><input name="public" type="radio" value="1"<?php if(!($updating || $updated) || $file->public == 1) echo ' checked="checked"'; ?> /><?php $this->e('Public'); ?></label>
						<label><input name="public" type="radio" value="0"<?php if(($updating || $updated) && $file->public == 0) echo ' checked="checked"'; ?> /><?php $this->e('Private'); ?></label>
						<p class="help"><?php $this->e('Public status of this file. Private files won\'t be displayed.'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top" class="label">
						<label><?php $this->e('Trial'); ?></label>
					</th>
					<td class="field">
						<label><input name="free" type="radio" value="0"<?php if(!($updating || $updated) || $file->free == 0) echo ' checked="checked"'; ?> /><?php $this->e('Impossible'); ?></label>
						<label><input name="free" type="radio" value="1"<?php if(($updating || $updated) && $file->free == 1) echo ' checked="checked"'; ?> /><?php $this->e('Possible for registered user'); ?></label>
						<label><input name="free" type="radio" value="2"<?php if(($updating || $updated) && $file->free == 2) echo ' checked="checked"'; ?> /><?php $this->e('Possible for everyone'); ?></label>
						<p class="help">
							<?php $this->e('Trial means <strong>user can download whole of the file</strong>.'); ?><br />
							<?php printf($this->_('If you want provide partial trial, <strong>make a partial file</strong> and specify it as &quot;%s&quot;'), $this->_('Possible for everyone')); ?>
						</p>
					</td>
				</tr>
				<tr>
				<tr>
					<th scope="row" valign="top" class="label">
						<label><?php $this->e('Devices'); ?></label>
					</th>
					<td class="field">
						<?php foreach($devices as $d): ?>
						<label><input name="devices[]" type="checkbox" value="<?php echo $d->ID; ?>"<?php if(($updating || $updated) && false !== array_search($d->ID, $devices_registered)) echo ' checked="checked"'; ?> /><?php echo $d->name; ?></label>　
						<?php endforeach; ?>
						<p class="help">
							<?php $this->e('Check device which can read this file.'); ?><br />
							<?php printf($this->_('To add device, go to %s.'), '<a href="#" onclick="parent.location.href = \''.admin_url('admin.php?page=lwp-devices').'\'">'.$this->_('Devices').'</a>'); ?>
						</p>
					</td>
				</tr>
				</tr>
				<tr>
					<th scope="row" valign="top" class="label">
						<label for="desc"><?php $this->e('Description'); ?></label>
					</th>
					<td class="field">
						<textarea id="desc" name="desc"><?php if($updating || $updated) echo esc_html($file->description); ?></textarea>
						<p class="help"><?php $this->e('Enter if required.'); ?></p>
					</td>
				</tr>
				<?php if(!($updating || $updated)): ?>
				<tr>
					<th scope="row" valign="top" class="label">
						<label for="file"><?php $this->e('File'); ?></label>
					</th>
					<td class="field">
						<input id="file" name="file" type="file" />
					</td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<p class="submit">
			<input type="submit" name="submit" value="<?php echo ($updating || $updated) ? $this->_('Update') : $this->_('Add'); ?>" />
			<?php if(($updating || $updated)): ?>
			<a class="button" href=""><?php $this->e('Go to add file'); ?></a>
			<?php endif; ?>
		</p>
	</div>
</form>

<?php if(!empty($files)): ?>
<div id="media-upload">
	<div id="media-items" style="margin:1em">
		<h4 class="media-sub-title"><?php $this->e('Registered files'); ?></h4>
		<?php foreach($files as $f): ?>
		<div class="media-item">
			<form method="post" class="describe-toggle-on">
				<?php wp_nonce_field("lwp_deleted"); ?>
				<input type="hidden" value="<?php echo $f->ID; ?>" name="file_id" />
				<input type="submit" class="button" value="<?php $this->e('Delete') ?>" onclick="if(!confirm('<?php $this->e('Are you sure to delete?'); ?>')) return false;" />
			</form>
			<form method="post" class="describe-toggle-on">
				<?php wp_nonce_field("lwp_update"); ?>
				<input type="hidden" value="<?php echo $f->ID; ?>" name="file_id" />
				<input type="submit" class="button" value="<?php $this->e('Update') ?>" />
			</form>
			<div class="filename">
				<span class="title"><?php echo esc_html($f->name); ?></span>
			</div>
			<br style="clear:both" />
		</div>
		<!-- .media-items -->
		
		<?php endforeach; ?>
	</div>
</div>
<?php endif; ?>