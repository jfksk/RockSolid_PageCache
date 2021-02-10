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

class RockSolid_PageCache_Model_Cache_Item implements Serializable
{
    /**
     * @var string
     */
    private $_content;

    /**
     * @var array
     */
    private $_metaData;

    /**
     * @param string $content
     * @param array $metaData
     */
    public function __construct(string $content, array $metaData)
    {
        $this->_content = $content;
        $this->_metaData = $metaData;
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
     * Get metadata from the cached page
     *
     * @param string $key
     * @return mixed
     */
    public function getMetaData(string $key = '')
    {
        if ($key == '') {
            return $this->_metaData;
        } else if (array_key_exists($key, $this->_metaData)) {
            return $this->_metaData[$key];
        }

        if (strpos($key,'/')) {
            $keyArr = explode('/', $key);
            $data = $this->_metaData;

            foreach ($keyArr as $k) {
                if ($k==='') {
                    return null;
                }

                if (is_array($data)) {
                    if (!isset($data[$k])) {
                        return null;
                    }

                    $data = $data[$k];
                } else {
                    return null;
                }
            }

            return $data;
        }

        return null;
    }

    /**
     * Serialize the cache item
     *
     * @return string
     */
    public function serialize()
    {
        return serialize(
            [
                'content' => $this->_content,
                'meta' => $this->_metaData
            ]
        );
    }

    /**
     * Populate the cache item from serialized data
     *
     * @param string $serialized
     * @return void
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized, ['allowed_classes' => false]);

        $this->_content = $data['content'] ?? '';
        $this->_metaData = $data['meta'] ?? [];
    }
}