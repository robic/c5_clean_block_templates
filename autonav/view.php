<?php
//For extra functionality, you can add the following page attributes (via Dashboard -> Pages and Themes -> Attributes):
//
// 1) Handle: replace_link_with_first_in_nav
//    Type: Checkbox
//    Functionality: If a page has this checked, clicking on it in the nav menu will go to its first child (sub-page) instead.
//
// 2) Handle: exclude_subpages_from_nav
//    Type: Checkbox
//    Functionality: If a page has this checked, all of that pages children (sub-pages) will be excluded from the nav menu (but the page itself will be included).
//
// 3) Handle: nav_item_class
//    Type: Text
//    Functionality: Whatever is entered into this textbox will be outputted as an additional CSS class for that page's nav item.


/*************************************************
 * DESIGNERS: SCROLL DOWN! (IGNORE ALL THIS STUFF)
 *************************************************/
defined('C5_EXECUTE') or die("Access Denied.");

$c = Page::getCurrentPage();

//Create an array of parent cIDs so we can determine the "nav path" of the current page
$inspectC = $c;
$selectedPathCIDs = array($inspectC->getCollectionID());
$parentCIDnotZero=true;
while ($parentCIDnotZero) {
	$cParentID = $inspectC->cParentID;
	if (!intval($cParentID)) {
		$parentCIDnotZero=false;
	} else {
		if ($cParentID != HOME_CID) {
			$selectedPathCIDs[] = $cParentID; //Don't want home page in nav-path-selected
		}
		$inspectC = Page::getById($cParentID);
	}
}

//Remove excluded pages from the list (do this first because some of the data prep code needs to "look ahead" in the list)
$allNavItems = $controller->generateNav();
$includedNavItems = array();
$excluded_parent_level = 9999; //Arbitrarily high number denotes that we're NOT currently excluding a parent (because all actual page levels will be lower than this)
$exclude_children_below_level = 9999; //Same deal as above. Note that in this case "below" means a HIGHER number (because a lower number indicates higher placement in the sitemp -- e.g. 0 is top-level)
foreach ($allNavItems as $ni) {
	$_c = $ni->getCollectionObject();
	$current_level = $ni->getLevel();
	
	if ($_c->getAttribute('exclude_nav') && ($current_level <= $excluded_parent_level)) {
		$excluded_parent_level = $current_level;
		$exclude_page = true;
	} else if (($current_level > $excluded_parent_level) || ($current_level > $exclude_children_below_level)) {
		$exclude_page = true;
	} else {
		$excluded_parent_level = 9999; //Reset to arbitrarily high number to denote that we're no longer excluding a parent
		$exclude_children_below_level = $_c->getAttribute('exclude_subpages_from_nav') ? $current_level : 9999;
		$exclude_page = false;
	}
	
	if (!$exclude_page) {
		$includedNavItems[] = $ni;
	}
}

