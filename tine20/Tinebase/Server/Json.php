<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  Server
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2007-2013 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * 
 */

/**
 * JSON Server class with handle() function
 * 
 * @package     Tinebase
 * @subpackage  Server
 */
class Tinebase_Server_Json extends Tinebase_Server_Abstract implements Tinebase_Server_Interface
{
    /**
     * handled request methods
     * 
     * @var array
     */
    protected $_methods = array();
    
    /**
     * 
     * @var boolean
     */
    protected $_supportsSessions = true;
    
    /**
     * (non-PHPdoc)
     * @see Tinebase_Server_Interface::handle()
     */
    public function handle(\Zend\Http\Request $request = null, $body = null)
    {
        $this->_request = $request instanceof \Zend\Http\Request ? $request : Tinebase_Core::get(Tinebase_Core::REQUEST);
        $this->_body    = $body !== null ? $body : fopen('php://input', 'r');
        
        $request = $request instanceof \Zend\Http\Request ? $request : new \Zend\Http\PhpEnvironment\Request();
        
        // handle CORS requests
        if ($request->getHeaders()->has('ORIGIN') && !$request->getHeaders()->has('X-FORWARDED-HOST')) {
            /**
             * First the client sends a preflight request
             * 
             * METHOD: OPTIONS
             * Access-Control-Request-Headers:x-requested-with, content-type
             * Access-Control-Request-Method:POST
             * Origin:http://other.site
             * Referer:http://other.site/example.html
             * User-Agent:Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/38.0.2125.111 Safari/537.36
             * 
             * We have to respond with
             * 
             * Access-Control-Allow-Credentials:true
             * Access-Control-Allow-Headers:x-requested-with, x-tine20-request-type, content-type, x-tine20-jsonkey
             * Access-Control-Allow-Methods:POST
             * Access-Control-Allow-Origin:http://other.site
             * 
             * Then the client sends the standard JSON request with two additional headers
             * 
             * METHOD: POST
             * Origin:http://other.site
             * Referer:http://other.site/example.html
             * Standard-JSON-Rquest-Headers...
             * 
             * We have to add two additional headers to our standard response
             * 
             * Access-Control-Allow-Credentials:true
             * Access-Control-Allow-Origin:http://other.site
             */
            $origin = $request->getHeaders('ORIGIN')->getFieldValue();
            $uri    = \Zend\Uri\UriFactory::factory($origin);
            
            if (in_array($uri->getScheme(), array('http', 'https'))) {
                $allowedOrigins = array_merge(
                    (array) Tinebase_Core::getConfig()->get(Tinebase_Config::ALLOWEDJSONORIGINS, array()),
                    array($this->_request->getServer('SERVER_NAME'))
                );
                
                if (in_array($uri->getHost(), $allowedOrigins)) {
                    // this headers have to be sent, for any CORS'ed JSON request
                    header('Access-Control-Allow-Origin: ' . $origin);
                    header('Access-Control-Allow-Credentials: true');
                }
                
                // check for CORS preflight request
                if ($request->getMethod() == \Zend\Http\Request::METHOD_OPTIONS &&
                    $request->getHeaders()->has('ACCESS-CONTROL-REQUEST-METHOD')
                ) {
                    $this->_methods = array('handleCors');
                    
                    if (in_array($uri->getHost(), $allowedOrigins)) {
                        header('Access-Control-Allow-Methods: POST');
                        header('Access-Control-Allow-Headers: x-requested-with, x-tine20-request-type, content-type, x-tine20-jsonkey');
                        header('Access-Control-Max-Age: 3600'); // cache result of OPTIONS request for 1 hour
                        
                    } else {
                        Tinebase_Core::getLogger()->WARN (__METHOD__ . '::' . __LINE__ . " unhandled CORS preflight request from $origin");
                        Tinebase_Core::getLogger()->INFO (__METHOD__ . '::' . __LINE__ . " you may want to set \"'allowedJsonOrigins' => array('{$uri->getHost()}'),\" to config.inc.php");
                        Tinebase_Core::getLogger()->DEBUG(__METHOD__ . '::' . __LINE__ . " allowed origins: " . print_r($allowedOrigins, TRUE));
                    }
                    
                    // stop further processing => is OPTIONS request
                    return;
                }
            }
        }
        
        $exception = false;
        
        if (Tinebase_Session::sessionExists()) {
            try {
                Tinebase_Core::startCoreSession();
            } catch (Zend_Session_Exception $zse) {
                $exception = new Tinebase_Exception_AccessDenied('Not Authorised', 401);
                
                // expire session cookie for client
                Tinebase_Session::expireSessionCookie();
            }
        }
        
        if ($exception === false) {
            try {
                Tinebase_Core::initFramework();
            } catch (Exception $exception) {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(
                    __METHOD__ . '::' . __LINE__ .' initFramework exception: ' . $exception);
            }
        }
        
        $json = file_get_contents('php://input');
        $json = Tinebase_Core::filterInputForDatabase($json);
        
        if (substr($json, 0, 1) == '[') {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' batched request');
            $isBatchedRequest = true;
            $requests = Zend_Json::decode($json);
        } else {
            $isBatchedRequest = false;
            $requests = array(Zend_Json::decode($json));
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) {
            $_requests = $requests;
            foreach (array('password', 'oldPassword', 'newPassword') as $field) {
                if (isset($requests[0]["params"][$field])) {
                    $_requests[0]["params"][$field] = "*******";
                }
            }
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .' is JSON request. rawdata: ' . print_r($_requests, true));
        } 
        
        $response = array();
        foreach ($requests as $requestOptions) {
            if ($requestOptions !== NULL) {
                $request = new Zend_Json_Server_Request();
                $request->setOptions($requestOptions);
                
                $response[] = $exception ?
                   $this->_handleException($request, $exception) :
                   $this->_handle($request);
            } else {
                if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' Got empty request options: skip request.');
                $response[] = NULL;
            }
        }
        
        header('Content-type: application/json');
        echo $isBatchedRequest ? '['. implode(',', $response) .']' : $response[0];
    }
    
    /**
     * get JSON from cache or new instance
     * 
     * @param array $classes for Zend_Cache_Frontend_File
     * @return Zend_Json_Server
     */
    protected static function _getServer($classes = null)
    {
        // setup cache if available
        if (is_array($classes) && Tinebase_Core::getCache()) {
            $masterFiles = array();
            
            $dirname = dirname(__FILE__) . '/../../';
            foreach ($classes as $class => $namespace) {
                $masterFiles[] = $dirname . str_replace('_', '/', $class) . '.php';
            }
            
            try {
                $cache = new Zend_Cache_Frontend_File(array(
                    'master_files'              => $masterFiles,
                    'lifetime'                  => null,
                    'automatic_serialization'   => true, // turn that off for more speed
                    'automatic_cleaning_factor' => 0,    // no garbage collection as this is done by a scheduler task
                    'write_control'             => false, // don't read cache entry after it got written
                    'logging'                   => (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)),
                    'logger'                    => Tinebase_Core::getLogger(),
                ));
                $cache->setBackend(Tinebase_Core::getCache()->getBackend());
                $cacheId = '_handle_' . sha1(Zend_Json_Encoder::encode($classes));
                
                $server = $cache->load($cacheId);
                if ($server instanceof Zend_Json_Server) {
                    return $server;
                }
                
            } catch (Zend_Cache_Exception $zce) {
                if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__
                    . " Failed to create cache. Exception: \n". $zce);
            }
        }
        
        $server = new Zend_Json_Server();
        $server->setAutoEmitResponse(false);
        $server->setAutoHandleExceptions(false);
        
        if (is_array($classes)) {
            foreach ($classes as $class => $namespace) {
                try {
                    $server->setClass($class, $namespace);
                } catch (Exception $e) {
                    if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                        . " Failed to add JSON API for '$class' => '$namespace' Exception: \n". $e);
                }
            }
        }
        
        if (isset($cache)) {
            $cache->save($server, $cacheId, array(), null);
        }
        
        return $server;
    }
    
    /**
     * handler for JSON api requests
     * @todo session expire handling
     * 
     * @param $request
     * @return JSON
     */
    protected function _handle($request)
    {
        try {
            $method = $request->getMethod();
            Tinebase_Core::getLogger()->INFO(__METHOD__ . '::' . __LINE__ .' is JSON request. method: ' . $method);
            
            $jsonKey = (isset($_SERVER['HTTP_X_TINE20_JSONKEY'])) ? $_SERVER['HTTP_X_TINE20_JSONKEY'] : '';
            $this->_checkJsonKey($method, $jsonKey);
            
            if (empty($method)) {
                // SMD request
                return self::getServiceMap();
            }
            
            $this->_methods[] = $method;
            
            $classes = array();
            
            // add json apis which require no auth
            $classes['Tinebase_Frontend_Json'] = 'Tinebase';
            
            // register additional Json apis only available for authorised users
            if (Tinebase_Session::isStarted() && Zend_Auth::getInstance()->hasIdentity()) {
                
                $applicationParts = explode('.', $method);
                $applicationName = ucfirst($applicationParts[0]);
                
                switch($applicationName) {
                    // additional Tinebase json apis
                    case 'Tinebase_Container':
                        $classes['Tinebase_Frontend_Json_Container'] = 'Tinebase_Container';
                        break;
                    case 'Tinebase_PersistentFilter':
                        $classes['Tinebase_Frontend_Json_PersistentFilter'] = 'Tinebase_PersistentFilter';
                        break;
                        
                    default;
                        if(Tinebase_Core::getUser() && Tinebase_Core::getUser()->hasRight($applicationName, Tinebase_Acl_Rights_Abstract::RUN)) {
                            $classes[$applicationName.'_Frontend_Json'] = $applicationName;
                        }
                        break;
                }
            }
            
            $server = self::_getServer($classes);
            
            $response = $server->handle($request);
            if ($response->isError()) {
                Tinebase_Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' Got response error: '
                    . print_r($response->getError()->toArray(), true));
            }
            return $response;
            
        } catch (Exception $exception) {
            return $this->_handleException($request, $exception);
        }
    }
    
    /**
     * handle exceptions
     * 
     * @param Zend_Json_Server_Request_Http $request
     * @param Exception $exception
     * @return Zend_Json_Server_Response
     */
    protected function _handleException($request, $exception)
    {
        $server = self::_getServer();
        
        $exceptionData = method_exists($exception, 'toArray')? $exception->toArray() : array();
        $exceptionData['message'] = htmlentities($exception->getMessage(), ENT_COMPAT, 'UTF-8');
        $exceptionData['code']    = $exception->getCode();
        
        if ($exception instanceof Tinebase_Exception) {
            $exceptionData['appName'] = $exception->getAppName();
            $exceptionData['title'] = $exception->getTitle();
        }
        
        Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' ' . get_class($exception) . ' -> ' . $exception->getMessage());
        
        $suppressTrace = Tinebase_Core::getConfig()->suppressExceptionTraces;
        if ($suppressTrace !== TRUE) {
            $exceptionData['trace'] = Tinebase_Exception::getTraceAsArray($exception);
        }
        
        Tinebase_Exception::log($exception, $suppressTrace);
        
        $server->fault($exceptionData['message'], $exceptionData['code'], $exceptionData);
        
        $response = $server->getResponse();
        if (null !== ($id = $request->getId())) {
            $response->setId($id);
        }
        if (null !== ($version = $request->getVersion())) {
            $response->setVersion($version);
        }
    
        return $response;
    }
    
    /**
     * return service map
     * 
     * @return Zend_Json_Server_Smd
     */
    public static function getServiceMap()
    {
        $classes = array();
        
        $classes['Tinebase_Frontend_Json'] = 'Tinebase';
        
        if (Tinebase_Core::isRegistered(Tinebase_Core::USER)) {
            $classes['Tinebase_Frontend_Json_Container'] = 'Tinebase_Container';
            $classes['Tinebase_Frontend_Json_PersistentFilter'] = 'Tinebase_PersistentFilter';
            
            $userApplications = Tinebase_Core::getUser()->getApplications(TRUE);
            foreach($userApplications as $application) {
                $jsonAppName = $application->name . '_Frontend_Json';
                $classes[$jsonAppName] = $application->name;
            }
        }
        
        $server = self::_getServer($classes);
        
        $server->setTarget('index.php')
               ->setEnvelope(Zend_Json_Server_Smd::ENV_JSONRPC_2);
            
        $smd = $server->getServiceMap();

        if (Tinebase_Core::isRegistered(Tinebase_Core::USER)) {
            // TODO make it work
            //self::_addModelConfigServices($smd);
        }

        return $smd;
    }

    /**
     * add default modelconfig functions to service map
     *
     * @param Zend_Json_Server_Smd $smd
     */
    protected static function _addModelConfigServices(Zend_Json_Server_Smd $smd)
    {
        $userApplications = Tinebase_Core::getUser()->getApplications(/* $_anyRight */ TRUE);

        $cache = Tinebase_Core::getCache();
        $cacheId = '_addModelConfigServices' . sha1(Zend_Json_Encoder::encode($userApplications->getArrayOfIds()));
        $services = $cache->load($cacheId);

        if (! $services) {
            $services = array();
            foreach ($userApplications as $application) {
                try {
                    $controller = Tinebase_Core::getApplicationInstance($application->name);
                    $models = $controller->getModels();
                    if (!$models) {
                        continue;
                    }
                } catch (Tinebase_Exception_NotFound $tenf) {
                    continue;
                }

                foreach ($models as $model) {
                    $config = function_exists($model . '::getConfiguration') ? $model::getConfiguration() : null;
                    if ($config && $config->exposeJsonApi) {
                        $commonServiceOptions = array(
                            'envelope' => 'JSON-RPC-2.0',
                            'transport' => 'POST',
                            'return' => 'array'
                        );

                        // TODO replace this with generic method
                        $simpleModelName = str_replace($application->name . '_Model_', '', $model);

                        $services[] = array_merge($commonServiceOptions, array(
                            'name' => $application->name . '.get' . $simpleModelName,
                            'params' =>
                                array(array(
                                    'type' => 'string',
                                    'name' => 'id',
                                    'optional' => false,
                                )),
                        ));
                        $services[] = array_merge($commonServiceOptions, array(
                            'name' => $application->name . '.save' . $simpleModelName,
                            'params' =>
                                array(array(
                                    'type' => 'any',
                                    'name' => 'recordData',
                                    'optional' => false,
                                )),
                        ));
                        $services[] = array_merge($commonServiceOptions, array(
                            'name' => $application->name . '.delete' . $simpleModelName . 's',
                            'params' =>
                                array(array(
                                    'type' => 'array',
                                    'name' => 'ids',
                                    'optional' => false,
                                )),
                            'return' => 'string'
                        ));
                        $services[] = array_merge($commonServiceOptions, array(
                            'name' => $application->name . '.search' . $simpleModelName . 's',
                            'params' =>
                                array(array(
                                    'type' => 'array',
                                    'name' => 'filter',
                                    'optional' => false,
                                ), array(
                                    'type' => 'array',
                                    'name' => 'paging',
                                    'optional' => false,
                                )),
                        ));
                    }
                }
            }

            $cache->save($services, $cacheId);
        }

        foreach ($services as $service) {
            $smd->addService($service);
        }
    }
    
    /**
     * check json key
     *
     * @param string $method
     * @param string $jsonKey
     */
    protected function _checkJsonKey($method, $jsonKey)
    {
        $anonymnousMethods = array(
            '', //empty method
            'Tinebase.getRegistryData',
            'Tinebase.getAllRegistryData',
            'Tinebase.authenticate',
            'Tinebase.login',
            'Tinebase.getAvailableTranslations',
            'Tinebase.getTranslations',
            'Tinebase.setLocale'
        );
        
        // check json key for all methods but some exceptions
        if ( !(in_array($method, $anonymnousMethods)) && $jsonKey !== Tinebase_Core::get('jsonKey')) {
        
            if (! Tinebase_Core::isRegistered(Tinebase_Core::USER)) {
                Tinebase_Core::getLogger()->INFO(__METHOD__ . '::' . __LINE__ .
                    ' Attempt to request a privileged Json-API method (' . $method . ') without authorisation from "' .
                    $_SERVER['REMOTE_ADDR'] . '". (session timeout?)');
            } else {
                Tinebase_Core::getLogger()->WARN(__METHOD__ . '::' . __LINE__ . ' Fatal: got wrong json key! (' . $jsonKey . ') Possible CSRF attempt!' .
                    ' affected account: ' . print_r(Tinebase_Core::getUser()->toArray(), true) .
                    ' request: ' . print_r($_REQUEST, true)
                );
            }
            
            throw new Tinebase_Exception_AccessDenied('Not Authorised', 401);
        }
    }
    
    /**
    * returns request method
    *
    * @return string|NULL
    */
    public function getRequestMethod()
    {
        return (! empty($this->_methods)) ? implode('|', $this->_methods) : NULL;
    }
}
