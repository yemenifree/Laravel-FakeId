<?php namespace Propaganistas\LaravelFakeId\Tests;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;
use Propaganistas\LaravelFakeId\Facades\FakeId;
use Propaganistas\LaravelFakeId\Tests\Entities\Fake;
use Propaganistas\LaravelFakeId\Tests\Entities\Real;
use Symfony\Component\Debug\ExceptionHandler;

class FakeIdTest extends TestCase
{
    protected $oldExceptionHandler;

    /**
     *
     */
    public function setUp()
    {
        parent::setUp();

        $this->configureDatabase();

        // disable throw error (500 & 404 in our case) in laravel <= 5.3
        $this->disableExceptionHandling();

        // add middleware for support version laravel >= 5.3
        $middlewareBindings = version_compare($this->app->version(), '5.3.0') >= 0 ? 'Illuminate\Routing\Middleware\SubstituteBindings' : null;

        Route::model('real', 'Propaganistas\LaravelFakeId\Tests\Entities\Real');
        Route::fakeIdModel('fake', 'Propaganistas\LaravelFakeId\Tests\Entities\Fake');

        Route::get('real/{real}', ['as' => 'real', function ($real) {
            return $real->id;
        }, 'middleware'                 => $middlewareBindings]);

        Route::get('fake/{fake}', ['as' => 'fake', function ($fake) {
            return $fake->id;
        }, 'middleware'                 => $middlewareBindings]);
    }

    protected function configureDatabase()
    {
        $db = new DB;
        $db->addConnection([
            'driver'    => 'sqlite',
            'database'  => ':memory:',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
        ]);
        $db->bootEloquent();
        $db->setAsGlobal();

        DB::schema()->create('reals', function ($table) {
            $table->increments('id');
            $table->timestamps();
        });

        DB::schema()->create('fakes', function ($table) {
            $table->increments('id');
            $table->timestamps();
        });
    }

    public function testFacade()
    {
        $this->assertInstanceOf('Jenssegers\Optimus\Optimus', FakeId::getFacadeRoot());
    }

    public function testDefaultModelBindingStillWorks()
    {
        $model = Real::create([]);

        $expected = url('real/' . $model->getRouteKey());
        $actual = route('real', ['real' => $model]);

        $this->assertEquals($expected, $actual);
    }

    public function testFakeIdTraitEnforcesEncodedRouteKey()
    {
        $model = Fake::create([]);

        $this->assertNotEquals($model->getRouteKey(), $model->getKey());
        $this->assertEquals($model->getRouteKey(), app('fakeid')->encode($model->getKey()));
    }

    public function testFakeIdModelBindingWorks()
    {
        $model = Fake::create([]);

        $expected = url('fake/' . $model->getRouteKey());
        $actual = route('fake', ['fake' => $model]);

        $this->assertEquals($expected, $actual);
    }

    public function testResponseNotFoundWhenDecodeFailAndDebugOff()
    {
        $this->app['config']->set('app.debug', false);

        $response = $this->call('get', route('fake', ['fake' => 'not-number']));

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testResponseErrorWhenDecodeFailDebugOn()
    {
        $this->app['config']->set('app.debug', true);

        $response = $this->call('get', route('fake', ['fake' => 'not-number']));

        $this->assertEquals(500, $response->getStatusCode());

        $this->app['config']->set('app.debug', false);
    }

    public function testResponseFineWhenPassFakeModel()
    {
        $model = Fake::create([]);

        $response = $this->call('get', route('fake', ['fake' => $model]));

        $this->assertContains((string)$model->id, $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testResponseFineWhenPassNormalModel()
    {
        $model = Real::create([]);

        $response = $this->call('get', route('real', ['real' => $model]));

        $this->assertContains((string)$model->id, $response->getContent());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testResponseFailWhenPassModelId()
    {
        $model = Fake::create([]);

        $response = $this->call('get', route('fake', ['fake' => $model->id]));

        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * Disabling Exception Handling in Laravel
     *
     * author @adamwathan
     */
    protected function disableExceptionHandling()
    {
        $this->oldExceptionHandler = $this->app->make('\Symfony\Component\Debug\ExceptionHandler');
        $this->app->instance('\Symfony\Component\Debug\ExceptionHandler', new class extends Handler
        {
            public function __construct()
            {
            }

            public function report(\Exception $e)
            {
            }

            public function render($request, \Exception $e)
            {
                throw $e;
            }
        });
    }

    /**
     * author @adamwathan
     */
    protected function withExceptionHandling()
    {
        $this->app->instance('\Symfony\Component\Debug\ExceptionHandler', $this->oldExceptionHandler);

        return $this;
    }

    protected function getPackageProviders($app)
    {
        return [
            'Propaganistas\LaravelFakeId\FakeIdServiceProvider',
        ];
    }
}