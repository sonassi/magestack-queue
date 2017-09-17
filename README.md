# MageStack Queue

MageStack Queue is a supporting module to provide queuing functionality on busy websites - where you want to control the volume of visitors on the website; either to prevent excessive load or to create the atmosphere of demand.

This module can be used with Magento (1 or 2), WordPress, Laravel or any other PHP application you want to add a queuing mechanism to.

## Installation

### Via composer

Add the following repository to your `composer.json` configuration file,

~~~~
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/sonassi/magestack-queue.git"
    }
]
~~~~

Then update composer and install the module,

~~~~
composer update
composer require magestack/queue
~~~~

In your application's [bootstrap](#common-bootstraps) file, add the include at the very top of the file after the opening `<?php`

    require __DIR__.'/../vendor/magestack/queue/queue.php';

Finally, create the queue database,

~~~~
cd vendor/magestack/queue
php queue.php --install
~~~~

### Standalone

The module should be downloaded to a non-publically accessible directory (eg. one level below the document root) - or, if placed in the document root in a custom directory, it must be [secured](#securing-the-module).

Download and extract the module outside of the document root,

    cd /microcloud/domains/example/domains/example.com
    wget --no-check-certificate https://github.com/sonassi/magestack-queue/archive/master.zip -O magestack-queue.zip
    unzip magestack-queue.zip
    mv magestack-queue-master ___queue
    rm magestack-queue.zip

In your application's [bootstrap](#common-bootstraps) file, add the (relative) include at the very top of the file after the opening `<?php`

    require __DIR__.'/../___queue/queue.php';

Finally, create the queue database,

~~~~
cd ___queue
php queue.php --install
~~~~

### Cron

The scheduled cron is mandatory to ensuring the metrics for the queue are updated and users are granted access. Without the cron, the queue will not function!

The cron method needs to specify the full path to your installation directory, please adjust as necessary to suit your installation method,

#### Composer

~~~~
* * * * * /usr/bin/php /microcloud/domains/example/domains/example.com/http/vendor/magestack/queue/queue.php --cron
~~~~

#### Standalone

~~~~
* * * * * /usr/bin/php /microcloud/domains/example/domains/example.com/___queue/queue.php --cron
~~~~

## Configuration

There are files you should customise to configure the module,

 - `config.php`: This contains all configuration variables for the module
 - `src/view/queue-landing.phtml`: This contains the template shown when a user is in the queue

### Variables

**Whitelist**

    regex (array of strings)

This setting supports the automatic whitelisting by IP or by URI - so that you can permanently grant access to specific requests from certain IPs or to certain destination URIs. Examples of this may be administrator users that should never be placed in the queue, or callbacks from payment gateways.

Regular expressions are supported for pattern matching both fields

**Enabled**

    true|false (boolean)

As the name would imply, this setting toggles the module on or off based on the value.

**Threshold**

    -1 to ~

This value defines the maximum number of active users on the store.

Eg. If set to 100, then only 100 active users will be allowed to browse the site, all other users will be put into the queue.

If set to `-1`, then the queue will be enabled for all users (this is ideal for testing purposes).

**Timer**

    0 to ~ (seconds)

When users successfully exit the queue and are allowed access to the site, the value set in the timer dictates how long they are allowed to remain "idle" on the site.

Eg. If the timer is set to 600 seconds and the user accesses the site but does not click a link for more than 600 seconds (from their last link click), they will be ejected from the store and put back into the queue. If the user however were actively clicking, they will remain on the site indefinitely occupying a queue slot.

**GA Code**

Enter your Google Analytics tracking code to be able to report users in the queue to Google.

### Template

The `queue-landing.phtml` file is what is shown to the user when they are actively queuing. This file can be customised as you wish, but we recommend not using any internally linked assets (ie. only load assets from a remote server/URL/CDN).

No PHP is supported in this file - although JavaScript is (provided your library is externally hosted).

### Appendix

### Common bootstraps

 - Magento 1: `/index.php`
   Installation: [Standalone](#standalone)

   ~~~~
   require realpath(__DIR__) . '/../___queue/queue.php';
   ~~~~

 - Magento 2: `/pub/index.php`
   Installation: [Composer](#composer)

   ~~~~
   require realpath(__DIR__) . '/../vendor/magestack/queue/queue.php';
   ~~~~

 - Laravel: `/public/index.php`
   Installation: [Composer](#composer)

   ~~~~
   require realpath(__DIR__) . '/../vendor/magestack/queue/queue.php';
   ~~~~

 - WordPress: `/index.php`
   Installation: [Standalone](#standalone)
   ~~~~
   require realpath(__DIR__) . '/../___queue/queue.php';
   ~~~~

### Securing the module

MageStack will automatically protect the default directory name (`magestack-queue`) - but if you have renamed it, ensure you add the following to your domain's `___general/example.com.conf` file.

~~~~
location ~* ^/my-queue-dir {
    deny all;
}
~~~~
