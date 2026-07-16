<?php


if (file_exists(__DIR__.'/../../../../../langfilemanager/src/resources/lang/'.basename(__DIR__).'/'.basename(__FILE__))) {
    return include __DIR__.'/../../../../../langfilemanager/src/resources/lang/'.basename(__DIR__).'/'.basename(__FILE__);
}

return [];
