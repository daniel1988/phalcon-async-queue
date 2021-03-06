<?php
define('APP_PATH', dirname(__DIR__));

use Phalcon\DI;
use Phalcon\Loader;
use Phalcon\Mvc\Router;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Http\Response;
use Phalcon\Http\Request;
use Phalcon\Mvc\View;
use Phalcon\Db\Adapter\Pdo\Mysql as Database;
use Phalcon\Mvc\Application as BaseApplication;
use Phalcon\Mvc\Model\Metadata\Memory as MemoryMetaData;
use Phalcon\Mvc\Model\Manager as ModelsManager;

class Application extends BaseApplication
{

	protected function registerAutoloaders()
	{

		$loader = new Loader();

		$loader->registerDirs(array(
			'../apps/controllers/',
			'../apps/models/'
		));

		$loader->register();
	}

	/**
	 * This methods registers the services to be used by the application
	 */
	protected function registerServices()
	{

		$di = new DI();

		//Registering a router
		$di->set('router', function(){
			return new Router();
		});

		//Registering a dispatcher
		$di->set('dispatcher', function(){
			return new Dispatcher();
		});

		//Registering a Http\Response
		$di->set('response', function(){
			return new Response();
		});

		//Registering a Http\Request
		$di->set('request', function(){
			return new Request();
		});

		//Registering the view component
		$di->set('view', function(){
			$view = new View();
			$view->setViewsDir('../apps/views/');
			return $view;
		});

		$di->set('db', function(){
			return new Database(array(
				"host" => "localhost",
				"username" => "root",
				"password" => "",
				"dbname" => "invo"
			));
		});

		//Registering the Models-Metadata
		$di->set('modelsMetadata', function(){
			return new MemoryMetaData();
		});

		//Registering the Models Manager
		$di->set('modelsManager', function(){
			return new ModelsManager();
		});

		$this->setDI($di);
	}

	public function main()
	{

		$this->registerServices();
		$this->registerAutoloaders();

		echo $this->handle()->getContent();
	}
}

try {

	$application = new Application();
	$application->main();

} catch (\Exception $e){
	echo $e->getMessage();
}
