<?php

/**
 * Edit layout template form.
 */

$page->layout = 'admin';

if (! User::require_admin ()) {
	$this->redirect ('/admin');
}

if (! preg_match ('/^(layouts|layouts\/[a-z0-9_-]+)\/[a-z0-9_-]+\.html$/i', $_GET['file'])) {
	$this->redirect ('/designer');
}

$lock = new Lock ('Designer', $_GET['file']);
if ($lock->exists ()) {
	$page->title = __ ('Editing Locked');
	echo $tpl->render ('admin/locked', $lock->info ());
	return;
} else {
	$lock->add ();
}

$f = new Form ('post', 'designer/editlayout');
$f->verify_csrf = false;
if ($f->submit ()) {
	if (@file_put_contents ($_GET['file'], $_POST['body'])) {
		$this->add_notification (__ ('Layout saved.'));
		try {
			@chmod ($_GET['file'], 0666);
		} catch (Exception $e) {}
		$lock->remove ();
		$this->redirect ('/designer');
	}
	$page->title = __ ('Saving Layout Failed');
	echo '<p>' . __ ('Check that your permissions are correct and try again.') . '</p>';
} else {
	$page->title = __ ('Edit Layout') . ': ' . $_GET['file'];
}

$o = new StdClass;
$o->file = $_GET['file'];
$o->body = @file_get_contents ($_GET['file']);

$o->failed = $f->failed;
$o = $f->merge_values ($o);
$page->add_script ('/apps/designer/css/edit_layout.css');
$page->add_script ('/apps/designer/js/jquery.bindWithDelay.js');
echo $tpl->render ('designer/edit/layout', $o);

?>