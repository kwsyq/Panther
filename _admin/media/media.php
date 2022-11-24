<?php 
/*  _admin/media/media.php

    EXECUTIVE SUMMARY: PAGE to list Vimeo videos. We get these via function getAllVimeoVideos in top-level functions.php, 
    which makes an API call to https://api.vimeo.com.
    
    No primary input because this is always a full list.
    >>>000032 presumably there is an intent to limit this to a particular category, but it hasn't been
    done as of 2019-05

    For each video there is a row with the following columns (no headers):
        * If a preview image exists, show it.
        * URI
        * name
        * description
        * duration 
*/
include '../../inc/config.php';
?>
<!DOCTYPE html>
<html>
<head>
</head>
<body>
<?php
$videos = getAllVimeoVideos();

echo '<table border="1" cellpadding="3" cellspacing="0">' . "\n";
    foreach ($videos as $video) {	
        echo '<tr>' . "\n";
            echo '<td>';
                if (isset($video['pictures'])) {                    
                    if (isset($video['pictures']['sizes'])) {            
                        $pictures = $video['pictures']['sizes'];            
                        if (count($pictures)) {                            
                            $picture = $pictures[0];                            
                            echo '<img src="' . $picture['link'] . '" width="' . $picture['width'] . '" height="' . $picture['height'] . '">';                            
                        }            
                    }                    
                }        
            echo '</td>' . "\n";
        
            echo '<td nowrap>' . $video['uri'] . '</td>' . "\n";
            echo '<td>' . $video['name'] . '</td>' . "\n";
            echo '<td>' . $video['description'] . '</td>' . "\n";
            echo '<td>' . $video['duration'] . '</td>' . "\n";	
        echo '</tr>' . "\n";	
    }
echo '</table>' . "\n";
?>
</body>
</html>
