<?php

namespace Drupal\ocha_assessments\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribing an event.
 */
class CorsSubscriber implements EventSubscriberInterface {

  /**
   * Executes actions on the request event.
   */
  public function onKernelRequest(GetResponseEvent $event) {
    if (!$event->isMasterRequest()) {
      return;
    }

    $request = $event->getRequest();

    // Only act on rest requests.
    if (strpos($request->getRequestURI(), '/rest/') === FALSE) {
      return;
    }

    $method = $request->getRealMethod();
    if (strtoupper($method) === 'OPTIONS') {
      $response = new Response();
      $event->setResponse($response);
    }
  }

  /**
   * Executes actions on the response event.
   */
  public function onKernelResponse(FilterResponseEvent $event) {
    if (!$event->isMasterRequest()) {
      return;
    }

    // Only act on rest requests.
    $request = $event->getRequest();
    if (strpos($request->getRequestURI(), '/rest/') === FALSE) {
      return;
    }

    $response = $event->getResponse();

    $response->headers->set('Access-Control-Allow-Origin', '*');
    $response->headers->set('Access-Control-Allow-Headers', 'Authorization, Accept, Accept-Language, Content-Language, Content-Type, Origin, X-Requested-With');
    $response->headers->set('Access-Control-Allow-Credentials', 'true');
    $response->headers->set('Access-Control-Expose-Headers', '');
    $response->headers->set('Access-Control-Allow-Methods', 'OPTIONS, GET');
    $response->headers->set('Access-Control-Max-Age', '3600');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST  => ['onKernelRequest', -10],
      KernelEvents::RESPONSE => ['onKernelResponse', -10],
    ];
  }

}
