<?php

/**
 * @file
 * Contains class to integrate with "SMS Broadcast" SMS gateway API.
 *
 * @see https://www.smsbroadcast.com.au
 * @see https://www.smsbroadcast.com.au/Advanced%20HTTP%20API.pdf
 */

class smsbroadcastau {
  /**
   * API endpoint URL.
   */
  protected $api_endpoint = 'https://www.smsbroadcast.com.au/api-adv.php';
  
  /**
   * API account username.
   *
   * Your SMS Broadcast username. This is the same username that you would use 
   * to login to the SMS Broadcast website. 
   *
   * @see smsbroadcastau::setAuthentication()
   */
  protected $api_username = '';
  
  /**
   * API account password.
   *
   * Your SMS Broadcast password. This is the same password that you would use 
   * to login to the SMS Broadcast website.
   *
   * @see smsbroadcastau::setAuthentication()
   */
  protected $api_password = '';
  
  /**
   * Array of recipient phone numbers.
   *
   * The numbers can be in the format: 
   *   - 04xxxxxxxx (Australian format)
   *   - 614xxxxxxxx (International format without a preceding +)
   *   - 4xxxxxxxx (missing leading 0) 
   * SMS Broadcast recommend using the international format, but your messages 
   * will be accepted in any of the above formats. The numbers should contain 
   * only numbers, with no spaces or other characters. 
   *
   * @see smsbroadcastau::addRecipient()
   */
  public $recipients = array();
  
  /**
   * The sender ID for the messages. 
   *
   * Can be a mobile number or letters, up to 11 characters and should not 
   * contain punctuation or spaces. Leave blank to use SMS BroadcastÕs 2-way 
   * number.
   */
  public $sender = '';
  
  /**
   * The content of the SMS message. 
   *
   * Must not be longer than 160 characters unless the maxsplit parameter is 
   * used. Must be URL encoded.
   */
  public $message = '';
  
  /**
   * Determines the maximum length of your SMS message.
   *
   * Standard SMS messages are limited to 160 characters, however our system 
   * allows you to send SMS messages up to 765 characters. This is achieved by 
   * splitting the message into parts. Each part is a normal SMS and is charged 
   * at the normal price. The SMS is then reconstructed by the receiving mobile 
   * phone and should display as a single SMS.
   *
   * The maxsplit setting determines how many times you are willing to split the
   * message. This allows you to control the maximum cost and length of each 
   * message. The default setting is 1 (160 characters). The maximum is 5 (765 
   * characters). 
   *
   * If your SMS is 160 characters or less, it will be sent (and cost) as a 
   * single SMS, regardless of the value of this setting. 
   *
   * If your message is longer than 160 characters, it is split into parts of up
   * to 153 characters (not 160).
   *
   * If set to NULL, the class automatically detects the value for you in the  
   * send() method.
   */
  public $maxsplit = NULL;
  
  /**
   * Your reference number for the message.
   *
   * Assists you track the message status. This parameter is optional and can be
   * up to 20 characters. 
   */
  public $ref = '';

  /**
   * Maximum number of characters that can be inlcuded in a single SMS.
   *
   * @see $this->maxsplit
   */
  const MAX_CHARS_PER_MESSAGE_SINGLE = 160;

  /**
   * Maximum number of characters that can be included in each SMS when sending
   * multipart SMSs.
   *
   * @see $this->maxsplit
   */
  const MAX_CHARS_PER_MESSAGE_MULTI = 153;

  /**
   * Maximum number of SMSs that can be part of a multipart SMS.
   *
   * @see $this->maxsplit
   */
  const MAX_SMS_PER_MULTIPART = 7;

  /**
   * Maximum number of characters that can be included in the sender string.
   *
   * @see $this->sender
   */
  const MAX_CHARS_SENDER = 11;
  
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
   * The numbers can be in the format: 
   *   - 04xxxxxxxx (Australian format)
   *   - 614xxxxxxxx (International format without a preceding +)
   *   - 4xxxxxxxx (missing leading 0) 
   * SMS Broadcast recommend using the international format, but your messages 
   * will be accepted in any of the above formats. The numbers should contain 
   * only numbers, with no spaces or other characters. 
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

    // Automatically detect the maxsplit value if required.
    if (is_null($this->maxsplit)) {
      $message_length = strlen($vars['message']);
      if ($message_length <= self::MAX_CHARS_PER_MESSAGE_SINGLE) {
        $vars['maxsplit'] = 1;
      }
      else {
        // API documentation states multi-part SMSs are limited to 153 chars.
        $vars['maxsplit'] = ceil($message_length / self::MAX_CHARS_PER_MESSAGE_MULTI);
      }
    }
    else {
      $vars['maxsplit'] = $this->maxsplit;
    }
    
    // Basic validation on the authentication details and POST data.
    foreach ($vars as $key => $value) {
      switch ($key) {
        case 'to':
          if (empty($value)) {
            throw new Exception('No recipients specified.');
          }
          break;
          
        case 'from':
          if (strlen($value) > self::MAX_CHARS_SENDER) {
            throw new Exception('From string must be 11 characters or less.');
          }
          break;

        case 'multisplit':
          // Ensure we don't attempt to send multi-part SMS longer than 7 long.
          if ($value > self::MAX_SMS_PER_MULTIPART) {
            $args = array(
              '!chars' => $message_length,
              '!multisplit' => $value,
            );
            throw new Exception(strtr('Can not send a multi-part message longer than 7 SMSs. Attempted to send !chars characters over !multisplit messages.', $args));
          }
          break;
      }
    }
  
    $retval = $this->executeApiRequest($vars);
    $data = array();
    foreach ($retval as $i => $line) {
      list($status, $receiving_number, $response) = $line;
      $data[$i] = array(
        'status' => trim($status),
        'receiving_number' => trim($receiving_number),
        'response' => trim($response),
      );
    }

    return $data;
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

    $data = $this->preparePostData($vars);
    $retval = $this->executePostRequest($data);
    
    list($status, $response) = explode(':', $retval);
    if ($status == 'ERROR') {
      throw new Exception(strtr('There was an error with this request: !error.', array('!error' => $response)));
    }
    
    $data = array();
    $lines = explode("\n", $retval);
    foreach (array_filter($lines) as $i => $line) {
      $line = trim($line);
      $data[$i] = explode(':', $line);
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
