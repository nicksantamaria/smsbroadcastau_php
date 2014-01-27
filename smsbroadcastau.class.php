<?php

/**
 * @file
 * Contains class to integrate with "SMS Broadcast" SMS gateway API.
 *
 * @see https://www.smsbroadcast.com.au
 */

class smsbroadcastau {
  /**
   * API endpoint URL.
   */
  protected $api_endpoint = 'https://www.smsbroadcast.com.au/api-adv.php';
  
  /**
   * API account username.
   *
   * @see smsbroadcastau::setAuthentication()
   */
  protected $api_username = '';
  
  /**
   * API account password.
   *
   * @see smsbroadcastau::setAuthentication()
   */
  protected $api_password = '';
  
  /**
   * Array of recipient phone numbers.
   *
   * @see smsbroadcastau::addRecipient()
   */
  public $recipients = array();
  
  /**
   * String which is displayed to recipients as the sender.
   *
   * Maximum of 11 characters.
   */
  public $sender = '';
  
  /**
   * Body of the SMS.
   */
  public $message = '';
  
  public $ref = '';
  
  /**
   * Constructor
   */
  public function __construct($username, $password) {
    $this->setAuthentication($username, $password);
  }
  
  /**
   * Helper method to set authentication details.
   * 
   * @param string $username
   * @param string $password
   */
  public function setAuthentication($username, $password) {
    $this->api_username = $username;
    $this->api_password = $password;
  }
  
  /**
   * Helper method which adds a new recipient to the SMS.
   *
   * @param string $number
   *   Phone number of the new recipient
   */
  public function addRecipient($number) {
    $this->recipients[] = $number;
  }
  
  /**
   * Executes sending of an SMS.
   * 
   * @return array 
   *   Response from SMS gateway. Contains response for each receiving address.
   *   Array (
   *     Array (
   *       status: Status of the SMS send. Possible values:
   *         - OK : This message was accepted.
   *         - BAD: This message was invalid. (eg, invalid phone number) 
   *       receiving_number: The receiving mobile number.
   *       response: Will display our reference number for the SMS message, or
   *                 the reason for a failed SMS message. 
   *     )
   *   )
   *
   * @throws Exception
   *
   * @see https://www.smsbroadcast.com.au/ajax/apiIn.php
   *
   * Usage example
   * @code
   *   $api = new smsbroadcastau($username, $password);
   *   $api->addRecipient('0400000000');
   *   $api->message = 'Message to send to recipients';
   *   $api->from = 'SMS API';
   *   $api->ref = 'identifier';
   *   $api->send();
   * @endcode
   */
  public function send() {
    $vars = array(
      'username' => $this->api_username,
      'password' => $this->api_password,
      'to' => $this->recipients,
      'from' => $this->sender,
      'message' => $this->message,
      'ref' => $this->ref,
    );
    
    // Basic validation on the authentication details and POST data.
    foreach ($vars as $key => $value) {
      switch ($key) {
        case 'username':
        case 'password':
          if (empty($value)) {
            throw new Exception('API username or password not specified.');
          }
          break;
        
        case 'to':
          if (empty($value)) {
            throw new Exception('No recipients specified.');
          }
          break;
          
        case 'from':
          if (strlen($value) > 11) {
            throw new Exception('From string must be 11 characters or less.');
          }
          break;
      }
    }
  
    return $this->executeApiRequest($vars);
  }
  
  /**
   * Checks the account SMS balance.
   * 
   * @return int 
   *   Number of SMS credits remaining on the account.
   *
   * @throws Exception
   *
   * @see https://www.smsbroadcast.com.au/ajax/apiIn.php
   */
  public function checkBalance() {
    $vars = array(
      'username' => $this->api_username,
      'password' => $this->api_password,
      'action' => 'balance',
    );
    
    // Basic validation on the authentication details
    foreach ($vars as $key => $value) {
      switch ($key) {
        case 'username':
        case 'password':
          if (empty($value)) {
            throw new Exception('API username or password not specified.');
          }
          break;
      }
    }
    
    $retval = $this->executeApiRequest($vars);
    list(, $response) = array_values(reset($retval));
    
    return (int) $response;
  }
  
  /**
   * Helper method to execute an API request.
   *
   * @param array $vars
   *   Data to POST to SMS gateway API endpoint.
   * 
   * @return array
   *   Response from SMS gateway.
   */
  public function executeApiRequest($vars) {
    $data = $this->preparePostData($vars);
    $retval = $this->executePostRequest($data);
    
    list($status, $response) = explode(':', $retval);
    if ($status == 'ERROR') {
      throw new Exception(strtr('There was an error with this request: !error.', array('!error' => $response)));
    }
    
    $data = array();
    $lines = explode("\n", $retval);
    foreach (array_filter($lines) as $i => $line) {
      list($status, $receiving_number, $response) = explode(':', $line);
      $data[$i] = array(
        'status' => trim($status),
        'receiving_number' => trim($receiving_number),
        'response' => trim($response),
      );
    }
    
    return $data;
  }
  
  /**
   * Protected helper which makes basic associative array suitable for POST.
   *
   * @param array $data
   *   Associative array containing data to POST to SMS gateway.
   * 
   * @return string
   *   URL encoded POST data.
   */
  protected function preparePostData($data) {
    $post_data = array();
    foreach ($data as $key => $value) {
      switch ($key) {
        case 'to':
          // Support multiple phone numbers.
          $value = implode(',', array_unique($value));
          break;
      }
      $post_data[] = $key . '=' . rawurlencode($value);
    }
    
    return implode('&', $post_data);
  }
  
  /**
   * Protected helper which executes the cURL POST request for API calls.
   *
   * @param string $data
   *   Data to POST.
   *
   * @return string $retval
   *   Raw response from SMS gateway.
   */
  protected function executePostRequest($data) {
    $ch = curl_init($this->api_endpoint);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $retval = curl_exec($ch);
    curl_close($ch);
    return $retval;
  }
}
