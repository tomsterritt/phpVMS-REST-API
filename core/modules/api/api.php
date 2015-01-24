<?php

/*
 * REST API module for phpVMS
 *
 * @author	Tom Sterritt
 * @link	http://sterri.tt/phpvms
 * @license	The ☺ license (http://license.visualidiot.com)
 *
 */

class api extends CodonModule {
    
    // Store GET/POST/PUT vars for easier access
    private static $vars;
    
    // Store the user ID on a request
    private static $user;
    
    // Sensitive keys to remove from output
    private static $sensitiveItems = ['password', 'salt', 'email', 'lastip'];
    
    // Store the request method for easier access
    private static function requestMethod($checkMethod){
        return strtoupper(trim($_SERVER['REQUEST_METHOD'])) === $checkMethod;
    }
    
    // Some pre-request setup
    private static function setup(){
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header('Content-Type: application/json');
        
        if(self::requestMethod("GET")){
            self::$vars = $_GET;
        } elseif(self::requestMethod("POST") || self::requestMethod("PUT")){
            self::$vars = json_decode(file_get_contents("php://input"), true);
        }
    }
    
    
    // Authenticate & authorise the user
    // Indended to be called at the start of each request type
    // Pass the minimum required permissions to complete the request
    private static function checkAuth($requiredPermission = NO_ADMIN_ACCESS){
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
            self::sendHeader(401);
            exit;
        }
        
        // Check these credentials are ok and they've been approved
        // (so people can't just register and immediately gain API access)
        $user = PilotData::getPilotData($username);
        if(!$user || $user->confirmed != 1 || !Auth::ProcessLogin($username, $password)){
            self::sendHeader(401);
            exit;
        }
        
        // Store the user for later
        self::$user = $user;
        
