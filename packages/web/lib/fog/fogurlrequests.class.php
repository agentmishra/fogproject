<?php
/**
 * Processes URL requests for our needs.
 *
 * PHP version 5
 *
 * @category FOGURLRequests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Processes URL requests for our needs.
 *
 * @category FOGURLRequests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FOGURLRequests extends FOGBase
{
    /**
     * The maximum urls to process at one time.
     *
     * @var int
     */
    private $_windowSize = 5;
    /**
     * The timeout value to process each url.
     *
     * @var int
     */
    private $_timeout = 86400;
    /**
     * Defines a specific call back request.
     *
     * TODO: Fixup more appropriately to get data
     * from a callback rather than from an execution
     * instance.
     *
     * @var string
     */
    private $_callback = '';
    /**
     * Contains the response of our url requests.
     *
     * @var array
     */
    private $_response = array();
    /**
     * Curl options to all url requests.
     *
     * @var array
     */
    public $options = array(
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 86400,
    );
    /**
     * Curl headers to send/request.
     *
     * @var array
     */
    private $_headers = array();
    /**
     * The requests themselves.
     *
     * @var array
     */
    private $_requests = array();
    /**
     * The mapping of requests so we can receive
     * information in the proper order as requested.
     *
     * @var array
     */
    private $_requestMap = array();
    /**
     * Initializes our url requests object.
     *
     * @param string $callback Optional callback
     */
    public function __construct($callback = null)
    {
        parent::__construct();
        $this->_callback = $callback;
    }
    /**
     * Cleans up when no longer needed.
     */
    public function __destruct()
    {
        unset(
            $this->_windowSize,
            $this->_callback,
            $this->options,
            $this->_headers,
            $this->_requests
        );
    }
    /**
     * Magic caller to get specialized methods
     * in a common method.
     *
     * @param string $name The method to get
     *
     * @return mixed
     */
    public function __get($name)
    {
        return (isset($this->{$name})) ? $this->{$name} : null;
    }
    /**
     * Magic caller to set specialized methods
     * in a common method.
     *
     * @param string $name  The method to set
     * @param mixed  $value The value to set
     *
     * @return object
     */
    public function __set($name, $value)
    {
        $addMethods = array(
            'options',
            'headers',
        );
        if (in_array($name, $addMethods)) {
            $this->{$name} = $value + $this->{$name};
        } else {
            $this->{$name} = $value;
        }

        return $this;
    }
    /**
     * Add a request to the requests variable.
     *
     * @param FOGRollingURL $request the request to add
     *
     * @return object
     */
    public function add($request)
    {
        $this->_requests[] = $request;

        return $this;
    }
    /**
     * Generates the request and stores to our requests variable.
     *
     * @param string $url       The url to request
     * @param string $method    The method to call
     * @param mixed  $post_data The data to pass
     * @param mixed  $headers   Any additional request headers to send
     * @param mixed  $options   Any additional request options to use
     *
     * @return object
     */
    public function request(
        $url,
        $method = 'GET',
        $post_data = null,
        $headers = null,
        $options = null
    ) {
        $this->_requests[] = new FOGRollingURL(
            $url,
            $method,
            $post_data,
            $headers,
            $options
        );

        return $this;
    }
    /**
     * Get method url request definition.
     *
     * @param string $url     The url to request to
     * @param mixed  $headers The custom headers to send with this
     * @param mixed  $options The custom options to send with this
     *
     * @return object
     */
    public function get(
        $url,
        $headers = null,
        $options = null
    ) {
        return $this->request(
            $url,
            'GET',
            null,
            $headers,
            $options
        );
    }
    /**
     * Post method url request definition.
     *
     * @param string $url       The url to request to
     * @param mixed  $post_data The post data to send
     * @param mixed  $headers   The custom headers to send with this
     * @param mixed  $options   The custom options to send with this
     *
     * @return object
     */
    public function post(
        $url,
        $post_data = null,
        $headers = null,
        $options = null
    ) {
        return $this->request(
            $url,
            'POST',
            $post_data,
            $headers,
            $options
        );
    }
    /**
     * Actually executes the requests.
     * If only one request, perform a _singleCurl.
     * If multiple perform _rollingCurl.
     *
     * @param mixed $window_size The window size to allow at run time
     * @param mixed $available   To test whether or not url is available
     *
     * @return object
     */
    public function execute($window_size = null, $available = false)
    {
        $window_count = count($this->_requests);
        if (empty($window_size)
            || !is_numeric($window_size)
            || $window_size > $window_count
        ) {
            $window_size = $window_count;
        }
        if ($window_count < 1) {
            return (array) false;
        }
        if ($window_count === 1) {
            return $this->_singleCurl($available);
        }

        return $this->_rollingCurl($window_size, $available);
    }
    /**
     * Run a single url request.
     *
     * @param bool $available To simply test if url is available
     *
     * @return mixed
     */
    private function _singleCurl($available = false)
    {
        $ch = curl_init();
        $request = array_shift($this->_requests);
        $options = $this->_getOptions($request);
        if ($available) {
            unset($options[CURLOPT_TIMEOUT]);
            unset($options[CURLOPT_CONNECTTIMEOUT]);
            $options[CURLOPT_TIMEOUT_MS] = 1200;
            $options[CURLOPT_CONNECTTIMEOUT_MS] = 500;
            $options[CURLOPT_RETURNTRANSFER] = true;
            $options[CURLOPT_NOBODY] = true;
            $options[CURLOPT_HEADER] = true;
        }
        curl_setopt_array($ch, $options);
        if ($available) {
            curl_exec($ch);
            $info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($info < 200
                || $info >= 400
            ) {
                return (array) false;
            }
            return (array) true;
        } else {
            $output = curl_exec($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
            if ($this->_callback && is_callable($this->_callback)) {
                $this->_callback($output, $info, $request);
            } else {
                return (array) $output;
            }
        }

        return (array)true;
    }
    /**
     * Perform multiple url requests.
     *
     * @param mixed $window_size The customized window size to use
     * @param mixed $available   To simply test if url is available enmass
     *
     * @return mixed
     */
    private function _rollingCurl($window_size = null, $available = false)
    {
        if ($window_size) {
            $this->_windowSize = $window_size;
        }
        if (sizeof($this->_requests) < $this->_windowSize) {
            $this->_windowSize = sizeof($this->_requests);
        }
        if ($this->_windowSize < 2) {
            throw new Exception(_('Window size must be greater than 1'));
        }
        $master = curl_multi_init();
        for ($i = 0; $i < $this->_windowSize; ++$i) {
            $ch = curl_init();
            $options = $this->_getOptions($this->_requests[$i]);
            if ($available) {
                unset($options[CURLOPT_TIMEOUT]);
                unset($options[CURLOPT_CONNECTTIMEOUT]);
                $options[CURLOPT_RETURNTRANSFER] = true;
                $options[CURLOPT_NOBODY] = true;
                $options[CURLOPT_HEADER] = true;
            }
            curl_setopt_array($ch, $options);
            curl_multi_add_handle($master, $ch);
            $key = (string) $ch;
            $this->_requestMap[$key] = $i;
        }
        do {
            while ((
                $execrun = curl_multi_exec(
                    $master,
                    $running
                )) == CURLM_CALL_MULTI_PERFORM) {
            }
            if ($execrun != CURLM_OK) {
                break;
            }
            while ($done = curl_multi_info_read($master)) {
                if ($available) {
                    $info = curl_getinfo($done['handle'], CURLINFO_HTTP_CODE);
                    $output = curl_multi_getcontent($done['handle']);
                    $key = (string) $done['handle'];
                    if ($info < 200
                        || $info >= 400
                    ) {
                        $this->_response[$this->_requestMap[$key]] = false;
                    } else {
                        $this->_response[$this->_requestMap[$key]] = true;
                    }
                } else {
                    $info = curl_getinfo($done['handle']);
                    $output = curl_multi_getcontent($done['handle']);
                    $key = (string) $done['handle'];
                    $this->_response[$this->_requestMap[$key]] = $output;
                    if ($this->_callback && is_callable($this->_callback)) {
                        $request = $this->_requests[$this->_requestMap[$key]];
                        unset($this->_requestMap[$key]);
                        $this->_callback($output, $info, $request);
                    }
                }
                $sizeof = sizeof($this->_requests);
                if ($i < $sizeof && isset($this->_requests[$i])) {
                    $ch = curl_init();
                    $options = $this->_getOptions($this->_requests[$i]);
                    curl_setopt_array($ch, $options);
                    curl_multi_add_handle($master, $ch);
                    $key = (string) $ch;
                    $this->_requestMap[$key] = $i;
                    ++$i;
                }
                curl_multi_remove_handle($master, $done['handle']);
            }
            if ($running) {
                curl_multi_select($master, $this->_timeout);
            }
        } while ($running);
        ksort($this->_response);
        curl_multi_close($master);

        return $this->_response;
    }
    /**
     * Get options of the request and whole.
     *
     * @param FOGRollingURL $request the request to get options from
     *
     * @return array
     */
    private function _getOptions($request)
    {
        $options = $this->__get('options');
        if (ini_get('safe_mode') == 'Off' || !ini_get('safe_mode')) {
            $options[CURLOPT_FOLLOWLOCATION] = 1;
            $options[CURLOPT_MAXREDIRS] = 5;
        }
        $url = $this->_validUrl($request->url);
        $headers = $this->__get('headers');
        if ($request->options) {
            $options = $request->options + $options;
        }
        $options[CURLOPT_URL] = $url;
        if ($request->post_data) {
            $options[CURLOPT_POST] = 1;
            $options[CURLOPT_POSTFIELDS] = $request->post_data;
        }
        if ($headers) {
            $options[CURLOPT_HEADER] = 0;
            $options[CURLOPT_HTTPHEADER] = $headers;
        }
        list($ip, $password, $port, $username) = self::getSubObjectIDs(
            'Service',
            array(
                'name' => array(
                    'FOG_PROXY_IP',
                    'FOG_PROXY_PASSWORD',
                    'FOG_PROXY_PORT',
                    'FOG_PROXY_USERNAME',
                ),
            ),
            'value',
            false,
            'AND',
            'name',
            false,
            false
        );
        $IPs = self::getSubObjectIDs('StorageNode', array('isEnabled' => 1));
        $pat = sprintf(
            '#%s#i',
            implode('|', $IPs)
        );
        if (!preg_match($pat, $url)) {
            if ($ip) {
                $options[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC;
                $options[CURLOPT_PROXYPORT] = $port;
                $options[CURLOPT_PROXY] = $ip;
                if ($username) {
                    $options[CURLOPT_PROXYUSERPWD] = sprintf(
                        '%s:%s',
                        $username,
                        $password
                    );
                }
            }
        }

        return $options;
    }
    /**
     * Function simply ensures the url is valid.
     *
     * @param string $url The url test check
     *
     * @return string
     */
    private function _validUrl(&$url)
    {
        $url = filter_var($url, FILTER_SANITIZE_URL);
        if (false === filter_var($url, FILTER_VALIDATE_URL)) {
            unset($url);
        }

        return $url;
    }
    /**
     * Processes the requests as needed.
     *
     * @param mixed  $urls       the urls to process
     * @param string $method     the method to use for all urls
     * @param mixed  $data       post/get data possibly
     * @param bool   $sendAsJSON Send data as json if needed
     * @param mixed  $auth       Any authorization data needed
     * @param string $callback   A callback to use if needed
     * @param string $file       A filename to use to download a file
     * @param mixed  $timeout    allow updating timeout values
     *
     * @return array
     */
    public function process(
        $urls,
        $method = 'GET',
        $data = null,
        $sendAsJSON = false,
        $auth = false,
        $callback = false,
        $file = false,
        $timeout = false
    ) {
        if (false !== $timeout) {
            $this->_timeout = $timeout;
            $this->options[CURLOPT_TIMEOUT] = $timeout;
        }
        if ($callback && is_callable($callback)) {
            $this->_callback = $callback;
        }
        if ($auth) {
            $this->options[CURLOPT_USERPWD] = $auth;
        }
        if ($sendAsJSON) {
            $data2 = json_encode($data);
            $datalen = strlen($data2);
            $this->options[CURLOPT_HEADER] = true;
            $this->options[CURLOPT_HTTPHEADER] = array(
                'Content-Type: application/json',
                "Content-Length: $datalen",
                'Expect:',
            );
        }
        if ($file) {
            $this->options[CURLOPT_FILE] = $file;
        }
        foreach ((array) $urls as &$url) {
            $request = new FOGRollingURL(
                $url
            );
            if ($method === 'GET') {
                $this->get($url);
            } else {
                $this->post($url, $data);
            }
            unset($url);
        }

        return $this->execute();
    }
    /**
     * Quick test if url is available.
     *
     * @param string $urls the url to check.
     *
     * @return void
     */
    public function isAvailable($urls)
    {
        foreach ((array) $urls as &$url) {
            $request = new FOGRollingURL(
                $url
            );
            $this->get($url);
            unset($url);
        }

        return $this->execute('', true);
    }
}
