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
 * php helpers
 */
require_once 'Helper.php';

/**
 * dispatcher and initialisation class (functions are static)
 * - dispatchRequest() function
 * - initXYZ() functions
 * - has registry and config
 *
 * @package     Tinebase
 * @subpackage  Server
 */
class Tinebase_Core
{
    /**************** registry indexes *************************/

    /**
     * constant for config registry index
     */
    const CONFIG = 'configFile';

    /**
     * constant for locale registry index
     */
    const LOCALE = 'locale';

    /**
     * constant for logger registry index
     */
    const LOGGER = 'logger';
    
    /**
     * constant for loglevel registry index
     *
     */
    const LOGLEVEL = 'loglevel';

    /**
     * constant for cache registry index
     */
    const CACHE = 'cache';
    
     /**
     * constant for shared cache registry index
     */
    const SHAREDCACHE = 'sharedCache';

    /**
     * constant for session namespace (tinebase) registry index
     */
    const SESSION = 'session';
    
    /**
     */
    const SESSIONID = 'sessionId';

    /**
     * constant for application start time in ms registry index
     */
    const STARTTIME = 'starttime';

    /**
     * constant for current account/user
     */
    const USER = 'currentAccount';

    /**
     * const for current users credentialcache
     */
    const USERCREDENTIALCACHE = 'usercredentialcache';

    /**
     * constant for database adapter
     */
    const DB = 'dbAdapter';
    
    /**
     * constant for database adapter name
     * 
     */
    const DBNAME = 'dbAdapterName';

    /**
     * constant for database adapter
     */
    const USERTIMEZONE = 'userTimeZone';

    /**
     * constant for preferences registry
     */
    const PREFERENCES = 'preferences';
    
    /**
     * constant for preferences registry
     */
    const SCHEDULER = 'scheduler';
    
    /**
     * constant temp dir registry
     */
    const TMPDIR = 'tmpdir';
    
    /**
     * constant temp dir registry
     */
    const FILESDIR = 'filesdir';
    
    /**
     * constant for request method registry
     */
    const METHOD = 'method';
    
    /**
     * const PDO_MYSQL
     *
     */
    const PDO_MYSQL = 'Pdo_Mysql';
    
    /**
     * minimal version of MySQL supported
     */
    const MYSQL_MINIMAL_VERSION = '5.0.0';
    
    /**
     * const PDO_PGSQL
     *
     */
    const PDO_PGSQL = 'Pdo_Pgsql';
    
    /**
     * minimal version of PostgreSQL supported
     */
    const PGSQL_MINIMAL_VERSION = '8.4.8';

    /**
     * const PDO_OCI
     *
     */
    const PDO_OCI = 'Pdo_Oci';
    
    /**
     * const ORACLE
     * Zend_Db adapter name for the oci8 driver.
     *
     */
    const ORACLE = 'Oracle';
    
    /**
     * minimal version of Oracle supported
     */
    const ORACLE_MINIMAL_VERSION = '9.0.0';
    
    /**
     * Application Instance Cache
     * @var array
     */
    protected static $appInstanceCache = array();
    
    /**
     * current cache status, maybe NULL / uninitialized, true / enabled, false / disabled
     * @var boolean
     */
    protected static $cacheStatus = NULL;
    
    /******************************* DISPATCH *********************************/
    
    /**
     * dispatch request
     */
    public static function dispatchRequest()
    {
        // check transaction header
        if (isset($_SERVER['HTTP_X_TINE20_TRANSACTIONID'])) {
            Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . " Client transaction {$_SERVER['HTTP_X_TINE20_TRANSACTIONID']}");
            Tinebase_Log_Formatter::setPrefix(substr($_SERVER['HTTP_X_TINE20_TRANSACTIONID'], 0, 5));
        }
        
        $server = NULL;
        
        /**************************** JSON API *****************************/
        if ((isset($_SERVER['HTTP_X_TINE20_REQUEST_TYPE']) && $_SERVER['HTTP_X_TINE20_REQUEST_TYPE'] == 'JSON')  ||
            (isset($_SERVER['CONTENT_TYPE']) && substr($_SERVER['CONTENT_TYPE'],0,16) == 'application/json')  ||
            (isset($_POST['requestType']) && $_POST['requestType'] == 'JSON') ||
            (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        ) {
            $server = new Tinebase_Server_Json();
            
        /**************************** SNOM API *****************************/
        } elseif(
            isset($_SERVER['HTTP_USER_AGENT']) &&
            preg_match('/^Mozilla\/4\.0 \(compatible; (snom...)\-SIP (\d+\.\d+\.\d+)/i', $_SERVER['HTTP_USER_AGENT'])
        ) {
            $server = new Voipmanager_Server_Snom();
            
        /**************************** ASTERISK API *****************************/
        } elseif(isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT'] == 'asterisk-libcurl-agent/1.0') {
            $server = new Voipmanager_Server_Asterisk();
            
        /**************************** ActiveSync API ****************************
         * RewriteRule ^Microsoft-Server-ActiveSync index.php?frontend=activesync [E=REMOTE_USER:%{HTTP:Authorization},L,QSA]
         */
        } elseif((isset($_SERVER['REDIRECT_ACTIVESYNC']) && $_SERVER['REDIRECT_ACTIVESYNC'] == 'true') ||
                 (isset($_GET['frontend']) && $_GET['frontend'] == 'activesync')) {
            $server = new ActiveSync_Server_Http();
            self::set('serverclassname', get_class($server));

        /**************************** WebDAV / CardDAV / CalDAV API **********************************
         * RewriteCond %{REQUEST_METHOD} !^(GET|POST)$
         * RewriteRule ^/$            /index.php?frontend=webdav [E=REMOTE_USER:%{HTTP:Authorization},L,QSA]
         *
         * RewriteRule ^/addressbooks /index.php?frontend=webdav [E=REMOTE_USER:%{HTTP:Authorization},L,QSA]
         * RewriteRule ^/calendars    /index.php?frontend=webdav [E=REMOTE_USER:%{HTTP:Authorization},L,QSA]
         * RewriteRule ^/principals   /index.php?frontend=webdav [E=REMOTE_USER:%{HTTP:Authorization},L,QSA]
         * RewriteRule ^/webdav       /index.php?frontend=webdav [E=REMOTE_USER:%{HTTP:Authorization},L,QSA]
         */
        } elseif(isset($_GET['frontend']) && $_GET['frontend'] == 'webdav') {
            $server = new Tinebase_Server_WebDAV();
            
        /**************************** CLI API *****************************/
        } elseif (php_sapi_name() == 'cli') {
            $server = new Tinebase_Server_Cli();
            
        /**************************** HTTP API ****************************/
        } else {
            
            /**************************** OpenID ****************************
             * RewriteRule ^/users/(.*)                      /index.php?frontend=openid&username=$1 [L,QSA]
             */
            if (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/xrds+xml') !== FALSE) {
                $_REQUEST['method'] = 'Tinebase.getXRDS';
            } elseif ((isset($_SERVER['REDIRECT_USERINFOPAGE']) && $_SERVER['REDIRECT_USERINFOPAGE'] == 'true') ||
                      (isset($_REQUEST['frontend']) && $_REQUEST['frontend'] == 'openid')) {
                $_REQUEST['method'] = 'Tinebase.userInfoPage';
            }
            
            if (!isset($_REQUEST['method']) && (isset($_REQUEST['openid_action']) || isset($_REQUEST['openid_assoc_handle'])) ) {
                $_REQUEST['method'] = 'Tinebase.openId';
            }
            
            $server = new Tinebase_Server_Http();
        }
        
        $server->handle();
        $method = get_class($server) . '::' . $server->getRequestMethod();
        self::set(self::METHOD, $method);
        
        self::finishProfiling();
        self::getDbProfiling();
    }
    
