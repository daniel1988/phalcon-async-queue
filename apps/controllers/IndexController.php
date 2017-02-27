<?php

use Phalcon\Mvc\Controller;

class IndexController extends Controller
{

	public function indexAction()
	{
        // echo date('Y-m-d H:i:s');
        //
        file_put_contents(APP_PATH. '/tmp/test.log', date('Y-m-d H:i:s') . "\n" , FILE_APPEND);
	}
}
