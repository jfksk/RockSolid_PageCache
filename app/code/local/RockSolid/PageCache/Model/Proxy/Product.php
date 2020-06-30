<?php
/**
 * This file is part of a RockSolid e.U. Module.
 *
 * This RockSolid e.U. Module is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This RockSolid e.U. Module is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Foobar.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  RockSolid
 * @package   RockSolid_PageCache
 * @author    Jan F. Kousek <jan@rocksolid.at>
 * @copyright 2020 RockSolid e.U. | Jan F. Kousek (http://www.rocksolid.at)
 * @license   http://opensource.org/licenses/gpl-3.0 GNU General Public License, version 3 (GPLv3)
 */

class RockSolid_PageCache_Model_Proxy_Product
    extends Mage_Catalog_Model_Product_Interceptor
{
    /**
     * Check availability display product in category
     *
     * @param   int $categoryId
     * @return  bool
     */
    public function canBeShowInCategory($categoryId): bool
    {
        if ($this->hasData('category_ids')) {
            return in_array($categoryId, $this->getData('category_ids'));
        }

        $this->_ensureLoaded();

        return (bool) parent::canBeShowInCategory($categoryId);
    }

    /**
     * Return the current category
     *
     * @return Mage_Catalog_Model_Category
     */
    public function getCategory(): Mage_Catalog_Model_Category
    {
        if (isset($this->_data['category'])) {
            return $this->_data['category'];
        }

        if (isset($this->_data['category_id'])) {
            $proxyFactory = Mage::getModel('fpc/proxyFactory');
            $category = $proxyFactory->getInstance(
                'fpc/proxy_category', 'catalog/category', [
                    'id' => $this->_data['category_id']
                ]
            );
            $this->_data['category'] = $category;

            return $category;
        }

        $this->_ensureLoaded();

        return parent::getCategory();
    }
}
