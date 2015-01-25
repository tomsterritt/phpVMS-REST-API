<?php

/*
 * REST API module for phpVMS - Pilots Resource
 * Extension for https://github.com/tomsterritt/phpVMS-REST-API
 *
 * @author	Tom Sterritt
 * @link	http://sterri.tt/phpvms
 * @license	The ☺ license (http://license.visualidiot.com)
 *
 */

class APIPilots {
    
    public function processPath(){
        $args = func_get_args();
        $numArgs = func_num_args();
        
        if($numArgs == 0){
            // List
            $this->listPilots();
        }
        
        if(is_numeric($args[0])){
            // Pilot ID provided
            $this->singlePilot($args[0]);
        }
        
        if($args[0] == "me"){
            // Get pilot details for requesting user
            $this->singlePilot(API::$user->pilotid);
        }
        
        API::noMethod();
    }
    
    private function listPilots(){
        API::sendHeader(200);
        
        $pilots = PilotData::findPilots('');
        foreach($pilots as $pilot){
            $pilot->link = API::resourceURL('pilots/'.$pilot->pilotid);
        }
        
        API::sendJSON($pilots);
    }
    
    private function singlePilot($id){
        $pilot = PilotData::getPilotData($id);
        
        if(!$pilot){
            API::exitWithHeader(404);
        }
        
        $pilot->link = API::resourceURL('pilots/'.$pilot->pilotid);
        
        API::sendHeader(200);
        API::sendJSON($pilot);
    }
    
}

// Register with API
API::RegisterClassNameForPath('APIPilots', 'pilots');

?>