//Prep all data and put it into a clean structure so markup output is as simple as possible
$navItems = array();
$navItemCount = count($includedNavItems);
for ($i = 0; $i < $navItemCount; $i++) {
	$ni = $includedNavItems[$i];
	$_c = $ni->getCollectionObject();
	$current_level = $ni->getLevel();
	
	//Link target (e.g. open in new window)
	$target = $ni->getTarget();
	$target = empty($target) ? '_self' : $target;
	
	//Link URL
	$pageLink = false;
	if ($_c->getAttribute('replace_link_with_first_in_nav')) {
		$subPage = $_c->getFirstChild(); //Note: could be a rare bug here if first child was excluded, but this is so unlikely (and can be solved by moving it in the sitemap) that it's not worth the trouble to check
		if ($subPage instanceof Page) {
			$pageLink = Loader::helper('navigation')->getLinkToCollection($subPage); //We could optimize by instantiating the navigation helper outside the loop, but this is such an infrequent attribute that I prefer code clarity over performance in this case
		}
	}			
	if (!$pageLink) {
		$pageLink = $ni->getURL();
	}
		
	//Current/ancestor page
	$selected = false;
	$path_selected = false;
	if ($c->getCollectionID() == $_c->getCollectionID()) { 
		$selected = true; //Current item is the page being viewed
		$path_selected = true;
	} elseif (in_array($_c->getCollectionID(), $selectedPathCIDs)) { 
		$path_selected = true; //Current item is an ancestor of the page being viewed
	}			 
	
	//Calculate difference between this item's level and next item's level so we know how many closing tags to output in the markup
	$next_level = isset($includedNavItems[$i+1]) ? $includedNavItems[$i+1]->getLevel() : 0;
	$levels_between_this_and_next = $current_level - $next_level;

	//Determine if this item has children (can't rely on $ni->hasChildren() because it doesn't ignore excluded items!)
	$has_children = $next_level > $current_level;
	
	//Calculate if this is the first item in its level (useful for CSS classes)
	$prev_level = isset($includedNavItems[$i-1]) ? $includedNavItems[$i-1]->getLevel() : -1;
	$is_first_in_level = $current_level > $prev_level;
	
	//Calculate if this is the last item in its level (useful for CSS classes)
	$is_last_in_level = true;
	for ($j = $i+1; $j < $navItemCount; $j++) {
		if ($includedNavItems[$j]->getLevel() == $current_level) {
			//we found a subsequent item at this level (before this level "ended"), so this is NOT the last in its level
			$is_last_in_level = false;
			break;
		}
		if ($includedNavItems[$j]->getLevel() < $current_level) {
			//we found a previous level before any other items in this level, so this IS the last in its level
			$is_last_in_level = true;
			break;
		}
	} //If loop ends before one of the "if" conditions is hit, then this is the last in its level (and $is_last_in_level stays true)
	
	
	//CSS classes
	$attribute_class = $_c->getAttribute('nav_item_class');
	$cid = $_c->getCollectionID();
	$classes = array();
	
/******************************************************************************
* DESIGNERS: CUSTOMIZE CSS CLASSES HERE...
*/
	if ($selected) {
		//class for the page currently being viewed
		$classes[] = 'nav-selected';
	}
	
	if ($path_selected) {
		//class for parent items of the page currently being viewed
		$classes[] = 'nav-path-selected';
	}
	
	if ($is_first_in_level) {
		//class for the first item in each menu section (first top-level item, and first item of each dropdown sub-menu)
		$classes[] = 'nav-first';
	}
	
	if ($is_last_in_level) {
		//class for the last item in each menu section (last top-level item, and last item of each dropdown sub-menu)
		$classes[] = 'nav-last';
	}
	
	if ($has_children) {
		//class for items that have dropdown sub-menus
		$classes[] = 'nav-dropdown';
	}
	
	if (!empty($attribute_class)) {
		//class that can be set by end-user via the 'nav_item_class' custom page attribute
		$classes[] = $attribute_class;
	}
	
	//unique class for every single menu item (disabled by default -- uncomment the next line to enable it)
	//$classes[] = 'nav-item-' . $cid;

	
/* END CSS CLASSESS (DESIGNERS: SCROLL DOWN A LITTLE MORE TO GET TO HTML)
 *****************************************************************************/

	$classes = array_filter($classes); //remove empty/null items
	$classesString = implode(" ", $classes);
	
	
	//Package up all the data
	$item = new stdClass();
	$item->url = $pageLink;
	$item->name = $ni->getName();
	$item->target = $target;
	$item->classes = $classesString;
	$item->children = $has_children;
	$item->depth = $levels_between_this_and_next;
	$item->first = $is_first_in_level;
	$item->last = $is_last_in_level;
	$item->current = $selected;
	$navItems[] = $item;
}

/******************************************************************************
* DESIGNERS: CUSTOMIZE THE NAV MENU HTML STARTING HERE...
*/

echo '<ul class="nav">'; //opens the top-level menu

foreach ($navItems as $ni) {
	
	echo '<li class="' . $ni->classes . '">'; //opens the nav item
	
	echo '<a href="' . $ni->url . '" target="' . $ni->target . '" class="' . $ni->classes . '">' . $ni->name . '</a>';
	
	if ($ni->children) {
		echo '<ul>'; //opens a dropdown sub-menu
	} else {
		echo '</li>'; //closes the nav item
		echo str_repeat('</ul></li>', $ni->depth); //closes dropdown sub-menu(s) and their top-level nav item(s)
	}
}

echo '</ul>'; //closes the top-level menu
