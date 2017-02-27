<?php

use Phalcon\Mvc\View;
use Phalcon\Mvc\Controller;

class IndexController extends Controller
{

	public function indexAction()
	{
        echo 'indexAction----';
        file_put_contents(APP_PATH. '/tmp/index.log', date('Y-m-d H:i:s') . "\n" , FILE_APPEND);
        $this->view->setRenderLevel(View::LEVEL_NO_RENDER);
    }

    public function testAction()
    {
        echo 'testAction';
        file_put_contents(APP_PATH. '/tmp/test.log', date('Y-m-d H:i:s') . "\n" , FILE_APPEND);
        $this->view->setRenderLevel(View::LEVEL_NO_RENDER);
    }
}
