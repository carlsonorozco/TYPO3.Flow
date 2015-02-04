<?php
namespace TYPO3\Flow\Security\Aspect;

/*                                                                        *
 * This script belongs to the TYPO3 Flow framework.                       *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Aop\JoinPointInterface;
use TYPO3\Flow\Log\SecurityLoggerInterface;
use TYPO3\Flow\Mvc\ActionRequest;
use TYPO3\Flow\Security\Authentication\EntryPoint\WebRedirect;
use TYPO3\Flow\Security\Authorization\FirewallInterface;
use TYPO3\Flow\Security\Context;
use TYPO3\Flow\Security\Exception\AccessDeniedException;
use TYPO3\Flow\Security\Exception\AuthenticationRequiredException;

/**
 * The central security aspect, that invokes the security interceptors.
 *
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 */
class RequestDispatchingAspect {

	/**
	 * @var \TYPO3\Flow\Log\SecurityLoggerInterface
	 */
	protected $securityLogger;

	/**
	 * @var \TYPO3\Flow\Security\Context
	 */
	protected $securityContext;

	/**
	 * @var \TYPO3\Flow\Security\Authorization\FirewallInterface
	 */
	protected $firewall;

	/**
	 * @param \TYPO3\Flow\Security\Context $securityContext
	 * @param \TYPO3\Flow\Security\Authorization\FirewallInterface $firewall
	 * @param \TYPO3\Flow\Log\SecurityLoggerInterface $securityLogger
	 */
	public function __construct(Context $securityContext, FirewallInterface $firewall, SecurityLoggerInterface $securityLogger) {
		$this->securityContext = $securityContext;
		$this->firewall = $firewall;
		$this->securityLogger = $securityLogger;
	}

	/**
	 * Advices the dispatch method so that illegal action requests are blocked before
	 * invoking any controller.
	 *
	 * The "request" referred to within this method is an ActionRequest or some other
	 * dispatchable request implementing RequestInterface. Note that we don't deal
	 * with HTTP requests here.
	 *
	 * @Flow\Around("method(TYPO3\Flow\Mvc\Dispatcher->dispatch())")
	 * @param \TYPO3\Flow\Aop\JoinPointInterface $joinPoint The current joinpoint
	 * @return mixed Result of the advice chain
	 * @throws \Exception|\TYPO3\Flow\Security\Exception\AccessDeniedException
	 * @throws \Exception|\TYPO3\Flow\Security\Exception\AuthenticationRequiredException
	 */
	public function blockIllegalRequestsAndForwardToAuthenticationEntryPoints(JoinPointInterface $joinPoint) {
		$request = $joinPoint->getMethodArgument('request');
		if (!($request instanceof ActionRequest) || $this->securityContext->areAuthorizationChecksDisabled()) {
			return $joinPoint->getAdviceChain()->proceed($joinPoint);
		}

		try {
			$this->firewall->blockIllegalRequests($request);
			return $joinPoint->getAdviceChain()->proceed($joinPoint);
		} catch (AuthenticationRequiredException $exception) {
			$response = $joinPoint->getMethodArgument('response');

			$entryPointFound = FALSE;
			/** @var $token \TYPO3\Flow\Security\Authentication\TokenInterface */
			foreach ($this->securityContext->getAuthenticationTokens() as $token) {
				$entryPoint = $token->getAuthenticationEntryPoint();

				if ($entryPoint !== NULL) {
					$entryPointFound = TRUE;
					if ($entryPoint instanceof WebRedirect) {
						$this->securityLogger->log('Redirecting to authentication entry point', LOG_INFO, $entryPoint->getOptions());
					} else {
						$this->securityLogger->log('Starting authentication with entry point of type ' . get_class($entryPoint), LOG_INFO);
					}
					$this->securityContext->setInterceptedRequest($request->getMainRequest());
					$entryPoint->startAuthentication($request->getHttpRequest(), $response);
				}
			}
			if ($entryPointFound === FALSE) {
				$this->securityLogger->log('No authentication entry point found for active tokens, therefore cannot authenticate or redirect to authentication automatically.', LOG_NOTICE);
				throw $exception;
			}
		} catch (AccessDeniedException $exception) {
			$this->securityLogger->log('Access denied', LOG_WARNING);
			throw $exception;
		}
		return NULL;
	}
}
