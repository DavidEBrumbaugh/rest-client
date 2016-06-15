<?php

require_once( 'RestClient.interface.php' );

/**
* The PHPRestClient class uses CURL to implement the iRestCLient interface.
*/
class PHPRestClient implements iRestClient {
	protected $authentication_scheme;
	protected $authentication_parameters;
	protected $base_url;
	protected $accept_self_signed;
	protected $custom_headers;

	/**
	* __construct Create a REST client that uses CURL
	* @param string $base_url                Base URL to the REST Server
	* @param string $authentication_scheme     Type of Authentiation:
	*                                          Basic: Standard HTTP Authentication
	*                                          Digest: Standard HTTP Digest Authentication
	*                                          Header: Hader Authentication
	*                                          Query: Authentication with query string parameters
	*                                          cert: Autenticate with a certificate file
	* @param array  $authentication_parameters [Authentication Parameters depend on authentication scheme]
	* $authentication_scheme='Basic', $authentication_parameters['username'] = username;
	*                                 $authentication_parameters['password'] = password;
	*
	* $authentication_scheme='Digest', $authentication_parameters['digest_header'] = digest header;
	*                                  $authentication_parameters['digest_parameters']['username']
	*                                  $authentication_parameters['digest_parameters']['password']
	*                                  $authentication_parameters['digest_parameters']['url']
	*
	* $authentication_scheme='Header'  $authentication_parameters['header_key'] = value;
	* $authentication_scheme='Query' $authentication_parameters['query_key'] = value
	*
	* $autentication_scheme = 'cert' $authentication_parameters['cert_file'] = system path to cert file
	*                                $authentication_parameters['cert_password'] = password to access cert file
	*
	* @param bool $accept_self_signed - Set to true if you want to accept a self-signed certificate
	*
	*/
	public function __construct( $base_url, $authentication_scheme = 'Basic', $authentication_parameters = array(), $accept_self_signed = false ) {
		$this->auhtentication_scheme = $authentication_scheme;
		$this->authentication_parameters = $authentication_parameters;
		$this->base_url = $base_url;
		$this->accept_self_signed = $accept_self_signed;
		$this->custom_headers = null;
	}

	/**
	* Set custom headers for REST Call
	* @param  array() $headers $key=>$value pairs of header values
	* @return void
	*/
	public function custom_headers( $headers ) {
		$this->custom_headers = $headers;
	}

	/**
	* Impelments a REST GET HTTP Call with CURL
	* @param  string $path   path to resource - Should not include the base URL
	* @param  array $params  $key=>$value pairs of query string parameters
	* @return array         'code'=>'200', // Response code 200 success, 404 not found etc.
	*                       'headers'=>array('key'=>'value') // Response headers ad key / value array
	*                       'body' => 'content', // Response content. Usually a JSON or XML string.
	*                                            // You still need to parse the string after you get it.
	*/
	public function rest_get( $path, $params ) {
		$api_url = rtrim( $this->base_url,'/' ).'/'.ltrim( $path,'/' );
		$headers = false;
		if ( 'query' === strtolower( isset( $this->authentication_scheme ) ) ) {
			$params = array_merge( $params, $this->authentication_parameters );
		} else {
			$headers = $this->setupAutentication();
		}
		return $this->CURLHttp( 'GET', $api_url, $params, $headers );
	}

	/**
	* Impelments a REST PUT HTTP Call with CURL
	* @param  string $path   path to resource - Should not include the base URL
	* @param  array $body  $key=>$value pairs simlar to that of a POST
	* @return array         'code'=>'200', // Response code 200 success, 404 not found etc.
	*                       'headers'=>array('key'=>'value') // Response headers ad key / value array
	*                       'body' => 'content', // Response content. Usually a confirmation or ID - you will need to parse it
	*
	*/
	public function rest_put( $path, $body ) {
		$api_url = rtrim( $this->base_url,'/' ).'/'.ltrim( $path,'/' );
		if ( 'query' === strtolower( isset( $this->authentication_scheme ) ) ) {
			$api_url .= '?'. http_build_query( $this->authentication_parameters );
		}

		return $this->CURLHttp( 'PUT', $api_url,$body );
	}

