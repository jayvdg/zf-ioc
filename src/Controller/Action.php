<?php

namespace Jeroenvandergeer\ZfIoc\Controller;

use Zend_Controller_Action;
use Zend_Controller_Request_Abstract;
use Zend_Controller_Response_Abstract;

class Action extends Zend_Controller_Action
{
    /**
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $container;

    /**
     * @var
     */
    protected $dispatchedAction;

    /**
     * @param \Zend_Controller_Request_Abstract  $request
     * @param \Zend_Controller_Response_Abstract $response
     * @param array                              $invokeArgs
     */
    public function __construct(
        Zend_Controller_Request_Abstract $request,
        Zend_Controller_Response_Abstract $response,
        array $invokeArgs = array()
    ) {
        parent::__construct($request, $response, $invokeArgs);

        $this->setContainer();
    }

    /**
     * @return \Illuminate\Contracts\Container\Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @return mixed
     */
    public function getDispatchedAction()
    {
        return $this->dispatchedAction;
    }

    /**
     * @param string $action
     */
    public function dispatch($action)
    {
        $this->setDispatchedAction($action);
        parent::dispatch('actionWrapper');
    }

    /**
     * @throws \Zend_Controller_Action_Exception
     */
    protected function actionWrapper()
    {
        if (!method_exists($this, $this->dispatchedAction)) {
            $this->__call($this->dispatchedAction, array());
        }

        $this->getContainer()->call(array($this, $this->dispatchedAction));
    }

    /**
     * @param $action
     */
    protected function setDispatchedAction($action)
    {
        $this->dispatchedAction = $action;
    }

    /**
     *
     */
    protected function setContainer()
    {
        $this->container = new \Illuminate\Container\Container();
    }
}