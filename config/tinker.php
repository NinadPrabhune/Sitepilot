<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auto Completor
    |--------------------------------------------------------------------------
    |
    | Tinker will automatically complete commands and arguments that have
    | very similar names to what you've typed. This helps you speed up
    | your workflow when working through the console.
    |
    */

    'completer' => true,

    /*
    |--------------------------------------------------------------------------
    | Commands
    |--------------------------------------------------------------------------
    |
    | Tinker ships with a number of helpful commands which can assist you
    | while working within your environment. You can disable any of these
    | commands from executing within your tinker session.
    |
    */

    'commands' => [
        \Laravel\Tinker\Console\TinkerCommand::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Don't Alias These Classes
    |--------------------------------------------------------------------------
    |
    | Tinker will not alias these classes to their short names. Typically,
    | these are classes that you may want to explicitly access with the
    | longer names, rather than their short aliases.
    |
    */

    'dont_alias' => [
        'App',
        'Illuminate',
        'Illuminate\Support\Facades',
        'Illuminate\Foundation\Testing',
        'Illuminate\Foundation\Testing\Concerns',
        'Illuminate\Foundation\Testing\DatabaseTransactions',
    ],

];
