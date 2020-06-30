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

class RockSolid_PageCache_Model_Core_Layout extends Mage_Core_Model_Layout
{
    private $_lazyRendering = false;

    /**
     * Add block object to layout based on xml node data
     *
     * @param Varien_Simplexml_Element $node
     * @param Varien_Simplexml_Element $parent
     * @return Mage_Core_Model_Layout
     */
    protected function _generateBlock($node, $parent)
    {
        if (!empty($node['class'])) {
            $className = (string)$node['class'];
        } else {
            $className = (string)$node['type'];
        }

        $blockName = (string)$node['name'];
        $_profilerKey = 'BLOCK: '.$blockName;
        Varien_Profiler::start($_profilerKey);

        $block = $this->addBlock($className, $blockName);
        if (!$block) {
            Varien_Profiler::stop($_profilerKey);
            return $this;
        }

        if (!empty($node['parent'])) {
            $parentBlock = $this->getBlock((string)$node['parent']);
        } else {
            $parentName = $parent->getBlockName();
            if (!empty($parentName)) {
                $parentBlock = $this->getBlock($parentName);
            }
        }
        if (!empty($parentBlock)) {
            $alias = isset($node['as']) ? (string)$node['as'] : '';
            if (isset($node['before'])) {
                $sibling = (string)$node['before'];
                if ('-'===$sibling) {
                    $sibling = '';
                }
                $parentBlock->insert($block, $sibling, false, $alias);
            } elseif (isset($node['after'])) {
                $sibling = (string)$node['after'];
                if ('-'===$sibling) {
                    $sibling = '';
                }
                $parentBlock->insert($block, $sibling, true, $alias);
            } else {
                $parentBlock->append($block, $alias);
            }

            // track if the block is within a placeholder.
            if ($parentBlock->getFpcCacheable() === false || $parentBlock->getFpcIsNonCacheableSibling()) {
                $block->setFpcIsNonCacheableSibling(true);
            }
        }

        if (!empty($node['template'])) {
            $block->setTemplate((string)$node['template']);
        }

        if (isset($node['fpc-cacheable']) && ($node['fpc-cacheable'] == '0' || $node['fpc-cacheable'] == 'false')) {
            $block->setFpcCacheable(false);
        }

        if (!empty($node['fpc-attributes'])) {
            $block->setFpcAttributes((string)$node['fpc-attributes']);
        }

        if (!empty($node['output'])) {
            $method = (string)$node['output'];
            $this->addOutputBlock($blockName, $method);
        }

        Varien_Profiler::stop($_profilerKey);

        return $this;
    }

    protected function _generateAction($node, $parent)
    {
        if (isset($node['ifconfig']) && ($configPath = (string)$node['ifconfig'])) {
            if (!Mage::getStoreConfigFlag($configPath)) {
                return $this;
            }
        }

        $method = (string)$node['method'];
        if (!empty($node['block'])) {
            $parentName = (string)$node['block'];
        } else {
            $parentName = $parent->getBlockName();
        }

        $_profilerKey = 'BLOCK ACTION: '.$parentName.' -> '.$method;
        Varien_Profiler::start($_profilerKey);

        if (!empty($parentName)) {
            $block = $this->getBlock($parentName);
        }

        if (!empty($block)) {
            $args = (array)$node->children();
            unset($args['@attributes']);

            foreach ($args as $key => $arg) {
                if (($arg instanceof Mage_Core_Model_Layout_Element)) {
                    if (isset($arg['helper'])) {
                        $helperName = explode('/', (string)$arg['helper']);
                        $helperMethod = array_pop($helperName);
                        $helperName = implode('/', $helperName);
                        $arg = $arg->asArray();
                        unset($arg['@']);
                        $args[$key] = call_user_func_array(array(Mage::helper($helperName), $helperMethod), $arg);
                    } else {
                        /**
                         * if there is no helper we hope that this is assoc array
                         */
                        $arr = [];
                        foreach($arg as $subkey => $value) {
                            $arr[(string)$subkey] = $value->asArray();
                        }
                        if (!empty($arr)) {
                            $args[$key] = $arr;
                        }
                    }
                }
            }

            if (isset($node['json'])) {
                $json = explode(' ', (string)$node['json']);
                foreach ($json as $arg) {
                    $args[$arg] = Mage::helper('core')->jsonDecode($args[$arg]);
                }
            }


            if (($method == 'insert' || $method == 'append') && $this->_lazyRendering) {
                $this->generateSiblings($args['block']);
            }

            $this->_translateLayoutNode($node, $args);
            call_user_func_array(array($block, $method), $args);
        }

        Varien_Profiler::stop($_profilerKey);

        return $this;
    }

    /**
     * Create a block and all its siblings
     *
     * @param string $blockName
     *
     * @return void
     */
    public function generateSiblings(string $blockName)
    {
        if (isset($this->_output[$blockName])) {
            return;
        }

        $nodes = $this->getXpath("//block[@name='".$blockName."']");
        if (!$nodes) {
            return;
        }

        Varien_Profiler::start('FPC::layout::generate_block');

        $this->_lazyRendering = true;

        $parent = new Mage_Core_Model_Layout_Element('<layout />');

        foreach ($nodes as $node) {
            $this->_generateBlock($node, $parent);
            $this->generateBlocks($node);
        }

        $refs = $this->getXpath("//reference[@name='".$blockName."']");
        if ($refs) {
            foreach ($refs as $ref) {
                $this->generateBlocks($ref);
            }
        }

        $this->_lazyRendering = false;

        Varien_Profiler::stop('FPC::layout::generate_block');
    }
}