<?php
/*  ajax/usgsajax.php

    >>>00001 JM 2019-05: I have no deep understanding of this, haven't studied the USGS API.
    If we ever want to change this, then that will probably require more understanding.

    INPUT $_REQUEST['lat']: latitude. Mandatory.
    INPUT $_REQUEST['lng']: longitude. Mandatory.
    INPUT $_REQUEST['edition']: known in the UI as "Design Code Reference Document" 
    INPUT $_REQUEST['variant']: known in the UI as "Earthquake Hazard Level".
    INPUT $_REQUEST['siteclass']: known in the UI as "Site Soil Classification".

    Makes RESTful calls to some USGS AJAX & packages the result as a snippet of HTML.

    Returns JSON for an associative array with the following members:    
        * 'status': "fail" if inputs (typically meaning lat, lng) not valid or any of several other failures; "success" on success.
        * 'errors': on success, an empty array; otherwise, in theory, an array of error messages but as of 2018-02 the only possible error message is 'Problem with Lat, Lng'.
        * 'html': This is an associative array with members:
            * 'table'. HTML table. Typical example below, broken out for clearer viewing.
            * 'images': array of associative arrays, each with the following members:
                * 'src' : image source, with a substitution of our own server for theirs.
                * 'width': image width
                * 'height': image height. 


    Typical example of 'table' in return from usgsajax.php:
    <table id="summary" cellspacing="0" cellpadding="0" border="0">
        <tbody>
            <tr>
                <th>S<sub>S</sub> = </th>
                <td>1.137 g</td>
                <th>S<sub>MS</sub> = </th>
                <td>0.909 g</td>
                <th>S<sub>DS</sub> = </th>
                <td>0.606 g</td>
            </tr>
            <tr>
                <th>S<sub>1</sub> = </th>
                <td>0.439 g</td>
                <th>S<sub>M1</sub> = </th>
                <td>0.351 g</td>
                <th>S<sub>D1</sub> = </th>
                <td>0.234 g</td>
            </tr>
        </tbody>
    </table>
*/    

include '../inc/config.php';
include '../inc/access.php';

ini_set('display_errors',0);
error_reporting(0);

$ret['status'] = 'fail';
$ret['errors'] = array();

$lat = isset($_REQUEST['lat']) ? $_REQUEST['lat'] : '';
$lng = isset($_REQUEST['lng']) ? $_REQUEST['lng'] : '';
$edition = isset($_REQUEST['edition']) ? $_REQUEST['edition'] : '';
$variant = isset($_REQUEST['variant']) ? $_REQUEST['variant'] : '';
$siteclass = isset($_REQUEST['siteclass']) ? $_REQUEST['siteclass'] : '';


if (is_numeric($lat) && is_numeric($lng)) {
    $params['latitude'] = $lat;
    $params['longitude'] = $lng;
    $params['siteclass'] = $siteclass;
    $params['riskcategory'] = '-1';
    $params['edition'] = $edition;
    $params['variant'] = $variant;
    $params['pe50'] = '';

    $str = '';
    foreach ($params as $pkey => $param) {
        if (strlen($str)) {
            // Not the first
            $str .= '&';
        }
        $str .= rawurlencode($pkey) . '=' . rawurlencode($param);
    }

    $output = '';
    $url = 'http://earthquake.usgs.gov/designmaps/us/inc/dataminer.inc.php?' . $str;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:28.0) Gecko/20100101 Firefox/28.0");
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $output = curl_exec($ch);
    curl_close($ch);

    $resultarray = json_decode($output, 1);

    if (is_array($resultarray)) {
        if (isset($resultarray['result_id']) && isset($resultarray['source_host'])) {
            $params['template'] = 'minimal';
            $params['resultid'] = $resultarray['result_id'];
                
            $str = '';                
            foreach ($params as $pkey => $param){                    
                if (strlen($str)) {
                    // Not the first
                    $str .= '&';
                }                    
                $str .= rawurlencode($pkey) . '=' . rawurlencode($param);                    
            }
                
            $output = '';                
            $url = $resultarray['source_host'] . '/summary.php?' . $str;                
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_REFERER, "http://earthquake.usgs.gov/designmaps/us/application.php");
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:28.0) Gecko/20100101 Firefox/28.0");
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $output = curl_exec($ch);
            curl_close($ch);
                
            $ret['html'] = parseOutput($output, $resultarray['source_host']);
            $ret['status'] = 'success';                
        }
    }
} else {
    $ret['errors'][] = 'Problem with Lat, Lng';
}

// INPUT $output - output from USGS summary.php call, apparently HTML - >>>00001 would like to know more
// INPUT $sourcehost - one of the outputs from USGS dataminer.inc.php call - >>>00001 would like to know more
function parseOutput($output, $sourcehost) {
    $ret['table'] = '<table></table>';
    $ret['images'] = array();

    $dom = new DomDocument();
    $dom->loadHTML($output);

    $tables = $dom->getElementsByTagName('table');

    foreach ($tables as $table) {
        if ($table->hasAttributes()) {
            if ($table->getAttribute('id') == 'summary') {
                $dom2 = new DOMDocument();
                $dom2->appendChild($dom2->importNode($table, true));
                $html = $dom2->saveHTML(); // >>>00012 JM: really no reason for variable html, could be just $ret['table'] = $dom2->saveHTML(); 
                $ret['table'] = $html;
            }   
        }
    }

    $images = $dom->getElementsByTagName('img');

    foreach ($images as $image) {
        if ($image->hasAttributes()) {                
            $pos = strpos($image->getAttribute('src'), '/designmaps/us/images/spectra.php');                
            if ($pos !== false){
                $width = intval($image->getAttribute('width'));
                $height = intval($image->getAttribute('height'));

                $ret['images'][] = array(
                    'src' => str_replace("/designmaps/us", $sourcehost, $image->getAttribute('src')), 
                    'width' => $width, 
                    'height' => $height
                );
            }                
        }
    }
    return $ret;
}

echo json_encode($ret);
die();
?>