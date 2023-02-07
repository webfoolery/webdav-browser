<?php
require_once('./webdav.php');
$webdav = new webdav();
// if (isset($webdav->lockedFiles)) echo'<pre>'.print_r($webdav->showLocked(), 1).'</pre>';exit;
// echo'<pre>'.print_r($webdav->showLocked(), 1).'</pre>';
// echo'<pre>'.print_r($webdav, 1).'</pre>';
?>

<!doctype html>
<html>
<head>
<meta charset="utf-8">
<link rel="shortcut icon" href="favicon.png" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/uikit@3.5.5/dist/css/uikit.min.css" />
<link rel="stylesheet" href="webdav.css" />
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="webdav.js"></script>
<script src="https://cdn.jsdelivr.net/npm/uikit@3.5.5/dist/js/uikit.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/uikit@3.5.5/dist/js/uikit-icons.min.js"></script>
<title>Webdav Browser</title>
</head>

<body>
	<h1 class="uk-heading-line uk-text-center"><span>Webdav Browser</span></h1>
	<div class="uk-container uk-container-expand">
		<div class="uk-grid-small" uk-grid>
			<div class="uk-width-1-4@m">
				<div class="uk-card uk-card-default">
					<div class="uk-card-header"><h2>Credentials</h2></div>
					<div class="uk-card-body">
						<form id="adminForm" class="uk-form-stacked" method="post">
							<div class="uk-margin">
								<label class="uk-form-label" for="username">Username</label>
								<div class="uk-form-controls">
									<input class="uk-input" name="username" id="username" value="<?php echo $webdav->username; ?>" placeholder="Username" type="text" />
								</div>
							</div>
							<div class="uk-margin">
								<label class="uk-form-label" for="password">Password</label>
								<div class="uk-form-controls">
									<input class="uk-input" name="password" id="password" value="<?php echo $webdav->password; ?>" placeholder="Password" type="password" />
								</div>
							</div>
							<div class="uk-margin">
								<label class="uk-form-label" for="location">Location</label>
								<div class="uk-form-controls">
									<input class="uk-input" name="location" id="location" value="<?php echo $webdav->location; ?>" placeholder="Scheme://location:port" type="text" />
								</div>
							</div>
							<div class="uk-margin">
								<label class="uk-form-label" for="endpoint">Endpoint</label>
								<div class="uk-form-controls">
									<input class="uk-input" name="endpoint" id="endpoint" value="<?php echo $webdav->endpoint; ?>" placeholder="Endpoint" type="text" />
								</div>
							</div>
							<div class="uk-margin">
								<label class="uk-form-label" for="task">Task</label>
								<div class="uk-form-controls">
									<select class="uk-select" name="task" id="task">
										<option value="">Directory browser</option>
										<option value="showLocked">Show all locked files</option>
										<option value="getlistFilesArray">getlistFilesArray</option>
										<option value="getPropertiesArray">getPropertiesArray</option>
										<option value="getLockToken">getLockToken</option>
										<option value="propfind">PROPFIND</option>
										<option value="unlock">unlock</option>
									</select>
								</div>
							</div>
							<input type="hidden" name="altUsername" value="" />
							<input type="hidden" name="altPassword" value="" />
							<div class="uk-margin"><button class="uk-button uk-button-primary" type="submit">Send</button></div>
						</form>
					</div>
				</div>
			</div>
			<div class="uk-width-3-4@m">
				<div class="uk-grid-small uk-child-width:1-1" uk-grid>
					<?php 
					if (isset($webdav->itemProperties)) : 
					?>
					<div>
						<div class="uk-card uk-card-default">
							<div class="uk-card-header"><h2>Properties</h2></div>
							<div class="uk-card-body">
								<?php if (isset($webdav->unlockStatus)) : ?>
									<?php if ($webdav->unlockStatus['status'] == 'ok') : ?>
										<div class="uk-alert-primary" uk-alert>
											<a class="uk-alert-close" uk-close></a>
											<p>The file was successfully unlocked.</p>
										</div>
									<?php else : ?>
										<div class="uk-alert-danger" uk-alert>
											<a class="uk-alert-close" uk-close></a>
											<p>Unlocking failed.</p>
										</div>
									<?php endif; ?>
								<?php endif; ?>
								<ul class="uk-list uk-list-collapse">
									<?php foreach ($webdav->itemProperties as $k=>$v) :?>
										<li><b><?php echo $k; ?>:</b> <?php echo $v; ?></li>
									<?php endforeach; ?>
								</ul>
								<?php if (isset($webdav->itemProperties['lockToken'])) : ?>
									<input class="uk-input uk-form-width-small" name="altUserEntry" type="text" placeholder="Alt username" />
									<input class="uk-input uk-form-width-small" name="altPassEntry" type="password" placeholder="Alt password" />
									<button type="button" id="unlockFile" class="uk-button uk-button-danger">Unlock this file</button>
								<?php endif; ?>
							</div>
						</div>
					</div>
					<?php endif; ?>
					
					
					<?php 
					if (isset($webdav->lockedFiles)) :
						$tableLockedFiles = '';
						foreach ($webdav->lockedFiles as $lockedFile) {
							if (!$lockedFile) continue;
							$lockedFile['type'] = explode('/', $lockedFile['type'])[count(explode('/', $lockedFile['type']))-1];
							$class = 'uk-text-danger';
							$tableLockedFiles .= '<tr data-endpoint="'.$lockedFile['path'].'" class="'.$class.'">
							<td>'.urldecode(str_replace($webdav->endpoint, '', $lockedFile['name'])).'</td>
							<td>'.$lockedFile['type'].'</td>
							<td>'.$lockedFile['created'].'</td>
							<td>'.$lockedFile['modified'].'</td>
							<td>'.$lockedFile['sizeFormatted'].'</td>
							<td>'.$lockedFile['lock'].'</td>
							</tr>';
						}
						?>
							<div>
								<div class="uk-card uk-card-default">
									<div class="uk-card-header"><h2>All Locked Files (recursed within <i><?php echo $webdav->endpoint; ?></i>)</h2></div>
									<div class="uk-card-body">
										<?php if (strlen($tableLockedFiles)) : ?>
											<table class="uk-table uk-table-small uk-table-striped fileList"><tr>
												<th>Name</th>
												<th>Type</th>
												<th>Created</th>
												<th>Modified</th>
												<th>Size</th>
												<th>Lock</th>
											</tr>
											<?php echo $tableLockedFiles;?>
											</table>
										<?php else: ?>
											<p>No files appear to be locked.</p>
										<?php endif; ?>
									</div>
								</div>
							</div>
					<?php endif; ?>
					
					
					
					
					<?php 
					if (isset($webdav->fileList) && !isset($webdav->fileList['error']) && is_array($webdav->fileList) && count($webdav->fileList)) :
						$fileList = $webdav->fileList;
						if (!isset($fileList['error']) && is_array($fileList) && count($fileList)) :
							$tableBrowser = '';
							$tableLockedFiles = '';
							foreach ($fileList as $file) {
								if (!$file) continue;
								$file['type'] = explode('/', $file['type'])[count(explode('/', $file['type']))-1];
								if ($file['lock']) {
									$class = 'uk-text-danger';
									$tableLockedFiles .= '<tr data-endpoint="'.$file['path'].'" class="'.$class.'">
									<td>'.urldecode(str_replace($webdav->endpoint, '', $file['name'])).'</td>
									<td>'.$file['type'].'</td>
									<td>'.$file['created'].'</td>
									<td>'.$file['modified'].'</td>
									<td>'.$file['sizeFormatted'].'</td>
									<td>'.$file['lock'].'</td>
									</tr>';
								}
								else $class = '';
								$tableBrowser .= '<tr data-endpoint="'.$file['path'].'" class="'.$class.'">
									<td>'.urldecode(str_replace($webdav->endpoint, '', $file['name'])).'</td>
									<td>'.$file['type'].'</td>
									<td>'.$file['created'].'</td>
									<td>'.$file['modified'].'</td>
									<td>'.$file['sizeFormatted'].'</td>
									<td>'.$file['lock'].'</td>
									</tr>';
							}
							if (strlen($tableLockedFiles) && !$webdav->lockedFiles) :
							?>
								<div>
									<div class="uk-card uk-card-default">
										<div class="uk-card-header"><h2>Locked Files</h2></div>
										<div class="uk-card-body">
											<table class="uk-table uk-table-small uk-table-striped fileList"><tr>
												<th>Name</th>
												<th>Type</th>
												<th>Created</th>
												<th>Modified</th>
												<th>Size</th>
												<th>Lock</th>
											</tr>
											<?php echo $tableLockedFiles; ?>
											</table>
										</div>
									</div>
								</div>
							<?php endif; ?>
						<div>
							<div class="uk-card uk-card-default">
								<div class="uk-card-header"><h2>Browser</h2></div>
								<div class="uk-card-body">
									<table class="uk-table uk-table-small uk-table-striped fileList"><tr>
										<th>Name</th>
										<th>Type</th>
										<th>Created</th>
										<th>Modified</th>
										<th>Size</th>
										<th>Lock</th>
									</tr>
									<?php echo $tableBrowser; ?>
									</table>
								</div>
							</div>
						</div>
					<?php endif; ?>
				<?php endif; ?>
				</div>
			</div>
		</div>
		<div class="uk-child-width-1-1" uk-grid>
			<div>
				<div class="uk-card uk-card-default">
					<div class="uk-card-header"><h2>Debugging</h2></div>
					<div class="uk-card-body">
						<ul uk-accordion="multiple: true">
							<?php
							if (isset($fileList['error'])) echo'<li><a class="uk-accordion-title" href="#">$fileList[error]</a><div class="uk-accordion-content"><pre>'.print_r($fileList, 1).'</pre></div></li>';
							if (isset($webdav->fileList)) echo'<li><a class="uk-accordion-title" href="#">fileList</a><div class="uk-accordion-content"><pre>'.print_r($webdav->fileList, 1).'</pre></div></li>';
							if (isset($webdav->unlockStatus)) echo'<li><a class="uk-accordion-title" href="#">unlockStatus</a><div class="uk-accordion-content"><pre>'.print_r($webdav->unlockStatus, 1).'</pre></div></li>';
							if (isset($webdav->itemProperties)) echo'<li><a class="uk-accordion-title" href="#">itemProperties</a><div class="uk-accordion-content"><pre>'.print_r($webdav->itemProperties, 1).'</pre></div></li>';
							if (isset($webdav->verboseLog)) echo'<li><a class="uk-accordion-title" href="#">verboseLog</a><div class="uk-accordion-content"><pre>'.print_r($webdav->verboseLog, 1).'</pre></div></li>';
							if (isset($webdav->propfind)) echo'<li><a class="uk-accordion-title" href="#">propfind</a><div class="uk-accordion-content"><pre>'.htmlentities($webdav->propfind, 1).'</pre></div></li>';
							if (isset($webdav)) echo'<li><a class="uk-accordion-title" href="#">webdav</a><div class="uk-accordion-content"><pre>'.print_r($webdav, 1).'</pre></div></li>';
							if (count($webdav->recurseLog)) echo'<li><a class="uk-accordion-title" href="#">recurseLog</a><div class="uk-accordion-content"><pre>'.print_r($webdav->recurseLog, 1).'</pre></div></li>';
							?>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</div>
	
	
</body>
</html>