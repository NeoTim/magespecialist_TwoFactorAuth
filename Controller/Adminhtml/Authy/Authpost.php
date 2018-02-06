<?php
/**
 * MageSpecialist
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@magespecialist.it so we can send you a copy immediately.
 *
 * @category   MSP
 * @package    MSP_TwoFactorAuth
 * @copyright  Copyright (c) 2017 Skeeller srl (http://www.magespecialist.it)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace MSP\TwoFactorAuth\Controller\Adminhtml\Authy;

use Magento\Backend\Model\Auth\Session;
use Magento\Backend\App\Action;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\View\Result\PageFactory;
use MSP\SecuritySuiteCommon\Api\AlertInterface;
use MSP\TwoFactorAuth\Api\TfaInterface;
use MSP\TwoFactorAuth\Api\TfaSessionInterface;
use MSP\TwoFactorAuth\Api\TrustedManagerInterface;
use MSP\TwoFactorAuth\Controller\Adminhtml\AbstractAction;
use MSP\TwoFactorAuth\Model\Provider\Engine\Authy;

/**
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
class Authpost extends AbstractAction
{
    /**
     * @var TfaInterface
     */
    private $tfa;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var PageFactory
     */
    private $pageFactory;

    /**
     * @var TfaSessionInterface
     */
    private $tfaSession;

    /**
     * @var TrustedManagerInterface
     */
    private $trustedManager;

    /**
     * @var Authy
     */
    private $authy;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var AlertInterface
     */
    private $alert;

    /**
     * Authpost constructor.
     * @param Action\Context $context
     * @param Session $session
     * @param PageFactory $pageFactory
     * @param Authy $authy
     * @param TfaSessionInterface $tfaSession
     * @param TrustedManagerInterface $trustedManager
     * @param TfaInterface $tfa
     * @param AlertInterface $alert
     * @param DataObjectFactory $dataObjectFactory
     */
    public function __construct(
        Action\Context $context,
        Session $session,
        PageFactory $pageFactory,
        Authy $authy,
        TfaSessionInterface $tfaSession,
        TrustedManagerInterface $trustedManager,
        TfaInterface $tfa,
        AlertInterface $alert,
        DataObjectFactory $dataObjectFactory
    ) {
        parent::__construct($context);
        $this->tfa = $tfa;
        $this->session = $session;
        $this->pageFactory = $pageFactory;
        $this->tfaSession = $tfaSession;
        $this->trustedManager = $trustedManager;
        $this->authy = $authy;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->alert = $alert;
    }

    /**
     * Get current user
     * @return \Magento\User\Model\User|null
     */
    private function getUser()
    {
        return $this->session->getUser();
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $user = $this->getUser();

        try {
            $this->authy->verify($user, $this->dataObjectFactory->create([
                'data' => $this->getRequest()->getParams(),
            ]));
            $this->trustedManager->handleTrustDeviceRequest(Authy::CODE, $this->getRequest());
            $this->tfaSession->grantAccess();
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            $this->alert->event(
                'MSP_TwoFactorAuth',
                'Authy error',
                AlertInterface::LEVEL_ERROR,
                $this->getUser()->getUserName(),
                AlertInterface::ACTION_LOG,
                $e->getMessage()
            );

            return $this->_redirect('*/*/auth');
        }

        return $this->_redirect('/');
    }

    /**
     * @inheritdoc
     */
    protected function _isAllowed()
    {
        $user = $this->getUser();

        return
            $user &&
            $this->tfa->getProviderIsAllowed($user->getId(), Authy::CODE) &&
            $this->tfa->getProvider(Authy::CODE)->isActive($user->getId());
    }
}