	/**
	* Impelments a REST DELETE call with CURL
	* @param  string $path   path to resource - Should not include the base URL
	* @param  string $body (optional) Technically DELETE CAN take a body, so in might.
	*                       Same $key=>$value pairs as POST and PUT
	* @return array         'code'=>'200', // Response code i.e. 200 success, 404 not found etc.
	*                       'headers'=>array('key'=>'value') // Response headers ad key / value array
	*                       'body' => 'content', // Response content. Usually a confirmation or ID - you will need to parse it
	*/
	public function rest_delete( $path, $body = false ) {
		$api_url = rtrim( $this->base_url,'/' ).'/'.ltrim( $path,'/' );
		if ( 'query' === strtolower( isset( $this->authentication_scheme ) ) ) {
			$api_url .= '?'. http_build_query( $this->authentication_parameters );
		}
		return $this->CURLHttp( 'DELETE', $api_url, $body );
	}

	/**
	* Impelments a REST POST HTTP Call with CURL
	* @param  string $path   path to resource - Should not include the base URL
	* @param  array $body  $key=>$value pairs  for POST fields
	* @return array         'code'=>'200', // Response code 200 success, 404 not found etc.
	*                       'headers'=>array('key'=>'value') // Response headers ad key / value array
	*                       'body' => 'content', // Response content. Usually a confirmation or ID - you will need to parse it
	*
	*/
	public function rest_post( $path, $body ) {
		$api_url = rtrim( $this->base_url,'/' ).'/'.ltrim( $path,'/' );
		if ( 'query' === strtolower( isset( $this->authentication_scheme ) ) ) {
			$api_url .= '?'. http_build_query( $this->authentication_parameters );
		}
		return $this->CURLHttp( 'POST', $api_url, $body );
	}

	/**
	* CURLHttp Generalized call to CURL for HTTP request
	* @param string $method       GET,POST,PUT or DELETE
	* @param string $url          FULL url
	* @param array $data         Usually a $key=>$value pair array to send with requst.
	* @param array $headers      $key=>$value pair array to send as header with request
	* @param bool $authenticate (optional) Defaults to true.
	*                           If true, send authentiation information based on
	*                           authentication scheme.
	*/
	protected function CURLHttp( $method, $url, $data = false, $headers = false, $authenticate = true ) {
		$curl = curl_init();

		switch ( $method ) {
			case 'POST':
				curl_setopt( $curl, CURLOPT_POST, 1 );
				if ( $data ) {
					curl_setopt( $curl, CURLOPT_POSTFIELDS, $data );
				}
			break;
			case 'PUT':
				// Credit: http://www.lornajane.net/posts/2009/putting-data-fields-with-php-curl
				curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, 'PUT' );
				if ( $data ) {
					curl_setopt( $curl, CURLOPT_POSTFIELDS, http_build_query( $data ) );
				}
			break;
			case 'DELETE':
				curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, 'DELETE' );
				if ( $data ) { // http://stackoverflow.com/questions/299628/is-an-entity-body-allowed-for-an-http-delete-request
					curl_setopt( $curl, CURLOPT_POSTFIELDS, http_build_query( $data ) );
				}
			break;
			case 'GET':
				if ( $data ) {
					$url = sprintf( '%s?%s', $url, http_build_query( $data ) );
				}
		}

		curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, ! $this->accept_self_signed );
		curl_setopt( $curl, CURLOPT_URL, $url );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $curl, CURLOPT_VERBOSE, 1 );
		curl_setopt( $curl, CURLOPT_HEADER, 1 );

		if ( is_array( $this->custom_headers ) ) {
			if ( is_array( $headers ) ) {
				$headers = array_merge( $headers, $this->custom_headers );
			} else {
				$headers = $this->custom_headers;
			}
		}

		if ( $authenticate ) {
			$authentication_headers = $this->setupAutentication( $curl,$method );
			if ( is_array( $authentication_headers ) ) {
				if ( is_array( $headers ) ) {
					$headers = array_merge( $headers, $authentication_headers );
				} else {
					$headers = $authentication_headers;
				}
			}
		}
		if ( $headers ) {
			$this->setHeaders( $curl, $headers );
		}

		$info = curl_getinfo( $curl );
		$result = curl_exec( $curl );

		$result = $this->parse_curl_response( $curl, $result );
		curl_close( $curl );

		return $result;
	}


	// Credit: http://stackoverflow.com/questions/10589889/returning-header-as-array-using-curl
	// Credit: http://stackoverflow.com/questions/9183178/php-curl-retrieving-response-headers-and-body-in-a-single-request
