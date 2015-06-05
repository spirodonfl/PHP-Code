<?php

/**
 * Defines Drupal\random_api\API\RestApi.
 */

namespace Drupal\random_api\API;

/**
 * External REST
 */
class RestApi {
  /**
   * The path prefix for all REST api endpoints.
   */
  const API_V1_PATH_PREFIX = 'api/v1';

  /**
   * The status code for a successful API request.
   */
  const API_STATUS_SUCCESS = 'success';

  /**
   * The status code for a failed API request.
   */
  const API_STATUS_FAILED = 'failed';

  /**
   * The OAuth content for random API calls.
   */
  const OAUTH_CONTEXT = 'random_api';

  /**
   * HTTP Status Codes that can be used in responses
   */
  const STATUS_CODE_OK = '200';
  const STATUS_CODE_CREATED = '201';
  const STATUS_CODE_ACCEPTED = '202';
  const STATUS_CODE_NO_CONTENT = '204';
  const STATUS_CODE_MOVED_PERMANENTLY = '301';
  const STATUS_CODE_FOUND = '302';
  const STATUS_CODE_BAD_REQUEST = '400';
  const STATUS_CODE_UNAUTHORIZED = '401';
  const STATUS_CODE_FORBIDDEN = '403';
  const STATUS_CODE_NOT_FOUND = '404';
  const STATUS_CODE_GONE = '410';
  const STATUS_CODE_INTERNAL_SERVER_ERROR = '500';
  const STATUS_CODE_NOT_IMPLEMENTED = '501';
  const STATUS_CODE_HTTP_NOT_SUPPORTED = '505';

  /**
   * Formats a successful API response.
   *
   * @param object $output
   *   The output data for the response.
   *
   * @return object
   *   A JSON object.
   */
  public static function responseSuccess($output) {
    //$output->status = self::API_STATUS_SUCCESS;
    return drupal_json_output($output);
  }

  /**
   * Formats a failed API response.
   *
   * @param object $output
   *   The output data for the response.
   *
   * @return object
   *   A JSON object.
   */
  public static function responseFail($output, $status_code = '401', $status_message = 'Unauthorized') {
    //drupal_add_http_header($status_code . ' ' . $status_message);
    header('HTTP/1.0 ' . $status_code . ' ' . $status_message);
    if (is_object($output)) {
      $output->status = self::API_STATUS_FAILED;
    }

    return drupal_json_output($output);
  }

  /**
   * Gets the request data for the current request.
   *
   * @return mixed
   *   The request data.
   */
  public static function requestData() {
    $request_data = FALSE;

    if (preg_match('/^application\/json/', $_SERVER['CONTENT_TYPE'])) {
      $request_data = json_decode(file_get_contents('php://input'));
    }
    else {
      $request_data = (object) $_REQUEST;
    }

    return $request_data;
  }

  /**
   * Dispatches the request for an API endpoint to the relevant controller.
   *
   * @param callable $controller_callback
   *   The callback that should be invoked for this endpoint.
   * @param array $arguments
   *   The arguments that should be passed on to the controller callback.
   *
   */
  public static function dispatchAPIEndpoint() {
    // First argument is controller callback.
    $arguments = func_get_args();

    $callback = array_shift($arguments);
    if (!is_callable($callback)) {
      watchdog('random_api', 'Unable to find controller for API endpoint: @controller',
        array('@controller' => $callback), WATCHDOG_ERROR);
      $output = new \stdClass();
      $output->message = 'Could not locate controller for endpoint.';
      print self::responseFail($output);
      exit(0);
    }

    if (user_is_anonymous()) {

      try {
        module_load_include('inc', 'oauth_common');

        list($signed, $consumer, $token) = oauth_common_verify_request();

        if (!$signed) {
          throw new \OAuthException('API Requests must be signed.');
        }

        if ($consumer == NULL) {
          throw new \OAuthException('Unable to verify user for request.');
        }

        if ($consumer->context !== self::OAUTH_CONTEXT) {
          throw new \OAuthException('The consumer is not valid in the random API context.');
        }

        // We are currently doing 2-legged OAuth, so there's no need to do token validation.
        // If the request was signed, a valid Drupal consumer was found, and the consumer has access
        // to the appropriate context, the request is authorized.
      }
      catch (\OAuthException $e) {
        watchdog('sym_api', 'Failed random API call: @reason', array('@reason' => $e->getMessage()), WATCHDOG_ERROR);
        $output = new \stdClass();
        $output->message = $e->getMessage();
        print self::responseFail($output);
        exit(0);
      }
    }

    print call_user_func_array($callback, $arguments);
    exit(0);
  }

  /**
   * Create a Drupal setting for the current Rest API path.
   */
  public static function setApiPathSetting() {
    drupal_add_js(array('random_rest_api_path' => self::API_V1_PATH_PREFIX), 'setting');
  }
}