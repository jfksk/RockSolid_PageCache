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

class RockSolid_PageCache_Model_Cache extends Mage_Core_Model_Cache
{
    const CACHE_TAG = 'FPC';
    const TYPE_CODE = 'fpc';

    public function __construct()
    {
        $node = Mage::getConfig()->getNode('global/full_page_cache');
        $options = [];
        if ($node) {
            $options = $node->asArray();
        }

        $configOptions = Mage::app()->getConfig()->getOptions();

        foreach (['backend_options', 'slow_backend_options'] as $tag) {
            if (!empty($options[$tag]['cache_dir'])) {
                $options[$tag]['cache_dir'] = Mage::getBaseDir('var') . DS . $options[$tag]['cache_dir'];
                $configOptions->createDirIfNotExists($options[$tag]['cache_dir']);
            }
        }

        parent::__construct($options);
    }

    /**
     * Save data to cache
     *
     * @param string   $data
     * @param string   $id
     * @param array    $tags
     * @param int|null $lifeTime
     *
     * @return bool
     */
    public function save($data, $id, $tags = [], $lifeTime = null)
    {
        if ($this->_disallowSave) {
            return true;
        }

        if (!in_array(self::CACHE_TAG, $tags)) {
            $tags[] = self::CACHE_TAG;
        }

        return $this->_frontend->save(
            $data,
            $this->_id($id),
            $this->_tags($tags),
            $this->_ttl($lifeTime)
        );
    }

    /**
     * Clean cache by tag
     *
     * @param array $tags
     *
     * @return  bool
     */
    public function clean($tags = [])
    {
        $mode = Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG;

        if (!empty($tags)) {
            if (!is_array($tags)) {
                $tags = [$tags];
            }
            $res = $this->getFrontend()->clean($mode, $this->_tags($tags));
        } else {
            $res = $this->getFrontend()->clean($mode, [self::CACHE_TAG]);
            $res = $res && $this->getFrontend()->clean(
                    $mode, [Mage_Core_Model_Config::CACHE_TAG]
                );

            $this->flushInterceptors();
        }

        return $res;
    }

    /**
     * Flush cached data
     *
     * @return  bool
     */
    public function flush()
    {
        $res = parent::flush();
        $this->flushInterceptors();

        return $res;
    }

    /**
     * Flush cached interceptors
     *
     * @return  self
     */
    public function flushInterceptors()
    {
        clearstatcache();

        $dir = Mage::getSingleton('fpc/proxy_factory')->getInterceptorCodeDir();
        if (!is_dir($dir)) {
            return $this;
        }

        array_map('unlink', glob("{$dir}*.php"));

        return $this;
    }

    /**
     * Mark specific cache type(s) as invalidated
     *
     * @param string|array $typeCode
     * @return RockSolid_PageCache_Model_Cache
     */
    public function invalidateType($typeCode)
    {
        if ($typeCode == self::TYPE_CODE) {
            Mage::dispatchEvent('fpc_cache_invalidate_before');
        }

        return parent::invalidateType($typeCode);
    }

    /**
     * Is the FPC active?
     *
     * @return  bool
     */
    public function isActive(): bool
    {
        return Mage::app()->useCache(self::TYPE_CODE);
    }

    /**
     * Returns the default ttl
     *
     * @return int|null|string
     */
    public function getDefaultTtl()
    {
        return $this->getFrontend()->getOption('lifetime');
    }

    /**
     * Prepares the ttl by appl. strtotime for non numeric ttls
     *
     * @param null|bool|int|string $ttl
     * @return int|null
     */
    protected function _ttl($ttl)
    {
        if ($ttl !== null && !is_bool($ttl) && !is_numeric($ttl)) {
            return strtotime($ttl) - time();
        }

        return $ttl;
    }
}
