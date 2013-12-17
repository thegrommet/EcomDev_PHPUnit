<?php
/**
 * API test case
 *
 * @author tmannherz
 */
class EcomDev_PHPUnit_Test_Case_Api extends EcomDev_PHPUnit_Test_Case
{
    /**
     * Call the API.
     *
     * @param string $adapterCode
     * @param string $apiPath
     * @param array $args
     * @param int $apiUserId
     * @return EcomDev_PHPUnit_Test_Case_Api
     */
    public function call ($adapterCode, $apiPath, $args = array(), $apiUserId = null)
    {
        $this->mockApiSession($apiUserId);
        $handler = $this->mockApiServer($adapterCode);
        $handler->call(null, $apiPath, $args);

        return $this;
    }

    /**
     * Create an API session mock for testing the API
     *
     * @param int $apiUserId
     * @return EcomDev_PHPUnit_Test_Case_Api
     */
    protected function mockApiSession ($apiUserId = null)
    {
        $apiSessionMock = $this->getModelMock(
            'api/session',
            array(
                'start',
                'init',
                'getSessionId',
                'setSessionId',
                'getUser',
                'clear',
                'login',
                'isAllowed',
                'isLoggedIn'
            )
        );

        $apiSessionMock->expects($this->any())
            ->method('start')
            ->will($this->returnValue($apiSessionMock));

        $apiSessionMock->expects($this->any())
            ->method('init')
            ->will($this->returnValue($apiSessionMock));

        $apiSessionMock->expects($this->any())
            ->method('login')
            ->will($this->returnValue($apiSessionMock));

        $apiSessionMock->expects($this->any())
            ->method('isAllowed')
            ->will($this->returnValue(true));

        $apiSessionMock->expects($this->any())
            ->method('isLoggedIn')
            ->will($this->returnValue(true));

        $this->replaceByMock('model', 'api/session', $apiSessionMock);

        if ($apiUserId) {
            $apiUser = Mage::getModel('api/user')->load($apiUserId);
            $apiUserMock = $this->getModelMock(
                'api/user',
                array(
                    'loadBySessId',
                    'getUsername',
                    'getUserId',
                )
            );
            $apiUserMock->expects($this->any())
                ->method('loadBySessId')
                ->will($this->returnValue($apiUserMock));

            $apiUserMock->expects($this->any())
                ->method('getUsername')
                ->will($this->returnValue($apiUser->getUsername()));

            $apiUserMock->expects($this->any())
                ->method('getUserId')
                ->will($this->returnValue($apiUser->getId()));

            $apiSessionMock->expects($this->any())
                ->method('getUser')
                ->will($this->returnValue($apiUserMock));

            $this->replaceByMock('model', 'api/user', $apiUserMock);
        }

        return $this;
    }

    /**
     * Mocks an API server.
     *
     * @param string $adapterCode
     * @return Mage_Api_Model_Server_Handler_Abstract
     */
    protected function mockApiServer ($adapterCode)
    {
        $helper = Mage::getSingleton('api/config');
        $adapters = $helper->getActiveAdapters();
        if (isset($adapters[$adapterCode])) {
            $adapterModelKey = (string)$adapters[$adapterCode]->model;
        }
        else {
            throw new Exception(Mage::helper('api')->__('Invalid webservice adapter specified.'));
        }

        $apiAdapterMock = $this->getModelMock(
            $adapterModelKey,
            array(
                '_getServer',
                'fault'
            )
        );

        $apiServerMock = $this->getModelMock(
            'api/server',
            array(
                'getAdapter',
                'getAdapterCode'
            )
        );

        $apiAdapterMock->expects($this->any())
            ->method('_getServer')
            ->will($this->returnValue($apiServerMock));

        $apiAdapterMock->expects($this->any())
            ->method('fault')
            ->will($this->returnCallback(
                function ($code, $message) {
                    throw new Exception($message, $code);
                }
            ));

        $apiServerMock->expects($this->any())
            ->method('getAdapter')
            ->will($this->returnValue($apiAdapterMock));

        $apiServerMock->expects($this->any())
            ->method('getAdapterCode')
            ->will($this->returnValue($adapterCode));

        $this->replaceByMock('singleton', 'api/server', $apiServerMock);
        $this->replaceByMock('model', $adapterModelKey, $apiAdapterMock);

        $handlers = $helper->getHandlers();
        $handler = (string)$adapters[$adapterCode]->handler;

        if (!isset($handlers->$handler)) {
            throw new Exception(Mage::helper('api')->__('Invalid webservice handler specified.'));
        }
        $handlerClassName = Mage::getConfig()->getModelClassName((string) $handlers->$handler->model);
        $handler = new $handlerClassName;

        return $handler;
    }
}