avangard-api
==========

PHP library for emulating online-banking API of AVANGARD Bank through web interface

Documentation
-------------

clbAvn - PHP class for business clients

Usage
-----

```php
<?php

use Avangard\clbAvn;

// create api
$api = new clbAvn($login, $password);

// print 1C export
print $api->export1C(new DateTime());

```
