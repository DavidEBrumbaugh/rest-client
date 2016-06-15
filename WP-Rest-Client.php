<?php
require_once( 'RestClient.interface.php' );

/**
* WPRestClinent implements a REST client useing WordPress HTTP library
*/

class WPRestClient implements iRestClient{

	protected $authentication_scheme;
	protected $authentication_parameters;
	protected $base_url;
	protected $accept_self_signed;

	/**
	* __construct Create a REST client that uses WordPress HTTP calls
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
	}

	/**
	 * Impelments a REST PUT HTTP Call using wp_remote_request
	 * * 	Note implements rest_put_args filters to update arguments if plugin or theme requires
	 * @param  string $path   path to resource - Should not include the base URL
	 * @param  array $body  $key=>$value pairs simlar to that of a POST
	 * @return array         'code'=>'200', // Response code 200 success, 404 not found etc.
	 *                       'headers'=>array('key'=>'value') // Response headers ad key / value array
	 *                       'body' => 'content', // Response content. Usually a confirmation or ID - you will need to parse it
	 *
	 */
	public function rest_put( $path, $body ) {

		$api_url = rtrim( $this->base_url,'/' ).'/'.ltrim( $path,'/' );
		$headers = $this->autentication_headers();

		$req_args = array(
			'method' => 'PUT',
			'body' => $body,
			'headers' => $headers,
			'sslverify' => ! $this->accept_self_signed,
		);

		$req_args = apply_filters( 'rest_put_args', $req_args );

		// make the remote request
		$result = wp_remote_request( esc_url( $api_url ), $req_args );

		return $result;
	}

	/**
	 * Impelments a REST DELETE call using wp_remote_request
	 * 	Note implements: rest_delete_args filters to update arguments if plugin or theme requires
	 * @param  string $path   path to resource - Should not include the base URL
	 * @param  string $body (optional) Technically DELETE CAN take a body, so in might.
	 *                       Same $key=>$value pairs as POST and PUT
	 * @return array         'code'=>'200', // Response code i.e. 200 success, 404 not found etc.
	 *                       'headers'=>array('key'=>'value') // Response headers ad key / value array
	 *                       'body' => 'content', // Response content. Usually a confirmation or ID - you will need to parse it
	 */
	public function rest_delete( $path, $body ) {
		$api_url = $this->authentication_query( rtrim( $this->base_url,'/' ).'/'.ltrim( $path,'/' ) );
		$headers = $this->autenticationHeaders();

		$req_args = array(
			'method' => 'DELETE',
			'body' => $body,
			'headers' => $headers,
			'sslverify' => ! $this->accept_self_signed,
		);

		$req_args = apply_filters( 'rest_delete_args', $req_args );
		// make the remote request
		$result = wp_remote_request( esc_url( $api_url ), $req_args );

		return $result;
	}

	/**
	 * Impelments a REST POST HTTP Call using wp_remote_post
	 * 	Note implements: rest_post_args filters to update arguments if plugin or theme requires
	 * @param  string $path   path to resource - Should not include the base URL
	 * @param  array $body  $key=>$value pairs  for POST fields
	 * @return array         'code'=>'200', // Response code 200 success, 404 not found etc.
	 *                       'headers'=>array('key'=>'value') // Response headers ad key / value array
	 *                       'body' => 'content', // Response content. Usually a confirmation or ID - you will need to parse it
	 *
	 */
	public function rest_post( $path, $body ) {
		$api_url = $this->authentication_query( rtrim( $this->base_url,'/' ).'/'.ltrim( $path,'/' ) );
		$headers = $this->autenticationHeaders();

		$args = array(
			'headers' => $headers,
			'body' => $body,
			'sslverify' => ! $this->accept_self_signed,

		);

		$args = apply_filters( 'rest_post_args', $args );
		$results = wp_remote_post( esc_url( $api_url ), $args );

		return $results;
	}

	/**
	 * Impelments a REST GET HTTP Call using wp_remote_get
	 * @param  string $path   path to resource - Should not include the base URL
	 * @param  array $params  $key=>$value pairs of query string parameters
	 * @return array         'code'=>'200', // Response code 200 success, 404 not found etc.
	 *                       'headers'=>array('key'=>'value') // Response headers ad key / value array
	 *                       'body' => 'content', // Response content. Usually a JSON or XML string.
	 *                                            // You still need to parse the string after you get it.
	 */
	public  function rest_get( $path, $params ) {
		$api_url = rtrim( $this->base_url,'/' ).'/'.ltrim( $path,'/' );
		$headers = $this->autenticationHeaders();

		$args = array(
			'headers' => $headers,
		);

		$args = apply_filters( 'rest_get_args', $args );
		if ( ! empty( $params ) ) {
			$q = http_build_query( $params );
			$api_url = $api_url.'?'.$q;
		}

		$api_url = $this->authentication_query( $api_url );

		$results = wp_remote_get( $api_url,$args );

		return $results;
	}

/**
 * Builds query based authentication string
 * @param  string $url FULL url being queried
 * @return $url with query string parameters
 */
	protected function authentication_query( $url ) {
		if ( is_array( $this->authentication_parameters ) && 'query' === strtolower( $this->authentication_scheme ) ) {
			$query_string = http_build_query( $this->authentication_parameters );
			if ( strstr( $url,'?' ) ) {
				$url .= '&'.$query_string;
			} else {
				$url .= '?'.$query_string;
			}
		}
		return $url;
	}

/**
 * Builds authenticatino headers
 * @param  string $method GET,PUT, POST or DELETE
 * @return array  http request headers as key=>value pair
 */
	protected function autentication_headers( $method ) {
		$headers = array();
		switch ( strtolower( $this->auhtentication_scheme ) ) {
			case 'basic':
				$username = $this->authentication_parameters['username'];
				$password = $this->authentication_parameters['password'];
				$headers['Authorization'] = 'Basic '.base64_encode( "$username:$password" );
			break;
			case 'header':
				$headers = $this->authentication_parameters;
			break;
			case 'digest':
				$headers = $this->digest_authenticate( $method );
			break;
		}

		return $headers;
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
			$args = array();
			$uri = isset( $this->authentication_parameters['digest_parameters']['url'] ) ?
			$this->authentication_parameters['digest_parameters']['url'] : $this->base_url;

			$req_args = array(
				'method' => $method,
				'sslverify' => ! $this->accept_self_signed,
			);

			$req_args = apply_filters( 'rest_authenticate_args', $req_args );

			$response = wp_remote_request( $uri, $req_args );
			if ( ! is_array( $response ) ) {
				return false;
			} else if ( 401 == $response['response']['code'] ) {
				$username = $this->authentication_parameters['digest_parameters']['username'];
				$username = $this->authentication_parameters['digest_parameters']['password'];
				$headers = $response['headers'];
				if ( isset( $headers['WWW-Authenticate'] ) ) {
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
}
