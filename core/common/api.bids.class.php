<?php

/*
 * REST API module for phpVMS - Bids Resource
 * Extension for https://github.com/tomsterritt/phpVMS-REST-API
 *
 * @author	Tom Sterritt
 * @link	http://sterri.tt/phpvms
 * @license	The ☺ license (http://license.visualidiot.com)
 *
 */

class APIBids {
    
    public function processPath(){
        $args = func_get_args();
        $numArgs = func_num_args();
        
        if($numArgs == 0 && API::requestMethod("POST")){
            // Add a bid
            $this->addBid();
        }
        
        API::noMethod();
    }
    
    private function addBid(){
        if(!isset(API::$vars['id'])){
            self::exitWithHeader(400);
        }

        // Block any other bids if they've already made a bid
        if(Config::Get('DISABLE_BIDS_ON_BID')){
            $bids = SchedulesData::getBids(API::$user->pilotid);
            if(count($bids) > 0){
                API::exitWithHeader(409);
            }
        }

        if(SchedulesData::AddBid(API::$user->pilotid, API::$vars['id'])){
            API::exitWithHeader(201);
        } else {
            API::exitWithHeader(400);
        }
    }
    
}

// Register with API
API::RegisterClassNameForPath('APIBids', 'bids');

?>