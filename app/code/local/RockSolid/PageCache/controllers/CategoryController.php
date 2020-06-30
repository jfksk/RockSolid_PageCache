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

class RockSolid_PageCache_CategoryController
    extends RockSolid_PageCache_Controller_Action
{
    public function viewAction()
    {
        $fpcRequestController = $this->getFpcRequestController();
        $request = $this->getRequest();
        $categoryId = (int) $request->getParam('id');

        $data = $fpcRequestController->getMetaData('category');
        $data['entity_id'] = $categoryId;

        /** @var $category RockSolid_PageCache_Model_Proxy_Category */
        $category = Mage::getSingleton('fpc/proxy_factory')->getInstance(
            'fpc/proxy_category', 'catalog/category', $data
        );

        Mage::getSingleton('catalog/session')
            ->setLastVisitedCategoryId($categoryId);

        Mage::register('current_category', $category);
        Mage::register('category', $category);
        Mage::register('current_entity_key', $category->getPath());

        $this->prepareLayout();

        if ($this->getLayout()->getBlock('breadcrumbs')) {
            $this->getLayout()->createBlock('catalog/breadcrumbs');
        }

        $this->initLayoutMessages(['catalog/session', 'checkout/session']);

        $this->renderLayout();
    }
}
