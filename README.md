# SMS-Forwarding-Backend
To perform SMS forwarding and device management in conjunction with air780e and other luatos devices

Implemented using PHP and jQuery, leveraging the Bootstrap 5 front-end framework.

There are 3 interfaces available for device invocation, namely task.php, heartbeat.php, and receive.php. Among them:

* task.php is used to handle tasks for sending SMS messages.
* heartbeat.php is used to periodically report the online status of the device.
* receive.php is used to receive forwarded SMS messages and perform push notifications (optional).

Additional Information:

The source code does not include the required icon files. Please download version 1.11.3 of bootstrap-icons, extract the contents, and place the extracted folder within the vendor directory.
