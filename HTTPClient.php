<?php
class HTTPClient {
  private $curl;

  function __construct($agent = NULL) {
    $this->curl = curl_init();
    curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, 3);
    # We'll do our own error handling
    curl_setopt($this->curl, CURLOPT_FAILONERROR, FALSE);
    if ($agent !== NULL) {
      curl_setopt($this->curl, CURLOPT_USERAGENT, $agent);
    }
  }

  function setOptions($options) {
    if (! curl_setopt_array($this->curl, $options)) {
      throw new Exception('Could not set options: ' . curl_error($this->curl));
    }
  }

  function get($url) {
    curl_setopt($this->curl, CURLOPT_HTTPGET, TRUE);
    return $this->exec($url);
  }

  function post($url, $body) {
    curl_setopt($this->curl, CURLOPT_POST, TRUE);
    curl_setopt($this->curl, CURLOPT_POSTFIELDS, $body);
    return $this->exec($url);
  }

  function exec($url) {
    curl_setopt($this->curl, CURLOPT_URL, $url);
    $ret = curl_exec($this->curl);
    $status = $this->getStatus();
    # file:// URLs return status 0
    if ($ret === FALSE || ($status !== 0 && $status !== 200)) {
      if ($ret !== FALSE) {
        # We got an error response. Let's log the page body
        $file = tempnam('/tmp', 'php-httpclient');
        file_put_contents($file, $ret);
        print "Curl error; wrote response to $file\n";
      }
      throw new Exception('Curl: ' . curl_error($this->curl) . " $url");
    }
    return $ret;
  }

  function getStatus() {
    return curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
  }
}
?>
