<?php
/**
 * Flickr strategy for OpAuth
 * Based on http://www.flickr.com/services/api/
 * 
 * More information on Opauth: http://opauth.org
 * 
 * @copyright Copyright 2012 Masato Sogame (http://poketo7878.dip.jp)
 * @link        http://opauth.org
 * @package     Opauth.FlickrStrategy
 * @license     MIT License
 */
class FlickrStrategy extends OpauthStrategy {

        /**
         * Comulsory parameters
         */
        public $expects = array('key','secret');

        /**
         * Optional parameters
         */
        public $defaults = array(
                'method' => 'POST',
                'oauth_callback' => '{complete_url_to_strategy}oauth_callback',
                'authorize_url' => 'http://www.flickr.com/services/oauth/authorize', 
                'request_token_url' => 'http://www.flickr.com/services/oauth/request_token',
                'access_token_url' =>  'http://www.flickr.com/services/oauth/access_token',
                'flickr_profile_url' => 'http://api.flickr.com/services/rest?format=json',

                // From tmhOAuth
                'user_token'					=> '',
                'user_secret'					=> '',
                'use_ssl'						=> true,
                'debug'							=> false,
                'force_nonce'					=> false,
                'nonce'							=> false, // used for checking signatures. leave as false for auto
                'force_timestamp'				=> false,
                'timestamp'						=> false, // used for checking signatures. leave as false for auto
                'oauth_version'					=> '1.0',
                'curl_connecttimeout'			=> 30,
                'curl_timeout'					=> 10,
                'curl_ssl_verifypeer'			=> false,
                'curl_followlocation'			=> false, // whether to follow redirects or not
                'curl_proxy'					=> false, // really you don't want to use this if you are using streaming
                'curl_proxyuserpwd'				=> false, // format username:password for proxy, if required
                'is_streaming'					=> false,
                'streaming_eol'					=> "\r\n",
                'streaming_metrics_interval'	=> 60,
                'as_header'				  		=> true,
        );

        /**
         * Request
         */

        public function __construct($strategy, $env){
                parent::__construct($strategy, $env);

                $this->strategy['consumer_key'] = $this->strategy['key'];
                $this->strategy['consumer_secret'] = $this->strategy['secret'];

                require dirname(__FILE__).'/Vendor/tmhOAuth/tmhOAuth.php';
                $this->tmhOAuth = new tmhOAuth($this->strategy);
        }

        /**
         * Auth request
         */
        public function request(){
                $params = array(
                        'oauth_callback' => $this->strategy['oauth_callback']
                );

                $results =  $this->_request('POST', $this->strategy['request_token_url'], $params);

                if ($results !== false && !empty($results['oauth_token']) && !empty($results['oauth_token_secret'])){
                        session_start();
                        $_SESSION['_opauth_flickr'] = $results;

                        $this->_authorize($results['oauth_token']);
                }
        }

