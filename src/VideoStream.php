<?php

namespace Micilini\VideoStream;

use Micilini\VideoStream\Providers\VideoStreamProvider;
use Micilini\VideoStream\Providers\LocalVideoStreamProvider;

class VideoStream{

    public function streamVideo($videoPath, $options){
        if(isset($options['is_localPath'])){
            if($options['is_localPath']){
                return $this->streamLocalVideo($videoPath, $options);
            }else{
                return $this->streamExternalVideo($videoPath, $options);
            }
        }else{
            return throw new \Exception('You must define [is_localPath] in $options, on Micilini\VideoStream\VideoStream Class, [Line 10]');
        }
    }

    private function streamLocalVideo($videoPath, $options){
        $videoStream = new LocalVideoStreamProvider($videoPath, $options);
        $videoStream->start();
    }

    private function streamExternalVideo($videoPath, $options){
        $videoStream = new VideoStreamProvider($videoPath, $options);
        $videoStream->start();
    }

}