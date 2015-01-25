<?php

/*
 * REST API module for phpVMS
 *
 * @author	Tom Sterritt
 * @link	http://sterri.tt/phpvms
 * @license	The ☺ license (http://license.visualidiot.com)
 *
 */

// Force inclusion of classes that might want to be used
// Because phpVMS doesn't load everything for us
// Bit hacky don't love it...
foreach(glob("core/common/api.*.class.php") as $object){
    include_once $object;
}

// Main router
class API extends CodonModule {
    
    // Store GET/POST/PUT vars for easier access
    public static $vars;
    
    // Store the user ID on a request
    public static $user;
    
    // Sensitive keys to remove from output
    private static $sensitiveItems = ['password', 'salt', 'email', 'lastip'];
    
    // Allow registering other classes to handle requests
    private static $externalPaths = array();
    
    public static function RegisterClassNameForPath($classname, $path){
        
        // No classname
        if(!isset($classname) || strlen($classname) < 1){
            trigger_error("No classname given trying to register as API resource", E_USER_WARNING);
            return;
        }
        
        // Doesn't even exist
        if(!class_exists($classname)){
            trigger_error("Cannot register class ".$classname." as API resource - class is not defined", E_USER_WARNING);
            return;
        }
        // Isn't an APIResource
        if(!method_exists(new $classname(), 'processPath')){
            trigger_error("Cannot register class ".$classname." as API resource - it doesn't implement processPath", E_USER_WARNING);
            return;
        }
        
        // No path
        if(!isset($path) || strlen($path) < 1){
            trigger_error("No path given trying to register class ".$classname." for API resource", E_USER_WARNING);
            return;
        }
        
        // Already exists
        if(array_key_exists($path, self::$externalPaths)){
            trigger_error("Cannot register ".$classname." for API path ".$path." - already registered by ".self::$externalPaths[$path], E_USER_WARNING);
            return;
        }
        
        // Gratz you made it
        self::$externalPaths[$path] = $classname;
    }
    
    
    // Access to request method
    public static function requestMethod($checkMethod){
        return strtoupper(trim($_SERVER['REQUEST_METHOD'])) === $checkMethod;
    }
    
    // Some pre-request setup
    private static function setup(){
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header('Content-Type: application/json');
        
        // All requests need credentials, let's check those
        self::checkUser();
        
        if(self::requestMethod("GET")){
            self::$vars = $_GET;
        } elseif(self::requestMethod("POST") || self::requestMethod("PUT")){
            self::$vars = json_decode(file_get_contents("php://input"), true);
        }
    }
    
    // Authenticate the user
    private static function checkUser(){
        // Check an authorization header has been sent
        if(array_key_exists('PHP_AUTH_USER', $_SERVER)){
            // PHP has done the work for us
            $username = $_SERVER['PHP_AUTH_USER'];
            $password = $_SERVER['PHP_AUTH_PW'];
        } elseif (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)){
            // If not, decode it
            if (strpos(strtolower($_SERVER['HTTP_AUTHORIZATION']), 'basic') === 0){
                list($username, $password) = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
            }
        } else {
            self::exitWithHeader(401);
        }
        
        // Check these credentials are ok and they've been approved
        // (so people can't just register and immediately gain API access)
        $user = PilotData::getPilotData($username);
        if(!$user || $user->confirmed != 1 || !Auth::ProcessLogin($username, $password)){
            self::exitWithHeader(401);
        }
        
        // Store the user for later
        self::$user = $user;
    }
    
    // Authorise the user
    // Indended to be called at the start of each request type
    // Pass the minimum required permissions to complete the request
    public static function checkPerms($requiredPermission = NO_ADMIN_ACCESS){
        // Now check they have the required permission to complete this action
        // We can assume everyone has at least NO_ADMIN_ACCESS by this point
        if($requiredPermission > NO_ADMIN_ACCESS){
            $groups = PilotGroups::getUserGroups(self::$user->pilotid);
            if(!PilotGroups::group_has_perm($groups, $requiredPermission)){
                self::exitWithHeader(403);
            }
        }
    }
    
    // Send a HTTP Status code or other header
    public static function sendHeader($header){
        if(is_numeric($header)){
            // Send a HTTP status code
            switch($header){
                case 200: $text = 'OK';                     break;
                case 201: $text = 'Created';                break;
                case 204: $text = 'No Content';             break;
                case 400: $text = 'Bad Request';            break;
                case 401: $text = 'Unauthorized';           break;
                case 403: $text = 'Forbidden';              break;
                case 404: $text = 'Not Found';              break;
                case 405: $text = 'Method Not Allowed';     break;
                case 409: $text = 'Conflict';               break;
                case 429: $text = 'Too Many Requests';      break;
                case 500: $text = 'Internal Server Error';  break;
                default: break;
            }

            $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';

            header($protocol.' '.$header.' '.$text);
        } else {
            header($header);
        }
    }
    
    // Send a HTTP Status code or other header and exit
    public static function exitWithHeader($header){
        self::sendHeader($header);
        exit;
    }
    
    // Remove sensitive information from responses
    // Uses a default array of sensitive information, but can be overridden if necessary
    private static function unsetSensitiveInfo($arr, $sensitiveInfo){
        $sensitiveInfo = !$sensitiveInfo ? self::$sensitiveItems : $sensitiveInfo;
        foreach($sensitiveInfo as $key){
            if(is_object($arr)){
                if(property_exists($arr, $key)){
                    unset($arr->$key);
                }
            }
            if(is_array($arr)){
                if(isset($arr[$key]) || array_key_exists($key, $arr)){
                    unset($arr[$key]);
                }
                foreach($arr as $item){
                    if(is_array($item) || is_object($item)){
                        self::unsetSensitiveInfo($item, $sensitiveInfo);
                    }
                }
            }
        }
        return $arr;
    }
    
    // Output formatted JSON, removing sensitive items
    public static function sendJSON($arr, $sensitiveInfo = NULL){
        print_r(json_encode(self::unsetSensitiveInfo($arr, $sensitiveInfo)));
        exit;
    }
    
    // Call at the end of each function, when no other methods complete
    public static function noMethod(){
        self::exitWithHeader(405);
    }
    
    // Generate a link URL for a resource
    public static function resourceURL($end){
        return fileurl('/index.php/api/'.$end);
    }
    
    //
    // - Default index
    //
    public function index(){
        self::setup();
        
        $arr = array(
            "version" => "0.1.0",
            "message" => "Please do not edit or remove this default message. It can be useful for clients to check you have the module installed, and at a compatible version, before attempting to make requests."
        );
        self::sendJSON($arr);
    }
    
    // Handle methods not defined here
    public function __call($name, $args){
        self::setup();
        
        if(array_key_exists($name, self::$externalPaths)){
            $class = new self::$externalPaths[$name]();
            call_user_func_array(array($class, "processPath"), $args);
            return;
        }
        
        self::noMethod();
    }
    
}

?>