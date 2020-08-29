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
use webfiori\entity\Response;
/**
 * A class that represents a set of web services.
 * 
 * This class is used to keep track of multiple related web services. It 
 * is used to group related services. For example, if we have create, read, write and 
 * delete services, they can be added to one instance of this class.
 * In order to create a simple web service, the developer must 
 * follow the following steps:
 * <ul>
 * <li>Extend this class.</li>
 * <li>Create web services using the class WebService.</li>
 * <li>Implement the abstract method <a href="#isAuthorized">WebServicesSet::isAuthorized()</a> 
 * and the method <a href="#processRequest">WebServicesSet::processRequest()</a></li>
 * </li>
 * When a request is made to the services set, An instance of the child class must be created 
 * and the method <a href="#process">WebServicesSet::process()</a> must be called.
 * 
 * @version 1.4.7
 */
abstract class WebServicesSet implements JsonI {
    /**
     * A constant which is used to indicate that the message that will be 
     * sent is of type error
     */
    const E = 'error';
    /**
     * A constant which is used to indicate that the message that will be 
     * sent is of type info
     */
    const I = 'info';
    /**
     * An array that contains the supported 'POST' request content types.
     * 
     * This array has the following values:
     * <ul>
     * <li>application/x-www-form-urlencoded</li>
     * <li>multipart/form-data</li>
     * <li>application/json</li>
     * </ul>
     * 
     * @var array An array that contains the supported 'POST' and 'PUT' request content types.
     * 
     * @since 1.1
     */
    const POST_CONTENT_TYPES = [
        'application/x-www-form-urlencoded',
        'multipart/form-data',
        'application/json'
    ];
    /**
     * An array that contains the action that can be performed by the API.
     * 
     * @var array
     * 
     * @since 1.0 
     */
    private $actions;
    /**
     * A general description for the API.
     * 
     * @var string
     * 
     * @since 1.3 
     */
    private $apiDesc;
    /**
     * The version number of the API.
     * 
     * @var string
     * 
     * @since 1.0 
     */
    private $apiVersion;
    /**
     * Actions that requires authentication in order to perform.
     * 
     * @var array
     * 
     * @since 1.0 
     */
    private $authActions;
    /**
     * The filter used to sanitize request parameters.
     * 
     * @var APIFilter
     * 
     * @since 1.0 
     */
    private $filter;
    /**
     * An array which contains body parameters that have invalid values.
     * 
     * @var array
     * 
     * @since 1.4.1 
     */
    private $invParamsArr;
    /**
     * An array which contains the missing required body parameters.
     * 
     * @var array
     * 
     * @since 1.4.1 
     */
    private $missingParamsArr;
    /**
     * The stream at which the output will be sent to.
     * 
     * @var resource|null
     */
    private $outputStream;
    /**
     * The path of the stream.
     * 
     * @var string|null 
     */
    private $outputStreamPath;
    /**
     * API request method.
     * 
     * @var string 
     * 
     * @since 1.0
     */
    private $requestMethod;
    /**
     * Creates new instance of the class.
     * 
     * By default, the API will have two services added to it:
     * <ul>
     * <li>api-info</li>
     * <li>request-info</li>
     * </ul>
     * The first service is used to return a JSON string which contains 
     * all needed information by the front-end to implement the API. The user 
     * can supply an optional parameter with it which is called 'version' in 
     * order to get information about specific API version. The 
     * second service is used to get basic info about the request.
     * 
     * @param string $version initial API version. Default is '1.0.0' Version 
     * number must follow the format 'X.X.X' where 'X' is a number between 
     * 0 and 9 inclusive.
     */
    public function __construct($version = '1.0.0') {
        $this->setVersion($version);
        $this->setDescription('NO DESCRIPTION');
        $this->requestMethod = filter_var(getenv('REQUEST_METHOD'));

        if (!in_array($this->requestMethod, WebService::METHODS)) {
            $this->requestMethod = 'GET';
        }
        $this->actions = [];
        $this->authActions = [];
        $this->filter = new APIFilter();
        $action = new WebService('api-info');
        $action->setDescription('Returns a JSON string that contains all needed information about all end points in the given API.');
        $action->addRequestMethod('get');
        $action->addParameter(new RequestParameter('version', 'string', true));
        $action->getParameterByName('version')->setDescription('Optional parameter. '
                .'If set, the information that will be returned will be specific '
                .'to the given version number.');
        $this->addAction($action,true);
        $this->invParamsArr = [];
        $this->missingParamsArr = [];
    }
    /**
     * Sends a response message to indicate that an action is not implemented.
     * 
     * This method will send back a JSON string in the following format:
     * <p>
     * {<br/>
     * &nbsp;&nbsp;"message":"Action not implemented.",<br/>
     * &nbsp;&nbsp;"type":"error",<br/>
     * }
     * </p>
     * In addition to the message, The response will sent HTTP code 404 - Not Found.
     * 
     * @since 1.0
     */
    public function actionNotImpl() {
        $this->sendResponse('Action not implemented.', self::E, 404);
    }
    /**
     * Sends a response message to indicate that an action is not supported by the API.
     * This method will send back a JSON string in the following format:
     * <p>
     * {<br/>
     * &nbsp;&nbsp;"message":"Action not supported",<br/>
     * &nbsp;&nbsp;"type":"error"<br/>
     * }
     * </p>
     * In addition to the message, The response will sent HTTP code 404 - Not Found.
     * 
     * @since 1.0
     */
    public function actionNotSupported() {
        $this->sendResponse('Action not supported.', self::E, 404);
    }
    /**
     * Adds new web service to the set of web services.
     * 
     * @param WebService $service The web service that will be added.
     * 
     * @param boolean $reqPermissions Set to true if the action require user login or 
     * any additional permissions. Default is false. If this one is set to 
     * true, the method 'WebservicesSet::isAuthorized()' will be called to check 
     * for permissions.
     * 
     * @return boolean true if the action is added. FAlSE otherwise.
     * 
     * @since 1.0
     * 
     * @deprecated since version 1.4.7 Use WebservicesSet::addService()
     */
    public function addAction($service,$reqPermissions = false) {
        if ($service instanceof WebService && (!in_array($service, $this->getActions()) && !in_array($service, $this->getAuthActions()))) {
            $service->setSince($this->getVersion());

            if ($reqPermissions === true) {
                array_push($this->authActions, $service);
            } else {
                array_push($this->actions, $service);
            }

            return true;
        }

        return false;
    }
    /**
     * Adds new web service to the set of web services.
     * 
     * @param WebService $service The web service that will be added.
     * 
     * @param boolean $reqPermissions Set to true if the action require user login or 
     * any additional permissions. Default is false. If this one is set to 
     * true, the method 'WebservicesSet::isAuthorized()' will be called to check 
     * for permissions.
     * 
     * @return boolean true if the action is added. false otherwise.
     * 
     * @since 1.0
     */
    public function addService($service, $reqPermissions = false) {
        return $this->addAction($service, $reqPermissions);
    }
    /**
     * Sends a response message to indicate that request content type is 
     * not supported by the API.
     * 
     * This method will send back a JSON string in the following format:
     * <p>
     * {<br/>
     * &nbsp;&nbsp;"message":"Content type not supported.",<br/>
     * &nbsp;&nbsp;"type":"error",<br/>
     * &nbsp;&nbsp;"request-content-type":"content_type"<br/>
     * }
     * </p>
     * In addition to the message, The response will sent HTTP code 404 - Not Found.
     * 
     * @param string $cType The value of the header 'content-type' taken from 
     * request header.
     * 
     * @since 1.1
     */
    public function contentTypeNotSupported($cType = '') {
        $j = new JsonX();
        $j->add('request-content-type', $cType);
        $this->sendResponse('Content type not supported.', self::E, 404,$j);
    }
    /**
     * Sends a response message to indicate that a database error has occur.
     * 
     * This method will send back a JSON string in the following format:
     * <p>
     * {<br/>
     * &nbsp;&nbsp;"message":"Database Error",<br/>
     * &nbsp;&nbsp;"type":"error",<br/>
     * &nbsp;&nbsp;"err-info":OTHER_DATA<br/>
     * }
     * </p>
     * In here, 'OTHER_DATA' can be a basic string or JSON string.
     * Also, The response will sent HTTP code 404 - Not Found.
     * 
     * @param JsonI|JsonX|string $info An object of type JsonI or 
     * JsonX that describe the error in more details. Also it can be a simple string 
     * or JSON string.
     * 
     * @since 1.0
     */
    public function databaseErr($info = '') {
        $this->sendResponse('Database Error.', self::E, 500, $info);
    }
    /**
     * Returns the name of the service which is being called.
     * 
     * The name of the service  must be passed in the body of the request for POST and PUT 
     * request methods (e.g. 'action=do-something' or 'service-name=do-something'). 
     * In case of GET and DELETE, it must be passed as query string. 
     * 
     * @return string|null The name of the service that was requested. If the name 
     * of the service is not set, the method will return null. 
     * 
     * @since 1.0
     * 
     * @deprecated since version 1.4.6 Use WebServicesSet::getCalledServiceName() instead.
     */
    public function getAction() {
        $reqMeth = $this->getRequestMethod();

        $serviceIdx = ['action', 'service-name'];
        
        $contentType = $this->getContentType();
        
        if ($contentType == 'application/json') {
            $body = file_get_contents('php://input');
            $jsonx = JsonX::decode($body);
            
            if ($jsonx instanceof JsonX) {
                foreach ($serviceIdx as $index) {
                    $serviceName = $jsonx->get($index);
                    if ($serviceName !== null) {
                        return filter_var($serviceName);
                    }
                }
                return null;
            } else {
                return null;
            }
        }
        
        foreach ($serviceIdx as $serviceNameIndex) {
            if (($reqMeth == 'GET' || 
               $reqMeth == 'DELETE' ||  
               $reqMeth == 'OPTIONS' || 
               $reqMeth == 'PATCH') && isset($_GET[$serviceNameIndex])) {
                return filter_var($_GET[$serviceNameIndex]);
            } else if (($reqMeth == 'POST' || $reqMeth == 'PUT') && isset($_POST[$serviceNameIndex])) {
                return filter_var($_POST[$serviceNameIndex]);
            }
        }

        return null;
    }
    /**
     * Returns a web service given its name.
     * 
     * @param string $serviceName The name of the service.
     * 
     * @return WebService|null The method will return an object of type 'APIAction' 
     * if the service is found. If no service was found which has the given name, 
     * The method will return null.
     * 
     * @since 1.3
     */
    public function getActionByName($serviceName) {
        $trimmed = trim($serviceName);

        if (strlen($trimmed) != 0) {
            foreach ($this->getActions() as $action) {
                if ($action->getName() == $trimmed) {
                    return $action;
                }
            }

            foreach ($this->getAuthActions() as $action) {
                if ($action->getName() == $trimmed) {
                    return $action;
                }
            }
        }

        return null;
    }
    /**
     * Returns an array that contains all added web services that does not require authentication.
     * 
     * @return array An array that contains an objects of type APIAction. 
     * The services on the returned array does not require authentication.
     * 
     * @since 1.0
     */
    public final function getActions() {
        return $this->actions;
    }
    /**
     * Returns an array that contains all added web services that require authentication.
     * 
     * @return array An array that contains an objects of type APIAction. 
     * The array will contains the services which require authentication. The 
     * authentication process is performed in the body of the method 
     * 'WebServicesSet::isAuthorized()'.
     * 
     * @since 1.0
     */
    public final function getAuthActions() {
        return $this->authActions;
    }
    /**
     * Returns the name of the service which is being called.
     * 
     * The name of the service  must be passed in the body of the request for POST and PUT 
     * request methods (e.g. 'action=do-something' or 'service-name=do-something'). 
     * In case of GET and DELETE, it must be passed as query string. 
     * 
     * @return string|null The name of the service that was requested. If the name 
     * of the service is not set, the method will return null. 
     * 
     * @since 1.0 
     * 
     * @return type
     */
    public function getCalledServiceName() {
        return $this->getAction();
    }
    /**
     * Returns request content type.
     * 
     * @return string The value of the header 'content-type' in the request.
     * 
     * @since 1.1
     */
    public final function getContentType() {
        $c = isset($_SERVER['CONTENT_TYPE']) ? filter_var($_SERVER['CONTENT_TYPE']) : null;

        if ($c !== null && $c !== false) {
            return trim(explode(';', $c)[0]);
        }

        return null;
    }
    /**
     * Returns the description of web services set.
     * 
     * @return string|null The description of web services set. The description is 
     * useful to describe what does the set of services can do. If the description is 
     * not set, the method will return null.
     * 
     * @since 1.3
     */
    public function getDescription() {
        return $this->apiDesc;
    }
    /**
     * Returns an associative array or an object of type JsonX of filtered request inputs.
     * 
     * The indices of the array will represent request parameters and the 
     * values of each index will represent the value which was set in 
     * request body. The values will be filtered and might not be exactly the same as 
     * the values passed in request body. Note that if a parameter is optional and not 
     * provided in request body, its value will be set to 'null'. Note that 
     * if request content type is 'application/json', only basic filtering will 
     * be applied. Also, parameters in this case don't apply.s
     * 
     * @return array|JsonX An array of filtered request inputs. This also can 
     * be an object of type 'JsonX' if request content type was 'application/json'.
     * 
     * @since 1.0
     */
    public function getInputs() {
        return $this->filter->getInputs();
    }
    /**
     * Returns an array that contains the names of request parameters which have invalid values.
     * 
     * @return array An array that contains the names of request parameters which have invalid values.
     * 
     * @since 1.4.1
     */
    public function getInvalidParameters() {
        return $this->invParamsArr;
    }
    /**
     * Returns an array that contains the names of missing required parameters. 
     * If a parameter is optional and not provided, it will not appear in the returned array.
     * 
     * @return array An array that contains the names of missing required parameters.
     * 
     * @since 1.4.1
     */
    public function getMissingParameters() {
        return $this->missingParamsArr;
    }
    /**
     * Returns an associative array of non-filtered request inputs.
     * The indices of the array will represent request parameters and the 
     * values of each index will represent the value which was set in 
     * request body. The values will be exactly the same as 
     * the values passed in request body. Note that if a parameter is optional and not 
     * provided in request body, its value will be set to 'null'.
     * 
     * @return array An array of request parameters.
     * 
     * @since 1.4.3
     */
    public function getNonFiltered() {
        return $this->filter->getNonFiltered();
    }
    /**
     * Returns the stream at which the output will be sent to.
     * 
     * @return resource|null If a custom output stream is set using the 
     * method 'WebServicesSet::setOutputStream()', the method will return a 
     * resource. The resource will be still open. If no custom stream is set, 
     * the method will return null.
     * 
     * @since 1.4.7
     */
    public function getOutputStream() {
        return $this->outputStream;
    }
    /**
     * Returns a string that represents the path of the custom output stream.
     * 
     * @return string|null A string that represents the path of the custom output stream. 
     * If no custom output stream is set, the method will return null.
     * 
     * @since 1.4.7
     */
    public function getOutputStreamPath() {
        return $this->outputStreamPath;
    }
    /**
     * Returns the name of request method which is used to call one of the services in the set.
     * 
     * @return string Request method such as POST, GET, etc....
     * 
     * @since 1.0
     */
    public final function getRequestMethod() {
        return $this->requestMethod;
    }
    /**
     * Returns version number of web services set.
     * 
     * @return string A string in the format 'X.X.X'.
     * 
     * @since 1.0
     */
    public final function getVersion() {
        return $this->apiVersion;
    }
    /**
     * Sends a response message to indicate that a request parameter(s) have invalid values.
     * 
     * This method will send back a JSON string in the following format:
     * <p>
     * {<br/>
     * &nbsp;&nbsp;"message":"The following parameter(s) has invalid values: 'param_1', 'param_2', 'param_n'",<br/>
     * &nbsp;&nbsp;"type":"error"<br/>
     * }
     * </p>
     * In addition to the message, The response will sent HTTP code 404 - Not Found.
     * 
     * @since 1.3
     */
    public function invParams() {
        $val = '';
        $i = 0;
        $paramsNamesArr = $this->getInvalidParameters();
        $count = count($paramsNamesArr);

        foreach ($paramsNamesArr as $paramName) {
            if ($i + 1 == $count) {
                $val .= '\''.$paramName.'\'';
            } else {
                $val .= '\''.$paramName.'\', ';
            }
            $i++;
        }
        $this->sendResponse('The following parameter(s) has invalid values: '.$val.'.', self::E, 404);
    }
    /**
     * Checks if the called service exist in the set or not.
     * 
     * @return boolean The method will return true if the requested service exist in 
     * the set. False if not exist or if no service is called. The name of the service 
     * must be provided with the request as a parameter with the name 'action' or 
     * the name 'sevice-name'.
     * 
     * @since 1.0
     */
    public final function isActionSupported() {
        $action = $this->getCalledServiceName();

        foreach ($this->getActions() as $val) {
            if ($val->getName() == $action) {
                return true;
            }
        }

        foreach ($this->getAuthActions() as $val) {
            if ($val->getName() == $action) {
                return true;
            }
        }

        return false;
    }
    /**
     * Checks if a user is authorized to call a srvice that require authorization.
     * 
     * @return boolean The method must be implemented by the sub-class in a way 
     * that makes it return true in case the user is allowed to call the 
     * service. If the user is not permitted, the method must return false.
     * 
     * @since 1.1
     */
    public abstract function isAuthorized();
    /**
     * Checks if request content type is supported by the service or not (For 'POST' 
     * and PUT requests only).
     * 
     * @return boolean Returns false in case the 'content-type' header is not 
     * set and the request method is 'POST' or 'PUT'. If content type is supported (for 
     * PUT and POST), the method will return true, false if not. Other than that, the method 
     * will return true.
     * 
     * @since 1.1
     */
    public final function isContentTypeSupported() {
        $c = $this->getContentType();
        $rm = $this->getRequestMethod();

        if ($c !== null && $rm == 'POST' || $rm == 'PUT') {
            return in_array($c, self::POST_CONTENT_TYPES);
        } else if ($c === null && $rm == 'POST' || $rm == 'PUT') {
            return false;
        }

        return true;
    }
    /**
     * Sends a response message to tell the front-end that the parameter 
     * 'action' or 'service-name' is missing from request body.
     * 
     * This method will send back a JSON string in the following format:
     * <p>
     * {<br/>
     * &nbsp;&nbsp;"message":"Service name is not set.",<br/>
     * &nbsp;&nbsp;"type":"error"<br/>
     * }
     * </p>
     * In addition to the message, The response will sent HTTP code 404 - Not Found.
     * 
     * @since 1.3.1
     */
    public function missingAPIAction() {
        $this->sendResponse('Service name is not set.', self::E, 404);
    }
    /**
     * Sends a response message to indicate that a request parameter or parameters are missing.
     * 
     * This method will send back a JSON string in the following format:
     * <p>
     * {<br/>
     * &nbsp;&nbsp;"message":"The following required parameter(s) where missing from the request body: 'param_1', 'param_2', 'param_n'",<br/>
     * &nbsp;&nbsp;"type":"error",<br/>
     * }
     * </p>
     * In addition to the message, The response will sent HTTP code 404 - Not Found.
     * 
     * @since 1.3
     */
    public function missingParams() {
        $val = '';
        $paramsNamesArr = $this->getMissingParameters();
        $i = 0;
        $count = count($paramsNamesArr);

        foreach ($paramsNamesArr as $paramName) {
            if ($i + 1 == $count) {
                $val .= '\''.$paramName.'\'';
            } else {
                $val .= '\''.$paramName.'\', ';
            }
            $i++;
        }
        $this->sendResponse('The following required parameter(s) where missing from the request body: '.$val.'.', self::E, 404);
    }
    /**
     * Sends a response message to indicate that a user is not authorized call a 
     * service or a resource.
     * 
     * This method will send back a JSON string in the following format:
     * <p>
     * {<br/>
     * &nbsp;&nbsp;"message":"Not authorized",<br/>
     * &nbsp;&nbsp;"type":"error"<br/>
     * }
     * </p>
     * In addition to the message, The response will sent HTTP code 401 - Not Authorized.
     * 
     * @since 1.0
     */
    public function notAuth() {
        $this->sendResponse('Not authorized.', self::E, 401);
    }
    /**
     * Process user request. 
     * 
     * This method must be called after creating any 
     * new instance of the class in order to process user request.
     * 
     * @since 1.0
     */
    public final function process() {
        $this->invParamsArr = [];
        $this->missingParamsArr = [];

        if ($this->isContentTypeSupported()) {
            if ($this->_checkAction()) {
                $actionObj = $this->getActionByName($this->getCalledServiceName());
                $params = $actionObj->getParameters();
                $this->filter->clearParametersDef();
                $this->filter->clearInputs();

                foreach ($params as $param) {
                    $this->filter->addRequestParameter($param);
                }
                $reqMeth = $this->getRequestMethod();
                
                if ($reqMeth == 'GET' || 
                    $reqMeth == 'DELETE' || 
                    $reqMeth == 'PUT' || 
                    $reqMeth == 'OPTIONS' || 
                    $reqMeth == 'PATCH') {
                    $this->filter->filterGET();
                } else if ($reqMeth == 'POST') {
                    $this->filter->filterPOST();
                }
                $i = $this->getInputs();
                $processReq = true;
                if (!($i instanceof JsonX)) {
                    foreach ($params as $param) {
                        if (!$param->isOptional() && !isset($i[$param->getName()])) {
                            array_push($this->missingParamsArr, $param->getName());
                            $processReq = false;
                        }

                        if (isset($i[$param->getName()]) && $i[$param->getName()] === 'INV') {
                            array_push($this->invParamsArr, $param->getName());
                            $processReq = false;
                        }
                    }

                    if ($processReq) {
                        if ($this->_isAuthorizedAction()) {
                            if ($this->getCalledServiceName() == 'api-info') {
                                $this->send('application/json', $this->toJSON());
                            } else {
                                $this->processRequest();
                            }
                        } else {
                            $this->notAuth();
                        }
                    } else if (count($this->missingParamsArr) != 0) {
                        $this->missingParams();
                    } else if (count($this->invParamsArr) != 0) {
                        $this->invParams();
                    }
                } else {
                    $paramsNames = $i->getPropsNames();
                    
                    foreach ($params as $param) {
                        if (!$param->isOptional() && !in_array($param->getName(), $paramsNames)) {
                            array_push($this->missingParamsArr, $param->getName());
                            $processReq = false;
                        }
                    }
                    
                    if ($processReq) {
                        if ($this->_isAuthorizedAction()) {
                            if ($this->getCalledServiceName() == 'api-info') {
                                $this->send('application/json', $this->toJSON());
                            } else {
                                $this->processRequest();
                            }
                        } else {
                            $this->notAuth();
                        }
                    } else if (count($this->missingParamsArr) != 0) {
                        $this->missingParams();
                    }
                }
            }
        } else {
            $this->contentTypeNotSupported($this->getContentType());
        }
    }
    /**
     * A method that is used to process the requested service.
     * 
     * @since 1.1
     */
    public abstract function processRequest();
    /**
     * Reads the content of output stream.
     * 
     * This method is used to read the content of the custom output stream. The 
     * method will only read it if it was set using its path.
     * 
     * @return string|null If the content was taken from the stream, the method 
     * will return it as a string. Other than that, the method will return null.
     * 
     * @since 1.4.7
     */
    public function readOutputStream() {
        $path = $this->getOutputStreamPath();

        if ($path !== null) {
            return file_get_contents($path);
        }
    }
    /**
     * Removes all added web services.
     * 
     * This method will simply re-initialize the arrays that holds all web 
     * services.
     * 
     * @since 1.4.5
     */
    public function removeServices() {
        $this->authActions = [];
        $this->actions = [];
    }
    /**
     * Sends a response message to indicate that request method is not supported.
     * 
     * This method will send back a JSON string in the following format:
     * <p>
     * {<br/>
     * &nbsp;&nbsp;"message":"Method Not Allowed.",<br/>
     * &nbsp;&nbsp;"type":"error",<br/>
     * }
     * </p>
     * 
     * In addition to the message, The response will sent HTTP code 405 - Method Not Allowed.
     * 
     * @since 1.0
     */
    public function requestMethodNotAllowed() {
        $this->sendResponse('Method Not Allowed.', self::E, 405);
    }
    /**
     * Sends Back a data using specific content type and specific response code.
     * 
     * @param string $conentType Response content type (such as 'application/json')
     * 
     * @param mixed $data Any data to send back. Mostly, it will be a string.
     * 
     * @param int $code HTTP response code that will be used to send the data. 
     * Default is HTTP code 200 - Ok.
     */
    public function send($conentType,$data,$code = 200) {
        if ($this->getOutputStream() !== null) {
            fwrite($this->getOutputStream(), $data);
            fclose($this->getOutputStream());
        } else if (class_exists('webfiori\entity\Response')) {
            Response::addHeader('content-type', $conentType);
            Response::append($data);
            Response::setCode($code);
            Response::send();
        } else {
            http_response_code($code);
            header('content-type:'.$conentType);
            echo $data;
        }
    }
    /**
     * Sends back multiple HTTP headers to the client.
     * 
     * @param array $headersArr An associative array. The keys will act as 
     * the headers names and the value of each key will represents the value 
     * of the header.
     * 
     * @since 1.4.3
     */
    public function sendHeaders($headersArr) {
        if (gettype($headersArr) == 'array') {
            foreach ($headersArr as $header => $val) {
                header($header.':'.$val);
            }
        }
    }
    /**
     * Sends a JSON response to the client.
     * 
     * The basic format of the message will be as follows:
     * <p>
     * {<br/>
     * &nbsp;&nbsp;"message":"Action is not set.",<br/>
     * &nbsp;&nbsp;"type":"error"<br/>
     * &nbsp;&nbsp;"http-code":404<br/>
     * &nbsp;&nbsp;"more-info":EXTRA_INFO<br/>
     * }
     * </p>
     * Where EXTRA_INFO can be a simple string or any JSON data.
     * 
     * @param string $message The message to send back.
     * 
     * @param string $type A string that tells the client what is the type of 
     * the message. The developer can specify his own message types such as 
     * 'debug', 'info' or any string. If it is empty string, it will be not 
     * included in response payload.
     * 
     * @param int $code Response code (such as 404 or 200). Default is 200.
     * 
     * @param mixed $otherInfo Any other data to send back it can be a simple 
     * string, an object... . If null is given, the parameter 'more-info' 
     * will be not included in response. Default is empty string. Default is null.
     * 
     * @since 1.0
     */
    public function sendResponse($message,$type = '',$code = 200,$otherInfo = null) {
        $json = new JsonX();
        $json->add('message', $message);
        $typeTrimmed = trim($type);

        if (strlen($typeTrimmed) !== 0) {
            $json->add('type', $typeTrimmed);
        }
        $json->add('http-code', $code);

        if ($otherInfo !== null) {
            $json->add('more-info', $otherInfo);
        }

        if ($this->getOutputStream() !== null) {
            fwrite($this->getOutputStream(), $json);
            fclose($this->getOutputStream());
        } else if (class_exists('webfiori\entity\Response')) {
            Response::addHeader('content-type', 'application/json');
            Response::append($json);
            Response::setCode($code);
            Response::send();
        } else {
            header('content-type:application/json');
            http_response_code($code);
            echo $json;
        }
    }
    /**
     * Sets the description of the web services set.
     * 
     * @param sting $desc Set description. Used to help front-end to identify 
     * the use of the services set.
     * 
     * @since 1.3
     */
    public function setDescription($desc) {
        $this->apiDesc = $desc;
    }
    /**
     * Sets a custom output stream.
     * 
     * This method is useful if the developer would like to test the output of a 
     * web service. Simply set the output stream to a custom one and read the 
     * content of the stream. Note that  if the 
     * resource already exist and has content, it will be erased.
     * 
     * @param resource|string $stream A resource which was opened by 'fopen()'. Also, 
     * it can be a string that points to a file.
     * 
     * @param boolean $new If set to true and the resource does not exist, the 
     * method will attempt to create it.
     * 
     * @since 1.4.7
     */
    public function setOutputStream($stream, $new = false) {
        if (is_resource($stream)) {
            $this->outputStream = $stream;
            $meat = stream_get_meta_data($this->outputStream);
            $this->outputStreamPath = $meat['uri'];
            file_put_contents($this->getOutputStreamPath(),'');

            return true;
        } 

        $trimmed = trim($stream);

        if (strlen($trimmed) > 0) {
            $create = $new === true;

            if (is_file($stream)) {
                return $this->setOutputStreamHelper($trimmed, 'r+');
            } else {
                if ($create) {
                    return $this->setOutputStreamHelper($trimmed, 'w');
                }
            }
        }

        return false;
    }
    /**
     * Sets version number of the set.
     * 
     * @param string $val Version number (such as 1.0.0). Version number 
     * must be provided in the form 'x.x.x' where 'x' is a number between 
     * 0 and 9 inclusive.
     * 
     * @return boolean true if set. false otherwise.
     * 
     * @since 1.0
     */
    public final function setVersion($val) {
        $nums = explode('.', $val);

        if (count($nums) == 3) {
            foreach ($nums as $v) {
                $len = strlen($v);

                for ($x = 0 ; $x < $len ; $x++) {
                    if ($v[$x] < '0' || $v[$x] > '9') {
                        return false;
                    }
                }
            }
            $this->apiVersion = $val;

            return true;
        }

        return false;
    }
    /**
     * Returns JsonX object that represents services set.
     * 
     * @return JsonX An object of type JsonX.
     * 
     * @since 1.0
     */
    public function toJSON() {
        $json = new JsonX();
        $json->add('api-version', $this->getVersion());
        $json->add('description', $this->getDescription());
        $i = $this->getInputs();
        $vNum = isset($i['version']) ? $i['version'] : null;

        if ($vNum === null || $vNum == false) {
            $json->add('actions', $this->getActions());
            $json->add('auth-actions', $this->getAuthActions());
        } else {
            $actionsArr = [];

            foreach ($this->getActions() as $a) {
                if ($a->getSince() == $vNum) {
                    array_push($actionsArr, $a);
                }
            }
            $authActionsArr = [];

            foreach ($this->getAuthActions() as $a) {
                if ($a->getSince() == $vNum) {
                    array_push($authActionsArr, $a);
                }
            }
            $json->add('actions', $actionsArr);
            $json->add('auth-actions', $authActionsArr);
        }

        return $json;
    }
    /**
     * Checks the status of the called service.
     * 
     * This method checks if the following conditions are met:
     * <ul>
     * <li>The parameter "action" or "service-name" is set in request body.</li>
     * <li>The service is supported by the set.</li>
     * <li>Request method of the service is correct.</li>
     * </ul>
     * If one of the conditions is not met, the method will return false and 
     * send back a response to indicate the issue.
     * 
     * @return boolean true if API action is valid.
     * 
     * @since 1.0
     */
    private final function _checkAction() {
        $action = $this->getCalledServiceName();
        //first, check if action is set and not null
        if ($action != null) {
            //after that, check if action is supported by the API.
            if ($this->isActionSupported()) {
                $isValidRequestMethod = false;

                foreach ($this->getAuthActions() as $val) {
                    if ($val->getName() == $action) {
                        $reqMethods = $val->getRequestMethods();

                        foreach ($reqMethods as $method) {
                            if ($method == $this->getRequestMethod()) {
                                $isValidRequestMethod = true;
                            }
                        }

                        if (!$isValidRequestMethod) {
                            $this->requestMethodNotAllowed();
                        }

                        return $isValidRequestMethod;
                    }
                }

                foreach ($this->getActions() as $val) {
                    if ($val->getName() == $action) {
                        $reqMethods = $val->getRequestMethods();

                        foreach ($reqMethods as $method) {
                            if ($method == $this->getRequestMethod()) {
                                $isValidRequestMethod = true;
                            }
                        }

                        if (!$isValidRequestMethod) {
                            $this->requestMethodNotAllowed();
                        }

                        return $isValidRequestMethod;
                    }
                }
            } else {
                $this->actionNotSupported();
            }
        } else {
            $this->missingAPIAction();
        }

        return false;
    }
    /**
     * Checks if a client is authorized to call the service using the given 
     * service name in request body.
     * 
     * @return boolean The method will return true if the client is allowed 
     * to call the service using the name in request body.
     * 
     * @since 1.3.1
     */
    private function _isAuthorizedAction() {
        $action = $this->getCalledServiceName();

        foreach ($this->getAuthActions() as $val) {
            if ($val->getName() == $action) {
                return $this->isAuthorized();
            }
        }

        return true;
    }
    private function setOutputStreamHelper($trimmed, $mode) {
        $tempStream = fopen($trimmed, $mode);

        if (is_resource($tempStream)) {
            $this->outputStream = $tempStream;
            $this->outputStreamPath = $trimmed;
            file_put_contents($this->getOutputStreamPath(),'');

            return true;
        }

        return false;
    }
}
