<?php

use Mockery as m;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class DatabaseEloquentMorphToManyTest extends PHPUnit_Framework_TestCase {

	public function tearDown()
	{
		m::close();
	}


	public function testEagerConstraintsAreProperlyAdded()
	{
		$relation = $this->getRelation();
		$relation->getQuery()->shouldReceive('whereIn')->once()->with('taggables.taggable_id', array(1, 2));
		$relation->getQuery()->shouldReceive('where')->once()->with('taggables.taggable_type', get_class($relation->getParent()));
		$model1 = new EloquentMorphToManyModelStub;
		$model1->id = 1;
		$model2 = new EloquentMorphToManyModelStub;
		$model2->id = 2;
		$relation->addEagerConstraints(array($model1, $model2));
	}


	public function testAttachInsertsPivotTableRecord()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\MorphToMany', array('touchIfTouching'), $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('taggables')->andReturn($query);
		$query->shouldReceive('insert')->once()->with(array(array('taggable_id' => 1, 'taggable_type' => get_class($relation->getParent()), 'tag_id' => 2, 'foo' => 'bar')))->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->expects($this->once())->method('touchIfTouching');

		$relation->attach(2, array('foo' => 'bar'));
	}


	public function testDetachRemovesPivotTableRecord()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\MorphToMany', array('touchIfTouching'), $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('taggables')->andReturn($query);
		$query->shouldReceive('where')->once()->with('taggable_id', 1)->andReturn($query);
		$query->shouldReceive('where')->once()->with('taggable_type', get_class($relation->getParent()))->andReturn($query);
		$query->shouldReceive('whereIn')->once()->with('tag_id', array(1, 2, 3));
		$query->shouldReceive('delete')->once()->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->expects($this->once())->method('touchIfTouching');

		$this->assertTrue($relation->detach(array(1, 2, 3)));
	}


	public function testDetachMethodClearsAllPivotRecordsWhenNoIDsAreGiven()
	{
		$relation = $this->getMock('Illuminate\Database\Eloquent\Relations\MorphToMany', array('touchIfTouching'), $this->getRelationArguments());
		$query = m::mock('stdClass');
		$query->shouldReceive('from')->once()->with('taggables')->andReturn($query);
		$query->shouldReceive('where')->once()->with('taggable_id', 1)->andReturn($query);
		$query->shouldReceive('where')->once()->with('taggable_type', get_class($relation->getParent()))->andReturn($query);
		$query->shouldReceive('whereIn')->never();
		$query->shouldReceive('delete')->once()->andReturn(true);
		$relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
		$mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
		$relation->expects($this->once())->method('touchIfTouching');

		$this->assertTrue($relation->detach());
	}


	public function getRelation()
	{
		list($builder, $parent) = $this->getRelationArguments();

		return new MorphToMany($builder, $parent, 'taggable', 'taggables', 'taggable_id', 'tag_id');
	}


	public function getRelationArguments()
	{
		$parent = m::mock('Illuminate\Database\Eloquent\Model');
		$parent->shouldReceive('getKey')->andReturn(1);
		$parent->shouldReceive('getCreatedAtColumn')->andReturn('created_at');
		$parent->shouldReceive('getUpdatedAtColumn')->andReturn('updated_at');

		$builder = m::mock('Illuminate\Database\Eloquent\Builder');
		$related = m::mock('Illuminate\Database\Eloquent\Model');
		$builder->shouldReceive('getModel')->andReturn($related);

		$related->shouldReceive('getTable')->andReturn('tags');
		$related->shouldReceive('getKeyName')->andReturn('id');

		$builder->shouldReceive('join')->once()->with('taggables', 'tags.id', '=', 'taggables.tag_id');
		$builder->shouldReceive('where')->once()->with('taggables.taggable_id', '=', 1);
		$builder->shouldReceive('where')->once()->with('taggables.taggable_type', get_class($parent));

		return array($builder, $parent, 'taggable', 'taggables', 'taggable_id', 'tag_id', 'relation_name', false);
	}

}

class EloquentMorphToManyModelStub extends Illuminate\Database\Eloquent\Model {
	protected $guarded = array();
}
