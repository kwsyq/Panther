<?php
// log4php/resources/config.log4php.php

$log4phpconfig = Array
(
    'rootLogger' => Array
        (
            'level' => 'trace',
            'appenders' => Array
                (
                    '0' => 'allMessages'
                )
        ),
    'loggers' => Array
        (
            'main' => Array
                (
                    'additivity' => 'false',
                    'appenders' => Array
                        (
                            '0' => 'allMessages'
                        )
                )
        ),
    'appenders' => Array
        (
            'allMessages' => Array
                (
                    'class' => 'LoggerAppenderSyslog',
                    'layout' => Array
                        (
                            'class' => 'LoggerLayoutPattern',
                            'params' => Array
                                (
                                    'conversionPattern' => '%date [%logger] [%level] [%file: %line: %s{REMOTE_ADDR}] %message%newline'
                                )
                        ),
                    'params' => Array
                        (                                
                        )
                )
        )
);
?>