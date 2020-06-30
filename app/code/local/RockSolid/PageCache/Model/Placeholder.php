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

class RockSolid_PageCache_Model_Placeholder implements JsonSerializable
{
    /**
     * Name of the block
     *
     * @var string
     */
    private $_blockName = '';

    /**
     * Type of the block
     *
     * @var string
     */
    private $_blockType = '';

    /**
     * Flag whether the block is not constructed from layout
     *
     * @var bool
     */
    private $_isAnonymousBlock = false;

    /**
     * Attributes of the block
     *
     * @var array
     */
    private $_blockAttributes = [];

    /**
     * Named cache key info of the block
     *
     * @var array
     */
    private $_blockInfo = [];


   const PATTERN = '/<!--\{\{(.*?)\}\}-->/i';

    /**
     * Factory method to create an instance form an base64 encoded definition
     *
     * @param string $data
     * @return self
     * @throws Exception
     */
    public static function fromString(string $data) : self
    {
        return new self(json_decode(base64_decode($data), true));
    }

    /**
     * @param array $data
     * @throws Exception
     */
    public function __construct(array $data = [])
    {
        if (!empty($data['is_anonymous_block'])) {
            $this->_isAnonymousBlock = (bool)$data['is_anonymous_block'];
        }

        if (!empty($data['block_type'])) {
            $this->_blockType = $data['block_type'];
        }

        if (!empty($data['block_name'])) {
            $this->_blockName = $data['block_name'];
        }

        if (!empty($data['block_attributes'])) {
            $this->_blockAttributes = $data['block_attributes'];
        }

        if (!empty($data['block_info'])) {
            $this->_blockInfo = $data['block_info'];
        }

        if ($this->_isAnonymousBlock && !$this->_blockName) {
            throw new Exception('Block name is missing');
        } else if (!$this->_isAnonymousBlock && !$this->_blockType) {
            throw new Exception('Block type is missing');
        }
    }

    /**
     * Returns whether the block is constructed from layout
     *
     * @return bool
     */
    public function getIsAnonymousBlock() : bool
    {
        return $this->_isAnonymousBlock;
    }

    /**
     * Returns the block's name
     *
     * @return string
     */
    public function getBlockType() : string
    {
        return $this->_blockType;
    }

    /**
     * Returns the block's name
     *
     * @return string
     */
    public function getBlockName() : string
    {
        return $this->_blockName;
    }

    /**
     * Returns the block's attributes
     *
     * @return array
     */
    public function getBlockAttributes() : array
    {
        return $this->_blockAttributes;
    }

    /**
     * Returns the block info (named cache key info)
     *
     * @return array
     */
    public function getBlockInfo() : array
    {
        return $this->_blockInfo;
    }

    /**
     * Returns a string representation of the definition
     *
     * @return string
     */
    public function toString() : string
    {
        return '<!--{{' . base64_encode(json_encode($this)) . '}}-->';
    }

    public function jsonSerialize()
    {
        return [
            'is_anonymous'     => $this->getIsAnonymousBlock(),
            'block_type'       => $this->getBlockType(),
            'block_name'       => $this->getBlockName(),
            'block_attributes' => $this->getBlockAttributes(),
            'block_info'       => $this->getBlockInfo()
        ];
    }

    /**
     * Returns a string representation of the definition
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }
}
