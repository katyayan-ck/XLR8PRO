<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Global Column Transformations
    |--------------------------------------------------------------------------
    |
    | Every model automatically receives these transformations
    | if the column exists.
    |
    */

    'code' => [
        'trim',
        'uppercase_alphanumeric_dash_underscore',
    ],

    'name' => [
        'trim_spaces',
        'title_case',
    ],

    'city' => [
        'trim_spaces',
        'title_case',
    ],

    'state' => [
        'trim_spaces',
        'title_case',
    ],

    'country' => [
        'trim_spaces',
        'title_case',
    ],

    'email' => [
        'trim',
        'lowercase',
    ],

    'phone' => [
        'numeric',
    ],

    'mobile' => [
        'numeric',
    ],

    'pincode' => [
        'numeric',
    ],

    'gst_no' => [
        'trim',
        'uppercase',
    ],

    'pan_no' => [
        'trim',
        'uppercase',
    ],

    'ifsc' => [
        'trim',
        'uppercase',
    ],

    'account_no' => [
        'numeric',
    ],

    'description' => [
        'trim_spaces',
    ],

    'address' => [
        'trim_spaces',
    ],

    'remarks' => [
        'trim_spaces',
    ],

    'notes' => [
        'trim_spaces',
    ],
];
