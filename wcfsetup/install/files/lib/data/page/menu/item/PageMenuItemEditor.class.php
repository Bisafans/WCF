<?php
namespace wcf\data\page\menu\item;
use wcf\data\DatabaseObjectEditor;
use wcf\data\IEditableCachedObject;
use wcf\system\cache\builder\PageMenuCacheBuilder;
use wcf\system\WCF;

/**
 * Provides functions to edit page menu items.
 * 
 * @author	Alexander Ebert
 * @copyright	2001-2013 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf
 * @subpackage	data.page.menu.item
 * @category	Community Framework
 */
class PageMenuItemEditor extends DatabaseObjectEditor implements IEditableCachedObject {
	/**
	 * @see	wcf\data\DatabaseObjectDecorator::$baseClass
	 */
	protected static $baseClass = 'wcf\data\page\menu\item\PageMenuItem';
	
	/**
	 * @see	wcf\data\IEditableObject::create()
	 * 
	 * @todo Handle language id and create related language item
	 */
	public static function create(array $parameters = array()) {
		// calculate show order
		$parameters['showOrder'] = self::getShowOrder($parameters['showOrder'], $parameters['menuPosition'], $parameters['parentMenuItem']);
		
		return parent::create($parameters);
	}
	
	/**
	 * @see	wcf\data\IEditableObject::update()
	 * 
	 * @todo Handle language id and update related language item
	 */
	public function update(array $parameters = array()) {
		1 == 0; // TODO: fix me (avoid sniffing error)
		parent::update($parameters);
	}
	
	/**
	 * @see	wcf\data\IEditableObject::delete()
	 */
	public function delete() {
		// update show order
		$sql = "UPDATE	wcf".WCF_N."_page_menu_item
			SET	showOrder = showOrder - 1
			WHERE	showOrder >= ?
				AND menuPosition = ?";
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute(array(
			$this->showOrder,
			$this->menuPosition
		));
		
		parent::delete();
	}
	
	/**
	 * Sets first top header menu item as landing page.
	 */
	public static function updateLandingPage() {
		$sql = "UPDATE	wcf".WCF_N."_page_menu_item
			SET	isLandingPage = 0";
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute();
		
		$sql = "UPDATE		wcf".WCF_N."_page_menu_item
			SET		isLandingPage = ?
			WHERE		menuPosition = ?
					AND parentMenuItem = ?
					AND menuItemController <> ?
			ORDER BY	showOrder ASC";
		$statement = WCF::getDB()->prepareStatement($sql, 1);
		$statement->execute(array(
			1,
			'header',
			'',
			''
		));
		
		self::resetCache();
	}
	
	/**
	 * Updates the positions of a page menu item directly.
	 * 
	 * @param	integer		$menuItemID
	 * @param	string		$menuPosition
	 * @param	integer		$showOrder
	 */
	public static function setShowOrder($menuItemID, $menuPosition = 'header', $showOrder = 1) {
		// Update
		$sql = "UPDATE	wcf".WCF_N."_page_menu_item
			SET	showOrder = ?,
				menuPosition = ?
			WHERE	menuItemID = ?";
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute(array(
			$showOrder,
			$menuPosition,
			$menuItemID
		));
	}
	
	/**
	 * Returns show order for a new menu item.
	 * 
	 * @param	integer		$showOrder
	 * @param	string		$menuPosition
	 * @return	integer
	 */
	protected static function getShowOrder($showOrder, $menuPosition, $parentMenuItem = '') {
		if ($showOrder == 0) {
			// get next number in row
			$sql = "SELECT	MAX(showOrder) AS showOrder
				FROM	wcf".WCF_N."_page_menu_item
				WHERE	parentMenuItem = ?
					AND menuPosition = ?";
			$statement = WCF::getDB()->prepareStatement($sql);
			$statement->execute(array(
				$parentMenuItem,
				$menuPosition
			));
			$row = $statement->fetchArray();
			if (!empty($row)) $showOrder = intval($row['showOrder']) + 1;
			else $showOrder = 1;
		}
		else {
			$sql = "UPDATE	wcf".WCF_N."_page_menu_item
				SET	showOrder = showOrder + 1
				WHERE	parentMenuItem = ?
					AND menuPosition = ?
					AND showOrder >= ?";
			$statement = WCF::getDB()->prepareStatement($sql);
			$statement->execute(array(
				$parentMenuItem,
				$menuPosition,
				$showOrder
			));
		}
		
		return $showOrder;
	}
	
	/**
	 * @see	wcf\data\IEditableCachedObject::resetCache()
	 */
	public static function resetCache() {
		PageMenuCacheBuilder::getInstance()->reset();
	}
}
