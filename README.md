# Video Stream (PHP) (Laravel)

This Library is a Video Stream made with PHP to work with Laravel. It has some features which are:

* Stream Local videos;
* Stream External videos;

## How to install this package?

First make sure you have a Laravel project preconfigured on your machine.

Next you need to download this package using the code:

```
composer require micilini/video-stream
```

## How to use this package?

Using this package is very simple, first you need to call the ```VideoStream``` class and then use its methods passing some additional parameters:

```
use Micilini\VideoStream\VideoStream;

$videoPath = 'FULL_LOCAL_VIDEO_PATH OR FULL_URL_PATH';

$options = array(
    'is_localPath' => true,
    'is_https' => false,
    'video_size' => null,
    'video_buffer' => 512,
    'content_type' => 'video/mp4',
    'cache_control' => 'max-age=2592000, public',
    'expires' => gmdate('D, d M Y H:i:s', time()+2592000).' GMT',
    'last_modified' => gmdate('D, d M Y H:i:s', @filemtime($videoPath)).' GMT'
);

$videoStream = new VideoStream();
$videoStream->streamVideo($videoPath, $options);
```

## How to work with ```$options```

As you can see, there are some options you can send to the ```VideoStream``` class, they are:

| key           | Required      | Description  |
| ------------- |:-------------:| -----:|
| ```is_localPath``` | YES      | Tells the class if the video stored in the ```$videoPath``` variable is a local path or a URL. If is a local video (store in your server) set ```true```, if not (url video) set ```false```. |
| ```is_https``` | NO | Tells the class if the URL of your video is HTTP or HTTP. If is HTTP set ```false```, if not (HTTPS) set as ```true```.
| ```video_size``` | NO | For both local and external videos, the class needs to get the total size (in bytes) of the video to be read. Inside the class there are methods that are able to return the size of the video, but if you want to save some memory and processing, you can send the size through this option.
| ```video_buffer``` | NO | Tells the class how many bytes you want to allocate in memory to read the video, the default value is ```512```.
| ```content_type``` | NO | You can send the Content-Type of the file, the default value is ```video/mp4```.
| ```cache_control``` | NO | You can send the max-age of video that will be stream, the default value is ```max-age=2592000, public```.
| ```expires``` | NO | You can send the expire date of video that will be stream, the default value is ```gmdate('D, d M Y H:i:s', time()+2592000).' GMT'```.
| ```last_modified``` | NO | You can send the last modified date of video that will be stream, the default value is ```gmdate('D, d M Y H:i:s', @filemtime($videoPath)).' GMT'```.

## Pratical Usage of this package

Here is a simple and practical use of this class for streaming video:

```
/* THIS EXAMPLE CODE EXISTS INSIDE WEB.PHP IN ROUTES FOLDER */

Route::get('/', function(Micilini\VideoStream\VideoStream $videoStream) {

  $html = '<video width="320" height="240" controls><source src="http://127.0.0.1:8000/loadVideoLocal" type="video/mp4">Your browser does not support the video tag.</video>';

  $html .= '<video width="320" height="240" controls><source src="http://127.0.0.1:8000/loadVideoExternal" type="video/mp4">Your browser does not support the video tag.</video>';

  return $html;

});

Route::get('/loadVideoLocal', function(Micilini\VideoStream\VideoStream $videoStream) {

    $videoPath = 'C:\Users\MyUSER\Desktop\videoStream\public\assets\mov_bbb.mp4';//This is the full path of my local video.

    $options = array(
        'is_localPath' => true,
        'is_https' => false,
        'video_size' => null,
        'video_buffer' => 512,
        'content_type' => 'video/mp4',
        'cache_control' => 'max-age=2592000, public',
        'expires' => gmdate('D, d M Y H:i:s', time()+2592000).' GMT',
        'last_modified' => gmdate('D, d M Y H:i:s', @filemtime($videoPath)).' GMT'
    );

    return $videoStream->streamVideo($videoPath, $options);

});

Route::get('/loadVideoExternal', function(Micilini\VideoStream\VideoStream $videoStream) {

    $videoPath = 'https://mysite.com/assets/videos/mov_bbb.mp4';//this is the full path of my external video.

    $options = array(
        'is_localPath' => false,
        'is_https' => true,
        'video_size' => null,
        'video_buffer' => 512,
        'content_type' => 'video/mp4',
        'cache_control' => 'max-age=2592000, public',
        'expires' => gmdate('D, d M Y H:i:s', time()+2592000).' GMT',
        'last_modified' => gmdate('D, d M Y H:i:s', @filemtime($videoPath)).' GMT'
    );

    return $videoStream->streamVideo($videoPath, $options);

});

```

# License

Licensed under the [MIT](https://github.com/git/git-scm.com/blob/main/MIT-LICENSE.txt).*
