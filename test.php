<?php
require_once 'PHPUnit/Framework.php';
require_once 'Hoptoad.php';
 
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/example.php';
 
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
  
    public function testTracer()
    {
      $expected_xml = <<<XML
        <backtrace>
          <line method="foo" file="foo.php" number="242"/>
          <line method="bar" file="bar.php" number="42"/>
        </backtrace>
XML;
      $this->assertXmlStringEqualsXmlString($expected_xml, $this->hoptoad->xml_backtrace());
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