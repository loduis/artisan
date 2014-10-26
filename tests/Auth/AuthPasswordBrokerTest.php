<?php

use Mockery as m;
use Illuminate\Contracts\Auth\PasswordBroker;

class AuthPasswordBrokerTest extends PHPUnit_Framework_TestCase {

	public function tearDown()
	{
		m::close();
	}


	public function testIfUserIsNotFoundErrorRedirectIsReturned()
	{
		$mocks = $this->getMocks();
		$broker = $this->getMock('Illuminate\Auth\Passwords\PasswordBroker', array('getUser', 'makeErrorRedirect'), array_values($mocks));
		$broker->expects($this->once())->method('getUser')->will($this->returnValue(null));

		$this->assertEquals(PasswordBroker::INVALID_USER, $broker->sendResetLink(array('credentials')));
	}


	/**
	 * @expectedException UnexpectedValueException
	 */
	public function testGetUserThrowsExceptionIfUserDoesntImplementCanResetPassword()
	{
		$broker = $this->getBroker($mocks = $this->getMocks());
		$mocks['users']->shouldReceive('retrieveByCredentials')->once()->with(array('foo'))->andReturn('bar');

		$broker->getUser(array('foo'));
	}


	public function testUserIsRetrievedByCredentials()
	{
		$broker = $this->getBroker($mocks = $this->getMocks());
		$mocks['users']->shouldReceive('retrieveByCredentials')->once()->with(array('foo'))->andReturn($user = m::mock('Illuminate\Contracts\Auth\CanResetPassword'));

		$this->assertEquals($user, $broker->getUser(array('foo')));
	}


	public function testBrokerCreatesTokenAndRedirectsWithoutError()
	{
		$mocks = $this->getMocks();
		$broker = $this->getMock('Illuminate\Auth\Passwords\PasswordBroker', array('emailResetLink', 'getUri'), array_values($mocks));
		$mocks['users']->shouldReceive('retrieveByCredentials')->once()->with(array('foo'))->andReturn($user = m::mock('Illuminate\Contracts\Auth\CanResetPassword'));
		$mocks['tokens']->shouldReceive('create')->once()->with($user)->andReturn('token');
		$callback = function() {};
		$broker->expects($this->once())->method('emailResetLink')->with($this->equalTo($user), $this->equalTo('token'), $this->equalTo($callback));

		$this->assertEquals(PasswordBroker::RESET_LINK_SENT, $broker->sendResetLink(array('foo'), $callback));
	}


	public function testMailerIsCalledWithProperViewTokenAndCallback()
	{
		unset($_SERVER['__password.reset.test']);
		$broker = $this->getBroker($mocks = $this->getMocks());
		$callback = function($message, $user) { $_SERVER['__password.reset.test'] = true; };
		$user = m::mock('Illuminate\Contracts\Auth\CanResetPassword');
		$mocks['mailer']->shouldReceive('send')->once()->with('resetLinkView', array('token' => 'token', 'user' => $user), m::type('Closure'))->andReturnUsing(function($view, $data, $callback)
		{
			return $callback;
		});
		$user->shouldReceive('getEmailForPasswordReset')->once()->andReturn('email');
		$message = m::mock('StdClass');
		$message->shouldReceive('to')->once()->with('email');
		$result = $broker->emailResetLink($user, 'token', $callback);
		call_user_func($result, $message);

		$this->assertTrue($_SERVER['__password.reset.test']);
	}


	public function testRedirectIsReturnedByResetWhenUserCredentialsInvalid()
	{
		$broker = $this->getBroker($mocks = $this->getMocks());
		$mocks['users']->shouldReceive('retrieveByCredentials')->once()->with(array('creds'))->andReturn(null);

		$this->assertEquals(PasswordBroker::INVALID_USER, $broker->reset(array('creds'), function() {}));
	}


	public function testRedirectReturnedByRemindWhenPasswordsDontMatch()
	{
		$creds = array('password' => 'foo', 'password_confirmation' => 'bar');
		$broker = $this->getBroker($mocks = $this->getMocks());
		$mocks['users']->shouldReceive('retrieveByCredentials')->once()->with($creds)->andReturn($user = m::mock('Illuminate\Contracts\Auth\CanResetPassword'));

		$this->assertEquals(PasswordBroker::INVALID_PASSWORD, $broker->reset($creds, function() {}));
	}


