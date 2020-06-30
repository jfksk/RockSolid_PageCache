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

class RockSolid_PageCache_ProductController
    extends RockSolid_PageCache_Controller_Action
{
    public function viewAction()
    {
        $session = Mage::getSingleton('catalog/session');
        $request = $this->getRequest();

        $fpcRequestController = $this->getFpcRequestController();

        $productId  = (int) $request->getParam('id');
        $categoryId = (int) $request->getParam('category', null);

        $proxyFactory = Mage::getSingleton('fpc/proxy_factory');

        $data = $fpcRequestController->getMetaData('product');
        $data['id'] = $productId;

        /** @var RockSolid_PageCache_Model_Proxy_Product **/
        $product = $proxyFactory->getInstance(
            'fpc/proxy_product', 'catalog/product', $data
        );

        if (!$categoryId && ($categoryId !== false)) {
            $lastId = $session->getLastVisitedCategoryId();
            if ($lastId && $product->canBeShowInCategory($lastId)) {
                $categoryId = $lastId;
            }
        }

        if ($categoryId) {
            $data = $fpcRequestController->getMetaData('category');
            $data['id'] = $categoryId;

            /** @var RockSolid_PageCache_Model_Proxy_Category **/
            $category = $proxyFactory->getInstance(
                'fpc/proxy_category', 'catalog/category', $data
            );

            $product->setData('category', $category);

            Mage::register('current_category', $category);
        }

        Mage::register('current_product', $product);
        Mage::register('product', $product);

        Mage::dispatchEvent(
            'catalog_controller_product_view', ['product' => $product]
        );

        $session->setLastViewedProductId($productId);

        $this->prepareLayout();

        if ($this->getLayout()->getBlock('breadcrumbs')) {
            $this->getLayout()->createBlock('catalog/breadcrumbs');
        }

        $this->initLayoutMessages(
            ['catalog/session', 'tag/session', 'checkout/session']
        );

        $this->renderLayout();
    }
}
