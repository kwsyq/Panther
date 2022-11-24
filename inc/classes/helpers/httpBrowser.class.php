<?php

/* inc/classes/helpers/httpBrowser.class.php

EXECUTIVE SUMMARY: Data-scraping. Follow up chains of links in HTTP documents.

>>>00014 JM 2020-03-23: It is not clear that this is being used at all.

>>>00017 Has a few odd issues in terms of non-parallel forms of passing equivalent data $params; probably worth reworking that
if we are using this.

* Public methods:
** __construct($userAgent = self::USERAGENT)
** __destruct()
** setCookieFile($cookiefilename)
** getCookieFile()
** callUrl($method, $url, $params = array(), $referer = '', $optHeader = false, $followLinks = true)
** postjson($url, $params, $referer = '', $optHeader = false, $followLinks = true)
** get($url, $referer = '', $optHeader = false, $followLinks = true)
** post($url, $params, $referer = '', $optHeader = false, $followLinks = true)
** lastHost()
** lastScheme()
** lastUriBase()
** lastUri()

*/

class httpBrowser
{
    public $userAgent = '';
    protected $cookies = array();
    protected $cookieFile = '';
    protected $lastHost = '';
    protected $lastScheme = '';
    protected $lastUri = '';
    protected $haveFavico = array();
    
