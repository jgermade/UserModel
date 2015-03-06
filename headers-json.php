<?php
    ob_start ("ob_gzhandler");
    header("Content-type: application/json");
    header("charset: utf-8");
    header("Cache-Control: no-cache");
    header("Expires: -1");