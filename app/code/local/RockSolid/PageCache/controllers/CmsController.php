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

class RockSolid_PageCache_CmsController
    extends RockSolid_PageCache_Controller_Action
{
    public function viewAction()
    {
        $id = (int) $this->getRequest()->getParam(
            'id', Mage::getStoreConfig(Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE)
        );

        /** @var RockSolid_PageCache_Model_Proxy_CmsPage **/
        $page = Mage::getSingleton('fpc/proxy_factory')->getInstance(
            'fpc/proxy_cmsPage', 'cms/page', ['id' => $id]
        );

        Mage::register('_singleton/cms/page', $page);

        $this->prepareLayout();

        $this->initLayoutMessages(
            ['catalog/session', 'customer/session', 'checkout/session']
        );

        $this->renderLayout();
    }
}
