<?php

/**
 * Renders a web page from the webpage table.
 * This is the default handler in Elefant,
 * allowing it to work as a web page-serving
 * CMS by default.
 */

// determine page id
$id = count ($this->params) ? $this->params[0] : 'index';

// check if cached
$res = $cache->get ('_admin_page_' . $id);
if ($res) {
	$pg = (is_object ($res)) ? $res : unserialize ($res);
	foreach ($pg as $key => $value) {
		$page->{$key} = $value;
	}

	// show admin edit buttons
	if (User::is_valid () && User::is ('admin')) {
		$lock = new Lock ('Webpage', $id);
		$page->locked = $lock->exists ();
		echo $tpl->render ('admin/editable', $page);
	}

	// output the page body
	echo $page->body;
	return;
}

// get it from the database
$wp = new Webpage ($id);

// page not found
if ($wp->error) {
	echo $this->error (404, __ ('Page not found'), '<p>' . __ ('Hmm, we can\'t seem to find the page you wanted at the moment.') . '</p>');
	return;
}

// access control
if ($wp->access !== 'public' && ! User::is ('admin')) {
	if (! User::require_login ()) {
		$page->title = __ ('Login required');
		echo $this->run ('user/login');
		return;
	}
	if (! User::access ($wp->access)) {
		$page->title = __ ('Login required');
		echo $this->run ('user/login');
		return;
	}
}


// set the page properties
$page->id = $id;
$page->title = $wp->title;
$page->_menu_title = $wp->menu_title;
$page->_window_title = $wp->window_title;
$page->description = $wp->description;
$page->keywords = $wp->keywords;
$page->layout = $wp->layout;
$page->head = $wp->head;

// show admin edit buttons
if (User::is_valid () && User::is('admin')) {
	$lock = new Lock ('Webpage', $id);
	$page->locked = $lock->exists ();
	echo $tpl->render ('admin/editable', $page);
}

// execute any embedded includes
$out = $tpl->run_includes ($wp->body);

if ($wp->access == 'public' && $out === $wp->body) {
	// public page, no includes, cacheable.
	$page->body = $out;
	$cache->set ('_admin_page_' . $id, serialize ($page));
}

// output the page body
echo $out;

?>
