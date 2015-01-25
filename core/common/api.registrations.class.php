<?php

/*
 * REST API module for phpVMS - Registrations Resource
 * Extension for https://github.com/tomsterritt/phpVMS-REST-API
 *
 * @author	Tom Sterritt
 * @link	http://sterri.tt/phpvms
 * @license	The ☺ license (http://license.visualidiot.com)
 *
 */

class APIRegistrations {
    
    public function processPath(){
        $args = func_get_args();
        $numArgs = func_num_args();
        
        // Make sure you're allowed to manage registrations first
        API::checkPerms(MODERATE_REGISTRATIONS);
        
        if($numArgs == 0 && API::requestMethod("GET")){
            // List pending
            $this->listPendingRegistrations();
        }
        
        if(is_numeric($args[0]) && API::requestMethod("PUT")){
            // Approve/Reject a registration request
            $this->updateRegistrationRequest($args[0]);
        }
        
        API::noMethod();
    }
    
    private function listPendingRegistrations(){
        $pending = PilotData::GetPendingPilots();
            
        if(!$pending || count($pending) < 1){
            API::exitWithHeader(404);
        }

        API::sendHeader(200);
        API::sendJSON($pending);
    }
    
    private function updateRegistrationRequest($id){
        
        // This is bad because it's a direct copy from the core code
        // If core ever changes this will be incorrect
        // Core needs updating to make this available from outside
        
        if(!isset(self::$vars['confirmed'])){
            API::exitWithHeader(400);
        }

        if(API::$vars['confirmed'] == PILOT_ACCEPTED){
            // Accept registration
            PilotData::AcceptPilot($args[0]);
            RanksData::CalculatePilotRanks();

            $pilot = PilotData::GetPilotData($args[0]);

            // Send pilot notification
            $subject = Lang::gs('email.register.accepted.subject');
            $this->set('pilot', $pilot);
            $message = Template::GetTemplate('email_registrationaccepted.tpl', true, true, true);
            Util::SendEmail($pilot->email, $subject, $message);

            LogData::addLog(API::$user->pilotid, '(via API) Approved '.PilotData::getPilotCode($pilot->code, $pilot->pilotid).' - ' .$pilot->firstname.' ' .$pilot->lastname);

            API::exitWithHeader(204);

        } else if(API::$vars['confirmed'] == PILOT_REJECTED){
            // Delete registration
            $pilot = PilotData::GetPilotData($args[0]);

            // Send pilot notification
            $subject = Lang::gs('email.register.rejected.subject');
            $this->set('pilot', $pilot);		
            $message = Template::Get('email_registrationdenied.tpl', true, true, true);
            Util::SendEmail($pilot->email, $subject, $message);

            // Reject in the end, since it's delted
            PilotData::RejectPilot($args[0]);
            LogData::addLog(API::$user->pilotid, '(via API) Approved '.PilotData::getPilotCode($pilot->code, $pilot->pilotid).' - ' .$pilot->firstname.' ' .$pilot->lastname);

            API::exitWithHeader(204);

        } else {
            API::exitWithHeader(400);
        }
    }
    
}

// Register with API
API::RegisterClassNameForPath('APIRegistrations', 'registrations');

?>