<?php

namespace Jeroenvandergeer\ZfIoc;

use Illuminate\Contracts\Container\Container;
use Zend_Controller_Action;
use Zend_Controller_Action_HelperBroker;
use Zend_Controller_Action_Interface;
use Zend_Controller_Dispatcher_Exception;
use Zend_Controller_Dispatcher_Standard;
use Zend_Controller_Request_Abstract;
use Zend_Controller_Response_Abstract;

/**
 * Class Dispatcher
 *
 * @package Jeroenvandergeer\ZfIoc
 */
class Dispatcher extends Zend_Controller_Dispatcher_Standard
{
    /**
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $container;

    /**
     * @param \Illuminate\Contracts\Container\Container $container
     * @param array                                     $params
     */
    public function __construct(Container $container, $params = array())
    {
        $this->setContainer($container);

        parent::__construct($params);
    }

    /**
     * @param \Illuminate\Contracts\Container\Container $container
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        // Pass to controller constructors
        $this->setParam('container', $container);
    }

    /**
     * @return \Illuminate\Contracts\Container\Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @param Zend_Controller_Request_Abstract  $request
     * @param Zend_Controller_Response_Abstract $response
     *
     * @throws \Exception
     * @throws Zend_Controller_Dispatcher_Exception
     * @throws \Zend_Controller_Exception
     */
    public function dispatch(Zend_Controller_Request_Abstract $request, Zend_Controller_Response_Abstract $response)
    {
        $this->setResponse($response);

        $controller = $this->getController($request);
        $action = $this->getActionMethod($request);

        $request->setDispatched(true);

        $obLevel = ob_get_level();
        if (!$this->outputBufferingIsDisabled()) {
            ob_start();
        }

        try {
            $this->dispatchToController($controller, $action);
        } catch (\Exception $e) {
            $this->cleanOutputBufferToLevel($obLevel);
            throw $e;
        }

        if (!$this->outputBufferingIsDisabled()) {
            $content = ob_get_clean();
            $response->appendBody($content);
        }

        // Destroy the page controller instance and reflection objects
        $controller = null;
    }

    /**
     * @param $controller
     * @param $action
     */
    protected function dispatchToController(Zend_Controller_Action $controller, $action)
    {
        $helperBroker = new Zend_Controller_Action_HelperBroker($controller);
        $helperBroker->notifyPreDispatch();

        $controller->preDispatch();
        if ($controller->getRequest()->isDispatched()) {
            if (!isset($classMethods) || null === $classMethods) {
                $classMethods = get_class_methods($controller);
            }

            if (!($this->getResponse()->isRedirect())) {
                if (in_array($action, $classMethods)) {
                    $this->call($controller, $action);
                } else {
                    $this->call($controller, '_call', array($action));
                }
            }
            $controller->postDispatch();
        }

        $helperBroker->notifyPostDispatch();
    }

    /**
     * @param Zend_Controller_Request_Abstract $request
     *
     * @return false|string
     * @throws Zend_Controller_Dispatcher_Exception
     * @throws \Zend_Controller_Exception
     */
    protected function getClassName(Zend_Controller_Request_Abstract $request)
    {
        if (!$this->isDispatchable($request)) {
            $controller = $request->getControllerName();
            if (!$this->getParam('useDefaultControllerAlways') && !empty($controller)) {
                require_once 'Zend/Controller/Dispatcher/Exception.php';
                throw new Zend_Controller_Dispatcher_Exception(
                    'Invalid controller specified ('.$request->getControllerName().')'
                );
            }

            $className = $this->getDefaultControllerClass($request);
        } else {
            $className = $this->getControllerClass($request);
            if (!$className) {
                $className = $this->getDefaultControllerClass($request);
            }
        }

        return $className;
    }

    /**
     * @param $className
     *
     * @return string
     */
    protected function getModuleClassName($className)
    {
        $moduleClassName = $className;
        if (($this->_defaultModule != $this->_curModule)
            || $this->getParam('prefixDefaultModule')
        ) {
            $moduleClassName = $this->formatClassName($this->_curModule, $className);

            return $moduleClassName;
        }

        return $moduleClassName;
    }

    /**
     * @param Zend_Controller_Request_Abstract $request
     *
     * @return mixed
     */
    protected function getController(Zend_Controller_Request_Abstract $request)
    {
        $className = $this->getClassName($request);
        $this->loadClass($className);
        $moduleClassName = $this->getModuleClassName($className);

        $controller = $this->getContainer()->make(
            $moduleClassName,
            array($request, $this->getResponse(), $this->getParams())
        );

        $this->assertControllerIsValid($controller);

        return $controller;
    }

    /**
     * @param $controller
     *
     * @throws Zend_Controller_Dispatcher_Exception
     */
    protected function assertControllerIsValid($controller)
    {
        if (!($controller instanceof Zend_Controller_Action_Interface) &&
            !($controller instanceof Zend_Controller_Action)
        ) {
            require_once 'Zend/Controller/Dispatcher/Exception.php';
            throw new Zend_Controller_Dispatcher_Exception(
                'Controller "'.get_class($controller).'" is not an instance of Zend_Controller_Action_Interface'
            );
        }
    }

    /**
     * @param \Zend_Controller_Action $controller
     * @param                         $action
     * @param array                   $params
     */
    protected function call(Zend_Controller_Action $controller, $action, $params = array())
    {
        $this->getContainer()->call(array($controller, $action), $params);
    }

    /**
     * @param $obLevel
     */
    protected function cleanOutputBufferToLevel($obLevel)
    {
        $curObLevel = ob_get_level();
        if ($curObLevel > $obLevel) {
            do {
                ob_get_clean();
                $curObLevel = ob_get_level();
            } while ($curObLevel > $obLevel);
        }
    }

    /**
     * @return mixed
     */
    protected function outputBufferingIsDisabled()
    {
        $disableOb = $this->getParam('disableOutputBuffering');

        return !empty($disableOb);
    }
}