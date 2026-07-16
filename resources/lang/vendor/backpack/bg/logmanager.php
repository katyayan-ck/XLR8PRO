<?php


if (file_exists(__DIR__.'/../../../../../logmanager/src/resources/lang/'.basename(__DIR__).'/'.basename(__FILE__))) {
    return include __DIR__.'/../../../../../logmanager/src/resources/lang/'.basename(__DIR__).'/'.basename(__FILE__);
}

return [];
