<?php

/*
 * REST API module for phpVMS - Schedules Resource
 * Extension for https://github.com/tomsterritt/phpVMS-REST-API
 *
 * @author	Tom Sterritt
 * @link	http://sterri.tt/phpvms
 * @license	The ☺ license (http://license.visualidiot.com)
 *
 */

class APISchedules {
    
    public function processPath(){
        $args = func_get_args();
        $numArgs = func_num_args();
        
        if($numArgs == 0 && API::requestMethod("GET")){
            // List
            $this->listSchedules();
        }
        
        if(is_numeric($args[0])){
            // Schedule ID provided
            $this->singleSchedule($args[0]);
        }
        
        API::noMethod();
    }
    
    private function listSchedules(){
        $schedules = SchedulesData::GetSchedules();
        if(count($schedules) < 1){
            API::exitWithHeader(404);
        }

        foreach($schedules as $schedule){
            $schedule->link = API::resourceURL('schedules/'.$schedule->id);
        }

        API::sendHeader(200);
        API::sendJSON($schedules);
    }
    
    private function singleSchedule($id){
        $schedule = SchedulesData::getScheduleDetailed($id);

        if(!$schedule){
            API::exitWithHeader(404);
        }

        $schedule->link = API::resourceURL('schedules/'.$schedule->id);

        API::sendHeader(200);
        API::sendJSON($schedule);
    }
    
}

// Register with API
API::RegisterClassNameForPath('APISchedules', 'schedules');

?>