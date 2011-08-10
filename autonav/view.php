<?php defined('C5_EXECUTE') or die("Access Denied.");

//Settings for designers...
$menuUlClass = 'nav'; //CSS class for the nav menu's <ul> element.
$navSelectedClass = 'nav-selected'; //CSS class for the page currently being viewed (applied to the <li> AND <a> elements)
$navPathSelectedClass = 'nav-path-selected'; //CSS class for the page currently being viewed AND that page's parent/grandparent/etc. (applied to the <li> AND <a> elements)
$firstLiInUlClass = 'nav-first'; //CSS Class for the first item in any UL (so first item of the top-level, and the first item of each dropdown, etc.)
$everyItemUniqueClassPrefix = 'nav-item-'; //Prepended to each item's collection id (leave blank if you don't want a unique class for each item).

$excludeChildrenFromNavAttrHandle = 'exclude_subpages_from_nav'; //Attribute that denotes a page should be excluded from the nav menu (will also exclude the page's children/grandchildren/etc.)
$replaceLinkWithFirstInNavAttrHandle = 'replace_link_with_first_in_nav'; //Attribute that denotes all of a page's children/grandchildren/etc. should be excluded from the nav menu
$navItemClassAttrHandle = 'nav_item_class';

$bottomOfDropdownsMarkup = ''; //HTML inserted at the bottom of each dropdown menu (items below the top-level) -- use this if you need non-semantic markup for rounded corners, drop-shadows, etc.


/*** SAMPLE CODE FOR "HARD-CODING" A SINGLE-LEVEL OR TWO-LEVEL DROPDOWN MENU INTO YOUR THEME TEMPLATES:
<?php
$nav = BlockType::getByHandle('autonav');
$nav->controller->orderBy = 'display_asc';
$nav->controller->displayPages = 'top';
$nav->controller->displaySubPages = 'all';
$nav->controller->displaySubPageLevels = 'custom';
$nav->controller->displaySubPageLevelsNum = 1; //<--change to 2 for a two-level dropdown menu
$nav->render('view');
?>
END SAMPLE CODE */


/*** Jordan's notes for implementing Superfish dropdown menu...
* Download jquery superfish plugin from http://users.tpg.com.au/j_birch/plugins/superfish/ (then click "Download & Support" tab)
  (Note that jQuery is only used for IE6 compatibility and optional visual effects -- you can just use its CSS if you don't care about IE6 or fancy stuff like dropdown menu fade-ins or dynamically-drawn arrows)
* Copy superfish.js and hoverIntent.js files to your theme's 'js' directory
* Copy the 2 image files to your theme's 'images' directory (arrows-ffffff.png and shadow.png)
* Copy css/superfish.css to your theme's 'css' directory
* Either change $menuClassOrId up above to 'class="sf-menu"', OR change all instances of "sf-menu" to "nav" in superfish.css
* In superfish.css, remove quotes from around url paths (this sometimes messes up C5)
* If you didn't put superfish.css inside a 'css' directory (it's just at the top-level of your theme directory), then change url paths so they correctly point to your theme's images directory (probably need to remove '../' from all paths)
* Add includes for css and js to theme's <head>:
    <link rel="stylesheet" type="text/css" href="<?php print $this->getThemePath(); ?>/css/superfish.css" />
    <script type="text/javascript" src="<?php print $this->getThemePath(); ?>/js/hoverIntent.js"></script>
    <script type="text/javascript" src="<?php print $this->getThemePath(); ?>/js/superfish.js"></script>
* Initialize superfish by putting this code in your theme's <head>:
    <script type="text/javascript">
        $(document).ready(function() {
            $('ul.nav').superfish({
                pathClass: 'nav-path-selected',
                autoArrows: false
            });
        });
    </script>
* MODIFY "DEMO SKIN" portion of superfish.css to accomodate your site (or remove it entirely if you've already styled the non-dropdown menu in yer css)
* Set width (and height) of submenus via the 4 comments containing the word "match" in the "ESSENTIAL STYLES" section of the css -- these need to be tweaked as per your theme's style
  [note that this is not optional: all 3 "match ul width" ones MUST actually match, otherwise the menu will not look right!]

==POTENTIAL CSS PROBS:
~Use firebug -- for example, superfish overwrites margin and padding to 0, but I had padding on my elements -- so just wrap the thing in a div and style that div instead.
~You can delete entire arrow section if you don't want that thing
~If it's not making sense based on top/left, margin/padding, etc. -- try floats and clears!

END Superfish Notes */


/**********************************************************
 * DESIGNERS: YOU CAN PROBABLY IGNORE EVERYTHING BELOW HERE
 **********************************************************/

//Initialize variables
$navItems = $controller->generateNav();
$c = Page::getCurrentPage();
$isFirstItem = true;
$isFirstLiInUl = true;
$excluded_parent_level = 9999; //Arbitrarily high number denotes that we're NOT currently excluding a parent (because all actual page levels will be lower than this)
$exclude_children_below_level = 9999; //Same deal as above. Note that in this case "below" means a HIGHER number (because a lower number indicates higher placement in the sitemp -- e.g. 0 is top-level)
$nh = Loader::helper('navigation');

