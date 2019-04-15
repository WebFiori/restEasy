<?php

/* 
 * The MIT License
 *
 * Copyright 2019 Ibrahim BinAlshikh, restEasy library.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
namespace restEasy;
use jsonx\JsonI;
use jsonx\JsonX;
/**
 * A class that represents request parameter.
 * @author Ibrahim
 * @version 1.2.1
 */
class RequestParameter implements JsonI{
    /**
     * A boolean value that can be set to true to allow empty strings.
     * @var boolean 
     * @since 1.2.1
     */
    private $isEmptStrAllowed;
    /**
     * The type of the data the parameter will represents.
     * @var string
     * @since 1.0 
     */
    private $type;
    /**
     * The name of the parameter.
     * @var string
     * @since 1.0 
     */
    private $name;
    /**
     * Indicates wither the attribute is optional or not.
     * @var boolean true if the parameter is optional.
     * @since 1.0
     */
    private $isOptional;
    /**
     * The default value that will be used in case of parameter filter 
     * failure.
     * @var type 
     * @since 1.1
     */
    private $default;
    /**
     * The minimum value. Used if the parameter type is numeric.
     * @var type 
     * @since 1.1
     */
    private $minVal;
    /**
     * The maximum value. Used if the parameter type is numeric.
     * @var type 
     * @since 1.1
     */
    private $maxVal;
    /**
     * The description of the parameter.
     * @var string
     * @since 1.0 
     */
    private $desc;
    /**
     * A callback that is used to make a custom filtered value.
     * @var Fulnction
     * @since 1.2 
     */
    private $customFilterFunc;
    /**
     * A boolean value that is set to true in case the 
     * basic filter will be applied before custom one.
     * @var boolean
     * @since 1.2 
     */
    private $applyBasicFilter;
    /**
     * Sets the description of the parameter.
     * This method is used to document the API. Used to help front-end developers.
     * @param string $desc Parameter description.
     * @since 1.1
     */
    public function setDescription($desc) {
        $this->desc = $desc;
    }
    /**
     * Returns the description of the parameter.
     * @return string|null The description of the parameter. If the description is 
     * not set, the method will return null.
     * @since 1.1
     */
    public function getDescription() {
        return $this->desc;
    }
    /**
     * Creates new instance of the class.
     * @param string $name The name of the parameter as it appears in the request body. 
     * It must be a valid name. If the given name is invalid, the parameter 
     * name will be set to 'a-parameter'. Valid name must comply with the following 
     * rules:
     * <ul>
     * <li>It can contain the letters [A-Z] and [a-z].</li>
     * <li>It can contain the numbers [0-9].</li>
     * <li>It can have the character '-' and the character '_'.</li>
     * </ul>
     * @param string $type The type of the data that will be in the parameter stored 
     * by the parameter. Supported types are:
     * <ul>
     * <li>string</li>
     * <li>integer</li>
     * <li>email</li>
     * <li>float</li>
     * <li>url</li>
     * <li>boolean</li>
     * <li>array</li>
     * </ul> 
     * If invalid type is given or no type is provided, 'string' will be used by 
     * default.
     * @param boolean $isOptional Set to true if the parameter is optional. Default 
     * is false.
     */
    public function __construct($name,$type='string',$isOptional=false) {
        if(!$this->setName($name)){
            $this->setName('a-parameter');
        }
        $this->isOptional = $isOptional;
        if(!$this->setType($type)){
            $this->type = 'string';
        }
        $this->applyBasicFilter = false;
        $this->isEmptStrAllowed = false;
    }
    /**
     * Returns the minimum numeric value the parameter can accept.
     * This method apply only to and integer type.
     * @return int|null The minimum numeric value the parameter can accept. 
     * If the request parameter type is not numeric, the method will return 
     * null.
     * @since 1.1
     */
    public function getMinVal() {
        return $this->minVal;
    }
    /**
     * Returns the maximum numeric value the parameter can accept.
     * This method apply only to integer type.
     * @return int|null The maximum numeric value the parameter can accept. 
     * If the request parameter type is not numeric, the method will return 
     * null.
     * @since 1.1
     */
    public function getMaxVal() {
        return $this->maxVal;
    }
    /**
     * Sets the minimum value that the parameter can accept.
     * The value will be updated 
     * only if:
     * <ul>
     * <li>The request parameter type is numeric ('integer').</li>
     * <li>The given value is less than RequestParameter::getMaxVal()</li>
     * </ul>
     * @param int $val The minimum value to set.
     * @return boolean The method will return true once the minimum value 
     * is updated. false if not.
     * @since 1.1
     */
    public function setMinVal($val) {
        $type = $this->getType();
        if($type == 'integer'){
            $max = $this->getMaxVal();
            if($max != null && $val < $max){
                $this->minVal = $val;
                return true;
            }
        }
        return false;
    }
    /**
     * Checks if empty strings are allowed as values for the parameter.
     * By default, The method will return false.
     * @return boolean true if empty strings are allowed as values for the parameter. 
     * false if not.
     * @since 1.2.1
     */
    public function isEmptyStringAllowed() {
        return $this->isEmptStrAllowed;
    }
    /**
     * Allow or disallow empty strings as values for the parameter.
     * The value of the attribute will be updated only if the type of the 
     * parameter is set to 'string'.
     * @param boolean $bool true to allow empty strings and false to disallow 
     * empty strings.
     * @since 1.2.1
     */
    public function setIsEmptyStringAllowed($bool) {
        if($this->getType() == 'string'){
            $this->isEmptStrAllowed = $bool === true ? true : false;
        }
    }
    /**
     * Sets the maximum value.
     * The value will be updated 
     * only if:
     * <ul>
     * <li>The request parameter type is numeric ('integer').</li>
     * <li>The given value is greater than RequestParameter::getMinVal()</li>
     * </ul>
     * @param int $val The maximum value to set.
     * @return boolean The method will return true once the maximum value 
     * is updated. false if not.
     * @since 1.1
     */
    public function setMaxVal($val) {
        $type = $this->getType();
        if($type == 'integer'){
            $min = $this->getMinVal();
            if($min != null && $val > $min){
                $this->maxVal = $val;
                return true;
            }
        }
        return false;
    }
    
