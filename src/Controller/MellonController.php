<?php

namespace Drupal\mellon\Controller;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for MellON routes.
 */
class MellonController extends ControllerBase {

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Guzzle\Client instance.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * MellON config.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ClientInterface $http_client,
    ConfigFactory $config_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->httpClient = $http_client;
    $this->config = $config_factory->get('mellon.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('http_client'),
      $container->get('config.factory')
    );
  }

  /**
   * **********
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function check() {
    return new Response('MellON enabled', 200,
      ['Content-Type' => 'text/plain']);
  }

  /**
   * ***********
   *
   * @param  \Symfony\Component\HttpFoundation\Request  $request
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function verify(Request $request) {
    try {
      $token = $request->query->get('token');
      $ref = $request->server->get('HTTP_REFERER');
      $email = $this->verifyToken($token, $ref);

      $user = $this->getUser($email);
      user_login_finalize($user);
      $this->getLogger('mellon')->info($email . ' ********.');

    } catch (\Exception $exception) {
      $this->getLogger('mellon')
        ->error('********: ' . $exception->getMessage());
    }
    return new RedirectResponse('/');
  }

  /**
   * ***********
   *
   * @param string $email
   *
   * @return \Drupal\user\Entity\User
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  private function getUser($email) {
    if ($this->config->get('user_mapping')) {
      /** @var \Drupal\user\Entity\User[] $account */
      $accounts = $this->entityTypeManager->getStorage('user')
        ->loadByProperties(['mail' => $email]);

      if ($accounts) {
        return reset($accounts);
      }

      if ($this->config->get('enforce_user_mapping')) {
        throw new \Exception('**********');
      }
    }

    $uid = $this->config->get('default_user') ?? 1;
    /** @var \Drupal\user\Entity\User[] $account */
    $accounts = $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['uid' => $uid]);

    if (!$accounts) {
      throw new \Exception('************');
    }

    return reset($accounts);
  }

  /**
   * Logic to verify token provided to verifications endpoint.
   *
   * @param  string  $token
   * @param  string  $ref
   *
   * @return string
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \Exception
   */
  private function verifyToken($token, $ref) {
    if (empty($token) || empty($ref)) {
      throw new \Exception('The request could not be processed.');
    }
    $tokenParts = explode('.', $token);
    $payload = $this->base64UrlDecode($tokenParts[1]);
    $payloadData = json_decode($payload);
    $host = \Drupal::request()->getHost();

    if (strpos($payloadData->site, $host) === FALSE) {
      throw new \Exception('Token payload does not match.');
    }

    $request = $this->httpClient->request('GET', $ref . 'api/verify',
      ['headers' => ['Authorization' => 'Bearer ' . $token]]
    );
    if ($request->getStatusCode() != 200) {
      throw new \Exception('Token is invalid.');
    }
    $email = $request->getBody()->getContents();
    if (strpos($email, '@mellenger.com') === FALSE) {
      throw new \Exception('*********');
    }

    return $email;
  }

  private function base64UrlDecode($data) {
    $urlUnsafeData = strtr($data, '-_', '+/');
    $paddedData = str_pad($urlUnsafeData, strlen($data) % 4, '=',
      STR_PAD_RIGHT);
    return base64_decode($paddedData);
  }

}
