<?php
require_once 'PHPUnit/Framework.php';
require_once 'Hoptoad.php';
 
$_SERVER = array(
  'HTTP_HOST'    => 'localhost',
  'REQUEST_URI'  => '/example.php',
  'HTTP_REFERER' => 'http://localhost/reports/somthing',
);

$_SESSION = array(
  'var1' => 'val1',
  'var2' => 'val2',
);

class HoptoadTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
      Hoptoad::$test_mode = true;
      $trace = array(
        array(
          'class' => 'Hoptoad',
          'file'  => 'file.php',
          'line'  => 23,
          'function' => 'foo',
        ),
        array(
          'class' => 'Foo',
          'file'  => 'foo.php',
          'line'  => 242,
          'function' => 'foo',
        ),
        array(
          'class' => 'Bar',
          'file'  => 'bar.php',
          'line'  => 42,
          'function' => 'bar',
        ),
      );
      $this->hoptoad = new Hoptoad('ERROR', 'Something went wrong', 'foo', 23, $trace);
    }
  
    public function testXMLBacktrace()
    {
      $expected_xml = <<<XML
        <backtrace>
          <line method="foo" file="foo.php" number="242"/>
          <line method="bar" file="bar.php" number="42"/>
        </backtrace>
XML;
      $this->assertXmlStringEqualsXmlString($expected_xml, $this->hoptoad->xml_backtrace());
    }
    
    function testXMLSession()
    {
      $expected_xml = <<<XML
        <session>
          <var key="var1">val1</var>
          <var key="var2">val2</var>
        </session>
XML;
      $this->assertXmlStringEqualsXmlString($expected_xml, $this->hoptoad->xml_session());
    }
    
    function testXMLCgiData()
    {
      $expected_xml = <<<XML
        <cgi-data>
          <var key="HTTP_HOST">localhost</var>
          <var key="REQUEST_URI">/example.php</var>
          <var key="HTTP_REFERER">http://localhost/reports/somthing</var>
        </cgi-data>
XML;
      $this->assertXmlStringEqualsXmlString($expected_xml, $this->hoptoad->xml_cgi_data());
    }

    public function testNotificationBody() 
    {
      $xmllint = popen('xmllint --schema hoptoad_2_0.xsd -', 'w');
      if ($xmllint) {
        fwrite($xmllint, $this->hoptoad->notification_body());
        $status = pclose($xmllint);
        $this->assertEquals(0, $status, "XML output did not validate against schema.");
      } else {
        $this->fail("Couldn't run xmllint command.");
      }
    }
    
}
?>