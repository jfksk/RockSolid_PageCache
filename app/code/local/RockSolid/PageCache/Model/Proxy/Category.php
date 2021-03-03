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

class RockSolid_PageCache_Model_Proxy_Category
    extends Mage_Catalog_Model_Category_Interceptor
{
    /**
     * Retrieve entity id field name in entity table
     * Rewritten because its factually static
     *
     * @return string
     */
    public function getIdFieldName()
    {
        return 'entity_id';
    }

    /**
     * @return bool
     */
    public function getDisableFlat(): bool
    {
        return false;
    }

    /**
     * Get array categories ids which are part of category path
     * Result array contain id of current category because it is part of the path
     *
     * @return array
     */
    public function getPathIds()
    {
        if (!$this->hasPathIds() && $this->hasPath()) {
            $ids = explode('/', $this->getPath());
            $this->setData('path_ids', $ids);
            return $ids;
        } else if ($this->hasPathIds()) {
            return $this->getData('path_ids');
        } else {
            return parent:: getPathIds();
        }
    }
}
