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
  
  protected $error_class;
  protected $message;
  protected $file;
  protected $line;
  protected $backtrace;
  
  // This should be assigned to your hoptoad api key.
  public static $api_key = 'YOUR_HOPTOAD_API_KEY';
  
  // Whether we're running in debug mode.
  public static $debug = false;
  
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
  
  function __construct($error_class, $message, $file, $line, $backtrace) {
    $this->error_class = $error_class;
    $this->message     = $message;
    $this->file        = $file;
    $this->line        = $line;
    $this->backtrace   = $backtrace;
  }
  
  /**
   * Build a trace that is formatted in the way Hoptoad expects
   *
   * @param string $trace 
   * @return void
   * @author Rich Cavanaugh
   */
  function format_trace()
  {
    $trace = $this->backtrace;
    
    $lines = array(); 
    
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

  /**
   * Pass the error and environment data on to Hoptoad
   *
   * @package default
   * @author Rich Cavanaugh
   */
  function notify()
  {
    if (self::$debug) {
      return $this->notification_body();
    } else {
      $this->send_notification();
    }
  }
  
  function notification_body() {
    $trace = $this->format_trace($this->trace);
    array_unshift($trace, $this->file . ':' . $this->line);

    if (isset($_SESSION)) {
      $session = array('key' => session_id(), 'data' => $_SESSION);
    } else {
      $session = array();
    }

    $url = "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";

    $body = <<<EOF
<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<notice version=\"2.0\">
  <api-key>{self::$api_key}</api-key>
  <notifier>
    <name>php-hoptoad-notifier</name>
    <version>0.2.0</version>
    <url>http://github.com/westarete/php-hoptoad-notifier</url>
  </notifier>
</notice>
EOF;
  }
  
  function send_notification() {
  	$curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, 'http://hoptoadapp.com/notifier_api/v2/notices');
    curl_setopt($curl, CURLOPT_POST, 1);	
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10); // in seconds
	  curl_setopt($curl, CURLOPT_POSTFIELDS, $this->notification_body());
	  curl_setopt($curl, CURLOPT_HTTPHEADER, array("Accept: text/xml, application/xml", "Content-type: text/xml"));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_exec($curl);
    curl_close($curl);
  }
    
}