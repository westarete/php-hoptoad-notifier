<?php
require_once 'PHPUnit/Framework.php';
require_once 'Hoptoad.php';
 
class HoptoadTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
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
      $trace = $this->hoptoad->format_trace();
      $this->assertEquals(2, sizeof($trace));
      $this->assertEquals("foo.php:242 in function foo in class Foo", $trace[0]);
      $this->assertEquals("bar.php:42 in function bar in class Bar", $trace[1]);
    }
}
?>