    const TIMEOUT = 30;
    const USERAGENT = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322; .NET CLR 2.0.50727)';
    const POST = 'POST';
    const GET = 'GET';
    const PUT = 'PUT';
    const POSTJSON = 'POSTJSON';
    
    const DEBUG = false;

    // INPUT $userAgent: allows caller to specify something other than the vanilla useragent that is the default.    
    public function __construct($userAgent = self::USERAGENT) {
        libxml_use_internal_errors(true); //  Disable libxml errors, but allow user to fetch error information as needed. 
        $this->userAgent = $userAgent;
        /* OLD CODE REMOVED 2019-03-06
        $this->cookieFile = tempnam(DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR, 'httpclientcookies');
        */
        // BEGIN NEW CODE 2019-03-06
        $this->cookieFile = tempnam('/tmp/httpclientcookies');
        // END NEW CODE 2019-03-06
    }
        
    public function __destruct() {
        if (file_exists($this->cookieFile)) {
            //@unlink($this->cookieFile); // Commented out by Martin before 2019.
        }
    }

    // Allows overriding cookieFile name (full path)
    // INPUT $cookiefilename - full path to preferred cookieFile
    public function setCookieFile($cookiefilename) {        
        $this->cookieFile = $cookiefilename;        		
    }        
        
    // RETURNs cookieFile name (full path), or false if there is none.
    public function getCookieFile() {        	
        if (file_exists($this->cookieFile)){
            $cookie = $this->cookieFile;
            return $cookie;
        }
        return false;
    }

    // Calls the "get" method for each link in the document, subject to certain constraints.
    //  (E.g. to see image links, $this->_followLinks($dom, 'img', 'src', false);
    // INPUT $dom - HTML DOM object
    // INPUT $tag - An HTML element type (e.g. 'LI') or '*' for all elements
    // INPUT $attribute - Attribute whose value we care about, should be 'src' or 'href'
    // INPUT $followLinks - quasi-Boolean, whether to follow links to further HTML pages
    private function _followLinks($dom, $tag, $attribute, $followLinks) {
        $nodes = $dom->getElementsByTagName($tag);
        
        if ($nodes->length > 0) {
            // For each node/element in the DOM of type $tag... 
            foreach ($nodes as $node) {
                // ... get the link value ('SRC' or 'HREF' value)...
                $link = $node->getAttribute($attribute);
                
                if ($link) {
                    // ...parse it as a URL...
                    $urlData = parse_url($link);
                    if ($urlData) {
                        // $urlData will be an associative array with the following elements:
                        //   'scheme' - e.g. 'http'
                        //   'host'
                        //   'port' (probably not relevant here)
                        //   'user' (probably not relevant here)
                        //   'pass' (probably not relevant here)
                        //   'path' - including filename
                        //   'query' - after the question mark ?
                        //   'fragment' - after the pound/has sign #
                        //  All of that is simply lexical: no deep context, just based on the string as read
                        if (!isset($urlData['scheme'])) {
                            // Curly braces in the following line are weird PHP, but completely interchangeable
                            //  with square braces. 
                            // Effect of the following is to assume the link is relative, and to ignore any leading '/'
                            //  >>>00026: JM 2019-03-06 is very skeptical about that working correctly in general, 
                            //  though it just might be OK on some particular set of pages this has been applied to.
                            //  I believe this needs to be reworked to make it more general.
                            $link = $this->lastUriBase() . ($link{0} == '/' ? substr($link, 1) : $link);
                            $urlData['scheme'] = $this->lastScheme();
                        }
                        
                        if (array_search($urlData['scheme'], array(
                            'http', 'https', 'ftp'
                        )) !== false) {
                            // A scheme we can support, so go the next step
                            $this->get($link, $this->lastUri(), false, $followLinks);
                        }
                    } // >>>00002 else very bad URL, can't be read, should log.
                }
            }
        }
    } // END private function _followLinks
    
    static protected function debug($str) {
        // if (self::DEBUG) echo $str."\n"; // commented out by Martin before 2019
    }
    
    // INPUT $html: should be at least reasonably well-formed HTML
    // Follow up the links we are interested in, as expressed in body of function by $arrTags.
    private function _processHtml($html) {
        $dom = new DomDocument();
        $dom->loadHTML($html); // >>>00002: should check status here, could return false if $html is really bad.
        
        $storedUri = $this->lastUri;
        
        $arrTags = array(
            array('tag'=>'img', 'linkSource'=>'src', 'followLinks'=>false),
            array('tag'=>'script', 'linkSource'=>'href', 'followLinks'=>false),
            array('tag'=>'links', 'linkSource'=>'src', 'followLinks'=>false),
            array('tag'=>'iframe', 'linkSource'=>'src', 'followLinks'=>false),
            array('tag'=>'frame', 'linkSource'=>'src', 'followLinks'=>false),
        );
        
        foreach ($arrTags as $tagConfig) {
            $this->lastUri = $storedUri; // Always go back to the context from which this function was called
            $this->_followLinks($dom, $tagConfig['tag'], $tagConfig['linkSource'], $tagConfig['followLinks']);
        }
        
        $this->lastUri = $storedUri; // Restore the context from which this function was called
    } // END private function _processHtml
    
    // This is common code for methods _get, _post, and _postjson.
    // INPUT $url
    // INPUT $response - actual content returned when we requested the page at this URL; 
    //   includes headers and content.
    // INPUT $info - the return of a call to PHP curl_getinfo
    // INPUT $followLinks - quasi-Boolean, whether to follow links to further HTML pages.
    protected function _postProcess($url, $response, $info, $followLinks = true) {
        $headerSize = $info['header_size'];
        $urlData = parse_url($url); // >>>00002 does not check here for $urlData==false, which can happen on an ill-formed URL. 

        // Get favicon, if any. (See https://en.wikipedia.org/wiki/Favicon if that term is unfamiliar.)
        $favUri = $urlData['scheme'] . '://' . $urlData['host'];
        if (!isset($this->haveFavico[$favUri])) {
            $this->haveFavico[$favUri] = true;
            $this->get($favUri . '/favicon.ico', '', false, false);
        }
        unset($favUri); // JM 2019-03-06: I think this is just Martin limiting scope
                        // of $favUri by guaranteeing it does not retain a value after this,
                        // but that is not at all the normal practice in this code.
        
        // If we are following further links, anything relative will be relative to this URL. 
        if ($followLinks) {
            $this->lastHost = $urlData['host'];
            $this->lastScheme = $urlData['scheme'];
            $this->lastUri = $info['url'];
        }
        
        self::debug($info['url']);
        
        // Separate headers, content
        $tmp = array();
        $tmp['headers'] = explode("\r\n", substr($response, 0, $headerSize));
        $tmp['content'] = substr($response, $headerSize);
        
        // Drop any empty headers, trim the rest.
        foreach ($tmp['headers'] as $idx => $header) {
            if (!strlen($header)) {
                unset($tmp['headers'][$idx]);
            } else {
                trim($tmp['headers'][$idx] = trim($header));
            }
        }
        
        if ($followLinks) {
            // Follow up into this page.
            $this->_processHtml($tmp['content']);
        }
        
        return $tmp;
    } // END protected function _postProcess
    
    // >>>00001 effectively a no-op as it stands
    private function _processHeadersFromFile($filename) {
        $lines = file($filename);    
        foreach ($lines as $line) {        
        }
    }
    
    // HTTP POST to the specified URL
    // INPUT $url
    // INPUT $params: An associative array, interpreted as key-value pairs. 
    //  The full data to post in a HTTP "POST" operation. Equivalent of the
    //  query portion under GET method.
    // INPUT $referer: what to pass as referer in the POST
    // INPUT $optHeader: An array of HTTP header fields to set, in the format 
    //   array('Content-type: text/plain', 'Content-length: 100') 
    // INPUT $followLinks: Boolean, whether to further follow links from HTML content of the returned page
    // CAN THROW EXCEPTION. // >>>00001 Where, if anywhere, are we catching these?
    protected function _post($url, array $params, $referer = '', $optHeader = false, $followLinks = true) {
        $str = '';
        foreach ($params as $key => $value) {
            $str .= (strlen($str) ? '&' : '') . $key . '=' . urlencode($value);
        }
    
        if (function_exists('curl_init')) {
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_REFERER, $referer);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $str);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
            
            if ($optHeader) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $optHeader);
            }
            
            $response = curl_exec($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
        } else {
            // Shouldn't ever arise, but if it does, an equivalent through using shell.
            $cmd = "curl -isLk -w '\\n%{url_effective}\\n%{size_header}' -A " . escapeshellarg($this->userAgent) . " -d " . escapeshellarg($str) . " -e " . escapeshellarg($referer) . " -b " . escapeshellarg($this->cookieFile) . " -c " . escapeshellarg($this->cookieFile) . " " . escapeshellarg($url) . "";
            $response = shell_exec($cmd);
            self::debug('POST '.$cmd);
            
            $info = array();
            
            $lines = explode("\n", $response);
            $lines = array_map('trim', $lines);
            
            $info['header_size'] = array_pop($lines);
            $info['url'] = array_pop($lines);
            
            unset($lines);
            $response = substr($response, 0, strlen($response) - strlen($info['header_size']) - strlen($info['url']) - 2);
        }
    
        if ($response === false) {
            // >>>00002 probably should log something before throwing exception
            throw new Exception('HTTP POST failed'); 
        }
    
        return $this->_postProcess($url, $response, $info, $followLinks);
    } // END protected function _post
    
    // HTTP GET of the specified URL
    // INPUT $url: can include a query string, so no $params.
    // INPUT $referer: what to pass as referer in the GET
    // INPUT $optHeader: An array of HTTP header fields to set, in the format 
    //   array('Content-type: text/plain', 'Content-length: 100') 
    // INPUT $followLinks: Boolean, whether to further follow links from HTML content of the returned page
    // CAN THROW EXCEPTION. // >>>00001 Where, if anywhere, are we catching these?
    protected function _get($url, $referer = '', $optHeader = false, $followLinks = true) {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_REFERER, $referer);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
            
            if ($optHeader) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $optHeader);
            }
            
            $response = curl_exec($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
        } else {
            // Shouldn't ever arise, but if it does, an equivalent through using shell.
            $cmd = "curl -isLk -w '\\n%{url_effective}\\n%{size_header}' -A " . escapeshellarg($this->userAgent). " -e " . escapeshellarg($referer) . " -b " . escapeshellarg($this->cookieFile) . " -c " . escapeshellarg($this->cookieFile) . " " . escapeshellarg($url) . "";
            $response = shell_exec($cmd);
            self::debug('GET '.$cmd);
            
            $lines = explode("\n", $response);
            $lines = array_map('trim', $lines);
            
            $info['header_size'] = array_pop($lines);
            $info['url'] = array_pop($lines);
            
            unset($lines);
            $response = substr($response, 0, strlen($response) - strlen($info['header_size']) - strlen($info['url']) - 2);
        }
    
        if ($response === false) {
            // >>>00002 probably should log something before throwing exception  
            throw new Exception('HTTP GET failed');
        }
    
        return $this->_postProcess($url, $response, $info, $followLinks);
    } // END protected function _get
    
    // HTTP POST to the specified URL, expecting JSON as return
    // INPUT $url
    // INPUT $params: A string formatted like the query portion under GET method.
    //
    //  >>>00001, >>>00026: JM 2019-03-07 VERY surprised this is different from _get, _post, but
    //  theoretically callers could make this OK by how they pass things in.
    //  2019-12-02: Almost certainly this has never been called, let alone with significant
    //   content in this parameter. I'd really like to see it rewritten so that these are all parallel.
    //   All relevant functions could be rewritten so that all would accept either the string or array form.
    // 
    // INPUT $referer: what to pass as referer in the POST
    // INPUT $optHeader: An array of HTTP header fields to set, in the format 
    //   array('Content-type: text/plain', 'Content-length: 100') 
    // INPUT $followLinks: Boolean, whether to further follow links from HTML content of the returned page
    // CAN THROW EXCEPTION. // >>>00001 Where, if anywhere, are we catching these?
    private function _postjson($url,  $params, $referer = '', $optHeader = false, $followLinks = true) {
        $str = $params;
        
        if (function_exists('curl_init')) {
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_REFERER, $referer);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $str); // MARTIN COMENT params is a string
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: text/html,application/xhtml+xml", "Content-Type: application/json; charset=utf-8", "Content-length: ".strlen($str)));
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
            
            if ($optHeader) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $optHeader);
            }
            
            $response = curl_exec($ch);
            $info = curl_getinfo($ch);
            
            curl_close($ch);
        } else {
            // Shouldn't ever arise, but if it does, an equivalent through using shell.
            $cmd = "curl -isLk -w '\\n%{url_effective}\\n%{size_header}' -H 'Content-Length: " . strlen($str) . "' -H 'Accept: text/html,application/xhtml+xml' -H 'Content-Type: application/json; charset=utf-8' -A " . escapeshellarg($this->userAgent) . " -d " . escapeshellarg($str) . " -e " . escapeshellarg($referer) . " -b " . escapeshellarg($this->cookieFile) . " -c " . escapeshellarg($this->cookieFile) . " " . escapeshellarg($url) . "";
            $response = shell_exec($cmd);
            self::debug('POSTJSON '.$cmd);
            
            $info = array();
            
            $lines = explode("\n", $response);
            $lines = array_map('trim', $lines);
            
            $info['header_size'] = array_pop($lines);
            $info['url'] = array_pop($lines);
            
            unset($lines);
            $response = substr($response, 0, strlen($response) - strlen($info['header_size']) - strlen($info['url']) - 2);
        }
        
        if ($response === false)  {
            // >>>00002 probably should log something before throwing exception;  
            // >>>00017 maybe a differently worded exception, so we know that one possibility is
            //  that there was content at this address, but not the content type we said we'd accept.
            throw new Exception('HTTP POST failed');
        }
        
        return $this->_postProcess($url, $response, $info, $followLinks);
    } // END private function _postjson

    // A more general layer over _get, _post, _postjson.
    // INPUT $method: should be self::POST, self::GET, or self::POSTJSON 
    // INPUT $url
    // INPUT $params:
    //   * Not supported for self::GET, will be ignored. However, $url can include a query string.
    //   * For self::POST, an associative array, interpreted as key-value pairs. 
    //     The full data to post in a HTTP "POST" operation. Equivalent of the
    //     query portion under GET method.
    //   * For self::POSTJSON, a string formatted like the query portion under GET method.
    //     >>>00001, >>>00026: JM 2019-03-07 VERY surprised this is different from _get, _post, but
    //     theoretically callers could make this OK by how they pass things in.
    //     Makes me suspect this has never been called, or at least not with significant
    //       content in this parameter. I'd really like to see it rewritten so that these are all parallel.
    //       All relevant functions could be rewritten so that all would accept either the string or array form.
    // INPUT $referer: what to pass as referer in the GET or POST
    // INPUT $optHeader: An array of HTTP header fields to set, in the format 
    //   array('Content-type: text/plain', 'Content-length: 100')
    // INPUT $followLinks: Boolean, whether to further follow links from HTML content of the returned page
    // CAN THROW EXCEPTION. // >>>00001 Where, if anywhere, are we catching these?    
    public function callUrl($method, $url, $params = array(), $referer = '', $optHeader = false, $followLinks = true) {
        if (empty($referer)) {
            $referer = $this->lastUri;
        }
        switch ($method) {
            case self::POST :
            $response = $this->_post($url, $params, $referer, $optHeader, $followLinks);
            break;
            
            case self::GET :
            $response = $this->_get($url, $referer, $optHeader, $followLinks);
            break;
            
            case self::POSTJSON :
            $response = $this->_postjson($url, $params, $referer, $optHeader, $followLinks);
            break;
            
            case self::PUT :
            throw new Exception('Not implemented');
            break;
            
            default :
            throw new Exception('Invalid method');
            break;
        }
        
        return $response;
    } // END public function callUrl
    
    // A public equivalent of private function _postjson; same arguments 
    public function postjson($url, $params, $referer = '', $optHeader = false, $followLinks = true) {
        return $this->callUrl(self::POSTJSON, $url, $params, $referer, $optHeader, $followLinks);
    }
    // A public equivalent of private function _get; same arguments
    public function get($url, $referer = '', $optHeader = false, $followLinks = true) {
        return $this->callUrl(self::GET, $url, array(), $referer, $optHeader, $followLinks);
    }
    // A public equivalent of private function _post; same arguments
    public function post($url, $params, $referer = '', $optHeader = false, $followLinks = true) {
        return $this->callUrl(self::POST, $url, $params, $referer, $optHeader, $followLinks);
    }
    
    // RETURN private member variable lastHost
    public function lastHost() {
        return $this->lastHost;
    }
    
    // RETURN private member variable lastScheme
    public function lastScheme() {
        return $this->lastScheme;
    }
    
    // RETURN private member variable lastHost reworked as a URI, e.g. if 
    //  lastHost is 'ssseng.com' returns something like "http://ssseng.com".
    public function lastUriBase() {
        return $this->lastScheme . '://' . $this->lastHost . '/';
    }
    
    // RETURN private member variable lastUri 
    public function lastUri(){
        return $this->lastUri;
    }
}
?>