<?php
class Curl_Persistent
{

    const MAX_REQUESTS = 3;

    public $DEBUG_CACHE = FALSE;
    public $DEBUG_TMP = '/tmp/curl_';
    public $lastError = '';
    private $curl = NULL;
    private $curlConnectionTimeout = 10;
    private $curlTimeout = 10;
    private $multicurl;
    private $use_cookies = FALSE;
    private $follow_location = TRUE;
    private $cookies_file = '';
    private $headers = array();
    private $referer = '';
    private $proxy = array();
    private $proxyIndex = 0;
    private $maxreq;

    public static $agents = array(
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:7.0.1) Gecko/20100101 Firefox/7.0.1',
        'Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.1.9) Gecko/20100508 SeaMonkey/2.0.4',
        'Mozilla/5.0 (Windows; U; MSIE 7.0; Windows NT 6.0; en-US)',
        'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_7; da-dk) AppleWebKit/533.21.1 (KHTML, like Gecko) Version/5.0.5 Safari/533.21.1'
    );

    public static function getRandAgent()
    {
        return self::$agents[array_rand(self::$agents)];
    }

    public function __construct()
    {
        if (!isset($this->curl)) {
            $this->curl      = curl_init();
            $this->multicurl = curl_multi_init();
        }
        $this->setTimeout($this->curlConnectionTimeout, $this->curlTimeout);
        $this->setMaxReq(self::MAX_REQUESTS);
    }

    /**
     * Set number of maximum concurrent parallel requests
     *
     * @param int $maxreq
     */
    public function setMaxReq($maxreq)
    {
        $this->maxreq = $maxreq;

        return TRUE;
    }

    public function setUseCookies($use_cookies, $cookie_file = '')
    {
        $this->use_cookies = (bool)$use_cookies;

        if (!$cookie_file) $this->cookies_file = tempnam("/tmp", "cookie");
        else $this->cookies_file = $cookie_file;

        return TRUE;
    }

    /**
     * Set HTTP headers for requests
     *
     * @param array $headers
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;

        return TRUE;
    }

    public function setReferer($referer)
    {
        $this->referer = $referer;

        return TRUE;
    }

    public function setFollowLocation($followLocation)
    {
        $this->follow_location = (bool)$followLocation;

        return TRUE;
    }

    public function setTimeout($curlConnectionTimeout, $curlTimeout)
    {
        curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, $curlConnectionTimeout);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, $curlTimeout);
        $this->curlConnectionTimeout = $curlConnectionTimeout;
        $this->curlTimeout           = $curlTimeout;
    }

    /**
     * Set Proxy for requests
     * $proxy format expected: type://login:password@host:port or type://host:port
     *
     * @param string $proxies
     */
    public function setProxy($proxies)
    {
        try {
            $this->proxy = array();
            foreach ((array)$proxies as $proxy) {
                if (!$proxy) {
                    continue;
                }
                list($proxy_type, $proxy_info) = explode("://", $proxy);
                if (strpos('@', $proxy_info) !== FALSE) {
                    list($proxy_auth, $proxy_addr) = explode("@", $proxy_info);
                } else {
                    $proxy_auth = NULL;
                    $proxy_addr = $proxy_info;
                }
                $this->proxy[] = array(
                    'type' => ($proxy_type == 'socks5') ? CURLPROXY_SOCKS5 : CURLPROXY_HTTP,
                    'auth' => $proxy_auth,
                    'addr' => $proxy_addr,
                );
            }
            $this->proxyIndex = 0;

            return count($this->proxy) > 0;
        } catch (Exception $e) {
            $this->proxy = array();

            return FALSE;
        }
    }

    public function setOpt(array $options = array())
    {
        foreach ($options as $key => $value) {
            curl_setopt($this->curl, $key, $value);
        }
    }

    public function prepareCurl($ch, $url, $postFields = NULL)
    {
        $ch || $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $this->follow_location);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->curlConnectionTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->curlTimeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        if ($this->use_cookies) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies_file);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies_file);
        }

        if ($this->referer) {
            curl_setopt($ch, CURLOPT_REFERER, $this->referer . ($this->follow_location ? ';auto' : ''));
        }

        if ($postFields) {

            if (is_array($postFields)) {
                $vars = '';
                foreach ($postFields as $field => $val) {
                    $vars .= ($vars ? '&' : '') . $field . '=' . urlencode($val);
                }
            } else $vars = $postFields;

            curl_setopt($this->curl, CURLOPT_POST, 1);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $vars);
        }

        if ($this->proxy) {
            $this->proxyIndex = ($this->proxyIndex + 1) % count($this->proxy);
            $proxy            = $this->proxy[$this->proxyIndex];
            curl_setopt($ch, CURLOPT_PROXYTYPE, $proxy['type']);
            if (isset($proxy['auth'])) {
                curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy['auth']);
            }
            curl_setopt($ch, CURLOPT_PROXY, $proxy['addr']);
        }

        return $ch;
    }

    /**
     * Make single HTTP GET/POST request
     *
     * @param string $url
     * @return string
     */
    public function get($url, $postFields = NULL)
    {
        echo $url . "\n";
        $file = $this->DEBUG_TMP . md5($url);
        if ($this->DEBUG_CACHE && file_exists($file) && !$postFields) {
            return file_get_contents($file);
        }
        $this->curl = $this->prepareCurl($this->curl, $url, $postFields);

        $res             = curl_exec($this->curl);
        $this->lastError = $res ? '' : curl_error($this->curl);

        if ($this->DEBUG_CACHE && !$this->lastError) {
            file_put_contents($file, $res);
        } elseif (!$this->DEBUG_CACHE && file_exists($file)) {
            unlink($file);
        }

        return $res;
    }

    public function getLastInfo()
    {
        return array(
            'url'  => curl_getinfo($this->curl, CURLINFO_EFFECTIVE_URL),
            'code' => curl_getinfo($this->curl, CURLINFO_HTTP_CODE),
        );
    }

    public function postJSON($url, $data)
    {
        $data_string = json_encode($data);

        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string))
        );

        return curl_exec($this->curl);
    }

    /**
     * Make multiple HTTP GET requests in queue mode
     *
     * @param array $links
     * @return array
     */
    public function queueget($links, $callback = NULL)
    {
        $results = array();
        foreach ($links as $id => $url) {
            $results[$id] = $this->get($url);
            $info         = array(
                'id'   => $id,
                'url'  => $url,
                'code' => curl_getinfo($this->curl, CURLINFO_HTTP_CODE),
                'actuality' => microtime(true),
            );
            if ($callback) {
                if (is_array($callback)) {
                    call_user_func($callback, $results[$id], $info);
                } elseif (is_callable($callback)) {
                    $callback($results[$id], $info);
                }
            }
        }

        return $results;
    }

    /**
     * Make miltiple HTTP GET requests in parallel mode
     *
     * @param array $links
     * @return array
     */
    public function multiget($links, $closeThreads = TRUE, $callback = NULL)
    {
        $threads = array();
        $results = array();

        $maxRequests = $this->maxreq * count($this->proxy);
        for ($i = 0; $i < count($links); $i += $maxRequests) {
            $linksTrans = array();
            foreach (array_slice($links, $i, $maxRequests, TRUE) as $id => $url) {
                $linksTrans[$url] = $id;
                $threads[$id]     = $this->prepareCurl(NULL, $url);

                curl_multi_add_handle($this->multicurl, $threads[$id]);
            }

            $done = NULL;

            do {
                curl_multi_exec($this->multicurl, $done);
                if ($callback) {
                    do {
                        $info = curl_multi_info_read($this->multicurl);
                        if (is_array($info) && $info['handle']) {
                            $ch      = $info['handle'];
                            $content = curl_multi_getcontent($ch);
                            $url     = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                            if (isset($linksTrans[$url])) {
                                $id = $linksTrans[$url];
                            } else {
                                echo "Id for url $url is not in array: ";
                                print_r($linksTrans);
                                continue;
                            }
                            $results[$id] = $content;
                            $callbackInfo = array(
                                'id'   => $id,
                                'url'  => $url,
                                'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
                                'actuality' => microtime(true),
                            );
                            if (is_array($callback)) {
                                call_user_func($callback, $content, $callbackInfo);
                            } elseif (is_callable($callback)) {
                                $callback($content, $callbackInfo);
                            }
                        }
                    } while ($info);
                }
            } while ($done > 0);
        }

        foreach ($links as $id => $url) {
            $results[$id] = curl_multi_getcontent($threads[$id]);
        }

        if ($closeThreads) {
            foreach ($threads as $ch) {
                curl_multi_remove_handle($this->multicurl, $ch);
                curl_close($ch);
            }
        }

        return $results;
    }

    /**
     * make HTTP POST request
     *
     * @return string
     */
    public function post($url, $postFields)
    {
        $this->curl = $this->prepareCurl($this->curl, $url, $postFields);

        $res             = curl_exec($this->curl);
        $this->lastError = $res ? '' : curl_error($this->curl);

        return $res;
    }

    /**
     * Make miltiple HTTP POST requests in parallel mode
     *
     * @param array $linkData ('url'+'data')
     * @return array
     */
    public function multiPost($linkData)
    {
        $threads = array();
        $results = array();

        foreach ($linkData as $id => $data) {
            $threads[$id] = curl_init();
            $data_string  = json_encode($data['data']);
            curl_setopt($threads[$id], CURLOPT_TIMEOUT, $this->curlTimeout);
            curl_setopt($threads[$id], CURLOPT_CONNECTTIMEOUT, $this->curlConnectionTimeout);
            curl_setopt($threads[$id], CURLOPT_URL, $data['url']);
            curl_setopt($threads[$id], CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($threads[$id], CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($threads[$id], CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($threads[$id], CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($data_string))
            );
            curl_multi_add_handle($this->multicurl, $threads[$id]);
        }

        do {
            curl_multi_exec($this->multicurl, $done);
        } while ($done > 0);

        foreach ($linkData as $id => $url) {
            $results[$id] = curl_multi_getcontent($threads[$id]);
        }

        return $results;
    }

}
