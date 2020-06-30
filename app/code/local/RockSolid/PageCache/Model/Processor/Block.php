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

 use RockSolid_PageCache_Model_Placeholder as Placeholder;

class RockSolid_PageCache_Model_Processor_Block
{
    CONST DEBUG_PATH = 'system/fpc/debug_highlight';

    /**
     * @var RockSolid_PageCache_Model_Placeholder
     */
    private $_placeholder;

    private $_block = null;

    /**
     * @param RockSolid_PageCache_Model_Placeholder $placeholder
     */
    public function __construct(Placeholder $placeholder)
    {
        $this->_placeholder = $placeholder;
    }

    /**
     * Replace placeholder with content
     *
     * @param string $content
     *
     * @return bool
     */
    public function applyContent(string &$content) : bool
    {
        $content = str_replace(
            $this->_placeholder->toString(),
            $this->getRenderedContent(),
            $content,
            $count
        );

        return $count >= 1;
    }

    /**
     * Renders the block and returns the result
     *
     * @return string
     */
    public function getRenderedContent() : string
    {
        $block = $this->getBlock();

        if (!$block) {
            if (Mage::getIsDeveloperMode()) {
                return '<!-- NOT FOUND: ' . json_encode($this->_placeholder) . ' -->';
            }
            return '';
        }

        Varien_Profiler::start('FPC::placeholder::render_block');
        $blockContent = $block->toHtml();
        Varien_Profiler::stop('FPC::placeholder::render_block');

        if (Mage::getIsDeveloperMode() && Mage::getStoreConfigFlag(self::DEBUG_PATH)) {
            $blockClass = get_class($block);
            return <<<HTML
<div style="position:relative; border:1px dotted #5F9EA0; padding:18px 2px 2px 2px;">
    <div style="position:absolute; right:0; top:0; padding:2px 5px; background:#5F9EA0; color:white; font:normal 11px Arial; text-align:left !important; z-index:998;" title="{$blockClass}">{$blockClass}</div>
        {$blockContent}
    </div>
</div>
HTML;
        } else {
            return $blockContent;
        }
    }

    /**
     * Replace the content with the placeholder
     *
     * @param string $content
     *
     * @return bool
     */
    public function replaceContent(string &$content) : bool
    {
        $tag = preg_quote($this->_placeholder->toString(), '/');

        $content = preg_replace(
            "/($tag.*?$tag)/ims",
            $this->_placeholder->toString(),
            $content,
            -1,
            $count
        );

        return $count >= 1;
    }

    /**
     * Mark content for replacement
     *
     * @param string $content
     *
     * @return self
     */
    public function prepareContent(string &$content) : self
    {
        $marker = $this->_placeholder->toString();
        $content = $marker . $content . $marker;

        return $this;
    }

    /**
     * Remove placeholder marker from content
     *
     * @param string $content
     *
     * @return bool
     */
    public function removePlaceholder(string &$content) : bool
    {
        $content = str_replace(
            $this->_placeholder->toString(),
            '',
            $content,
            $count
        );

        return $count >= 1;
    }

    /**
     * Create block from the placeholder data
     *
     * @return Mage_Core_Block_Abstract|false
     */
    public function getBlock()
    {
        if ($this->_block !== null) {
            return $this->_block;
        }

        /** @var RockSolid_PageCache_Model_Core_Layout **/
        $layout = Mage::app()->getLayout();

        $attributes = $this->_placeholder->getBlockAttributes();

        if ($this->_placeholder->getIsAnonymousBlock()) {
            return $layout->createBlock(
                $this->_placeholder->getBlockType(), null, $attributes
            );
        }

        $this->_block = $layout->getBlock($this->_placeholder->getBlockName());
        if ($this->_block) {
            $this->_block->addData($attributes);
            return $this->_block;
        }

        $layout->generateSiblings($this->_placeholder->getBlockName());
        $this->_block = $layout->getBlock($this->_placeholder->getBlockName());
        if ($this->_block) {
            $this->_block->addData($attributes);
            return $this->_block;
        }

        return false;
    }
}