    /**
     * returns TRUE if request is HTTPS
     * 
     * @return boolean
     */
    public static function isHttpsRequest()
    {
        return (! empty($_SERVER['HTTPS']) && strtoupper($_SERVER['HTTPS']) != 'OFF');
    }
    
    /**
     * enable profiling
     * - supports xhprof
     */
    public static function enableProfiling()
    {
        if (! self::getConfig() || ! self::getConfig()->profiler) {
            return;
        }
        
        $config = self::getConfig()->profiler;
        
        if ($config && $config->xhprof) {
            $XHPROF_ROOT = $config->path ? $config->path : '/usr/share/php5-xhprof';
            if (file_exists($XHPROF_ROOT . "/xhprof_lib/utils/xhprof_lib.php")) {
                define('XHPROF_LIB_ROOT', $XHPROF_ROOT . '/xhprof_lib');
                Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Enabling xhprof');
                xhprof_enable(XHPROF_FLAGS_MEMORY);
            } else {
                Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Could not find xhprof lib root');
            }
        } 
    }

    /**
     * finish profiling / save profiling data to a file
     * - supports xhprof
     */
    public static function finishProfiling()
    {
        if (! self::getConfig() || ! self::getConfig()->profiler) {
            return;
        }
        
        $config = self::getConfig()->profiler;
        $method = self::get(self::METHOD);
    
        if ($config->xhprof) {
            $xhprof_data = xhprof_disable();
            
            if ($config->method) {
                Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Filtering xhprof profiling method: ' . $config->method);
                if (! preg_match($config->method, $method)) {
                    Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Method mismatch, do not save profiling info.');
                    return;
                }
            }
            
            Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Saving xhprof profiling run for method ' . $method);
            
            if (! defined('XHPROF_LIB_ROOT')) {
                Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . '  ' . print_r($xhprof_data, TRUE));
            } else {
                include_once XHPROF_LIB_ROOT . "/utils/xhprof_lib.php";
                include_once XHPROF_LIB_ROOT . "/utils/xhprof_runs.php";
                $xhprof_runs = new XHProfRuns_Default();
                $run_id = $xhprof_runs->save_run($xhprof_data, "tine");
            }
        }
    }
    
    /******************************* APPLICATION ************************************/

    /**
     * returns an instance of the controller of an application
     *
     * @param   string $_applicationName appname / modelname
     * @param   string $_modelName
     * @return  Tinebase_Controller_Abstract|Tinebase_Controller_Record_Abstract the controller of the application
     * @throws  Tinebase_Exception_NotFound
     */
    public static function getApplicationInstance($_applicationName, $_modelName = '', $_ignoreACL = FALSE)
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
            . ' Params: application: ' . $_applicationName . ' / model: ' . $_modelName);
        
        $cacheKey = $_applicationName . '_' . $_modelName . '_' . ($_ignoreACL?1:0);
        if (isset(self::$appInstanceCache[$cacheKey])) {
            return self::$appInstanceCache[$cacheKey];
        }
        
        // modified (some model names can have both . and _ in their names and we should treat them as JS model name
        if (strpos($_applicationName, '_') && ! strpos($_applicationName, '.')) {
            // got (complete) model name name as first param
            list($appName, $i, $modelName) = explode('_', $_applicationName, 3);
        } else if (strpos($_applicationName, '.')) {
            // got (complete) model name name as first param (JS style)
            list($j, $appName, $i, $modelName) = explode('.', $_applicationName, 4);
        } else {
            $appName = $_applicationName;
            $modelName = $_modelName;
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
            . ' Extracted appName: ' . $appName . ' modelName: ' . $modelName);
        
        $controllerName = ucfirst((string) $appName);
        if ($appName !== 'Tinebase' || ($appName === 'Tinebase' && ! $modelName)) {
            // only app controllers are called "App_Controller_Model"
            $controllerName .= '_Controller';
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
            . ' controllerName: ' . $controllerName);

        if (! empty($modelName)) {
            if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
                . ' Checking for model controller ...');
            
            $modelName = preg_replace('/^' . $appName . '_' . 'Model_/', '', $modelName);
            $controllerNameModel = $controllerName . '_' . $modelName;
            if (! class_exists($controllerNameModel)) {
                throw new Tinebase_Exception_NotFound('No Application Controller found (checked class ' . $controllerNameModel . ')!');
                // check for generic app controller
                if (! class_exists($controllerName)) {
                    throw new Tinebase_Exception_NotFound('No Controller found (checked classes '. $controllerName . ' and ' . $controllerNameModel . ')!');
                }
            } else {
                $controllerName = $controllerNameModel;
            }
        } else if (! class_exists($controllerName)) {
            throw new Tinebase_Exception_NotFound('No Application Controller found (checked class ' . $controllerName . ')!');
        }
        
        if (! $_ignoreACL && is_object(Tinebase_Core::getUser()) && ! Tinebase_Core::getUser()->hasRight($appName, Tinebase_Acl_Rights_Abstract::RUN)) {
            if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ 
                . ' User ' . Tinebase_Core::getUser()->accountDisplayName . '/' . Tinebase_Core::getUser()->getId() . ' has no right to access ' . $appName);
            throw new Tinebase_Exception_AccessDenied('No right to access application ' . $appName);
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
            . ' Getting instance of ' . $controllerName);
        
        $controller = call_user_func(array($controllerName, 'getInstance'));
        self::$appInstanceCache[$cacheKey] = $controller;
        
        return $controller;
    }
    
    /******************************* SETUP ************************************/

    /**
     * init tine framework
     */
    public static function initFramework()
    {
        Tinebase_Core::setupTempDir();
        
        Tinebase_Core::setupStreamWrapper();
        
        //Cache must be setup before User Locale because otherwise Zend_Locale tries to setup 
        //its own cache handler which might result in a open_basedir restriction depending on the php.ini settings
        Tinebase_Core::setupCache();
        
        Tinebase_Core::setupBuildConstants();
        
        Tinebase_Session::setupSession();
        
        if (Tinebase_Session::sessionExists()) {
            Tinebase_Core::startCoreSession();
        }
        
        // setup a temporary user locale/timezone. This will be overwritten later but we 
        // need to handle exceptions during initialisation process such as session timeout
        // @todo add fallback locale to config file
        Tinebase_Core::set('locale', new Zend_Locale('en_US'));
        Tinebase_Core::set('userTimeZone', 'UTC');
        
        Tinebase_Core::setupUserCredentialCache();
        
        Tinebase_Core::setupUserTimezone();
        
        Tinebase_Core::setupUserLocale();
        
        Tinebase_Core::enableProfiling();
        
        if (PHP_SAPI !== 'cli') {
            header('X-API: http://www.tine20.org/apidocs/tine20/');
            if (isset($_SERVER['HTTP_X_TRANSACTIONID'])) {
                header('X-TransactionID: ' . substr($_SERVER['HTTP_X_TRANSACTIONID'], 1, -1) . ';' . $_SERVER['SERVER_NAME'] . ';16.4.5009.816;' . date('Y-m-d H:i:s') . ' UTC;265.1558 ms');
            }
        }
    }
    
    /**
     * start session helper function
     *
     * @param array $_options
     * @throws Exception
     */
    public static function startCoreSession ($namespace = null)
    {
        try {
            $coreSession = Tinebase_Session::getSessionNamespace();
            $userSession = Tinebase_User_Session::getSessionNamespace();
        } catch (Exception $e) {
            throw $e;
        }
        
        if (isset($userSession->currentAccount)) {
            self::set(self::USER, $userSession->currentAccount);
        }
        
        if (! isset($coreSession->jsonKey)) {
            $coreSession->jsonKey = Tinebase_Record_Abstract::generateUID();
        }
        
        Tinebase_Core::set('jsonKey', $coreSession->jsonKey);
        Tinebase_Core::setDbCapabilitiesInSession($coreSession);
    }
    
    /**
     * initializes the build constants like buildtype, package information, ...
     */
    public static function setupBuildConstants()
    {
        $config = self::getConfig();
        define('TINE20_BUILDTYPE',     strtoupper($config->get('buildtype', 'DEVELOPMENT')));
        define('TINE20_CODENAME',      getDevelopmentRevision());
        define('TINE20_PACKAGESTRING', 'none');
        define('TINE20_RELEASETIME',   'none');
    }
    
    /**
     * tines error exception handler for catchable fatal errors
     *
     * NOTE: PHP < 5.3 don't throws exceptions for Catchable fatal errors per default,
     * so we convert them into exceptions manually
     *
     * @param integer $severity
     * @param string $errstr
     * @param string $errfile
     * @param integer $errline
     * @throws ErrorException
     */
    public static function errorHandler($severity, $errstr, $errfile, $errline)
    {
        if (error_reporting() == 0) {
            return;
        }
        
        $logLine = " $errstr in {$errfile}::{$errline} ($severity)";
        $e = new Exception('just to get trace');
        $trace = $e->getTraceAsString();
        
        switch ($severity) {
            case E_COMPILE_ERROR:
            case E_CORE_ERROR:
            case E_ERROR:
            case E_PARSE:
            case E_RECOVERABLE_ERROR:
            case E_USER_ERROR:
                throw new ErrorException($errstr, 0, $severity, $errfile, $errline);
                break;
                
            case E_COMPILE_WARNING:
            case E_CORE_WARNING:
            case E_USER_WARNING:
            case E_WARNING:
                if (Tinebase_Core::isRegistered(Tinebase_Core::LOGGER)) {
                    Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . $logLine);
                    Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' ' . $trace);
                } else {
                    error_log(__METHOD__ . '::' . __LINE__ . $logLine);
                    error_log(__METHOD__ . '::' . __LINE__ . ' ' . $trace);
                }
                break;
                
            case E_NOTICE:
            case E_STRICT:
            case E_USER_NOTICE:
            default:
                if (Tinebase_Core::isRegistered(Tinebase_Core::LOGGER)) {
                    Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . $logLine);
                    Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' ' . $trace);
                } else {
                    error_log(__METHOD__ . '::' . __LINE__ . $logLine);
                    error_log(__METHOD__ . '::' . __LINE__ . ' ' . $trace);
                }
                break;
        }
    }

    /**
     * initializes the config
     */
    public static function setupConfig()
    {
        self::set(self::CONFIG, Tinebase_Config::getInstance());
    }

    /**
     * setup temp dir registry setting retrieved by {@see _getTempDir()}
     *
     * @return void
     */
    public static function setupTempDir()
    {
        self::set(self::TMPDIR, self::guessTempDir());
    }

    /**
     * figure out temp directory:
     * config.inc.php > sys_get_temp_dir > session_save_path > /tmp
     *
     * @return String
     */
    public static function guessTempDir()
    {
        $config = self::getConfig();
        
        $tmpdir = $config->tmpdir;
        if ($tmpdir == Tinebase_Model_Config::NOTSET || !@is_writable($tmpdir)) {
            $tmpdir = sys_get_temp_dir();
            if (empty($tmpdir) || !@is_writable($tmpdir)) {
                $tmpdir = session_save_path();
                if (empty($tmpdir) || !@is_writable($tmpdir)) {
                    $tmpdir = '/tmp';
                }
            }
        }
        
        return $tmpdir;
    }
    
    /**
     * initializes the logger
     *
     * @param $_defaultWriter Zend_Log_Writer_Abstract default log writer
     */
    public static function setupLogger(Zend_Log_Writer_Abstract $_defaultWriter = NULL)
    {
        $config = self::getConfig();
        $logger = new Tinebase_Log();
        
        if (isset($config->logger) && $config->logger->active) {
            try {
                $logger->addWriterByConfig($config->logger);
                if ($config->logger->additionalWriters) {
                    foreach ($config->logger->additionalWriters as $writerConfig) {
                        $logger->addWriterByConfig($writerConfig);
                    }
                }
            } catch (Exception $e) {
                error_log("Tine 2.0 can't setup the configured logger! The Server responded: $e");
                $writer = ($_defaultWriter === NULL) ? new Zend_Log_Writer_Null() : $_defaultWriter;
                $logger->addWriter($writer);
            }
            
            // For saving log into syslog too, create a key syslog into logger (value does not matter)
            if ((bool) $config->logger->syslog){
                $writer = new Zend_Log_Writer_Syslog(array(
                        'application'   => 'Tine 2.0'
                ));
                $prio = ($config->logger->priority) ? (int) $config->logger->priority : 3;
                $filter = new Zend_Log_Filter_Priority($prio);
                $writer->addFilter($filter);
                $logger->addWriter($writer);
            }
        } else {
            $writer = new Zend_Log_Writer_Syslog(array(
                'application'   => 'Tine 2.0'
            ));
            
            $filter = new Zend_Log_Filter_Priority(Zend_Log::WARN);
            $writer->addFilter($filter);
            $logger->addWriter($writer);
        }
        
        self::set(self::LOGGER, $logger);
        
        if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) $logger->info(__METHOD__ . '::' . __LINE__ .' Logger initialized');
        if (isset($loggerConfig) && Tinebase_Core::isLogLevel(Zend_Log::TRACE)) $logger->trace(__METHOD__ . '::' . __LINE__ 
            .' Logger settings: ' . print_r($loggerConfig->toArray(), TRUE));
    }
    
    /**
     * setup the cache and add it to zend registry
     *
     * @param bool $_enabled disabled cache regardless what's configured in config.inc.php
     * 
     * @todo use the same config keys as Zend_Cache (backend + frontend) to simplify this
     */
    public static function setupCache($_enabled = true)
    {
        if ( self::$cacheStatus !== NULL && self::$cacheStatus === $_enabled ) {
            return;
        }
        
        $config = self::getConfig();
        if ($config->caching && $config->caching->active) {
            if (isset($config->caching->shared) && ($config->caching->shared === true)) {
                self::set(self::SHAREDCACHE, true);
            } else {
                self::set(self::SHAREDCACHE, false);
            }
        }
        
        // create zend cache
        if ($_enabled === true && $config->caching && $config->caching->active) {
            $frontendOptions = array(
                'lifetime'                  => ($config->caching->lifetime) ? $config->caching->lifetime : 7200,
                'automatic_serialization'   => true, // turn that off for more speed
                'caching'                   => true,
                'automatic_cleaning_factor' => 0,    // no garbage collection as this is done by a scheduler task
                'write_control'             => false, // don't read cache entry after it got written
                'logging'                   => (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)),
                'logger'                    => self::getLogger(),
            );
            
            $backendType = ($config->caching->backend) ? ucfirst($config->caching->backend) : 'File';
            $backendOptions = ($config->caching->backendOptions) ? $config->caching->backendOptions->toArray() : false;
            
            if (! $backendOptions) {
                switch ($backendType) {
                    case 'File':
                        $backendOptions = array(
                            'cache_dir'              => ($config->caching->path)     ? $config->caching->path     : Tinebase_Core::getTempDir(),
                            'hashed_directory_level' => ($config->caching->dirlevel) ? $config->caching->dirlevel : 4, 
                            'logging'                => (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)),
                            'logger'                 => self::getLogger(),
                        );
                        break;
                        
                    case 'Memcached':
                        $host = $config->caching->host ? $config->caching->host : ($config->caching->memcached->host ? $config->caching->memcached->host : 'localhost');
                        $port = $config->caching->port ? $config->caching->port : ($config->caching->memcached->port ? $config->caching->memcached->port : 11211);
                        $backendOptions = array(
                            'servers' => array(
                                'host' => $host,
                                'port' => $port,
                                'persistent' => TRUE
                        ));
                        break;
                        
                    case 'Redis':
                        $host = $config->caching->host ? $config->caching->host : ($config->caching->redis->host ? $config->caching->redis->host : 'localhost');
                        $port = $config->caching->port ? $config->caching->port : ($config->caching->redis->port ? $config->caching->redis->port : 6379);
                        $prefix = (Setup_Controller::getInstance()->isInstalled('Tinebase')) ? Tinebase_Application::getInstance()->getApplicationByName('Tinebase')->getId() : 'TINESETUP';
                        $prefix .= '_CACHE_';
                        $backendOptions = array(
                            'servers' => array(
                                'host'   => $host,
                                'port'   => $port,
                                'prefix' => $prefix
                        ));
                        break;
                        
                    default:
                        $backendOptions = array();
                        break;
                }
            }
            
            Tinebase_Core::getLogger()->INFO(__METHOD__ . '::' . __LINE__ . " cache of backend type '{$backendType}' enabled");
            
            if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) {
                // logger is an object, that makes ugly traces :)
                $backendOptionsWithoutLogger = $backendOptions;
                if (isset($backendOptionsWithoutLogger['logger'])) {
                    unset($backendOptionsWithoutLogger['logger']);
                }
                Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . " backend options: " . print_r($backendOptionsWithoutLogger, TRUE));
            }
            
        } else {
            Tinebase_Core::getLogger()->INFO(__METHOD__ . '::' . __LINE__ . ' cache disabled');
            $backendType = 'Test';
            $frontendOptions = array(
                'caching' => false
            );
            $backendOptions = array(
            );
        }
        
        // getting a Zend_Cache_Core object
        try {
            $cache = Zend_Cache::factory('Core', $backendType, $frontendOptions, $backendOptions);
            
        } catch (Zend_Cache_Exception $e) {
            $enabled = FALSE;
            if ('File' === $backendType && !is_dir($backendOptions['cache_dir'])) {
                // create cache directory and re-try
                if (mkdir($backendOptions['cache_dir'], 0770, true)) {
                    $enabled = $_enabled;
                }
            }
            
            Tinebase_Core::getLogger()->WARN(__METHOD__ . '::' . __LINE__ . ' Cache error: ' . $e->getMessage());
            
            self::setupCache($enabled);
            return;
        }
        
        // some important caches
        Zend_Locale::setCache($cache);
        Zend_Translate::setCache($cache);
        
        Zend_Db_Table_Abstract::setDefaultMetadataCache($cache);
        self::set(self::CACHE, $cache);
        self::$cacheStatus = $_enabled;
    }
    
    /**
     * places user credential cache id from cache adapter (if present) into registry
     */
    public static function setupUserCredentialCache()
    {
        try {
            $cache = Tinebase_Auth_CredentialCache::getInstance()->getCacheAdapter()->getCache();
        } catch (Zend_Db_Statement_Exception $zdse) {
            // could not get credential cache adapter, perhaps Tine 2.0 is not installed yet
            $cache = NULL;
        }
        if ($cache !== NULL) {
            self::set(self::USERCREDENTIALCACHE, $cache);
        }
    }
    
    /**
     * setup stream wrapper for tine20:// prefix
     * 
     */
    public static function setupStreamWrapper()
    {
        if (empty(Tinebase_Core::getConfig()->filesdir)) {
            Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ 
                . " Filesdir config value not set. tine20:// streamwrapper not registered, virtual filesystem not available.");
            return;
        }
        
        stream_wrapper_register('tine20', 'Tinebase_FileSystem_StreamWrapper');
    }
    
    /**
     * set database capabilities in session
     * 
     * @param Zend_Session_Namespace $session
     */
    public static function setDbCapabilitiesInSession($session)
    {
        if (! isset($session->dbcapabilities)) {
            $db = Tinebase_Core::getDb();
            $capabilities = array();
            if ($db instanceof Zend_Db_Adapter_Pdo_Pgsql) {
                $capabilities['unaccent'] = Tinebase_Core::checkUnaccentExtension($db);
            }
            $session->dbcapabilities = $capabilities;
        }
    }
    
    /**
     * initializes the database connection
     */
    public static function setupDatabaseConnection()
    {
        $config = self::getConfig();
        
        if (isset($config->database)) {
            $dbConfig = $config->database;
            
            if (! defined('SQL_TABLE_PREFIX')) {
                define('SQL_TABLE_PREFIX', $dbConfig->get('tableprefix') ? $dbConfig->get('tableprefix') : 'tine20_');
            }
            
            $db = self::createAndConfigureDbAdapter($dbConfig->toArray());
            Zend_Db_Table_Abstract::setDefaultAdapter($db);
            
            // place table prefix into the concrete adapter
            $db->table_prefix = SQL_TABLE_PREFIX;
            
            self::set(self::DB, $db);
            
        } else {
            die ('database section not found in central configuration file');
        }
    }
    
    /**
     * create db adapter and configure it for Tine 2.0
     * 
     * @param array $dbConfigArray
     * @param string $dbBackend
     * @return Zend_Db_Adapter_Abstract
     * @throws Tinebase_Exception_Backend_Database
     * @throws Tinebase_Exception_UnexpectedValue
     */
    public static function createAndConfigureDbAdapter($dbConfigArray, $dbBackend = NULL)
    {
        if ($dbBackend === NULL) {
            $constName = 'self::' . strtoupper($dbConfigArray['adapter']);
            if (empty($dbConfigArray['adapter']) || ! defined($constName)) {
                self::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' Wrong/no db adapter configured (' . $dbConfigArray['adapter'] . '). Using default: ' . self::PDO_MYSQL);
                $dbBackend = self::PDO_MYSQL;
                $dbConfigArray['adapter'] = self::PDO_MYSQL;
            } else {
                $dbBackend = constant($constName);
            }
        }
        
        self::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Creating ' . $dbBackend . ' DB adapter');
        
        switch ($dbBackend) {
            case self::PDO_MYSQL:
                foreach (array('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY', 'PDO::MYSQL_ATTR_INIT_COMMAND') as $pdoConstant) {
                    if (! defined($pdoConstant)) {
                        throw new Tinebase_Exception_Backend_Database($pdoConstant . ' is not defined. Please check PDO extension.');
                    }
                }
                
                // force some driver options
                $dbConfigArray['driver_options'] = array(
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => FALSE,
                    // set utf8 charset
                    // @todo set to utf8mb4 / @see 0008708: switch to mysql utf8mb4
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES UTF8;",
                );
                $db = Zend_Db::factory('Pdo_Mysql', $dbConfigArray);
                try {
                    // set mysql timezone to utc and activate strict mode
                    $db->query("SET time_zone ='+0:00';");
                    $db->query("SET SQL_MODE = 'STRICT_ALL_TABLES'");
                    $db->query("SET SESSION group_concat_max_len = 81920");
                } catch (Exception $e) {
                    self::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' Failed to set "SET SQL_MODE to STRICT_ALL_TABLES or timezone: ' . $e->getMessage());
                }
                break;
                
            case self::PDO_OCI:
                $db = Zend_Db::factory('Pdo_Oci', $dbConfigArray);
                break;
                
            case self::ORACLE:
                $db = Zend_Db::factory(self::ORACLE, $dbConfigArray);
                $db->supportPositionalParameters(true);
                $db->setLobAsString(true);
                break;
                
            case self::PDO_PGSQL:
                unset($dbConfigArray['adapter']);
                unset($dbConfigArray['tableprefix']);
                if (empty($dbConfigArray['port'])) {
                    $dbConfigArray['port'] = 5432;
                }
                $db = Zend_Db::factory('Pdo_Pgsql', $dbConfigArray);
                try {
                    // set mysql timezone to utc and activate strict mode
                    $db->query("SET timezone ='+0:00';");
                    // PostgreSQL has always been strict about making sure data is valid before allowing it into the database
                } catch (Exception $e) {
                    self::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' Failed to set "SET timezone: ' . $e->getMessage());
                }
                break;
                
            default:
                throw new Tinebase_Exception_UnexpectedValue('Invalid database adapter defined. Please set adapter to ' . self::PDO_MYSQL . ' or ' . self::PDO_OCI . ' in config.inc.php.');
                break;
        }
        
        return $db;
    }
    
    /**
     * get value of session variable "unaccent"
     * 
     * @param Zend_Db_Adapter_Abstract $db
     * @return boolean $valueUnaccent
     * 
     * @todo should be moved to pgsql adapter / helper functions
     */
    public static function checkUnaccentExtension($db)
    {
        $tableName = 'pg_extension';
        $cols = 'COUNT(*)';
        
        $select = $db->select()
            ->from($tableName, $cols)
            ->where("extname = 'unaccent'");
        
        // if there is no table pg_extension, returns 0 (false)
        try {
            // checks if unaccent extension is installed or not
            // (1 - yes; unaccent found)
            $result = (bool) $db->fetchOne($select);
        } catch (Zend_Db_Statement_Exception $zdse) {
            // (0 - no; unaccent not found)
            self::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Unaccent extension disabled (' . $zdse->getMessage() . ')');
            $result = FALSE;
        }
        
        return $result;
    }

    /**
     * get db profiling
     * 
     * Enable db profiling like this (in config.inc.php):
     * 
     *   'database' => 
     *      array(
     *         [...] // db connection params  
     *         'profiler' => TRUE
     *      ),
     *   'profiler' =>
     *      array(
     *         'queryProfiles' => TRUE,
     *         'queryProfilesDetails' => TRUE,
     *         'user' => 'loginname',             // only profile this user 
     *         'profilerFilterElapsedSecs' => 1,  // only show queries whose elapsed time is equal or greater than this
     *      )
     *    ),
     * 
     */
    public static function getDbProfiling()
    {
        if (! self::getConfig() || ! self::getConfig()->database || ! (bool) self::getConfig()->database->profiler) {
            return;
        }
        
        $config = self::getConfig()->profiler;
        
        if ($config->user && is_object(self::getUser()) && $config->user !== self::getUser()->accountLoginName) {
            return;
        }
        
        $profiler = Zend_Db_Table::getDefaultAdapter()->getProfiler();
        
        if (! empty($config->profilerFilterElapsedSecs)) {
            $profiler->setFilterElapsedSecs($config->profilerFilterElapsedSecs);
        }
        
        $data = array(
            'totalNumQueries' => $profiler->getTotalNumQueries(),
            'totalElapsedSec' => $profiler->getTotalElapsedSecs(),
            'longestTime'        => 0,
            'longestQuery'       => ''
        );
        
        if ($config && (bool) $config->queryProfiles) {
            $queryProfiles = $profiler->getQueryProfiles();
            if (is_array($queryProfiles)) {
                $data['queryProfiles'] = array();
                foreach ($queryProfiles as $profile) {
                    if ((bool) $config->queryProfilesDetails) {
                        $data['queryProfiles'][] = array(
                            'query'       => $profile->getQuery(),
                            'elapsedSecs' => $profile->getElapsedSecs(),
                        );
                    }
                    
                    if ($profile->getElapsedSecs() > $data['longestTime']) {
                        $data['longestTime']  = $profile->getElapsedSecs();
                        $data['longestQuery'] = $profile->getQuery();
                    }
                }
            }
        }
        
        self::getLogger()->debug(__METHOD__ . ' (' . __LINE__ . ') value: ' . print_r($data, true));
    }

    /**
     * sets the user locale
     *
     * @param  string $localeString
     * @param  bool   $saveaspreference
     */
    public static function setupUserLocale($localeString = 'auto', $saveaspreference = FALSE)
    {
        $session = Tinebase_User_Session::getSessionNamespace();
        
        if (self::isLogLevel(Zend_Log::DEBUG)) self::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " given localeString '$localeString'");
        
        // get locale object from session or ...
        if (   $session !== NULL 
            && isset($session->userLocale) 
            && is_object($session->userLocale) 
            && ($session->userLocale->toString() === $localeString || $localeString === 'auto')
        ) {
            $locale = $session->userLocale;

            if (self::isLogLevel(Zend_Log::DEBUG)) self::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . " Got locale from session : " . (string)$locale);
            
        // ... create new locale object
        } else {
            if ($localeString === 'auto') {
                // check if cookie or pref with language is available
                if (isset($_COOKIE['TINE20LOCALE'])) {
                    $localeString = $_COOKIE['TINE20LOCALE'];
                    if (self::isLogLevel(Zend_Log::DEBUG)) self::getLogger()->debug(__METHOD__ . '::' . __LINE__
                        . " Got locale from cookie: '$localeString'");
                    
                } elseif (isset($session->currentAccount)) {
                    $localeString = self::getPreference()->getValue(Tinebase_Preference::LOCALE, 'auto');
                    if (self::isLogLevel(Zend_Log::DEBUG)) self::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
                        . " Got locale from preference: '$localeString'");
                } else {
                    if (self::isLogLevel(Zend_Log::DEBUG)) self::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
                        . " Try to detect the locale of the user (browser, environment, default)");
                }
            }
            
            $locale = Tinebase_Translation::getLocale($localeString);
            
            // save in session
            if ($session !== NULL) {
                $session->userLocale = $locale;
            }
        }
        
        // save in registry
        self::set('locale', $locale);
        
        $localeString = (string)$locale;
        if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) self::getLogger()->info(__METHOD__ . '::' . __LINE__ . " Setting user locale: " . $localeString);
        
        // save locale as preference
        if (is_object(Tinebase_Core::getUser()) && ($saveaspreference || self::getPreference()->{Tinebase_Preference::LOCALE} === 'auto')) {
            self::getPreference()->{Tinebase_Preference::LOCALE} = $localeString;
        }
        
        // set correct ctype locale, to make sure that the filesystem functions like basename() are working correctly with utf8 chars
        $ctypeLocale = setlocale(LC_CTYPE, 0);
        if (! preg_match('/utf-?8/i', $ctypeLocale)) {
            // use en_US as fallback locale if region string is missing
            $newCTypeLocale = ((strpos($localeString, '_') !== FALSE) ? $localeString : 'en_US') . '.UTF8';
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . ' Setting CTYPE locale from "' . $ctypeLocale . '" to "' . $newCTypeLocale . '".');
            setlocale(LC_CTYPE, $newCTypeLocale);
        }
    }

    /**
     * intializes the timezone handling
     *
     * @param  string $_timezone
     * @param  bool   $_saveaspreference
     * @return string
     */
    public static function setupUserTimezone($_timezone = NULL, $_saveaspreference = FALSE)
    {
        $session = Tinebase_User_Session::getSessionNamespace();

        if ($_timezone === NULL) {
            
            if ($session instanceof Zend_Session_Namespace && isset($session->timezone)) {
                $timezone = $session->timezone;
            } else {
                // get timezone from preferences
                $timezone = self::getPreference()->getValue(Tinebase_Preference::TIMEZONE);
                if ($session instanceof Zend_Session_Namespace) {
                    $session->timezone = $timezone;
                }
            }

        } else {
            $timezone = $_timezone;
            if ($session instanceof Zend_Session_Namespace) {
                $session->timezone = $timezone;
            }
            
            if ($_saveaspreference) {
                // save as user preference
                self::getPreference()->setValue(Tinebase_Preference::TIMEZONE, $timezone);
            }
        }

        self::set(self::USERTIMEZONE, $timezone);

        return $timezone;
    }

    /**
     * set php execution life (max) time
     *
     * @param int $_seconds
     * @return int old max exexcution time in seconds
     */
    public static function setExecutionLifeTime($_seconds)
    {
        $oldMaxExcecutionTime = ini_get('max_execution_time');
        
        if ($oldMaxExcecutionTime > 0) {
            $safeModeSetting = ini_get('safe_mode');
            if ($safeModeSetting !== 'off' && (bool) $safeModeSetting === true) {
                if (Tinebase_Core::isRegistered(self::LOGGER) && Tinebase_Core::isLogLevel(Zend_Log::WARN)) {
                    Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ 
                        . ' max_execution_time(' . $oldMaxExcecutionTime . ') is too low. Can\'t set limit to ' 
                        . $_seconds . ' because of safe mode restrictions. safe_mode = ' . $safeModeSetting);
                }
            } else {
                if (Tinebase_Core::isRegistered(self::LOGGER) && Tinebase_Core::isLogLevel(Zend_Log::INFO)) {
                    Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' setting execution life time to: ' . $_seconds);
                }
                set_time_limit($_seconds);
            }
        }
        
        return $oldMaxExcecutionTime;
    }
    
    /**
     * set php memory (max) limit
     *
     * @param string $_limit
     * @return string old max memory limit
     */
    public static function setMemoryLimit($_limit)
    {
        $oldMaxMemoryLimit = ini_get('memory_limit');
        
        if (! empty($oldMaxMemoryLimit)) {
            if ((bool)ini_get('safe_mode') === true) {
                if (Tinebase_Core::isRegistered(self::LOGGER) && Tinebase_Core::isLogLevel(Zend_Log::WARN)) {
                    Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ 
                        . ' memory_limit(' . $oldMaxMemoryLimit . ') is too low. Can\'t set limit to ' 
                        . $_limit . ' because of safe mode restrictions.');
                }
            } else {
                if (Tinebase_Core::isRegistered(self::LOGGER) && Tinebase_Core::isLogLevel(Zend_Log::INFO)) {
                    Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' setting memory limit to: ' . $_limit);
                }
                ini_set('memory_limit', $_limit);
            }
        }
        
        return $oldMaxMemoryLimit;
    }
    
    /**
     * log memory usage
     *
     */
    public static function logMemoryUsage()
    {
        if (function_exists('memory_get_peak_usage')) {
            $memory = memory_get_peak_usage(true);
        } else {
            $memory = memory_get_usage(true);
        }
        
        return  ' Memory usage: ' . ($memory / 1024 / 1024) . ' MB';
    }
    
    public static function logCacheSize()
    {
        if(function_exists('realpath_cache_size')) {
            $realPathCacheSize = realpath_cache_size();
        } else {
            $realPathCacheSize = 'unknown';
        }
        
        return ' Real patch cache size: ' . $realPathCacheSize;
    }
    
    /******************************* REGISTRY ************************************/
    
    /**
     * get a value from the registry
     *
     */
    public static function get($index)
    {
        return (Zend_Registry::isRegistered($index)) ? Zend_Registry::get($index) : NULL;
    }
    
    /**
     * set a registry value
     * 
     * @throws Tinebase_Exception_InvalidArgument
     * @return mixed value
     */
    public static function set($index, $value)
    {
        if ($index === self::USER) {
            if ($value === null) {
                throw new Tinebase_Exception_InvalidArgument('Invalid user object!');
            }
            if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) {
                if ($value instanceof Tinebase_Model_FullUser) {
                    $userString =  $value->accountLoginName;
                } else if ($value instanceof Tinebase_Model_User) {
                    $userString = $value->accountDisplayName;
                } else {
                    $userString = var_export($value, true);
                }
                
                Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Setting user ' . $userString);
            }
        }
        
        Zend_Registry::set($index, $value);
    }
    
    /**
     * checks a registry value
     *
     * @return boolean
     */
    public static function isRegistered($index)
    {
        return Zend_Registry::isRegistered($index);
    }

    /**
     * Returns the auth typ from config or default value
     *
     * @return String
     */
    public static function getAuthType()
    {
        if (isset(Tinebase_Core::getConfig()->authentication)) {
            $authType = Tinebase_Core::getConfig()->authentication->get('backend', Tinebase_Auth::SQL);
        } else {
            $authType = Tinebase_Auth::SQL;
        }

        return ucfirst($authType);
    }
    
    /**
     * get config from the registry
     *
     * @return Zend_Config|Zend_Config_Ini|Tinebase_Config
     */
    public static function getConfig()
    {
        if (! self::get(self::CONFIG)) {
            self::setupConfig();
        }
        return self::get(self::CONFIG);
    }
    
    /**
     * get max configured loglevel
     * 
     * @return integer
     */
    public static function getLogLevel()
    {
        if (! ($logLevel = self::get(self::LOGLEVEL))) {
            $config = self::getConfig();
            $logLevel = Tinebase_Log::getMaxLogLevel(isset($config->logger) ? $config->logger : NULL);
            self::set(self::LOGLEVEL, $logLevel);
        }
        
        return $logLevel;
    }
    
    /**
     * check if given loglevel should be logged
     * 
     * @param  integer $_prio
     * @return boolean
     */
    public static function isLogLevel($_prio)
    {
        return self::getLogLevel() >= $_prio;
    }
    
    /**
     * get config from the registry
     *
     * @return Tinebase_Log the logger
     */
    public static function getLogger()
    {
        if (! self::get(self::LOGGER) instanceof Tinebase_Log) {
            Tinebase_Core::setupLogger();
        }
        
        return self::get(self::LOGGER);
    }
    
    /**
     * get cache from the registry
     *
     * @return Zend_Cache_Core the cache
     */
    public static function getCache()
    {
        return self::get(self::CACHE);
    }
    
    /**
     * get locale from the registry
     *
     * @return Zend_Locale
     */
    public static function getLocale()
    {
        return self::get(self::LOCALE);
    }
        
    /**
     * get current user account
     *
     * @return Tinebase_Model_FullUser the user account record
     */
    public static function getUser()
    {
        $result = (self::isRegistered(self::USER)) ? self::get(self::USER) : NULL;
        return $result;
    }

    /**
     * get preferences instance by application name (create+save it to registry if it doesn't exist)
     *
     * @param string $_application
     * @param boolean $_throwException throws exception if class does not exist
     * @return Tinebase_Preference_Abstract
     */
    public static function getPreference($_application = 'Tinebase', $_throwException = FALSE)
    {
        $result = NULL;

        if (self::isRegistered(self::PREFERENCES)) {
            $prefs = self::get(self::PREFERENCES);
            if (isset($prefs[$_application])) {
                $result = $prefs[$_application];
            }
        } else {
            $prefs = array();
        }

        if ($result === NULL) {
            $prefClassName = $_application . '_Preference';
            if (@class_exists($prefClassName)) {
                $result = new $prefClassName();
                $prefs[$_application] = $result;
                self::set(self::PREFERENCES, $prefs);
            } else if ($_throwException) {
                throw new Tinebase_Exception_NotFound('No preference class found for app ' . $_application);
            }
        }

        return $result;
    }

    /**
     * get db adapter
     *
     * @return Zend_Db_Adapter_Abstract
     */
    public static function getDb()
    {
        if (! self::get(self::DB) instanceof Zend_Db_Adapter_Abstract) {
            Tinebase_Core::setupDatabaseConnection();
        }
        
        return self::get(self::DB);
    }
    
    /**
     * get db name
     * 
     * @return string
     */
    public static function getDbName()
    {
        if (! self::get(self::DBNAME)) {
            $db = self::getDb();
            $adapterName = get_class($db);
    
            if (empty($adapterName) || strpos($adapterName, '_') === FALSE) {
                throw new Tinebase_Exception('Could not get DB adapter name.');
            }
    
            $adapterNameParts = explode('_', $adapterName);
            $type = array_pop($adapterNameParts);
    
            // special handling for Oracle
            $type = str_replace('Oci', self::ORACLE, $type);
            self::set(self::DBNAME, $type);
        }
        
        return self::get(self::DBNAME);
    }
    
    /**
     * get temp dir string (without PATH_SEP at the end)
     *
     * @return string
     */
    public static function getTempDir()
    {
        return self::get(self::TMPDIR);
    }
    
    /**
     * returns protocol + hostname
     * 
     * @return string
     */
    public static function getHostname()
    {
        $hostname = self::get('HOSTNAME');
        if (! $hostname) {
            $request = new Sabre\HTTP\Request();
            $hostname = strlen($request->getUri()) > 1 ?
                str_replace($request->getUri(), '', $request->getAbsoluteUri()) :
                $request->getAbsoluteUri();

            self::set('HOSTNAME', $hostname);
        }
        
        return $hostname;
    }
    
    /**
     * Singleton instance
     *
     * @return Zend_Scheduler
     */
    public static function getScheduler()
    {
        if (! self::get(self::SCHEDULER) instanceof Zend_Scheduler) {
            $scheduler =  new Zend_Scheduler();
            $scheduler->setBackend(new Zend_Scheduler_Backend_Db(array(
                'DbAdapter' => self::getDb(),
                'tableName' => SQL_TABLE_PREFIX . 'scheduler',
                'taskClass' => 'Tinebase_Scheduler_Task'
            )));
            
            self::set(self::SCHEDULER, $scheduler);
        }
        return self::get(self::SCHEDULER);
    }
    
    /**
     * filter input string for database as some databases (looking at you, MySQL) can't cope with some chars
     * 
     * @param string $string
     * @return string
     *
     * @see 0008644: error when sending mail with note (wrong charset)
     * @see http://stackoverflow.com/questions/1401317/remove-non-utf8-characters-from-string/8215387#8215387
     * @see http://stackoverflow.com/questions/8491431/remove-4-byte-characters-from-a-utf-8-string
     */
    public static function filterInputForDatabase($string)
    {
        $db = self::getDb();
        if ($db && $db instanceof Zend_Db_Adapter_Pdo_Mysql) {
            $string = mbConvertTo($string);
            
            // remove 4 byte utf8
            $result = preg_replace('/[\xF0-\xF7].../s', '?', $string);
        } else {
            $result = $string;
        }
        
        return $result;
    }
    
    /**
     * checks if a system command exists. Works on POSIX systems.
     * 
     * @param string $name
     */
    public static function systemCommandExists($name)
    {
        $ret = shell_exec('which ' . $name);
        return ! empty($ret);
    }
    
    /**
     * calls a system command with escapeshellcmd
     * 
     * @param unknown $cmd
     */
    public static function callSystemCommand($cmd)
    {
        return shell_exec(escapeshellcmd($cmd));
    }
}
