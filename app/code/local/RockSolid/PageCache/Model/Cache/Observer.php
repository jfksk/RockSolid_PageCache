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

class RockSolid_PageCache_Model_Cache_Observer
{
    /**
     * Purges the FPC
     *
     *  EVENTS:
     *   - core_clean_cache
     *   - clean_media_cache_after
     *   - clean_catalog_images_cache_after
     *   - adminhtml_cache_flush_all
     *   - catalogrule_after_apply
     *   - clean_configurable_swatches_cache_after
     *
     * @return void
     */
    public function cleanCache()
    {
        $this->_getCacheInstance()->flush();
    }

    /**
     * Removes expired entries form the fpc cache
     *
     * @return void
     */
    public function cleanOldCache()
    {
        $this->_getCacheInstance()->getFrontend()->clean(
            Zend_Cache::CLEANING_MODE_OLD
        );
    }

    /**
     *  Events:
     *   - model_save_commit_after
     *   - model_delete_commit_after
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function registerEntityAction(Varien_Event_Observer $observer)
    {
        if (!$this->_isCacheEnabled()) {
            return;
        }

        $object = $observer->getEvent()->getObject();

        $tags = $object->getCacheIdTags();
        if (!empty($tags)) {
            $this->_getCacheInstance()->clean($tags);
        }
    }

    /**
     * Purges the FPC on a mass action in the backend
     *
     *  Event: controller_action_predispatch_adminhtml_cache_massRefresh
     * @return void
     */
    public function registerMassRefresh()
    {
        if (!$this->_isCacheEnabled()) {
            return;
        }

        $types = Mage::app()->getRequest()->getParam('types');
        $fpcType = RockSolid_PageCache_Model_Cache::TYPE_CODE;

        if (is_array($types) && in_array($fpcType, $types)) {
            $this->cleanCache();
        } else if ($types == $fpcType){
            $this->cleanCache();
        }
    }

    /**
     * Purges the cache by cache-tag when a tag was changed
     *
     *  Events:
     *   - tag_save_after
     *   - tag_delete_before
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function registerTagsChange(Varien_Event_Observer $observer)
    {
        if (!$this->_isCacheEnabled()) {
            return;
        }

        $tags = [];
        $object = $observer->getEvent()->getObject();
        $productCollection = $object->getEntityCollection()->addTagFilter($object->getId());

        foreach ($productCollection as $product) {
            $tags[] = Mage_Catalog_Model_Product::CACHE_TAG . '_' . $product->getId();
        }

        $this->_getCacheInstance()->clean($tags);
    }

    /**
     * Purges the cache by cache-tag when a review was changed
     *
     *  Events:
     *   - review_delete_before
     *   - review_save_after
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function registerReviewChange(Varien_Event_Observer $observer)
    {
        if (!$this->_isCacheEnabled()) {
            return;
        }

        $object = $observer->getEvent()->getObject();
        $this->_getCacheInstance()->clean(
            [Mage_Catalog_Model_Product::CACHE_TAG . '_' . $object->getEntityPkValue()]
        );
    }

    /**
     * Purges the cache by cache-tag when the stock-level was changed
     *
     *  Event: cataloginventory_stock_item_save_after
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function registerStockItemChange(Varien_Event_Observer $observer)
    {
        if (!$this->_isCacheEnabled()) {
            return;
        }

        $object = $observer->getEvent()->getItem();
        if ($object->getStockStatusChangedAuto()) {
            $this->_getCacheInstance()->clean(
                [Mage_Catalog_Model_Product::CACHE_TAG . '_' .  $object->getProductId()]
            );
        }
    }

    /**
     * Purge the cache by query tag if the results changed
     *
     *  EVENT: catalogsearch_query_save_after
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function registerQueryChange(Varien_Event_Observer $observer)
    {
        if (!$this->_isCacheEnabled()) {
            return;
        }

        $query = $observer->getDataObject();
        $text = $query->getOrigData('query_text');
        $tag = Mage_CatalogSearch_Model_Query::CACHE_TAG  .  '_' . md5($text);

        if ($query->dataHasChangedFor('num_results')) {
            $this->_getCacheInstance()->clean([$tag]);
        } else if ($query->dataHasChangedFor('query_text')) {
            $this->_getCacheInstance()->clean([$tag]);
        } else if ($query->dataHasChangedFor('redirect')) {
            $this->_getCacheInstance()->clean([$tag]);
        }
    }

    /**
     * Purge the cache by query tag if the query was deleted
     *
     *  EVENT: catalogsearch_query_save_after
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function registerQueryDelete(Varien_Event_Observer $observer)
    {
        if (!$this->_isCacheEnabled()) {
            return;
        }

        $query = $observer->getDataObject();
        $text = $query->getOrigData('query_text');
        $tag = Mage_CatalogSearch_Model_Query::CACHE_TAG  .  '_' . md5($text);

        $this->_getCacheInstance()->clean([$tag]);
    }

    /**
     * Add the search query as tag to the current page
     *
     * EVENT: fpc_save_page_catalogsearch_result_index_before
     *
     * @param Varien_Event_Observer $observer
     */
    public function registerSearchTags(Varien_Event_Observer $observer)
    {
        if (!$this->_isCacheEnabled()) {
            return;
        }

        $query = Mage::helper('catalogsearch')->getQueryText();
        $tag = Mage_CatalogSearch_Model_Query::CACHE_TAG  . '_' . md5($query);

        $observer->getResponseController()->addCacheTags([$tag]);
    }

    /**
     * Invalidates the FPC
     *
     *  EVENTS:
     *   - after_reindex_process_cataloginventory_stock
     *   - after_reindex_process_catalog_product_price
     *   - after_reindex_process_catalog_product_flat
     *   - after_reindex_process_catalog_category_flat
     *   - after_reindex_process_catalog_category_product
     *   - admin_system_config_changed_section_currency
     *   - controller_action_postdispatch_adminhtml_system_config_save
     *
     * @return void
     */
    public function invalidateCache()
    {
        Mage::app()->getCacheInstance()->invalidateType(
            RockSolid_PageCache_Model_Cache::TYPE_CODE
        );
    }

    /**
     * Is the FPC active?
     *
     * @return bool
     */
    protected function _isCacheEnabled(): bool
    {
        return Mage::getSingleton('fpc/cache')->isActive();
    }

    /**
     * Returns the FPC instance
     *
     * @return RockSolid_PageCache_Model_Cache
     */
    protected function _getCacheInstance(): RockSolid_PageCache_Model_Cache
    {
        return Mage::getSingleton('fpc/cache');
    }
}
