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

use Mage_Persistent_Helper_Data as PersistentHelper;

class RockSolid_PageCache_Model_Controller_Response
    extends RockSolid_PageCache_Model_Controller_Abstract
{
    const CURRENCY_MODIFIER_PATH        = 'system/fpc/modifier_curency';
    const CUSTOMER_GROUP_MODIFIER_PATH  = 'system/fpc/modifier_customer_group';
    const CUSTOMER_GROUPS_MODIFIER_PATH = 'system/fpc/modifier_customer_groups';
    const TAX_MODIFIER_PATH             = 'system/fpc/modifier_tax';
    const STORE_MODIFIER_PATH           = 'system/fpc/modifier_store';

    const PARAM_CNT_PATH               = 'system/fpc/allowed_param_count';

    /**
     * Meta-data to store with the page
     *
     * @var array<string, mixed>
     */
    private $_metaData = [];

    /**
     * Cache tags for the page cache entry
     *
     * @var array<int, string>
     */
    private $_cacheTags = [];

    /**
     * Flag: can the response be processed
     *
     * @var bool|null
     */
    private $_canProcess = null;

    /**
     * @var string|null
     */
    private $_requestIdModifier = null;

    /**
     * @var array<int, string>
     */
    private $_ignoreCacheTags;

    /**
     * @var array<string, string>
     */
    private $_rewriteCacheTags;

    public function __construct()
    {
        $this->_prepareCacheTagConfig();
    }

    /**
     * Check if the current request can be cached (once)
     *
     * @return bool
     */
    public function canProcess(): bool
    {
        if ($this->_canProcess !== null) {
            return $this->_canProcess;
        }

        $request = Mage::app()->getRequest();
        if ($request->getInternallyForwarded() || !$request->isGet()) {
            return $this->_canProcess = false;
        }

        if (!Mage::getSingleton('fpc/cache')->isActive()) {
            return $this->_canProcess = false;
        }

        $fqn = Mage::app()->getFrontController()->getAction()->getFullActionName();
        $handledRequests = $this->_getRouteConfiguration();

        if (isset($handledRequests[$fqn]) === false || $handledRequests[$fqn] == 'disabled') {
            return $this->_canProcess = false;
        }

        if (Mage::app()->getStore()->isAdmin())  {
            return $this->_canProcess = false;
        }

        if ($this->_hasBlockingParameters()) {
            return $this->_canProcess = false;
        }

        if (count($this->_getParameters()) > intval(Mage::getStoreConfig(self::PARAM_CNT_PATH))) {
            return $this->_canProcess = false;
        }

        $flag = new Varien_Object(['can_process' => true]);

        Mage::dispatchEvent('fpc_can_process_response', ['flag' => $flag]);
        if (!$flag->getCanProcess()) {
            return $this->_canProcess = false;
        }

        Mage::dispatchEvent("fpc_can_process_response_{$fqn}", ['flag' => $flag]);
        if (!$flag->getCanProcess()) {
            return $this->_canProcess = false;
        }

        return $this->_canProcess = true;
    }

    /**
     * Save a page to cache
     *
     * @return bool
     */
    public function process(): bool
    {
        try {
            if (!$this->canProcess()) {
                return false;
            }

            $app      = Mage::app();
            $request  = $app->getRequest();
            $response = $app->getResponse();

            $fqn = $app->getFrontController()->getAction()->getFullActionName();

            $metaData = [];
            $metaData['request'] = [
                'fqn'          => $fqn,
                'path_info'    => $request->getPathInfo(),
                'routing_info' => [
                    'aliases'          => $request->getAliases(),
                    'request_uri'      => $request->getRequestUri(),
                    'requested_route'  => $request->getRequestedRouteName(),
                    'requested_action' => $request->getRequestedActionName(),
                    'requested_controller' => $request->getRequestedControllerName(),
                ]
            ];

            $layoutHandles = array_flip(
                $app->getLayout()->getUpdate()->getHandles()
            );
            // remove stateful handles
            unset(
                $layoutHandles['customer_logged_out'],
                $layoutHandles['customer_logged_in'],
                $layoutHandles[PersistentHelper::LOGGED_IN_LAYOUT_HANDLE],
                $layoutHandles[PersistentHelper::LOGGED_OUT_LAYOUT_HANDLE]
            );
            $metaData['layout_handles'] = array_flip($layoutHandles);

            $responseBody   = $response->getBody();
            $processorClass = Mage::getConfig()->getModelClassName('fpc/processor_page');

            $processor = new $processorClass($this->getCacheId(), $responseBody);
            $processor->replaceContent();

            $ttlTransport = new Varien_Object(
                ['ttl' => $this->_getCache()->getDefaultTtl()]
            );

            $eventData = [
                'response_controller' => $this,
                'page_processor'      => $processor,
                'ttl_transport'       => $ttlTransport
            ];

            Mage::dispatchEvent('fpc_save_page_before', $eventData);
            Mage::dispatchEvent("fpc_save_page_{$fqn}_before", $eventData);

            $cacheItemClass = Mage::getConfig()->getModelClassName('fpc/cache_item');

            $cacheItem = new $cacheItemClass(
                $processor->getContent(), array_merge($metaData, $this->_metaData)
            );

            $this->_getCache()->save(
                serialize($cacheItem),
                $this->getRequestId(),
                array_keys($this->_cacheTags),
                $ttlTransport->getTtl()
            );

            if (!Mage::getIsDeveloperMode()) {
                $response->setBody(
                    (new $processorClass($this->getCacheId(), $responseBody))->removePlaceholders()->getContent()
                );
            }

            return true;
        } catch (Exception $e) {
            $this->_getCache()->remove($this->getRequestId());
            Mage::logException($e);

            return false;
        } catch (Error $e) {
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
     *   "config/frontend/fpc/response/parameters/{type}"
     *
     * @param string $type
     *
     * @return array<string, string>
     */
    protected function _getParameterConfig(string $type): array
    {
        $config = Mage::getConfig()->getNode("frontend/fpc/response/parameters/$type");

        if ($config) {
            return $config->asArray();
        }

        return [];
    }

    /**
     * Add cachetags for the page to be stored
     *
     * @param array<int, string> $tags
     *
     * @return self
     */
    public function addCacheTags(array $tags)
    {
        $tags = array_map('strtoupper', $tags);
        $rewriteCacheTags = $this->_rewriteCacheTags;
        $tags = array_map(function ($v) use ($rewriteCacheTags) {
            return isset($rewriteCacheTags[$v]) ? $rewriteCacheTags[$v] : $v;
        }, $tags);
        $tags = array_diff($tags, $this->_ignoreCacheTags);

        $this->_cacheTags = array_merge($this->_cacheTags, array_flip($tags));

        return $this;
    }

    /**
     * Load and prepare cache-tag ignore and rewrite config
     *
     * @return void
     */
    private function _prepareCacheTagConfig()
    {
        $config = Mage::getConfig();

        $rewriteConfig = $config->getNode("frontend/fpc/response/cachetags/rewrite");
        if ($rewriteConfig) {
            $this->_rewriteCacheTags = $rewriteConfig->asArray();
            $this->_rewriteCacheTags = array_change_key_case($this->_rewriteCacheTags, CASE_UPPER);
            $this->_rewriteCacheTags = array_map('strtoupper', $this->_rewriteCacheTags);
        }

        $ignoreConfig = $config->getNode("frontend/fpc/response/cachetags/ignore");
        if ($ignoreConfig) {
            $this->_ignoreCacheTags = array_flip($ignoreConfig->asArray());
            $this->_ignoreCacheTags = array_map('strtoupper', $this->_ignoreCacheTags);
        }
    }

    /**
     * Add meta-data for the page to be stored
     *
     * @param array<string, mixed> $data
     *
     * @return self
     */
    public function addMetaData(array $data)
    {
        $this->_metaData = array_merge($data, $this->_metaData);

        return $this;
    }

    /**
     * Get the cache key modifier for the current request
     *
     * @return string|null md5 value
     */
    public function getRequestIdModifier(): string
    {
        if ($this->_requestIdModifier !== null) {
            return $this->_requestIdModifier;
        }

        $modifier = [];

        if (Mage::getStoreConfigFlag(self::CURRENCY_MODIFIER_PATH)) {
            $modifier['currency'] = $this->_currencyModifier();
        }

        if (Mage::getStoreConfigFlag(self::CUSTOMER_GROUP_MODIFIER_PATH)) {
            $modifier['customer_group'] = $this->_customerGroupModifier();
        }

        if (Mage::getStoreConfigFlag(self::TAX_MODIFIER_PATH)) {
            $modifier['tax'] = $this->_taxModifier();
        }

        if (Mage::getStoreConfigFlag(self::STORE_MODIFIER_PATH)) {
            $modifier['store'] = $this->_getStoreModifier();
        }

        $modifier = array_filter($modifier);

        $transport = new Varien_Object($modifier);
        Mage::dispatchEvent(
            'fpc_build_request_id_modifier', ['modifiers' => $transport]
        );

        $modifier = $transport->getData();

        $modifier = array_filter($modifier);

        if (empty($modifier)) {
            return $this->_requestIdModifier = '';
        }

        $modifier = md5(
            implode('_', $modifier) .
            md5(Mage::getConfig()->getNode('global/install/date'))
        );

        return $this->_requestIdModifier = $modifier;
    }

    /**
     * Get the currency cache key modifier for the current request
     *
     * @return string|null
     */
    protected function _currencyModifier()
    {
        $store   = Mage::app()->getCurrentStore();
        $session = Mage::getSingleton('core/session');

        if ($store->getDefaultCurrency() != $session->getCurrencyCode()) {
            return $session->getCurrencyCode();
        }

        return null;
    }

    /**
     * Get the customer group cache key modifier for the current request
     *
     * @return string|null
     */
    protected function _customerGroupModifier()
    {
        $group = Mage::getSingleton('customer/session')->getCustomerGroupId();
        if (!$group) {
            return null;
        }

        if ($groups = explode(',', Mage::getStoreConfig(self::CUSTOMER_GROUPS_MODIFIER_PATH))) {
            if (!in_array($group, $groups)) {
                return null;
            }
        }

        return $group;
    }

    /**
     * Get the tax cache key modifier for the current request
     *
     * @return string|null
     */
    protected function _taxModifier()
    {
        $session = Mage::getSingleton('customer/session');

        $persistentHelper = Mage::helper('persistent/session');

        if (!$session->isLoggedIn() && !$persistentHelper->isPersistent()) {
            return null;
        } elseif (!$session->isLoggedIn() && !$session->getCustomerId()) {
            return null;
        }

        $calculator = Mage::getSingleton('tax/calculation');

        $taxRateRequest = $calculator->getRateRequest();
        $defaultRateRequest = $calculator->getDefaultRateRequest();

        if ($calculator->compareRequests($taxRateRequest, $defaultRateRequest)) {
            return null;
        }

        $modifier[] = $taxRateRequest->getCountryId();
        $modifier[] = $taxRateRequest->getCustomerClassId();

        return implode('_', $modifier);
    }

    /**
     * Get the store cache key modifier for the current request
     *
     * @return string|null
     */
    protected function _getStoreModifier()
    {
        $store = Mage::app()->getCurrentStore();
        $defaultCode = Mage::registry('application_params')['scope_code'];

        if ($store->getCode() == $defaultCode) {
            return null;
        }

        return $store->getCode();
    }

    /**
     * Stores the cache key modifier (if any) into a cookie
     *
     * @return self
     */
    public function memorizeRequestIdModifier()
    {
        $cookie = Mage::getSingleton('core/cookie');

        $modifier = $this->getRequestIdModifier();
        $modifierCookie = $cookie->get(self::ID_MODIFIER_COOKIE);

        if (!$modifier && !$modifierCookie) {
            return $this;
        }

        if (!$modifier && $modifierCookie) {
            $cookie->delete(self::ID_MODIFIER_COOKIE);
            return $this;
        }

        $lifeTime = 0;
        if (Mage::helper('persistent/session')->isPersistent()) {
            $lifeTime = Mage::helper('persistent')->getLifeTime();
        }

        if ($modifierCookie == $modifier) {
            $cookie->renew(self::ID_MODIFIER_COOKIE, $lifeTime);
            return $this;
        }

        $cookie->set(self::ID_MODIFIER_COOKIE, $modifier, $lifeTime);

        return $this;
    }
}
