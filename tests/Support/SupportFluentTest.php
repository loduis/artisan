<?php

use Illuminate\Support\Fluent;

class SupportFluentTest extends PHPUnit_Framework_TestCase {

	public function testAttributesAreSetByConstructor()
	{
		$array  = array('name' => 'Taylor', 'age' => 25);
		$fluent = new Fluent($array);

		$refl = new \ReflectionObject($fluent);
		$attributes = $refl->getProperty('attributes');
		$attributes->setAccessible(true);

		$this->assertEquals($array, $attributes->getValue($fluent));
		$this->assertEquals($array, $fluent->getAttributes());
	}


	public function testAttributesAreSetByConstructorGivenStdClass()
	{
		$array  = array('name' => 'Taylor', 'age' => 25);
		$fluent = new Fluent((object) $array);

		$refl = new \ReflectionObject($fluent);
		$attributes = $refl->getProperty('attributes');
		$attributes->setAccessible(true);

		$this->assertEquals($array, $attributes->getValue($fluent));
		$this->assertEquals($array, $fluent->getAttributes());
	}


	public function testAttributesAreSetByConstructorGivenArrayIterator()
	{
		$array  = array('name' => 'Taylor', 'age' => 25);
		$fluent = new Fluent(new FluentArrayIteratorStub($array));

		$refl = new \ReflectionObject($fluent);
		$attributes = $refl->getProperty('attributes');
		$attributes->setAccessible(true);

		$this->assertEquals($array, $attributes->getValue($fluent));
		$this->assertEquals($array, $fluent->getAttributes());
	}


	public function testGetMethodReturnsAttribute()
	{
		$fluent = new Fluent(array('name' => 'Taylor'));

		$this->assertEquals('Taylor', $fluent->get('name'));
		$this->assertEquals('Default', $fluent->get('foo', 'Default'));
		$this->assertEquals('Taylor', $fluent->name);
		$this->assertNull($fluent->foo);
	}


	public function testMagicMethodsCanBeUsedToSetAttributes()
	{
		$fluent = new Fluent;

		$fluent->name = 'Taylor';
		$fluent->developer();
		$fluent->age(25);

		$this->assertEquals('Taylor', $fluent->name);
		$this->assertTrue($fluent->developer);
		$this->assertEquals(25, $fluent->age);
		$this->assertInstanceOf('Illuminate\Support\Fluent', $fluent->programmer());
	}


	public function testIssetMagicMethod()
	{
		$array  = array('name' => 'Taylor', 'age' => 25);
		$fluent = new Fluent($array);

		$this->assertTrue(isset($fluent->name));

		unset($fluent->name);

		$this->assertFalse(isset($fluent->name));
	}


	public function testToArrayReturnsAttribute()
	{
		$array  = array('name' => 'Taylor', 'age' => 25);
		$fluent = new Fluent($array);

		$this->assertEquals($array, $fluent->toArray());
	}


	public function testToJsonEncodesTheToArrayResult()
	{
		$fluent = $this->getMock('Illuminate\Support\Fluent', array('toArray'));
		$fluent->expects($this->once())->method('toArray')->will($this->returnValue('foo'));
		$results = $fluent->toJson();

		$this->assertEquals(json_encode('foo'), $results);
	}

}


class FluentArrayIteratorStub implements \IteratorAggregate {
	protected $items = array();

	public function __construct(array $items = array())
	{
		$this->items = (array) $items;
	}

	public function getIterator()
	{
		return new \ArrayIterator($this->items);
	}
}