/**
 * Put curl reesponse into array
 * @param  $ch    curl handle
 * @param  $response raw response from curl
 * @return array $code=>'200' /i.e. the http response code
 *               $header => array of key/value pairs in the respons header
 *               $body => body of response (un parsed)
 */
	protected function parse_curl_response( $ch, $response ) {
		$result = array();
		$headers = array();

		$result['code'] = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$header_size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
		$header_text = substr( $response, 0, $header_size );
		$body = substr( $response, $header_size );

		// Credit: https://core.trac.wordpress.org/browser/tags/4.5.2/src/wp-includes/class-http.php#L533
		// Tolerate line terminator: CRLF = LF (RFC 2616 19.3).
		$header_text = str_replace( "\r\n", "\n", $header_text );
		/*
		* Unfold folded header fields. LWS = [CRLF] 1*( SP | HT ) <US-ASCII SP, space (32)>,
		* <US-ASCII HT, horizontal-tab (9)> (RFC 2616 2.2).
		*/
		$header_text = preg_replace( '/\n[ \t]/', ' ', $header_text );
		// Create the headers array.
		$header_lines = explode( "\n", $header_text );

		foreach ( $header_lines as $i => $line ) {
			if ( 0 === $i ) {
				continue;
			} else {
				list ( $key, $value ) = explode( ': ', $line );
				$headers[ $key ] = $value;
			}
		}

		$result['headers'] = $headers;
		$result['body'] = $body;

		return $result;
	}

/**
 * Sets up authentication based on object authetication parameters
 * @param  $curl   curl handle
 * @param  string $method GET, PUT, POST or DELETE
 * @return headers for CURL to send with request
 */
	protected function setupAutentication( $curl, $method ) {
		$headers = false;
		switch ( strtolower( $this->auhtentication_scheme ) ) {
			case 'basic':
				$username = $this->authentication_parameters['username'];
				$password = $this->authentication_parameters['password'];
				$headers = array();
				$headers['Authorization'] = 'Basic '.base64_encode( "$username:$password" );
			break;
			case 'header':
				$headers = $this->authentication_parameters;
			break;
			case 'digest':
				$headers = $this->digest_authenticate( $method );
			break;
			case 'cert':
				curl_setopt( $curl, CURLOPT_SSLCERT, $this->authentication_parameters['cert_file'] );
				curl_setopt( $curl, CURLOPT_SSLCERTPASSWD, $this->authentication_parameters['cert_password'] );
			break;
		}
		return $headers;
	}