    /**
     * Sets a default value for the parameter to use if the parameter is 
     * not provided.
     * This method can be used to include a default value for the parameter if 
     * it is optional.
     * @param mixed $val default value for the parameter to use if the parameter is 
     * not provided.
     * @since 1.1
     */
    public function setDefault($val) {
        $this->default = $val;
    }
    /**
     * Returns the default value to use in case the parameter is 
     * not provided.
     * @return mixed|null The default value to use in case the parameter is 
     * not provided. If no default value is provided, the method will 
     * return null.
     * @since 1.1
     */
    public function getDefault() {
        return $this->default;
    }
    /**
     * Sets the type of the parameter.
     * @param string $type The type of the parameter. It must be a value 
     * form the array APIFilter::TYPES.
     * @return boolean true is returned if the type is updated. false 
     * if not.
     * @since 1.1
     */
    public function setType($type) {
        $sType = strtolower(trim($type));
        if(in_array($sType, APIFilter::TYPES)){
            $this->type = $sType;
            if($sType == 'integer'){
                $this->minVal = defined('PHP_INT_MIN') ? PHP_INT_MIN : ~PHP_INT_MAX;
                $this->maxVal = PHP_INT_MAX;
            }
            else{
                $this->maxVal = null;
                $this->minVal = null;
            }
            return true;
        }
        return false;
    }
    /**
     * Sets the name of the parameter.
     * A valid parameter name must 
     * follow the following rules:
     * <ul>
     * <li>It can contain the letters [A-Z] and [a-z].</li>
     * <li>It can contain the numbers [0-9].</li>
     * <li>It can have the character '-' and the character '_'.</li>
     * </ul>
     * @param string $name The name of the parameter. 
     * @return boolean If the given name is valid, the method will return 
     * true once the name is set. false is returned if the given 
     * name is invalid.
     * @since 1.0
     */
    public function setName($name){
        $nameTrimmed = trim($name);
        $len = strlen($nameTrimmed);
        if($len != 0){
            for ($x = 0 ; $x < $len ; $x++){
                $ch = $nameTrimmed[$x];
                if($ch == '_' || $ch == '-' || ($ch >= 'a' && $ch <= 'z') || ($ch >= 'A' && $ch <= 'Z') || ($ch >= '0' && $ch <= '9')){

                }
                else{
                    return false;
                }
            }
            $this->name = $nameTrimmed;
            return true;
        }
        return false;
    }
    /**
     * Returns the name of the parameter.
     * @return string The name of the parameter.
     * @since 1.0
     */
    public function getName(){
        return $this->name;
    }
    /**
     * Returns a boolean value that can be used to tell if the parameter is 
     * optional or not.
     * @return boolean true if the parameter is optional and false 
     * if not.
     * @since 1.0
     */
    public function isOptional(){
        return $this->isOptional;
    }
    /**
     * Returns the type of the parameter.
     * @return string The type of the parameter (Such as 'string', 'email', 'integer').
     * @since 1.0
     */
    public function getType(){
        return $this->type;
    }
    /**
     * Returns a JsonX object that represents the request parameter.
     * This method is used to help front-end developers in showing the 
     * documentation of the request parameter.
     * @return JsonX An object of type JsonX.
     * @since 1.0
     */
    public function toJSON() {
        $json = new JsonX();
        $json->add('name', $this->name);
        $json->add('type', $this->getType());
        $json->add('description', $this->getDescription());
        $json->add('is-optional', $this->isOptional());
        if($this->getDefault() !== null){
            $json->add('default', $this->getDefault());
        }
        if($this->getMinVal() !== null){
            $json->add('min-val', $this->getMinVal());
        }
        if($this->getMaxVal() !== null){
            $json->add('max-val', $this->getMaxVal());
        }
        return $json;
    }
    /**
     * Sets a callback method to work as a filter for request parameter.
     * The callback method 
     * will have two parameters passed to it. The first one is an associative 
     * array that contains the not-filtered value and the value filtered 
     * using basic filter. The values are contained in two 
     * indices: 
     * <ul>
     * <li>original-value</li>
     * <li>basic-filter-result</li>
     * </ul>
     * If the parameter $applyBasicFilter is set to false, the index 'basic-filter-result' 
     * will have the value 'NOT_APLICABLE'.
     * The second parameter is an object of type <b>RequestParameter</b> 
     * which contains original information for the filter. The method 
     * must be implemented in a way that makes it return false if the 
     * parameter has invalid value. If the parameter is filtered and 
     * was validated, the method must return the valid and filtered 
     * value.
     * @param callback $function A callback function. 
     * @param boolean $applyBasicFilter If set to true, 
     * the basic filter will be applied to the parameter. Default 
     * is true.
     * @since 1.2
     */
    public function setCustomFilterFunction($function,$applyBasicFilter=true) {
        if(is_callable($function)){
            $this->customFilterFunc = $function;
        }
        $this->applyBasicFilter = $applyBasicFilter === true ? true : false;
    }
    /**
     * Checks if we need to apply basic filter or not 
     * before applying custom filter callback.
     * @return boolean The method will return true 
     * if the basic filter will be applied before applying custom filter.
     * @since 1.2
     */
    public function applyBasicFilter() {
        return $this->applyBasicFilter;
    }
    /**
     * Returns the function that is used as a custom filter 
     * for the parameter.
     * @return callback|null The function that is used as a custom filter 
     * for the parameter. If not set, the method will return null.
     * @since 1.2
     */
    public function getCustomFilterFunction() {
        return $this->customFilterFunc;
    }
}