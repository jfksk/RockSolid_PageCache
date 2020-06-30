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
 * @category  RockSolid
 * @package   RockSolid_PageCache
 * @author    Jan F. Kousek <jan@rocksolid.at>
 * @copyright 2020 RockSolid e.U. | Jan F. Kousek (http://www.rocksolid.at)
 * @license   http://opensource.org/licenses/gpl-3.0 GNU General Public License, version 3 (GPLv3)
 */

class RockSolid_PageCache_Model_Config extends Varien_Simplexml_Config
{
    /**
     * Class constructor
     * load cache configuration
     *
     * @param $data
     */
    public function __construct($data = null)
    {
        parent::__construct($data);
        $this->setCacheId('pagecache_config');
        $this->_cacheChecksum   = null;
        $this->_cache = Mage::app()->getCache();

        $canUsaCache = Mage::app()->useCache('config');
        if ($canUsaCache) {
            if ($this->loadCache()) {
                return $this;
            }
        }

        $config = Mage::getConfig()->loadModulesConfiguration('pagecache.xml');
        $this->setXml($config->getNode());

        if ($canUsaCache) {
            $this->saveCache([Mage_Core_Model_Config::CACHE_TAG]);
        }
    }
}
