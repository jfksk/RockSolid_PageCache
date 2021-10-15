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

class RockSolid_PageCache_Model_Observer
{
    /**
     * @var RockSolid_PageCache_Model_Controller_Response|null
     */
    private $_responseController = null;

    /**
     * @var RockSolid_PageCache_Model_Controller_Request|null
     */
    private $_requestController = null;

    /**
     * @var bool|null
     */
    private $_isRequestCacheable = null;

    public function __construct()
    {
        $this->_responseController = Mage::getSingleton('fpc/controller_response');
        $this->_requestController = Mage::getSingleton('fpc/controller_request');
    }

    /**
     * Wrap placeholder into definition markers
     *
     *  EVENT: core_block_abstract_to_html_after
     *
     * @param Varien_Event_Observer $event
     */
    public function handleAfterBlockRendered(Varien_Event_Observer $event)
    {
        if (!$this->_isRequestCacheable()) {
            return;
        }

        /** @var Mage_Core_Block_Abstract $block */
        $block = $event->getBlock();

        $cacheable = $block->getFpcCacheable();
        if (in_array($cacheable, [null, '1', 'true', 1, true], true)) {
            if (!$block->hasData('fpc_is_non_cacheable_sibling') && $block->getData('type') != 'core/text_list') {
                $this->_responseController->addCacheTags($block->getCacheTags());
            }
            return;
        }

        $transport = $event->getTransport();
        $content = $transport->getHtml();

        if (!$block->getType()) {
            $type = get_class($block);
        } else {
            $type = $block->getType();
        }

        $placeholder = Mage::getModel(
            'fpc/placeholder',
            [
                'is_anonymous_block' => $block->getIsAnonymous(),
                'block_type'         => $type,
                'block_name'         => $block->getNameInLayout(),
                'block_attributes'   => $this->_extractBlockAttributes($block),
                'block_info'         => $this->_extractBlockInfo($block)
            ]
        );

        $processor = Mage::getModel('fpc/processor_block', $placeholder);
        $processor->prepareContent($content);
        $transport->setHtml($content);
    }

    /**
     * Helper method for handleAfterBlockRendered. Extract data from block
     *
     * @param  Mage_Core_Block_Abstract $block
     *
     * @return array<string, mixed> attribute data from block
     */
    private function _extractBlockAttributes(Mage_Core_Block_Abstract $block): array
    {
        if ($attrString = $block->getData('fpc_attributes')) {
            $attributes = array_map(
                'trim', explode($attrString, ',')
            );

            return $block->toArray($attributes);
        }

        return [];
    }

    /**
     * Helper method for handleAfterBlockRendered. Filter cache-key-info from block
     *
     * @param  Mage_Core_Block_Abstract $block
     *
     * @return array<string, mixed> cache key info from block
     */
    private function _extractBlockInfo(Mage_Core_Block_Abstract $block): array
    {
        return array_filter(
            $block->getCacheKeyInfo(),
            function($key) { return !is_int($key); },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Prepare request for FPC
     *
     *  EVENT: controller_action_layout_generate_blocks_before
     *
     * @param Varien_Event_Observer $event
     */
    public function handleLayoutGenerateBlocksBefore(Varien_Event_Observer $event)
    {
        if (!$this->_responseController->canProcess()) {
            return;
        }

        Mage::getSingleton('catalog/session')->setParamsMemorizeDisabled(true);
    }

    /**
     * Save cacheable pages to cache
     *
     *  EVENT: controller_front_send_response_before
     *
     * @param Varien_Event_Observer $event
     */
    public function handleResponseSendBefore(Varien_Event_Observer $event)
    {
        if (Mage::getSingleton('fpc/cache')->isActive()) {
            $this->_responseController->memorizeRequestIdModifier();
        }

        if (!$this->_isRequestCacheable()) {
            return;
        }

        $response = $event->getFront()->getResponse();
        if ($response->getHttpResponseCode() != 200) {
            return;
        }

        $this->_responseController->process();
    }


    /**
     * Send response before shutdown
     *
     *  EVENT: controller_front_send_response_after
     *
     * @param Varien_Event_Observer $event
     */
    public function handleResponseSendAfter(Varien_Event_Observer $event)
    {
        if (!$this->_requestController->isCached()) {
            return;
        }

        if (php_sapi_name() == 'fpm-fcgi' && function_exists('fastcgi_finish_request')) {
            ignore_user_abort(true);
            fastcgi_finish_request();
        }
    }

    /**
     * Add additional metadata to the page cache
     *
     * EVENT: fpc_save_page_cms_page_view_before
     *
     * @param Varien_Event_Observer $event
     */
    public function registerCmsPageView(Varien_Event_Observer $event)
    {
        if (!$this->_isRequestCacheable()) {
            return;
        }

        $page = Mage::getSingleton('cms/page');
        $this->_responseController->addMetaData(
            [
                'cms_page' => [
                    'title' => $page->getTitle(),
                ]
            ]
        );
    }

    /**
     * Add additional metadata to the page cache
     *
     * EVENT: fpc_save_page_catalog_category_view_before
     *
     * @param Varien_Event_Observer $event
     */
    public function registerCategoryView(Varien_Event_Observer $event)
    {
        if (!$this->_isRequestCacheable()) {
            return;
        }

        /** @var Mage_Catalog_Model_Category $category */
        $category = Mage::registry('current_category');
        $this->_responseController->addMetaData(
            [
                'category' => [
                    'path' => $category->getPath(),
                    'name' => $category->getName()
                ]
            ]
        );
    }

    /**
     * Add additional metadata to the page cache
     *
     * EVENT: fpc_save_page_catalog_product_view_before
     *
     * @param Varien_Event_Observer $event
     */
    public function registerProductView(Varien_Event_Observer $event)
    {
        if (!$this->_isRequestCacheable()) {
            return;
        }

        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::registry('current_product');
        $this->_responseController->addMetaData(
            [
                'product' => [
                    'sku'  => $product->getSku(),
                    'name' => $product->getName(),
                    'category_ids' => $product->getCategoryIds()
                ]
            ]
        );

        if (!Mage::app()->getRequest()->getParam('category', false)) {
            return;
        }

        $category = Mage::registry('current_category');
        $this->_responseController->addMetaData(
            [
                'category' => [
                    'path' => $category->getPath(),
                    'name' => $category->getName()
                ]
            ]
        );
    }

    /**
     * Is the FPC active?
     *
     * @return bool
     */
    protected function _isRequestCacheable(): bool
    {
        if ($this->_isRequestCacheable !== null) {
            return $this->_isRequestCacheable;
        }

        $this->_isRequestCacheable = $this->_responseController->canProcess()
            && !$this->_requestController->isCached();

        return $this->_isRequestCacheable;
    }
}
