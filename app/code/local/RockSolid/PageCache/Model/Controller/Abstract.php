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

abstract class RockSolid_PageCache_Model_Controller_Abstract
{
    const ID_MODIFIER_COOKIE = 'fpcmdid';

    /**
     * Request ID / Cache ID
     *
     * @var string
     */
    private $_requestId = null;

    /**
     * Filtered request parameters
     *
     * @var array|null
     */
    private $_params = null;

    /**
     * Parsed routing config
     *
     * @var array|null
     */
    private static $_routeConfig = null;

    /**
     * Check if the request can be processed
     *
     * @return bool
     */
    abstract public function canProcess() : bool;

    /**
     * Check if the request can be processed
     *
     * @return bool
     */
    abstract public function process() : bool;

    /**
     * Generates an id for the current request (once) and returns it
     *
     * @return string
     */
    public function getRequestId() : string
    {
        if ($this->_requestId !== null) {
            return $this->_requestId;
        }

        $baseurl = current(explode('?', $_SERVER['REQUEST_URI'], 2));

        $params = [];

        $appParams = Mage::registry('application_params');
        $params['store_scope_code'] = $appParams['scope_code'];
        $params['store_scope_type'] = $appParams['scope_type'];

        $params['query'] = $this->_getParameters();

        $transport = new Varien_Object($params);
        Mage::dispatchEvent(
            'fpc_build_request_id', ['params' => $transport]
        );
        $params = $transport->getData();

        $this->_requestId = md5($baseurl . json_encode($params));

        return $this->_requestId;
    }

    /**
     * Returns the context filtered URI params of the current request
     *
     * @return array
     */
    protected function _getParameters() : array
    {
        if ($this->_params !== null) {
            return $this->_params;
        }

        $this->_params = $_GET;

        if (isset($this->_params['p']) && $this->_params['p'] == 1) {
            unset($this->_params['p']);
        }

        if (empty($this->_params)) {
            return $this->_params;
        }

        ksort($this->_params, SORT_NATURAL);

        $rules = $this->_getParameterConfig('ignore');
        if (empty($rules)) {
            return $this->_params;
        }

        foreach ($rules as $key => $param) {
            if (array_key_exists($param, $this->_params)) {
                unset($this->_params[$param]);
            }
        }

        return $this->_params;
    }

    /**
     * Check if the current request has parameters that block caching
     *
     * @return bool
     */
    protected function _hasBlockingParameters() : bool
    {
        $rules = $this->_getParameterConfig('block');
        if (empty($rules)) {
            return false;
        }

        $parameters = $this->_getParameters();
        if (empty($parameters)) {
            return false;
        }

        return count(array_intersect($rules, array_keys($parameters))) > 0;
    }

    /**
     * Returns the rewrite configuration from
     *   "config/frontend/fpc/request/routes"
     *
     * @return array
     */
    protected function _getRouteConfiguration() : array
    {
        if (self::$_routeConfig !== null) {
            return self::$_routeConfig;
        }

        $config = Mage::getConfig()->getNode('frontend/fpc/routes');

        if ($config) {
            $config = $config->asArray();
        } else {
            return [];
        }

        foreach ($config as $source => $target) {
            if (strpos($target, '_') === false) {
                self::$_routeConfig[$source] = $target;
            } else {
                self::$_routeConfig[$source] = explode('_', $target);
            }
        }

        return self::$_routeConfig;
    }

    /**
     * Generates an id-modifier for the current request (always) and returns it
     *
     * @return string
     */
    abstract public function getRequestIdModifier() : string;

    /**
     * Returns the cache ID for the current request
     *
     * @return string
     */
    public function getCacheId() : string
    {
        return $this->getRequestId() . $this->getRequestIdModifier();
    }

    /**
     * Returns the FPC instance
     *
     * @return RockSolid_PageCache_Model_Cache
     */
    protected function _getCache()
    {
        return Mage::getSingleton('fpc/cache');
    }
}