        /**
         * Receives oauth_verifier, requests for access_token and redirect to callback
         */
        public function oauth_callback(){
                session_start();
                $session = $_SESSION['_opauth_flickr'];
                unset($_SESSION['_opauth_flickr']);

                if ($_REQUEST['oauth_token'] == $session['oauth_token']){
                        $this->tmhOAuth->config['user_token'] = $session['oauth_token'];
                        $this->tmhOAuth->config['user_secret'] = $session['oauth_token_secret'];

                        $params = array(
                                'oauth_verifier' => $_REQUEST['oauth_verifier'],
                        );

                        $results =  $this->_request('POST',$this->strategy['access_token_url'], $params);

                        if ($results !== false && !empty($results['oauth_token']) && !empty($results['oauth_token_secret'])){
                                $credentials = $this->_verify_credentials($results['oauth_token'], $results['oauth_token_secret']);

                                if (!empty($credentials['user']['id'])) {
                                        $userInfo = $this->_getUserInfo($credentials['user']['id']);
                                        
                                        if (!empty($userInfo['person']['id'])) {
                                                $person = $userInfo['person'];
                                                                                       
                                                $this->auth = array(
                                                        'uid' => $person['id'],
                                                        'info' => array(
                                                                'image' => 'http://farm'.$person['iconfarm'].'.staticflickr.com/'.$person['iconserver'].'/buddyicons/'.$person['nsid'].'.jpg'
                                                        ),
                                                        'credentials' => array(
                                                                'token' => $results['oauth_token'],
                                                                'secret' => $results['oauth_token_secret']
                                                        ),
                                                        'raw' => $userInfo
                                                );
                                        
                                                $this->mapProfile($person, 'username._content', 'info.nickname');
                                                $this->mapProfile($person, 'realname._content', 'info.name');
                                                $this->mapProfile($person, 'location._content', 'info.location');
                                                $this->mapProfile($person, 'description._content', 'info.description');
                                                $this->mapProfile($person, 'profileurl._content', 'info.urls.flickr');

                                                $this->callback();
                                        }
                                        else {
                                                $error = array(
                                                        'code' => 'flickr.people.getInfo_failed',
                                                        'message' => 'Unable to obtain user info',
                                                        'raw' => $userInfo
                                                );
                                        }
                                }
                                else {
                                        $error = array(
                                                'code' => 'flickr.test.login_failed',
                                                'message' => 'Unable to obtain User ID',
                                                'raw' => $credentials
                                        );
                                }
                        }
                }
                else{
                        $error = array(
                               'code' => 'access_denied',
                                'message' => 'User denied access.',
                                'raw' => $_GET

                        );

                        $this->errorCallback($error);
                }
        }

        private function _authorize($oauth_token){
                $params = array(
                        'oauth_token' => $oauth_token
                );

                $this->clientGet($this->strategy['authorize_url'], $params);
        }

        private function _verify_credentials($user_token, $user_token_secret){
                $this->tmhOAuth->config['user_token'] = $user_token;
                $this->tmhOAuth->config['user_secret'] = $user_token_secret;

                $params = array( 'nojsoncallback' => 1,
				 'format'=>'json',
				 'method'=>'flickr.test.login' );

                $response = $this->_request('GET', $this->strategy['flickr_profile_url'], $params);

                return $this->recursiveGetObjectVars($response);
        }
        
        private function _getUserInfo($user_id) {
                $params = array( 'nojsoncallback' => 1,
				 'format'=>'json',
				 'method'=>'flickr.people.getInfo',
				 'user_id' => $user_id );
                
                $response = $this->_request('GET', $this->strategy['flickr_profile_url'], $params);
                
                return $this->recursiveGetObjectVars($response);
        }



        /**
         * Wrapper of tmhOAuth's request() with Opauth's error handling.
         * 
         * request():
         * Make an HTTP request using this library. This method doesn't return anything.
         * Instead the response should be inspected directly.
         *
         * @param string $method the HTTP method being used. e.g. POST, GET, HEAD etc
         * @param string $url the request URL without query string parameters
         * @param array $params the request parameters as an array of key=value pairs
         * @param string $useauth whether to use authentication when making the request. Default true.
         * @param string $multipart whether this request contains multipart data. Default false
         */	
        private function _request($method, $url, $params = array(), $useauth = true, $multipart = false){
                $code = $this->tmhOAuth->request($method, $url, $params, $useauth, $multipart);

                if ($code == 200){
			if (strpos($url, 'json') !== false)
				$response = json_decode($this->tmhOAuth->response['response']);
			else
				$response = $this->tmhOAuth->extract_params($this->tmhOAuth->response['response']);

                        return $response;		
                }
                else {
                        $error = array(
                                'code' => $code,
                                'raw' => $this->tmhOAuth->response['response']
                        );

                        $this->errorCallback($error);

                        return false;
                }
        }

}
