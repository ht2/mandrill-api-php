<?php


require_once 'Mandrill_Templates.php';
require_once 'Mandrill_Exports.php';
require_once 'Mandrill_Users.php';
require_once 'Mandrill_Rejects.php';
require_once 'Mandrill_Inbound.php';
require_once 'Mandrill_Tags.php';
require_once 'Mandrill_Messages.php';
require_once 'Mandrill_Whitelists.php';
require_once 'Mandrill_Internal.php';
require_once 'Mandrill_Urls.php';
require_once 'Mandrill_Webhooks.php';
require_once 'Mandrill_Senders.php';

class Mandrill
{

  public $apikey;
  public $ch;
  public $root = 'https://mandrillapp.com/api/1.0';
  public $debug = false;

  public static $error_map = [
    "ValidationError" => Mandrill_ValidationError::class,
    "Invalid_Key" => Mandrill_Invalid_Key::class,
    "PaymentRequired" => Mandrill_PaymentRequired::class,
    "Unknown_Template" => Mandrill_Unknown_Template::class,
    "ServiceUnavailable" => Mandrill_ServiceUnavailable::class,
    "Unknown_Message" => Mandrill_Unknown_Message::class,
    "Invalid_Tag_Name" => Mandrill_Invalid_Tag_Name::class,
    "Invalid_Reject" => Mandrill_Invalid_Reject::class,
    "Unknown_Sender" => Mandrill_Unknown_Sender::class,
    "Unknown_Url" => Mandrill_Unknown_Url::class,
    "Invalid_Template" => Mandrill_Invalid_Template::class,
    "Unknown_Webhook" => Mandrill_Unknown_Webhook::class,
    "Unknown_InboundDomain" => Mandrill_Unknown_InboundDomain::class,
    "Unknown_Export" => Mandrill_Unknown_Export::class,
  ];

  public function __construct($apikey = null)
  {
    if (!$apikey) $apikey = getenv('MANDRILL_APIKEY');
    if (!$apikey) $apikey = $this->readConfigs();
    if (!$apikey) throw new Mandrill_Error('You must provide a Mandrill API key');
    $this->apikey = $apikey;

    $this->ch = curl_init();
    curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mandrill-PHP/1.0.43');
    curl_setopt($this->ch, CURLOPT_POST, true);
    curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($this->ch, CURLOPT_HEADER, false);
    curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($this->ch, CURLOPT_TIMEOUT, 600);
    curl_setopt($this->ch, CURLOPT_CAINFO, dirname(__FILE__) . '/cert/cacert.pem');

    $this->root = rtrim($this->root, '/') . '/';

    $this->templates = new Mandrill_Templates($this);
    $this->exports = new Mandrill_Exports($this);
    $this->users = new Mandrill_Users($this);
    $this->rejects = new Mandrill_Rejects($this);
    $this->inbound = new Mandrill_Inbound($this);
    $this->tags = new Mandrill_Tags($this);
    $this->messages = new Mandrill_Messages($this);
    $this->whitelists = new Mandrill_Whitelists($this);
    $this->internal = new Mandrill_Internal($this);
    $this->urls = new Mandrill_Urls($this);
    $this->webhooks = new Mandrill_Webhooks($this);
    $this->senders = new Mandrill_Senders($this);
  }

  public function __destruct()
  {
    curl_close($this->ch);
  }

  public function call($url, $params)
  {
    $params['key'] = $this->apikey;
    $params = json_encode($params);
    $ch = $this->ch;

    curl_setopt($ch, CURLOPT_URL, $this->root . $url . '.json');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_VERBOSE, $this->debug);

    $start = microtime(true);
    $this->log('Call to ' . $this->root . $url . '.json: ' . $params);
    if ($this->debug) {
      $curl_buffer = fopen('php://memory', 'w+');
      curl_setopt($ch, CURLOPT_STDERR, $curl_buffer);
    }

    $response_body = curl_exec($ch);
    $info = curl_getinfo($ch);
    $time = microtime(true) - $start;
    if ($this->debug) {
      rewind($curl_buffer);
      $this->log(stream_get_contents($curl_buffer));
      fclose($curl_buffer);
    }
    $this->log('Completed in ' . number_format($time * 1000, 2) . 'ms');
    $this->log('Got response: ' . $response_body);

    if (curl_error($ch)) {
      throw new Mandrill_HttpError("API call to $url failed: " . curl_error($ch));
    }
    $result = json_decode($response_body, true);
    if ($result === null) throw new Mandrill_Error('We were unable to decode the JSON response from the Mandrill API: ' . $response_body);

    if (floor($info['http_code'] / 100) >= 4) {
      throw $this->castError($result);
    }

    return $result;
  }

  public function readConfigs()
  {
    $paths = ['~/.mandrill.key', '/etc/mandrill.key'];
    foreach ($paths as $path) {
      if (file_exists($path)) {
        $apikey = trim(file_get_contents($path));
        if ($apikey) return $apikey;
      }
    }
    return false;
  }

  public function castError($result)
  {
    if ($result['status'] !== 'error' || !$result['name']) throw new Mandrill_Error('We received an unexpected error: ' . json_encode($result));

    $class = (isset(self::$error_map[$result['name']])) ? self::$error_map[$result['name']] : Mandrill_Error::class;
    return new $class($result['message'], $result['code']);
  }

  public function log($msg)
  {
    if ($this->debug) error_log($msg);
  }
}


