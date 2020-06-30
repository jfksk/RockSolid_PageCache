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

abstract class RockSolid_PageCache_Controller_Action extends Mage_Core_Controller_Front_Action
{
    const PROFILER_KEY = 'FPC::dispatch::controller::action';

    /**
     * Return the currently used Page-Processor instance
     *
     * @return RockSolid_PageCache_Model_Controller_Request
     */
    public function getFpcRequestController() : RockSolid_PageCache_Model_Controller_Request
    {
        return Mage::getSingleton('fpc/controller_request');
    }

    /**
     * Prepares the layout for partial rendering
     *
     * @return self
     */
    public function prepareLayout() : self
    {
        $profilerKey = self::PROFILER_KEY . '::' . $this->getFullActionName();

        Varien_Profiler::start("$profilerKey::layout_prepare");

        $layoutHandles = $this->getFpcRequestController()->getMetadata('layout_handles');

        $this->getLayout()->getUpdate()->addHandle($layoutHandles);
        $this->loadLayoutUpdates();
        $this->generateLayoutXml();

        $this->getFpcRequestController()->getPageProcessor()->generateBlocks();

        Varien_Profiler::stop("$profilerKey::layout_prepare");

        return $this;
    }

    /**
     * Rendering the page
     *
     * @param string $output for compatibility. Do not pass a value!
     * @return self
     * @throws Mage_Core_Exception
     */
    public function renderLayout($output='') : self
    {
        if ($output != '') {
            throw new Mage_Core_Exception(
                'Additional output-blocks are not supported on cached pages'
            );
        }

        $processor = $this->getFpcRequestController()->getPageProcessor();
        $processor->applyContent();
        $this->getResponse()->setBody($processor->getContent());

        return $this;
    }

    /**
     * Dispatch the page-cache request
     *
     * @param string $action
     * @return void
     */
    public function dispatch($action)
    {
        try {
            $actionMethodName = $action . 'Action';

            Varien_Profiler::start(self::PROFILER_KEY.'::predispatch');
            $this->preDispatch();
            Varien_Profiler::stop(self::PROFILER_KEY.'::predispatch');

            if ($this->getRequest()->isDispatched()) {
                $profilerKey = self::PROFILER_KEY.'::'.$this->getFullActionName();

                Varien_Profiler::start($profilerKey);
                $this->$actionMethodName();
                Varien_Profiler::stop($profilerKey);

                Varien_Profiler::start(self::PROFILER_KEY.'::postdispatch');
                $this->postDispatch();
                Varien_Profiler::stop(self::PROFILER_KEY.'::postdispatch');
            }
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_forwardOriginal();
        } catch (Error $e) {
            Mage::log(
                sprintf('%s in %s:%s', $e->getMessage(), $e->getFile(), $e->getLine()),
                Zend_Log::ERR
            );

            $this->_forwardOriginal();
        }
    }

    /**
     * Lightweight predispatch for a page-cache request
     *
     * @return void
     * @throws Mage_Core_Model_Store_Exception
     */
    public function preDispatch()
    {
        $app = Mage::app();

        // Prohibit disabled store actions
        if (!$app->getStore()->getIsActive()) {
            $app->throwStoreException();
        }

        $checkCookie = !$this->getRequest()->getParam('nocookie', false);
        $cookies = Mage::getSingleton('core/cookie')->get();

        /** @var $session Mage_Core_Model_Session */
        $session = Mage::getSingleton('core/session', ['name' => $this->_sessionNamespace])->start();

        if (empty($cookies) && Mage::getStoreConfig('web/browser_capabilities/cookies')) {
            if ($session->getCookieShouldBeReceived()) {
                $session->unsCookieShouldBeReceived();
                $session->setSkipSessionIdFlag(true);
                $this->_forward('noCookies', 'index', 'core');
                return;
            } else if ($checkCookie) {
                if (isset($_GET[$session->getSessionIdQueryParam()]) && $app->getUseSessionInUrl()) {
                    $session->setCookieShouldBeReceived(true);
                } else {
                    $this->_forward('noCookies', 'index', 'core');
                    return;
                }
            }
        }

        $app->loadArea('frontend');

        Mage::dispatchEvent(
            'controller_action_predispatch', ['controller_action' => $this]
        );

        Mage::dispatchEvent(
            'controller_action_predispatch_' . $this->getRequest()->getRouteName(),
            ['controller_action' => $this]
        );

        Mage::dispatchEvent(
            'controller_action_predispatch_' . $this->getFullActionName(),
            ['controller_action' => $this]
        );
    }

    /**
     * Postdispatch for a page-cache request
     *
     * @return void
     */
    public function postDispatch()
    {
        Mage::getSingleton('core/session')
            ->setLastUrl(Mage::getUrl('*/*/*', ['_current'=>true]));

        Mage::dispatchEvent(
            'controller_action_postdispatch_'.$this->getFullActionName(),
            ['controller_action' => $this]
        );

        Mage::dispatchEvent(
            'controller_action_postdispatch_'.$this->getRequest()->getRouteName(),
            ['controller_action' => $this]
        );

        Mage::dispatchEvent(
            'controller_action_postdispatch',
            ['controller_action' => $this]
        );
    }

    /**
     * Forward to the original requested controller (non-cached request)
     *
     * @return void
     */
    protected function _forwardOriginal()
    {
        $request = $this->getRequest();

        $request->setInternallyForwarded(true);

        $this->_forward(
            $request->getRequestedActionName(),
            $request->getRequestedControllerName(),
            $request->getRequestedRouteName()
        );
    }
}
