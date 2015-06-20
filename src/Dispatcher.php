<?php

namespace Jeroenvandergeer\ZfIoc;

use Illuminate\Contracts\Container\Container;
use Zend_Controller_Action;
use Zend_Controller_Action_HelperBroker;
use Zend_Controller_Action_Interface;
use Zend_Controller_Dispatcher_Standard;
use Zend_Controller_Request_Abstract;
use Zend_Controller_Response_Abstract;

/**
 * Class Dispatcher
 *
 * @package Webbeheer\Ioc
 */
class Dispatcher extends Zend_Controller_Dispatcher_Standard
{
    /**
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $container;

    /**
     * @param \Illuminate\Contracts\Container\Container $container
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
    }

    public function getContainer()
    {
        return $this->container;
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
            $this->call($controller, $action);
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
    protected function call(Zend_Controller_Action $controller, $action)
    {
        // Notify helpers of action preDispatch state
        $helperBroker = new Zend_Controller_Action_HelperBroker($controller);
        $helperBroker->notifyPreDispatch();

        $controller->preDispatch();
        if ($controller->getRequest()->isDispatched()) {
            if (!isset($classMethods) || null === $classMethods) {
                $classMethods = get_class_methods($controller);
            }

            // If pre-dispatch hooks introduced a redirect then stop dispatch
            // @see ZF-7496
            if (!($this->getResponse()->isRedirect())) {
                // preDispatch() didn't change the action, so we can continue
                if ($controller->getInvokeArg('useCaseSensitiveActions') || in_array($action, $classMethods)) {
                    if ($controller->getInvokeArg('useCaseSensitiveActions')) {
                        trigger_error(
                            'Using case sensitive actions without word separators is deprecated; please do not rely on this "feature"'
                        );
                    }

                    $this->getContainer()->call([$controller, $action]);
                } else {
                    $this->getContainer()->call([$controller, '_call'], [$action]);
                }
            }
            $controller->postDispatch();
        }

        // whats actually important here is that this action controller is
        // shutting down, regardless of dispatching; notify the helpers of this
        // state
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
     * @param Zend_Controller_Request_Abstract $request
     * @param                                   $moduleClassName
     *
     * @return mixed
     */
    protected function buildController(Zend_Controller_Request_Abstract $request, $moduleClassName)
    {
        $controller = new $moduleClassName($request, $this->getResponse(), $this->getParams());

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

}