<?php

namespace Symbid\Chainlink;

use Mockery\MockInterface;
use InvalidArgumentException;

class ContextTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var Context
     */
    protected $target;

    /**
     * Setup
     */
    protected function setUp()
    {
        parent::setUp();
        $this->target = new Context();
    }

    public function testAddHandler()
    {
        $handler = \Mockery::mock('Symbid\Chainlink\Handler\HandlerInterface');

        $this->target->addHandler($handler);

        $property = new \ReflectionProperty($this->target, 'handlers');
        $property->setAccessible(true);
        $registeredHandlers = $property->getValue($this->target);

        $this->assertCount(1, $registeredHandlers);

        $registeredHandlers = $property->getValue($this->target);
        $this->target->addHandler($handler);

        $this->assertCount(1, $registeredHandlers);
    }

    public function testAddHandlerWithPriority()
    {
        $handler1 = \Mockery::mock('testClass1', 'Symbid\Chainlink\Handler\HandlerInterface');
        $handler2 = \Mockery::mock('testClass2', 'Symbid\Chainlink\Handler\HandlerInterface');
        $handler3 = \Mockery::mock('testClass3', 'Symbid\Chainlink\Handler\HandlerInterface');
        $handler4 = \Mockery::mock('testClass4', 'Symbid\Chainlink\Handler\HandlerInterface');
        $this->target->addHandler($handler1);
        $this->target->addHandler($handler2, 9000);
        $this->target->addHandler($handler3, 100);
        $this->target->addHandler($handler4);

        $property = new \ReflectionProperty($this->target, 'handlers');
        $property->setAccessible(true);
        $registeredHandlers = $property->getValue($this->target);
        $this->assertInstanceOf('testClass2', $registeredHandlers[0]);
        $this->assertInstanceOf('testClass3', $registeredHandlers[1]);
        $this->assertInstanceOf('testClass1', $registeredHandlers[2]);
        $this->assertInstanceOf('testClass4', $registeredHandlers[3]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @dataProvider nanProvider
     */
    public function testAddHandlerWithInvalidPriority($nan)
    {
        $handler = \Mockery::mock('Symbid\Chainlink\Handler\HandlerInterface');

        $this->target->addHandler($handler, $nan);
    }

    public function nanProvider()
    {
        return [
            ['asdf'],
            ['0x'],
            ['12345asdf'],
            ['-e']
        ];
    }

    public function testHandlerSorting()
    {
        $input = [
            10 => ['bar', 'baz'],
            100 => ['foo'],
            1 => ['quux']
        ];
        $expectedOutput = ['foo', 'bar', 'baz', 'quux'];
        $method = new \ReflectionMethod($this->target, 'sortHandlers');
        $method->setAccessible(true);
        $output = $method->invoke($this->target, $input);
        $this->assertSame($expectedOutput, $output);
    }

    public function testGetHandlerFor()
    {
        $input = new \stdClass();

        $handlers = $this->buildHandlers(false, true, false, $input);

        $handler = $this->target->getHandlerFor($input);

        $this->assertEquals($handlers[1], $handler);
        $this->assertNotEquals($handlers[0], $handler);
    }

    public function testHandle()
    {
        $input = new \stdClass();
        $return = uniqid();

        $handlers = $this->buildHandlers(false, true, false, $input);
        $handlers[1]->shouldReceive('handle')->with($input)->andReturn($return);
        $handlers[0]->shouldReceive('handle')->never();
        $handlers[2]->shouldReceive('handle')->never();

        $this->assertEquals($return, $this->target->handle($input));
    }

    public function testGetAllHandlersFor()
    {
        $input = new \stdClass();

        $handlers = $this->buildHandlers(false, true, true, $input);

        $returnHandlers = $this->target->getAllHandlersFor($input);

        $this->assertCount(2, $returnHandlers);
        $this->assertContains($handlers[1], $returnHandlers);
        $this->assertContains($handlers[2], $returnHandlers);
    }

    /**
     * @throws NoHandlerException
     * @expectedException \Symbid\Chainlink\NoHandlerException
     */
    public function testGetAllHandlersForWithNoCompatibleHandler()
    {
        $input = new \stdClass();

        $handlers = $this->buildHandlers(false, false, false, $input);

        $returnHandlers = $this->target->getAllHandlersFor($input);
    }

    /**
     * @param bool $one
     * @param bool $two
     * @param bool $three
     * @param mixed $input
     * @return MockInterface []
     */
    public function buildHandlers($one, $two, $three, $input)
    {
        $handler1 = \Mockery::mock('Symbid\Chainlink\Handler\HandlerInterface');
        $handler1->shouldReceive('handles')->with($input)->andReturn($one);
        $handler2 = \Mockery::mock('Symbid\Chainlink\Handler\HandlerInterface');
        $handler2->shouldReceive('handles')->with($input)->andReturn($two);
        $handler3 = \Mockery::mock('Symbid\Chainlink\Handler\HandlerInterface');
        $handler3->shouldReceive('handles')->with($input)->andReturn($three);

        $this->target->addHandler($handler1);
        $this->target->addHandler($handler2);
        $this->target->addHandler($handler3);

        return [$handler1, $handler2, $handler3];
    }
}
