<?php

use Mockery as m;
use Lewis\OAuth2\Server\Storage\RedisScope;

class RedisScopeTest extends PHPUnit_Framework_TestCase {


	public function tearDown()
	{
		m::close();
	}


	public function setUp()
	{
		$this->redis = m::mock('Predis\Client');
		$this->server = m::mock('League\OAuth2\Server\AbstractServer');
		$this->storage = new RedisScope($this->redis);
		$this->storage->setServer($this->server);
	}


	public function testGetScopeReturnsNullForInvalidScope()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:scopes:foo')->andReturn(null);

		$this->assertNull($this->storage->get('foo'));
	}


	public function testGetScopeReturnsScopeEntity()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:scopes:foo')->andReturn('{"id":"foo","description":"foo"}');

		$scope = $this->storage->get('foo');

		$this->assertInstanceOf('League\OAuth2\Server\Entity\ScopeEntity', $scope);
		$this->assertEquals('foo', $scope->getId());
		$this->assertEquals('foo', $scope->getDescription());
	}


}
