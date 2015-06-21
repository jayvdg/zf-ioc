<?php

namespace Jeroenvandergeer\ZfIoc;

use Zend_Controller_Action;
use Zend_Controller_Action_HelperBroker;
use Zend_Controller_Action_Interface;
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
     * @return \Illuminate\Contracts\Container\Container
     */
    public function getContainer()
    {
        return $this->getFrontController()->getParam('container');
    }

    /**
     * @return bool
     */
    public function hasContainer()
    {
        return null !== $this->getFrontController()->getParam('container');
    }

    /**
     * @param Zend_Controller_Request_Abstract  $request
     * @param Zend_Controller_Response_Abstract $response
     *
     * @throws \Exception
     * @throws \Zend_Controller_Dispatcher_Exception
     * @throws \Zend_Controller_Exception
     */
    public function dispatch(Zend_Controller_Request_Abstract $request, Zend_Controller_Response_Abstract $response)
    {
        $this->assertContainerIsAvailable();

        $this->setResponse($response);

        $className = $this->getClassName($request);
        $moduleClassName = $this->getModuleClassName($className);
        $this->loadClass($className);

        $controller = $this->buildController($request, $moduleClassName);
        $this->assertControllerIsAction($controller, $moduleClassName);
        $action = $this->getActionMethod($request);

        /**
         * Dispatch the method call
         */
        $request->setDispatched(true);

        // by default, buffer output
        $disableOb = $this->getParam('disableOutputBuffering');
        $obLevel = ob_get_level();
        if (empty($disableOb)) {
            ob_start();
        }

        try {
            $this->dispatchToController($controller, $action);
        } catch (\Exception $e) {
            // Clean output buffer on error
            $curObLevel = ob_get_level();
            if ($curObLevel > $obLevel) {
                do {
                    ob_get_clean();
                    $curObLevel = ob_get_level();
                } while ($curObLevel > $obLevel);
            }
            throw $e;
        }

        if (empty($disableOb)) {
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
     * @throws \Zend_Controller_Dispatcher_Exception
     * @throws \Zend_Controller_Exception
     */
    protected function getClassName(Zend_Controller_Request_Abstract $request)
    {
        if (!$this->isDispatchable($request)) {
            $controller = $request->getControllerName();
            if (!$this->getParam('useDefaultControllerAlways') && !empty($controller)) {
                require_once 'Zend/Controller/Dispatcher/Exception.php';
                throw new \Zend_Controller_Dispatcher_Exception(
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
     * @param Zend_Controller_Request_Abstract  $request
     * @param                                   $moduleClassName
     *
     * @return mixed
     */
    protected function buildController(Zend_Controller_Request_Abstract $request, $moduleClassName)
    {
        $controller = $this->getContainer()->make(
            $moduleClassName,
            array($request, $this->getResponse(), $this->getParams())
        );

        return $controller;
    }

    /**
     * @param $controller
     * @param $moduleClassName
     *
     * @throws \Zend_Controller_Dispatcher_Exception
     */
    protected function assertControllerIsAction($controller, $moduleClassName)
    {
        if (!($controller instanceof Zend_Controller_Action_Interface) &&
            !($controller instanceof Zend_Controller_Action)
        ) {
            require_once 'Zend/Controller/Dispatcher/Exception.php';
            throw new \Zend_Controller_Dispatcher_Exception(
                'Controller "'.$moduleClassName.'" is not an instance of Zend_Controller_Action_Interface'
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
     * @throws \Zend_Controller_Dispatcher_Exception
     */
    protected function assertContainerIsAvailable()
    {
        if (!$this->hasContainer()) {
            throw new \Zend_Controller_Dispatcher_Exception(
                'No container available to resolve from'
            );
        }
    }

}