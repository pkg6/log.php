# Install 

~~~
composer require pkg6/log
~~~

# Use

~~~
<?php

require 'vendor/autoload.php';
$log = new \Pkg6\Log\Logger([
    new \Pkg6\Log\handler\StreamHandler("./log/text.log"),
]);
$log = new \Pkg6\Log\Logger([
    "console" => new \Pkg6\Log\handler\StreamHandler(),
]);
$log->info("test");
~~~