	public function testRedirectReturnedByRemindWhenPasswordNotSet()
	{
		$creds = array('password' => null, 'password_confirmation' => null);
		$broker = $this->getBroker($mocks = $this->getMocks());
		$mocks['users']->shouldReceive('retrieveByCredentials')->once()->with($creds)->andReturn($user = m::mock('Illuminate\Contracts\Auth\CanResetPassword'));

		$this->assertEquals(PasswordBroker::INVALID_PASSWORD, $broker->reset($creds, function() {}));
	}


	public function testRedirectReturnedByRemindWhenPasswordsLessThanSixCharacters()
	{
		$creds = array('password' => 'abc', 'password_confirmation' => 'abc');
		$broker = $this->getBroker($mocks = $this->getMocks());
		$mocks['users']->shouldReceive('retrieveByCredentials')->once()->with($creds)->andReturn($user = m::mock('Illuminate\Contracts\Auth\CanResetPassword'));

		$this->assertEquals(PasswordBroker::INVALID_PASSWORD, $broker->reset($creds, function() {}));
	}


	public function testRedirectReturnedByRemindWhenPasswordDoesntPassValidator()
	{
		$creds = array('password' => 'abcdef', 'password_confirmation' => 'abcdef');
		$broker = $this->getBroker($mocks = $this->getMocks());
		$broker->validator(function($credentials) { return strlen($credentials['password']) >= 7; });
		$mocks['users']->shouldReceive('retrieveByCredentials')->once()->with($creds)->andReturn($user = m::mock('Illuminate\Contracts\Auth\CanResetPassword'));

		$this->assertEquals(PasswordBroker::INVALID_PASSWORD, $broker->reset($creds, function() {}));
	}


	public function testRedirectReturnedByRemindWhenRecordDoesntExistInTable()
	{
		$creds = array('token' => 'token');
		$broker = $this->getMock('Illuminate\Auth\Passwords\PasswordBroker', array('validateNewPassword'), array_values($mocks = $this->getMocks()));
		$mocks['users']->shouldReceive('retrieveByCredentials')->once()->with(array_except($creds, array('token')))->andReturn($user = m::mock('Illuminate\Contracts\Auth\CanResetPassword'));
		$broker->expects($this->once())->method('validateNewPassword')->will($this->returnValue(true));
		$mocks['tokens']->shouldReceive('exists')->with($user, 'token')->andReturn(false);

		$this->assertEquals(PasswordBroker::INVALID_TOKEN, $broker->reset($creds, function() {}));
	}


	public function testResetRemovesRecordOnReminderTableAndCallsCallback()
	{
		unset($_SERVER['__password.reset.test']);
		$broker = $this->getMock('Illuminate\Auth\Passwords\PasswordBroker', array('validateReset', 'getPassword', 'getToken'), array_values($mocks = $this->getMocks()));
		$broker->expects($this->once())->method('validateReset')->will($this->returnValue($user = m::mock('Illuminate\Contracts\Auth\CanResetPassword')));
		$mocks['tokens']->shouldReceive('delete')->once()->with('token');
		$callback = function($user, $password)
		{
			$_SERVER['__password.reset.test'] = compact('user', 'password');
			return 'foo';
		};

		$this->assertEquals(PasswordBroker::PASSWORD_RESET, $broker->reset(array('password' => 'password', 'token' => 'token'), $callback));
		$this->assertEquals(array('user' => $user, 'password' => 'password'), $_SERVER['__password.reset.test']);
	}


	protected function getBroker($mocks)
	{
		return new \Illuminate\Auth\Passwords\PasswordBroker($mocks['tokens'], $mocks['users'], $mocks['mailer'], $mocks['view']);
	}


	protected function getMocks()
	{
		$mocks = array(
			'tokens' => m::mock('Illuminate\Auth\Passwords\TokenRepositoryInterface'),
			'users'     => m::mock('Illuminate\Auth\UserProviderInterface'),
			'mailer'    => m::mock('Illuminate\Contracts\Mail\Mailer'),
			'view'      => 'resetLinkView',
		);

		return $mocks;
	}

}
