<?php

/*
An example of the type of request that we'll want to generate and send to hoptoad:

<?xml version="1.0" encoding="UTF-8"?>
<notice version="2.0">
  <api-key>76fdb93ab2cf276ec080671a8b3d3866</api-key>
  <notifier>
    <name>Hoptoad Notifier</name>
    <version>1.2.4</version>
    <url>http://hoptoadapp.com</url>
  </notifier>
  <error>
    <class>RuntimeError</class>
    <message>RuntimeError: I've made a huge mistake</message>
    <backtrace>
      <line method="public" file="/testapp/app/models/user.rb" number="53"/>
      <line method="index" file="/testapp/app/controllers/users_controller.rb" number="14"/>
    </backtrace>
  </error>
  <request>
    <url>http://example.com</url>
    <component/>
    <action/>
    <cgi-data>
      <var key="SERVER_NAME">example.org</var>
      <var key="HTTP_USER_AGENT">Mozilla</var>
    </cgi-data>
  </request>
  <server-environment>
    <project-root>/testapp</project-root>
    <environment-name>production</environment-name>
  </server-environment>
</notice>

*/

class Hoptoad
{
  /**
   * Install the error and exception handlers that connect to Hoptoad
   *
   * @return void
   * @author Rich Cavanaugh
   */
  public static function installHandlers($api_key=NULL)
  {
    if (isset($api_key)) define('HOPTOAD_API_KEY', $api_key);
    
    set_error_handler(array("Hoptoad", "errorHandler"));
    set_exception_handler(array("Hoptoad", "exceptionHandler"));
  }
  
  /**
   * Handle a php error
   *
   * @param string $code 
   * @param string $message 
   * @param string $file 
   * @param string $line 
   * @return void
   * @author Rich Cavanaugh
   */
  public static function errorHandler($code, $message, $file, $line)
  {
    if ($code == E_STRICT) return;
	$trace = Hoptoad::tracer();
    Hoptoad::notifyHoptoad(HOPTOAD_API_KEY, $message, $file, $line, $trace, null);
  }
  
  /**
   * Handle a raised exception
   *
   * @param string $exception 
   * @return void
   * @author Rich Cavanaugh
   */
  public static function exceptionHandler($exception)
  {
    $trace = Hoptoad::tracer($exception->getTrace());
    Hoptoad::notifyHoptoad(HOPTOAD_API_KEY, $exception->getMessage(), $exception->getFile(), $exception->getLine(), $trace, null);
  }
  
  /**
   * Pass the error and environment data on to Hoptoad
   *
   * @package default
   * @author Rich Cavanaugh
   */
  public static function notifyHoptoad($api_key, $message, $file, $line, $trace, $error_class=null)
  {
    array_unshift($trace, "$file:$line");
    
    if (isset($_SESSION)) {
      $session = array('key' => session_id(), 'data' => $_SESSION);
    } else {
      $session = array();
    }
    
    $url = "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
    $body = array(
      'api_key'         => $api_key,
      'error_class'     => $error_class,
      'error_message'   => $message,
      'backtrace'       => $trace,
      'request'         => array("params" => $_REQUEST, "url" => $url),
      'session'         => $session,
      'environment'     => $_SERVER
    );
	$yaml = Spyc::YAMLDump(array("notice" => $body),4,60);

	$curlHandle = curl_init(); // init curl

    // cURL options
    curl_setopt($curlHandle, CURLOPT_URL, 'http://hoptoadapp.com/notifier_api/v2/notices'); // set the url to fetch
    curl_setopt($curlHandle, CURLOPT_POST, 1);	
    curl_setopt($curlHandle, CURLOPT_HEADER, 0);
    curl_setopt($curlHandle, CURLOPT_TIMEOUT, 10); // time to wait in seconds
	curl_setopt($curlHandle, CURLOPT_POSTFIELDS,  $yaml);
	curl_setopt($curlHandle, CURLOPT_HTTPHEADER, array("Accept: text/xml, application/xml", "Content-type: application/x-yaml"));
    curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);

    curl_exec($curlHandle);   // Make the call for sending the SMS
    curl_close($curlHandle);  // Close the connection 
  }
  
  /**
   * Build a trace that is formatted in the way Hoptoad expects
   *
   * @param string $trace 
   * @return void
   * @author Rich Cavanaugh
   */
  public static function tracer($trace = NULL)
  {
    $lines = Array(); 

    $trace = $trace ? $trace : debug_backtrace();
    
    $indent = '';
    $func = '';
    
    foreach($trace as $val) {
      if (isset($val['class']) && $val['class'] == 'Hoptoad') continue;
      
      $file = isset($val['file']) ? $val['file'] : 'Unknown file';
      $line_number = isset($val['line']) ? $val['line'] : '';
      $func = isset($val['function']) ? $val['function'] : '';
      $class = isset($val['class']) ? $val['class'] : '';
      
      $line = $file;
      if ($line_number) $line .= ':' . $line_number;
      if ($func) $line .= ' in function ' . $func;
      if ($class) $line .= ' in class ' . $class;
      
      $lines[] = $line;
    }
    
    return $lines;
  }  
}