/**
 * Sets up headers to send with request
 * @param $curl  CURL handle
 * @param array $key_value_header Key value pairs to be sent with header
 */
	protected function setHeaders( $curl, $key_value_header ) {
		$headers = array();
		if ( is_array( $key_value_header ) ) {
			foreach ( $key_value_header as $key => $value ) {
				$headers[] = $key . ' : ' . $value;
			}
		}
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

	}

	// Credit: https://gist.github.com/funkatron/949952
	// Credit: https://www.sitepoint.com/understanding-http-digest-access-authentication/
	// See also: https://tools.ietf.org/html/rfc2617#page-6
	/**
	 * Handles digest authentication
	 * @param  string $method GET, PUT, POST or DELETE
	 * @return array $headers Digest authentication headers
	 */
	protected function digest_authenticate( $method ) {
		// If already pre-built:
		if ( isset( $this->authentication_parameters['digest_header'] ) ) {
				$headers['Authorization'] = 'Digest ' . $this->authentication_parameters['digest_header'];
		} else if ( is_array( $this->authentication_parameters['digest_parameters'] ) ) {

			$uri = isset( $this->authentication_parameters['digest_parameters']['url'] ) ?
			$this->authentication_parameters['digest_parameters']['url'] : $this->base_url;

			$response = $this->CURLHttp( $method, $uri, false, false, false );

			if ( ! is_array( $response ) ) {
				return false;
			} else if ( $response['response']['code'] == 401 ) {
				$username = $this->authentication_parameters['digest_parameters']['username'];
				$username = $this->authentication_parameters['digest_parameters']['password'];
				$headers = $response['headers'];
				if ( isset($headers['WWW-Authenticate'] ) ) {
					$auth_resp_header = $headers['WWW-Authenticate'];
					$auth_resp_header = explode( ',', preg_replace( '/^Digest/i', '', $auth_resp_header ) );
					$auth_pieces = array();
					foreach ( $auth_resp_header as &$piece ) {
						$piece = trim( $piece );
						$piece = explode( '=', $piece );
						$auth_pieces[ $piece[0] ] = trim( $piece[1], '"' );
					}
					// build response digest
					$nc = str_pad( '1', 8, '0', STR_PAD_LEFT );
					$A1 = md5( "{$username}:{$auth_pieces['realm']}:{$password}" );
					$A2 = md5( "{$method}:{$uri}" );
					$cnonce = uniqid();
					$auth_pieces['response'] = md5( "{$A1}:{$auth_pieces['nonce']}:{$nc}:{$cnonce}:{$auth_pieces['qop']}:${A2}" );
					$digest_header = "Digest username=\"{$username}\", realm=\"{$auth_pieces['realm']}\", nonce=\"{$auth_pieces['nonce']}\", uri=\"{$uri}\", cnonce=\"{$cnonce}\", nc={$nc}, qop=\"{$auth_pieces['qop']}\", response=\"{$auth_pieces['response']}\", opaque=\"{$auth_pieces['opaque']}\", algorithm=\"{$auth_pieces['algorithm']}\"";
					$headers['Authorization'] = $digest_header;
				} else {
					return false;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}

		return $headers;
	}

	/**
	* update_connection Change the connection settings for the REST client
	* @param [string] $base_url                  [Base URL of the REST Server]
	* @param string $authentication_scheme     [Type of Authentiation:
	*                                          Basic: Standard HTTP Authentication
	*                                          Digest: Standard HTTP Digest Authentication
	*                                          Header: Hader Authentication
	*                                          Query: Authentication with query string parameters
	*                                          cert: Autenticate with a certificate file 	]
	* @param array  $authentication_parameters [Authentication Parameters depend on authentication scheme]
	* $authentication_scheme='Basic', $authentication_parameters['username'] = username;
	*                                 $authentication_parameters['password'] = password;
	*
	* $authentication_scheme='Digest', $authentication_parameters['digest_header'] = digest header;
	*                                  $authentication_parameters['digest_parameters']['username']
	*                                  $authentication_parameters['digest_parameters']['password']
	*                                  $authentication_parameters['digest_parameters']['url']
	*
	* $authentication_scheme='Header'  $authentication_parameters['header_key'] = value;
	* $authentication_scheme='Query' $authentication_parameters['query_key'] = value
	*
	* $autentication_scheme = 'cert' $authentication_parameters['cert_file'] = system path to cert file
	*                                $authentication_parameters['cert_password'] = password to access cert file
	*
	* @param bool $accept_self_signed - Set to true if you want to accept a self-signed certificate
	*
	*/
	public function update_connection( $base_url, $authentication_scheme = 'Basic', $authentication_parameters = array(), $accept_self_signed = false ) {
		$this->auhtentication_scheme = $authentication_scheme;
		$this->authentication_parameters = $authentication_parameters;
		$this->base_url = $base_url;
		$this->accept_self_signed = $accept_self_signed;
		$this->custom_headers = null;
	}

	/**
	* update_connection Change the connection settings for the REST client
	* @param string $authentication_scheme     [Type of Authentiation:
	*                                          Basic: Standard HTTP Authentication
	*                                          Digest: Standard HTTP Digest Authentication
	*                                          Header: Hader Authentication
	*                                          Query: Authentication with query string parameters
	*                                          cert: Autenticate with a certificate file 	]
	* @param array  $authentication_parameters [Authentication Parameters depend on authentication scheme]
	* $authentication_scheme='Basic', $authentication_parameters['username'] = username;
	*                                 $authentication_parameters['password'] = password;
	*
	* $authentication_scheme='Digest', $authentication_parameters['digest_header'] = digest header;
	*                                  $authentication_parameters['digest_parameters']['username']
	*                                  $authentication_parameters['digest_parameters']['password']
	*                                  $authentication_parameters['digest_parameters']['url']
	*
	* $authentication_scheme='Header'  $authentication_parameters['header_key'] = value;
	* $authentication_scheme='Query' $authentication_parameters['query_key'] = value
	*
	* $autentication_scheme = 'cert' $authentication_parameters['cert_file'] = system path to cert file
	*                                $authentication_parameters['cert_password'] = password to access cert file
	*
	* @param bool $accept_self_signed - Set to true if you want to accept a self-signed certificate
	*
	*/
	public function update_authentication( $authentication_scheme = 'Basic', $authentication_parameters = array() ) {
		$this->auhtentication_scheme = $authentication_scheme;
		$this->authentication_parameters = $authentication_parameters;
	}
}