        // Now check they have the required permission to complete this action
        // We can assume everyone has at least NO_ADMIN_ACCESS by this point
        if($requiredPermission > NO_ADMIN_ACCESS){
            $groups = PilotGroups::getUserGroups($user->pilotid);
            if(!PilotGroups::group_has_perm($groups, $requiredPermission)){
                self::sendHeader(403);
                exit;
            }
        }
    }
    
    // Send a HTTP Status code or other header
    private static function sendHeader($header){
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
    private static function sendJSON($arr, $sensitiveInfo = NULL){
        print_r(json_encode(self::unsetSensitiveInfo($arr, $sensitiveInfo)));
        exit;
    }
    
    // Call at the end of each function, when no other methods complete
    private static function noMethod(){
        self::sendHeader(405);
        exit;
    }
    
    // Generate a link URL for a resource
    private static function resourceURL($end){
        return fileurl('/index.php/api/'.$end);
    }
    
    // Handle no method available
    public function __call($name, $args){
        self::noMethod();
    }
    
    //
    // - Index
    //
    public function index(){
        self::setup();
        $arr = array(
            "version" => "1.0.0",
            "message" => "Please do not edit or remove this default message. It can be useful for clients to check you have the module installed, and at a compatible version, before attempting to make requests."
        );
        self::sendJSON($arr);
    }
    
    //
    // - Pilots
    //
    public function pilots(){
        self::setup();
        
        $args = array();
        $numArgs = func_num_args();
        for($i = 0; $i < $numArgs; $i++){
            $args[] = func_get_arg($i);
        }
        
        if($numArgs == 0){
            // List
            self::checkAuth();
            self::sendHeader(200);
            $pilots = PilotData::findPilots('');
            foreach($pilots as $pilot){
                $pilot->link = self::resourceURL('pilots/'.$pilot->pilotid);
            }
            self::sendJSON($pilots);
        }
        
        if(is_numeric($args[0])){
            // Pilot ID provided
            
            self::checkAuth();
            
            $pilot = PilotData::getPilotData($args[0]);
            
            if(!$pilot){
                self::sendHeader(404);
                exit;
            }
            
            $pilot->link = self::resourceURL('pilots/'.$pilot->pilotid);
            
            self::sendHeader(200);
            self::sendJSON($pilot);
        }
        
        if($args[0] == "me"){
            // Get pilot details for requesting user
            self::checkAuth();
            
            $me = PilotData::getPilotData(self::$user);
            $me->link = self::resourceURL('pilots/'.$me->pilotid);
            
            // $me shouldn't ever return false - we'd have prevented that by now in auth
            self::sendHeader(200);
            self::sendJSON($me, ['password', 'salt', 'lastip']);
        }
        
        self::noMethod();
    }
    
    //
    // - Registrations
    // - (Separated from pilots for convenience)
    //
    public function registrations(){
        self::setup();
        
        $args = array();
        $numArgs = func_num_args();
        for($i = 0; $i < $numArgs; $i++){
            $args[] = func_get_arg($i);
        }
        
        // Check you're allowed to manage registrations before continuing
        self::checkAuth(MODERATE_REGISTRATIONS);
        
        if($numArgs == 0){
            // List pending registrations
            $pending = PilotData::GetPendingPilots();
            
            if(!$pending || count($pending) < 1){
                self::sendHeader(404);
                exit;
            }
            
            self::sendHeader(200);
            self::sendJSON($pending);
        }
        
        if(is_numeric($args[0])){
            if(self::requestMethod("PUT")){
                
                // Approve/Reject a registration
                if(!isset(self::$vars['confirmed'])){
                    self::sendHeader(400);
                    exit;
                }
                
                if(self::$vars['confirmed'] == PILOT_ACCEPTED){
                    // Accept registration
                    PilotData::AcceptPilot($args[0]);
                    RanksData::CalculatePilotRanks();

                    $pilot = PilotData::GetPilotData($args[0]);

                    // Send pilot notification
                    $subject = Lang::gs('email.register.accepted.subject');
                    $this->set('pilot', $pilot);
                    $message = Template::GetTemplate('email_registrationaccepted.tpl', true, true, true);
                    Util::SendEmail($pilot->email, $subject, $message);

                    LogData::addLog(self::$user->pilotid, '(via API) Approved '.PilotData::getPilotCode($pilot->code, $pilot->pilotid).' - ' .$pilot->firstname.' ' .$pilot->lastname);
                    
                    self::sendHeader(204);
                    exit;
                    
                } else if(self::$vars['confirmed'] == PILOT_REJECTED){
                    // Delete registration
                    $pilot = PilotData::GetPilotData($args[0]);
		
                    // Send pilot notification
                    $subject = Lang::gs('email.register.rejected.subject');
                    $this->set('pilot', $pilot);		
                    $message = Template::Get('email_registrationdenied.tpl', true, true, true);
                    Util::SendEmail($pilot->email, $subject, $message);

                    # Reject in the end, since it's delted
                    PilotData::RejectPilot($args[0]);
                    LogData::addLog(self::$user->pilotid, '(via API) Approved '.PilotData::getPilotCode($pilot->code, $pilot->pilotid).' - ' .$pilot->firstname.' ' .$pilot->lastname);
                    
                    self::sendHeader(204);
                    exit;
                    
                } else {
                    self::sendHeader(400);
                    exit;

                }}
        }
        
        self::noMethod();
    }
    
    //
    // - News
    //
    public function news(){
        self::setup();
        
        $args = array();
        $numArgs = func_num_args();
        for($i = 0; $i < $numArgs; $i++){
            $args[] = func_get_arg($i);
        }
        
        if($numArgs == 0){
            if(self::requestMethod("GET")){
                // List news
                self::checkAuth();

                $page = isset(self::$vars['page']) ? self::$vars['page'] : 1;

                $news = NewsData::GetNews(NewsData::$itemsperpage, ($page * NewsData::$itemsperpage) - NewsData::$itemsperpage);

                if(!$news){
                    self::sendHeader(404);
                    exit;
                }
                
                // Add links
                foreach($news as $newsItem){
                    $newsItem->link = self::resourceURL('news/'.$newsItem->id);
                }

                // List News
                self::sendHeader(200);
                self::sendJSON($news);
            }
        }
        
        if(is_numeric($args[0]) && self::requestMethod("GET")){
            // Get a single item
            self::checkAuth();
            
            $news = NewsData::Single($args[0]);
            
            if(!$news){
                self::sendHeader(404);
                exit;
            }
            
            $news->link = self::resourceURL($news->id);
            
            self::sendHeader(200);
            self::sendJSON($news);
        }
        
        self::noMethod();
    }
    
    //
    // - Flights
    //
    public function flights(){
        self::setup();
        
        $args = array();
        $numArgs = func_num_args();
        for($i = 0; $i < $numArgs; $i++){
            $args[] = func_get_arg($i);
        }
        
        // Was going to add open flights but you can get that through action.php/acars/data
        // Any point duplicating?
        
        self::noMethod();
    }
    
    //
    // - Schedules
    //
    public function schedules(){
        self::setup();
        
        $args = array();
        $numArgs = func_num_args();
        for($i = 0; $i < $numArgs; $i++){
            $args[] = func_get_arg($i);
        }
        
        if($numArgs == 0 && self::requestMethod("GET")){
            // List schedules
            self::checkAuth();
            
            $schedules = SchedulesData::GetSchedules();
            if(count($schedules) < 1){
                self::sendHeader(404);
                exit;
            }
            
            foreach($schedules as $schedule){
                $schedule->link = self::resourceURL('schedules/'.$schedule->id);
            }
            
            self::sendHeader(200);
            self::sendJSON($schedules);
        }
        
        if(is_numeric($args[0])){
            if(self::requestMethod("GET")){
                // Get a single schedule
                $schedule = SchedulesData::getScheduleDetailed($args[0]);

                if(!$schedule){
                    self::sendHeader(404);
                    exit;
                }

                $schedule->link = self::resourceURL('schedules/'.$schedule->id);

                self::sendHeader(200);
                self::sendJSON($schedule);
            }
        }
        
        self::noMethod();
    }
    
    //
    // - Bids
    //
    public function bids(){
        self::setup();
        
        $args = array();
        $numArgs = func_num_args();
        for($i = 0; $i < $numArgs; $i++){
            $args[] = func_get_arg($i);
        }
        
        if($numArgs == 0){
            if(self::requestMethod("POST")){
                // Add a new bid
                self::checkAuth();
                if(!isset(self::$vars['id'])){
                    self::sendHeader(400);
                    exit;
                }
                
                // Block any other bids if they've already made a bid
                if(Config::Get('DISABLE_BIDS_ON_BID')){
                    $bids = SchedulesData::getBids(self::$user->pilotid);
                    if(count($bids) > 0){
                        self::sendHeader(409);
                        exit;
                    }
                }
                
                if(SchedulesData::AddBid(self::$user->pilotid, self::$vars['id'])){
                    send::sendHeader(201);
                    exit;
                } else {
                    self::sendHeader(400);
                    exit;
                }
            }
        }
        
        self::noMethod();
    }
    
}

?>