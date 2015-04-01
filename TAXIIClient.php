<?php
class TAXIIClient {
  private $http;
  private $url;
  private $boilerplate = <<< EOT
xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
xmlns:taxii_11="http://taxii.mitre.org/messages/taxii_xml_binding-1.1"
xsi:schemaLocation="http://taxii.mitre.org/messages/taxii_xml_binding-1.1 http://taxii.mitre.org/messages/taxii_xml_binding-1.1"
EOT;
  
  function __construct($url, $username = NULL, $password = NULL, $pem = NULL,
   $pempass = NULL) {
    $this->url = $url;

    $this->http = new HTTPClient();
    $this->http->setOptions(array(
     CURLOPT_HTTPHEADER => array(
      'Content-Type: application/xml',
      'Accept: application/xml',
      'X-TAXII-Accept: urn:taxii.mitre.org:message:xml:1.1',
      'X-TAXII-Content-Type: urn:taxii.mitre.org:message:xml:1.1',
      'X-TAXII-Protocol: urn:taxii.mitre.org:protocol:https:1.0'),
     CURLOPT_USERAGENT => 'PHP TAXIIClient',
     CURLOPT_VERBOSE => FALSE));
    if (isset($username) && isset($password)) {
      $this->http->setOptions(array(CURLOPT_USERPWD => "$username:$password"));
    } else if (isset($pem)) {
      $this->http->setOptions(array(CURLOPT_SSLCERT => $pem));
      if (isset($pempass)) {
        $this->http->setOptions(array(CURLOPT_SSLCERTPASSWD => $pempass));
      }
    } else {
      throw new Exception('TAXII client needs either username/password or certificate file');
    }
  }

  function sendQuery($body) {
    $response = $this->http->post($this->url, $body);
#    print "$response\n";
    $document = new DOMDocument;
    $document->loadxml($response);
    $xpath = new DOMXpath($document);
    return $xpath;
  }

  function poll($feed) {
    $feed = htmlentities($feed, ENT_XML1);
    $messageID = self::generateMessageID();
    $xml = <<< EOT
<?xml version="1.0" encoding="UTF-8" ?>
<taxii_11:Poll_Request
 {$this->boilerplate}
 message_id="{$messageID}"
 collection_name="{$feed}">
  <taxii_11:Poll_Parameters allow_asynch="false">
    <taxii_11:Response_Type>FULL</taxii_11:Response_Type>
   <taxii_11:Content_Binding binding_id="urn:stix.mitre.org:xml:1.1.1" />
  </taxii_11:Poll_Parameters>
</taxii_11:Poll_Request>
EOT;
    $this->sendQuery($xml);
    $xpath = $this->sendQuery($xml);
    $ret = array('dns' => array(), 'ip' => array());

    # You have to register the namespace before xpath will let you search
    # using it
    $xpath->registerNamespace('DomainNameObj', 'http://cybox.mitre.org/objects#DomainNameObject-1');
    foreach($xpath->query('//DomainNameObj:Value')
     as $node) {
      if ($node->getAttribute('condition') == 'Equals') {
        $ret['dns'][] = $node->textContent;
      }
    }

    $xpath->registerNamespace('AddressObj', 'http://cybox.mitre.org/objects#AddressObject-2');
    foreach($xpath->query('//AddressObj:Address_Value')
     as $node) {
      if ($node->getAttribute('condition') == 'Equals'
       && self::isValidIP($node->textContent)) {
        $ret['ip'][] = $node->textContent;
      }
    }

    return $ret;
  }

  function discover() {
    $messageID = self::generateMessageID();
    $xml = <<<EOT
<?xml version="1.0" encoding="UTF-8" ?>
<taxii_11:Discovery_Request
 {$this->boilerplate}
 message_id="{$messageID}" />
EOT;
    $xpath = $this->sendQuery($xml);
    $services = array();
    foreach($xpath->query('/taxii_11:Discovery_Response/taxii_11:Service_Instance')
     as $node) {
      $service = array();
      $service['type'] = $node->getAttribute('service_type');
      $service['version'] = $node->getAttribute('service_version');
      $service['available'] = $node->getAttribute('available');
      foreach ($node->childNodes as $child) {
        if ($child->localName == 'Protocol_Binding') {
          $service['protocol binding'] = $child->textContent;
        } else if ($child->localName == 'Address') {
          $service['address'] = $child->textContent;
        } else if ($child->localName == 'Message_Binding') {
          $service['message binding'] = $child->textContent;
        } else if ($child->localName == 'Message') {
          $service['message'] = $child->textContent;
        }        
      }
      $services[] = $service;
    }
    return $services;
  }

  function getCollectionInfo() {
    $messageID = self::generateMessageID();
    $xml = <<< EOT
<?xml version="1.0" encoding="UTF-8" ?>
<taxii_11:Collection_Information_Request
 {$this->boilerplate}
 message_id="{$messageID}" />
EOT;
    $xpath = $this->sendQuery($xml);
    $collections = array();
    foreach($xpath->query('/taxii_11:Collection_Information_Response/taxii_11:Collection')
     as $node) {
      $collection = array();
      $collection['name'] = $node->getAttribute('collection_name');
      $collection['type'] = $node->getAttribute('collection_type');
      $collection['available'] = $node->getAttribute('available');
      foreach ($node->childNodes as $child) {
        if ($child->localName == 'Description') {
          $collection['description'] = $child->textContent;
        } else if ($child->localName == 'Polling_Service') {
          foreach ($child->childNodes as $subChild) {
            if ($subChild->localName == 'Protocol_Binding') {
              $collection['protocol binding'] = $subChild->textContent;
            } else if ($subChild->localName == 'Address') {
              $collection['address'] = $subChild->textContent;
            } else if ($subChild->localName == 'Message_Binding') {
              $collection['message binding'] = $subChild->textContent;
            }
          }
        }        
      }
      $collections[] = $collection;
    }
    return $collections;
  }

  # This function is untested as I have no idea what it does
  function sendInboxMessage($content) {
    $messageID = self::generateMessageID();
    # The content is already in XML, so no need to escape it
    # I guess we could validate it
    $xml = <<< EOT
<?xml version="1.0" encoding="UTF-8" ?>
<taxii_11:Inbox_Message
 {$this->boilerplate}
 message_id="{$messageID}">
  <taxii_11:Content_Block>
    <taxii_11:Content_Binding binding_id="urn:stix.mitre.org:xml:1.1.1" />
    <taxii_11:Content>{$content}</taxii_11:Content>
  </taxii_11:Content_Block>
</taxii_11:Inbox_Message>
EOT;
    $this->sendQuery($xml);
  }

  private static function generateMessageID() {
    if (function_exists('openssl_random_pseudo_bytes')) {
      $id = bin2hex(openssl_random_pseudo_bytes(32));
    } else if (function_exists('mt_rand')) {
      $id = bin2hex(decbin(mt_rand()));
    } else {
      $id = bin2hex(decbin(rand()));
    }
    return $id;
  }

  private static function isValidIP($ip) {
    return (preg_match('/^[12]?[0-9]{1,2}\.[12]?[0-9]{1,2}\.[12]?[0-9]{1,2}\.[12]?[0-9]{1,2}$/', $ip)
     && ip2long($ip) !== FALSE);
  }
}
?>
