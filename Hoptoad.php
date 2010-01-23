<?php

class Hoptoad
{
  
  protected $error_class;
  protected $message;
  protected $file;
  protected $line;
  protected $trace;
  
  // This should be assigned to your hoptoad api key.
  public static $api_key = 'YOUR_HOPTOAD_API_KEY';
  
  // Whether we're running in test mode.
  public static $test_mode = false;
  
  /**
   * Install the error and exception handlers that connect to Hoptoad.
   */
  static function install_handlers()
  {
    set_error_handler(array("Hoptoad", "error_handler"));
    set_exception_handler(array("Hoptoad", "exception_handler"));
  }
  
  /**
   * Callback for PHP error handler.
   *
   * @param string $code 
   * @param string $message 
   * @param string $file 
   * @param string $line 
   * @return void
   */
  static function error_handler($code, $message, $file, $line)
  {
    $hoptoad = new Hoptoad($code, $message, $file, $line, debug_backtrace());
    $hoptoad->notify();
  }
  
  /**
   * Handle a raised exception
   *
   * @param string $exception 
   * @return void
   * @author Rich Cavanaugh
   */
  static function exception_handler($exception)
  {
    $hoptoad = new Hoptoad('Exception', $exception->getMessage(), $exception->getFile(), $exception->getLine(), $exception->getTrace());
    $hoptoad->notify();
  }
  
  function __construct($error_class, $message, $file, $line, $trace) {
    $this->error_class = $error_class;
    $this->message     = $message;
    $this->file        = $file;
    $this->line        = $line;
    $this->trace       = $trace;
  }
  
  /**
   * Build a trace that is formatted in the way Hoptoad expects
   *
   * @param string $trace 
   * @return void
   * @author Rich Cavanaugh
   */
  function xml_backtrace()
  {
    $trace = $this->trace;
    $xml = "<backtrace>\n";
    foreach($trace as $val) {
      // Skip the portion of the backtrace that originated from within 
      // this class.
      if (isset($val['class']) && $val['class'] == 'Hoptoad') {
        continue;
      }
      
      $file   = isset($val['file'])     ? $val['file']     : '';
      $number = isset($val['line'])     ? $val['line']     : '';
      $method = isset($val['function']) ? $val['function'] : '';
      $class  = isset($val['class'])    ? $val['class']    : '';
      
      $xml .= "      <line method=\"$method\" file=\"$file\" number=\"$number\"/>\n";
    }
    $xml .= "    </backtrace>";
    
    return $xml;
  }

  /**
   * Pass the error and environment data on to Hoptoad
   *
   * @package default
   * @author Rich Cavanaugh
   */
  function notify()
  {
    $body = $this->notification_body();
    if (self::$test_mode) {
      return $body;
    } else {
    	$curl = curl_init();
      curl_setopt($curl, CURLOPT_URL, 'http://hoptoadapp.com/notifier_api/v2/notices');
      curl_setopt($curl, CURLOPT_POST, 1);	
      curl_setopt($curl, CURLOPT_HEADER, 0);
      curl_setopt($curl, CURLOPT_TIMEOUT, 10); // in seconds
  	  curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
  	  curl_setopt($curl, CURLOPT_HTTPHEADER, array("Accept: text/xml, application/xml", "Content-type: text/xml"));
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_exec($curl);
      curl_close($curl);
    }
  }
  
  function params() {
    return array_merge((array)$_GET, (array)$_POST);
  }
  
  function session() {
    return $_SESSION;
  }
  
  function cgi_data() {
    return $_SERVER;
  }
  
  function xml_params() {
    return $this->xml_keys_and_values('params', $this->params());
  }

  function xml_session() {
    return $this->xml_keys_and_values('session', $this->session());
  }
  
  function xml_cgi_data() {
    return $this->xml_keys_and_values('cgi-data', $this->cgi_data());
  }
  
  function request_uri() {
    if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
      $protocol = 'https';
    } else {
      $protocol = 'http';
    }
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $path = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $query_string = isset($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';
    return "{$protocol}://{$host}{$path}{$query_string}";
  }
  
  function notification_body() {
  
    $url = $this->request_uri();
    $api_key = self::$api_key;
    $error_class = $this->error_class;
    $message = $this->message;
    $xml_trace = $this->xml_backtrace();
    $xml_params = $this->xml_params();
    $xml_session = $this->xml_session();
    $xml_cgi_data = $this->xml_cgi_data();

    return <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<notice version="2.0">
  <api-key>{$api_key}</api-key>
  <notifier>
    <name>php-hoptoad-notifier</name>
    <version>0.2.0</version>
    <url>http://github.com/westarete/php-hoptoad-notifier</url>
  </notifier>
  <error>
    <class>{$error_class}</class>
    <message>{$message}</message>
    {$xml_trace}
  </error>
  <request>
    <url>{$url}</url>
    <component></component>
    <action></action>
    {$xml_params}
    {$xml_session}
    {$xml_cgi_data}
  </request>
  <server-environment>
    <project-root>/testapp</project-root>
    <environment-name>production</environment-name>
  </server-environment>
</notice>
EOF;
  }
  
  function xml_keys_and_values($parent, $ary)
  {
    if ($ary) {
      $xml = "<{$parent}>\n";
      foreach ($ary as $key => $value) {
        $xml .= "      <var key=\"{$key}\">{$value}</var>\n";
      }
      $xml .= "    </{$parent}>";
    } else {
      $xml = '';
    }
    return $xml;
  }
  
}