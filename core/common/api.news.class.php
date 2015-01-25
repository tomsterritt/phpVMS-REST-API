<?php

/*
 * REST API module for phpVMS - News Resource
 * Extension for https://github.com/tomsterritt/phpVMS-REST-API
 * Requires SimpleNews: https://github.com/tomsterritt/SimpleNews
 *
 * @author	Tom Sterritt
 * @link	http://sterri.tt/phpvms
 * @license	The ☺ license (http://license.visualidiot.com)
 *
 */

class APISimpleNews {
    
    public function processPath(){
        $args = func_get_args();
        $numArgs = func_num_args();
        
        if($numArgs == 0 && API::requestMethod("GET")){
            // Paginated list
            $this->listNews();
        }
        
        if(is_numeric($args[0]) && API::requestMethod("GET")){
            // Individual news item
            $this->singleNewsItem($args[0]);
        }
        
        API::noMethod();
    }
    
    private function listNews(){
        $page = isset(API::$vars['page']) ? API::$vars['page'] : 1;

        $news = NewsData::GetNews(NewsData::$itemsperpage, ($page * NewsData::$itemsperpage) - NewsData::$itemsperpage);

        if(!$news){
            API::exitWithHeader(404);
        }

        // Add links
        foreach($news as $newsItem){
            $newsItem->link = API::resourceURL('news/'.$newsItem->id);
        }

        // List News
        API::sendHeader(200);
        API::sendJSON($news);
    }
    
    private function singleNewsItem($id){
        $news = NewsData::Single($id);
            
        if(!$news){
            API::exitWithHeader(404);
        }

        $news->link = API::resourceURL($news->id);

        API::sendHeader(200);
        API::sendJSON($news);
    }
    
}

// Register with API
API::RegisterClassNameForPath('APISimpleNews', 'news');

?>