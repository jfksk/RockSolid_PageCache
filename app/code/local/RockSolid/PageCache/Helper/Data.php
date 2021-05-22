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

class RockSolid_PageCache_Helper_Data extends Mage_Core_Helper_Abstract
{
    const FORMKEY_PLACEHOLDER = '{{FORM_KEY}}';
    const SID_PLACEHOLDER = '{{SID}}';

    /**
     * Replaces the session ID within the $content with a placeholder
     *
     * @param string $content
     * @return string
     */
    public function replaceSid(string $content): string
    {
        if (!$content) {
            return $content;
        }

        $session = Mage::getSingleton('core/session');
        return str_replace(
            $session->getSessionIdQueryParam() . '=' . $session->getSessionId(),
            $session->getSessionIdQueryParam() . '=' . self::SID_PLACEHOLDER,
            $content
        );
    }

    /**
     * Replaces the session ID placeholder within the $content with
     *  the current session ID
     *
     * @param string $content
     * @return string
     */
    public function restoreSid(string $content): string
    {
        if (!$content) {
            return $content;
        }

        $session = Mage::getSingleton('core/session');
        return str_replace(
            self::SID_PLACEHOLDER, htmlspecialchars($session->getSessionId()), $content
        );
    }

    /**
     * Replaces the formkey within the $content with a placeholder
     *
     * @param string $content
     * @return string
     */
    public static function replaceFormKey(string $content): string
    {
        if (!$content) {
            return $content;
        }

        $session = Mage::getSingleton('core/session');
        return str_replace(
            $session->getFormKey(), self::FORMKEY_PLACEHOLDER, $content
        );

    }

    /**
     * Replaces the formkey placeholder within the $content with
     *  the current formkey
     *
     * @param string $content
     * @return string
     */
    public static function restoreFormKey(string $content): string
    {
        if (!$content) {
            return false;
        }

        $session = Mage::getSingleton('core/session');
        return str_replace(
            self::FORMKEY_PLACEHOLDER, $session->getFormKey(), $content
        );
    }
}