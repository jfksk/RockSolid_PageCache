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

class RockSolid_PageCache_Model_Processor_Page
{
    protected $_id = '';
    protected $_content = '';
    protected $_blockProcessors = null;

    /**
     * @param string $id
     * @param string $content
     */
    public function __construct(string $id, string $content)
    {
        $this->_id = $id;
        $this->_content = $content;
    }

    /**
     * Returns the cache ID
     *
     * @return string
     */
    public function getId() : string
    {
        return $this->_id;
    }

    /**
     * Returns the page content
     *
     * @return string
     */
    public function getContent() : string
    {
        return $this->_content;
    }

    /**
     * Generate all blocks
     *
     * @return void
     */
    public function generateBlocks()
    {
        foreach ($this->getBlockProcessors() as $processor) {
            $processor->getBlock();
        }
    }

    /**
     * Retrurns an array of placeholder instances used on the page
     *
     * @return array
     */
    public function getBlockProcessors() : array
    {
        if ($this->_blockProcessors !== null) {
            return $this->_blockProcessors;
        }

        $this->_blockProcessors = [];

        $matches = [];
        preg_match_all(
            RockSolid_PageCache_Model_Placeholder::PATTERN,
            $this->_content,
            $matches,
            PREG_PATTERN_ORDER
        );

        $placeholderClass = Mage::getConfig()
            ->getModelClassName('fpc/placeholder');

        foreach (array_unique($matches[1]) as $placeholder) {
            $this->_blockProcessors[] = Mage::getModel(
                'fpc/processor_block', $placeholderClass::fromString($placeholder)
            );
        }

        return $this->_blockProcessors;
    }

    /**
     * Replace content, SIDs an formkeys with placeholders
     *
     * @return self
     */
    public function replaceContent() : self
    {
        foreach ($this->getBlockProcessors() as $processor) {
            $processor->replaceContent($this->_content);
        }

        $helper = $this->_getHelper();
        $this->_content = $helper->replaceFormKey($this->_content);
        $this->_content = $helper->replaceSid($this->_content);

        return $this;
    }

    /**
     * Replace all placeholders with content
     *
     * @return bool
     */
    public function applyContent()
    {
        $result = true;
        foreach ($this->getBlockProcessors() as $id => $processor) {
            if ($processor->applyContent($this->_content)) {
                unset($this->_blockProcessors[$id]);
            } else {
                $result = false;
            }
        }

        $helper = $this->_getHelper();
        $this->_content = $helper->restoreFormKey($this->_content);
        $this->_content = $helper->restoreSid($this->_content);

        return $result;
    }

    /**
     * Remove all placeholders from content
     *
     * @return self
     */
    public function removePlaceholders() : self
    {
        $this->_content = preg_replace(
            RockSolid_PageCache_Model_Placeholder::PATTERN, '', $this->_content
        );

        return $this;
    }

    /**
     * Returns the helper instance
     *
     * @return RockSolid_PageCache_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('fpc');
    }
}