//Create an array of parent cIDs so we can determine the "nav path" of the current page
$inspectC=$c;
$selectedPathCIDs=array( $inspectC->getCollectionID() );
$parentCIDnotZero=true;
while($parentCIDnotZero){
	$cParentID=$inspectC->cParentID;
	if(!intval($cParentID)){
		$parentCIDnotZero=false;
	}else{
		if ($cParentID != HOME_CID) {
			$selectedPathCIDs[]=$cParentID; //Don't want home page in nav-path-selected
		}
		$inspectC=Page::getById($cParentID);
	}
}

//Loop through each page
foreach($navItems as $ni) {
	//Determine if this page should be excluded from the nav menu
	$_c = $ni->getCollectionObject();
	if ($_c->getCollectionAttributeValue('exclude_nav') && ($ni->getLevel() <= $excluded_parent_level)) {
		$excluded_parent_level = $ni->getLevel();
	} else if ($ni->getLevel() <= $excluded_parent_level && $ni->getLevel() <= $exclude_children_below_level) {
		$excluded_parent_level = 9999; //Reset to arbitrarily high number to denote that we're no longer excluding a parent
		$exclude_children_below_level = 9999; //Same as above
		if ($_c->getCollectionAttributeValue($excludeChildrenFromNavAttrHandle)) {
			$exclude_children_below_level = $ni->getLevel();
		}
		
		
		//Output opening list tag if this is the first time through the loop
		if ($isFirstItem) {
			echo '<ul class="'.$menuUlClass.'">';
		}
		
		//Output appropriate opening/closing list tags depending on where we are in the loop
		$thisLevel = $ni->getLevel();
		if ($thisLevel > $lastLevel) {
			echo '<ul>';
			$isFirstLiInUl = true;
		} else if ($thisLevel < $lastLevel) {
			for ($j = $thisLevel; $j < $lastLevel; $j++) {
				echo '</li>';
				echo $bottomOfDropdownsMarkup;
				echo '</ul>';
				if ($lastLevel - $j <= 1) {
					echo '</li>';
				}
			}
		} else if ($i > 0) {
			echo '</li>';
		}
		

		//PREP DATA FOR NAV ITEM OUTPUT...

		$pageName = $ni->getName();

		//Page URL (might be first child page instead of this page)
		$pageLink = false;
		if ($_c->getCollectionAttributeValue($replaceLinkWithFirstInNavAttrHandle)) {
			$subPage = $_c->getFirstChild();
			if ($subPage instanceof Page) {
				$pageLink = $nh->getLinkToCollection($subPage);
			}
		}
		if (!$pageLink) {
			$pageLink = $ni->getURL();
		}
		
		//Link target (e.g. open in new window)
		$target = $ni->getTarget();
		$target = empty($target) ? '_self' : $target;
		
		//CSS Classes
		$navItemClassArray = array();
		if (!empty($everyItemUniqueClassPrefix)) {
			$navItemClassArray[] = $everyItemUniqueClassPrefix . $_c->getCollectionID();
		}
		if ($isFirstLiInUl && !empty($firstLiInUlClass)) {
			$navItemClassArray[] = $firstLiInUlClass;
		}
		if (!empty($navItemClassAttrHandle)) {
			$attributeClass = $_c->getCollectionAttributeValue($navItemClassAttrHandle);
			if (!empty($attributeClass)) {
				$navItemClassArray[] = $attributeClass;
			}
		}
		if ($c->getCollectionID() == $_c->getCollectionID()) {
			//This nav item is for the page being viewed
			if (!empty($navSelectedClass)) {
				$navItemClassArray[] = $navSelectedClass;
			}
			if (!empty($navPathSelectedClass)) {
				$navItemClassArray[] = $navPathSelectedClass;
			}
		} else if (in_array($_c->getCollectionID(), $selectedPathCIDs)) {
			//This nav item is for a parent/grandparent of the page being viewed
			$navItemClassArray[] = $navPathSelectedClass;
		}
		$navItemClasses = implode(" ", $navItemClassArray);

		//List Item Output...
		?>

		<li class="<?php echo $navItemClasses; ?>">
			<a class="<?php echo $navItemClasses; ?>" href="<?php echo $pageLink; ?>" target="<?php echo $target; ?>"><?php echo $pageName; ?></a>
		</li>

		<?php
		//END List Item Output
		
		//Prep variables for the next loop iteration
		$lastLevel = $thisLevel;
		$isFirstItem = false;
		$isFirstLiInUl = false;
	}
}

//Output closing list tags if there was at least one nav item
$thisLevel = 0;
if (!$isFirstItem) {
	for ($i = $thisLevel; $i <= $lastLevel; $i++) {
		echo '</li>';
		echo ($lastLevel > 0 && $i == 0) ? $bottomOfDropdownsMarkup : '';
		echo '</ul>';
	}
}

?>