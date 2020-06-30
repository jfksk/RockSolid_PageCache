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

class RockSolid_PageCache_Model_Controller_Request
    extends RockSolid_PageCache_Model_Controller_Abstract
{
    /**
     * Flag: can the request be processed
     *
     * @var bool|null
     */
    private $_canProcess = null;

    /**
     * @var RockSolid_PageCache_Model_Processor_Page|null
     */
    private $_pageProcessor = null;

    /**
     * @var RockSolid_PageCache_Model_Cache_Item|null
     */
    private $_cacheItem = null;

    /**
     * Flag: is the request a cached request
     *
     * @var bool
     */
    private $_isCached = false;

    /**
     * Check if the request can be processed
     *
     * @return bool
     */
    public function canProcess() : bool
    {
        if ($this->_canProcess !== null) {
            return $this->_canProcess;
        }

        if (Mage::app()->getRequest()->getInternallyForwarded()) {
            return $this->_canProcess = false;
        }

        if (!Mage::getSingleton('fpc/cache')->isActive()) {
            return $this->_canProcess = false;
        }

        if (!Mage::app()->getRequest()->isGet()) {
            return $this->_canProcess = false;
        }

        if (Mage::app()->getStore()->isAdmin())  {
            return $this->_canProcess = false;
        }

        if ($this->_hasBlockingParameters()) {
            return $this->_canProcess = false;
        }

        return $this->_canProcess = true;
    }

    /**
     * Rewrite the request to page cache if the page can be surfed from cache
     *
     * @return bool
     */
    public function process() : bool
    {
        try {
            if (!$this->canProcess()) {
                return false;
            }

            $processorClass = Mage::getConfig()
                ->getModelClassName('fpc/processor_page');

            $cacheEntry = $this->_getCache()->load($this->getCacheId());

            if (!$cacheEntry) {
                return $this->_canProcess = false;
            }

            $this->_cacheItem = unserialize($cacheEntry);

            $this->_isCached = true;

            $this->_pageProcessor = new $processorClass(
                $this->getCacheId(), $this->_cacheItem->getContent()
            );

            $configuration = $this->_getRouteConfiguration();
            $fqn = $this->getMetaData('request/fqn');

            if (!isset($configuration[$fqn]) || $configuration[$fqn] == 'disabled') {
                return $this->_canProcess = false;
            }

            $request = Mage::app()->getRequest();

            $request->setModuleName($configuration[$fqn][0])
                ->setControllerName($configuration[$fqn][1])
                ->setActionName($configuration[$fqn][2]);

            $request->setPathInfo($this->getMetaData('request/path_info'));
            $request->setRoutingInfo($this->getMetaData('request/routing_info'));

            $request->setInternallyForwarded(true);
            $request->isStraight(true);

            return true;
        } catch (Exception $e) {
            $this->_isCached = false;
            $this->_canProcess = false;
            $this->_getCache()->remove($this->getRequestId());
            Mage::logException($e);

            return false;
        } catch (Error $e) {
            $this->_isCached = false;
            $this->_canProcess = false;
            $this->_getCache()->remove($this->getRequestId());
            Mage::log(
                sprintf('%s in %s:%s', $e->getMessage(), $e->getFile(), $e->getLine()),
                Zend_Log::ERR
            );

            return false;
        }
    }

    /**
     * Returns the param configuration from
     *   "config/frontend/fpc/request/params/{type}"
     *
     * @param string $type
     * @return array
     */
    protected function _getParameterConfig($type) : array
    {
        $config = Mage::getConfig()->getNode("frontend/fpc/request/parameters/$type");

        if ($config) {
            return $this->_parseParameterConfig($config->asArray());
        }

        return [];
    }

    /**
     * Returns cache key modifier cookie content - if any
     *
     * @return string
     */
    public function getRequestIdModifier() : string
    {
        $hash = Mage::getSingleton('core/cookie')->get(self::ID_MODIFIER_COOKIE);

        if ($hash != '' && preg_match('/^[a-f0-9]{32}$/', $hash)) {
            return $hash;
        }

        return '';
    }

    /**
     * Get meta-data from the curretly loaded page
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getMetaData(string $key = '')
    {
        if ($this->_isCached) {
            return $this->_cacheItem->getMetaData($key);
        }

        return null;
    }

    /**
     * Returns the uses page processor instance
     *
     * @return RockSolid_PageCache_Model_Processor_Page|false
     */
    public function getPageProcessor()
    {
        if ($this->_isCached) {
            return $this->_pageProcessor;
        }

        return false;
    }

    /**
     * Check if the request was rewritten to page cache
     *
     * @return bool
     */
    public function isCached() : bool
    {
        return $this->_isCached;
    }
}
