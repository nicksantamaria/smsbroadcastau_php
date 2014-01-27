SMS Broadcast - PHP API
=======================

Who should use this library?
----------------------------

This class is for PHP applications wanting to utilise the [SMS Broadcast](https://www.smsbroadcast.com.au) SMS gateway.

Using this class you can:

* Check SMS credit balance of an account.
* Send SMSs to single and multiple recipients.


Usage
-----

    <?php
    // Initialise API object with username and password.
    $api = new smsbroadcastau($username, $password);
    
    // Check account balance.
    $available_credits = $api->checkBalance();
    
    // Send an SMS.
    $api->addRecipient('0400000000');
    $api->message = 'Message to send to recipients';
    $api->from = 'SMS API';
    $api->send();


License
-------

Distributed under MIT license. See LICENSE for details.
