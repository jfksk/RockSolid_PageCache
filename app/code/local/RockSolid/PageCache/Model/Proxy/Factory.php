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
 * You should have received a copy of the GNU General Public License
 * along with Foobar.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  RockSolid
 * @package   RockSolid_PageCache
 * @author    Jan F. Kousek <jan@rocksolid.at>
 * @copyright 2020 RockSolid e.U. | Jan F. Kousek (http://www.rocksolid.at)
 * @license   http://opensource.org/licenses/gpl-3.0 GNU General Public License, version 3 (GPLv3)
 */

class RockSolid_PageCache_Model_Proxy_Factory
{
    protected $_for = '';
    protected $_config = [];
    protected $_origFor;
    protected $_forAlias;

    public function __construct()
    {
        $this->_config = Mage::getSingleton('fpc/config')->getNode('proxy')->asArray();
        $this->_config = array_change_key_case($this->_config,  CASE_LOWER);
    }

    /**
     * Factory that creates a $proxy $for a given class and returns the instance.
     *  The Interceptor will be created OTF
     *
     * @param $proxy
     * @param $for
     * @param array $args optional constructor arguments
     * @return object
     * @throws ReflectionException
     */
    public function getInstance($proxy, $for, array $args = [])
    {
        $class = $this->getClass($proxy, $for);
        return new $class($args);
    }

    /**
     * Returns the class-name of an $proxy $for a given class.
     *  The Interceptor will be created OTF
     *
     * @param $proxy
     * @param $for
     * @return string
     * @throws ReflectionException
     */
    public function getClass($proxy, $for)
    {
        $class = Mage::getConfig()->getModelClassName($proxy);

        if (class_exists($class, false)) {
            return $class;
        }

        $this->_for = Mage::getConfig()->getModelClassName($for);
        $this->_forAlias = $for;
        $this->_origFor = $this->_getOrigForClassName();

        $file = $this->getInterceptorCodeDir() . $this->_getInterceptorFileName();
        if (is_file($file)) {
            include_once $file;
        } else {
            $this->_generateInterception();
            include_once $file;
        }

        return $class;
    }

    /**
     * Returns the interceptor cache dir.
     *
     * @return string
     */
    public function getInterceptorCodeDir()
    {
        return Mage::getBaseDir('var') . DS . 'code' . DS . 'FPC' . DS .  'Proxy' . DS ;
    }

    /**
     * Returns the interceptor class-name for the current proxy
     *
     * @return string
     */
    protected function _getInterceptorClassName()
    {
        return $this->_origFor . '_Interceptor';
    }

    /**
     * Returns the fqn class-name for which the current proxy is for
     *
     * @return string
     */
    protected function _getOrigForClassName()
    {
        $classArr = explode('/', $this->_forAlias);
        $grp = Mage::getConfig()->getNode('global/models/' . $classArr[0] . '/class');

        return uc_words($grp . '_' . $classArr[1]);
    }

    /**
     * Returns the current interceptor file name
     *
     * @return string
     */
    protected function _getInterceptorFileName()
    {

        return str_replace('_', '', $this->_getInterceptorClassName()) . '.php';
    }

    /**
     * Generated the interceptor for the current proxy
     *
     * @throws ReflectionException
     * @return void
     */
    protected function _generateInterception()
    {
        $path = $this->getInterceptorCodeDir();
        if (!file_exists($path) || !is_dir($path)) {
            mkdir($path, 0777, true);
        }

        $intercepted = array_map('strtolower', class_parents($this->_for));

        $methodConfig = $this->_config[strtolower($this->_for)] ?? [];
        foreach ($intercepted as $class) {
            if (isset($this->_config[$class])) {
                $methodConfig = array_merge($methodConfig, $this->_config[$class]);
            }
        }

        $methodConfig = array_change_key_case($methodConfig, CASE_LOWER);

        $methods = [];
        $class = new ReflectionClass($this->_for);

        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = strtolower($method->getName());
            if (array_key_exists($methodName, $methodConfig) && empty($methodConfig[$methodName])) {
                continue;
            }

            if ($method->isFinal()) {
                continue;
            }

            if ($method->getDeclaringClass()->getName() === 'Varien_Object') {
                continue;
            }

            $methods[] = $method;
        }

        $code[] = '<?php';
        $code[] = '// AUTOMATICALLY GENERATED. DO NOT EDIT.';
        $code[] = 'class ' . $this->_getInterceptorClassName() . ' extends ' . $this->_for;
        $code[] = '{';

        foreach ($methods as $method) {
            $methodName = $method->getName();
            $methodKey = strtolower($methodName);

            $code[] = '    // from ' . $method->getDeclaringClass()->getName();
            $code[$methodKey] = '    public function ' . $methodName;

            $params = [];
            $callParams = [];
            /** @var ReflectionParameter $param */
            foreach ($method->getParameters() as $param) {
                $params[] = $this->_generateMethodParameter($param);
                $callParams[] = '$' . $param->getName();
            }

            $callParamStr = implode(', ', $callParams);

            $code[$methodKey] .= sprintf('(%s) ', implode(', ', $params));

            if (isset($methodConfig[$methodKey]['before'])) {
               $code[$methodKey] .= sprintf(
                    '{ $this->%s(); return parent::%s(%s); }',
                    $methodConfig[$methodKey]['before'], $methodName, $callParamStr
                );
            } else if (isset($methodConfig[$methodKey]['after'])) {
               $code[$methodKey] .= sprintf(
                    '{ parent::%s(%s); return $this->%s(); }',
                    $methodName, $callParamStr, $methodConfig[$methodKey]['after']
                );
            } else if (isset($methodConfig[$methodKey]['around'])) {
               $code[$methodKey] .= sprintf(
                    '{ if(!$this->%s()) {  $this->_ensureLoaded(); return parent::%s(%s); } else { return parent::%s(%s); } }',
                    $methodConfig[$methodKey]['around'], $methodName, $callParamStr, $methodName, $callParamStr
                );
            } else if (isset($methodConfig[$methodKey]['replace'])) {
               $code[$methodKey] .= sprintf(
                    '{ return $this->%s(); }', $methodConfig[$methodKey]['replace']
                );
            } else {
               $code[$methodKey] .= sprintf(
                    '{ $this->_ensureLoaded(); return parent::%s(%s); }', $methodName, $callParamStr
                );
            }
        }

$code[]  = <<<'EOD'
    protected $_constructCalled = false;
    protected $_loadCalled = false;

    public function __construct()
    {
        $this->_initOldFieldsMap();
        if ($this->_oldFieldsMap) {
            $this->_prepareSyncFieldsMap();
        }

        $args = func_get_args();
        if (empty($args[0])) {
            $args[0] = [];
        }
        $this->_data = $args[0];
        $this->_addFullNames();
    }

    protected function _ensureConstruct()
    {
        if ($this->_constructCalled) {
            return;
        }

        $this->_constructCalled = true;
        $this->_construct();
    }

    protected function _ensureLoaded()
    {
        if ($this->_loadCalled) {
            return;
        }

        $this->_loadCalled = true;

        $this->_initOldFieldsMap();
        if ($this->_oldFieldsMap) {
            $this->_prepareSyncFieldsMap();
        }

        $this->_ensureConstruct();

        $this->load($this->getId());
    }

    protected function _crudGuard()
    {
        throw new Exception('CRUD operations are not allowed in FPC context');
    }

    protected function _getData($key)
    {
        if ($data = parent::_getData($key)) {
            return $data;
        }

        $this->_ensureLoaded();
        return parent::_getData($key);
    }

    public function getData($key = '', $index = null)
    {
        if ($data = parent::getData($key)) {
            return $data;
        }

        $this->_ensureLoaded();
        return parent::getData($key);
    }
EOD;


        $code[] = '}';

        $file = $this->getInterceptorCodeDir() . $this->_getInterceptorFileName();
        file_put_contents($file, implode("\n", $code), LOCK_EX);

    }

    /**
     * Returns method parameters as string
     *
     * @param ReflectionParameter $param
     * @return string
     * @throws ReflectionException
     */
    protected function _generateMethodParameter(ReflectionParameter $param)
    {
        $output = '';

        if ($param->hasType()) {
            $output .= $param->getType() . ' ';
        }

        if($param->isPassedByReference()) {
            $output .= '&';
        }

        if($param->isVariadic()) {
            $output .= '...';
        }

        $output .= '$' . $param->getName();

        if ($param->isDefaultValueAvailable()) {
            $output .= ' = ';
            if ($param->isDefaultValueConstant()) {
                $output .= $param->getDefaultValueConstantName();
            } else if (is_string($param->getDefaultValue())) {
                $output .= "'" . $param->getDefaultValue() . "'";
            } else {
                $output .= var_export($param->getDefaultValue(), true);
            }
        }

        return $output;
    }
}
