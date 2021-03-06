<?php

/**
 * Print the site tree as an HTML list with the current
 * context open as sub-lists up to the first-level children
 * of the current page. The currently active page will have
 * the class `current` and parents will have the class
 * `parent`.
 *
 * Usage:
 *
 *     $n = new Navigation;
 *     $path = $n->path ($page->id);
 *     $path = ($path) ? $path : array ();
 *     navigation_print_context ($n->tree, $path);
 */
function navigation_print_context ($tree, $path) {
	echo '<ul>';
	foreach ($tree as $item) {
		if ($item->attr->id == $path[count ($path) - 1]) {
			printf ('<li class="current"><a href="/%s">%s</a>', $item->attr->id, $item->data);
			if (isset ($item->children)) {
				navigation_print_context ($item->children, $path);
			}
			echo '</li>';
		} elseif (in_array ($item->attr->id, $path)) {
			printf ('<li class="parent"><a href="/%s">%s</a>', $item->attr->id, $item->data);
			if (isset ($item->children)) {
				navigation_print_context ($item->children, $path);
			}
			echo '</li>';
		} else {
			printf ('<li><a href="/%s">%s</a></li>', $item->attr->id, $item->data);
		}
	}
	echo '</ul>';
}

/**
 * Print the full site tree as an HTML list.
 */
function navigation_print_level ($tree) {
	echo '<ul>';
	foreach ($tree as $item) {
		printf ('<li><a href="/%s">%s</a>', $item->attr->id, $item->data);
		if (isset ($item->children)) {
			navigation_print_level ($item->children);
		}
		echo '</li>';
	}
	echo '</ul>';
}

/**
 * Clears all cache keys for the navigation app.
 */
function navigation_clear_cache () {
	global $cache;
	$cache->delete ('_navigation_top');
	$cache->delete ('_c_navigation_map');
	$cache->delete ('_c_navigation_dropmenu');
}

/**
 * Returns a list of pages that are not in the navigation.
 */
function navigation_get_other_pages ($ids) {
	$pages = array ();
	$res = DB::fetch ('select id, title, menu_title from #prefix#webpage where access = "public"');
	foreach ($res as $p) {
		if (in_array ($p->id, $ids)) {
			// skip if in tree
			continue;
		}
		if (! empty ($p->menu_title)) {
			$pages[$p->id] = $p->menu_title;
		} else {
			$pages[$p->id] = $p->title;
		}
	}
	return $pages;
}

function navigation_print_admin_tree ($tree, $tree_root=true) {
	echo ($tree_root) ?'<ul class="tdd-tree">' : "<ul>";
	foreach ($tree as $item) {
		printf ('<li id="%s"><i class="%s"></i> %s', $item->attr->id, $item->attr->classname,  $item->data);
		if (isset ($item->children)) {
			navigation_print_admin_tree ($item->children, false);
		}
		echo '</li>';
	}
	echo '</ul>';
}


?>
