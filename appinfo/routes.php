<?php

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'dashboard#getData', 'url' => '/api/data', 'verb' => 'GET'],
        ['name' => 'dashboard#createNote', 'url' => '/api/notes', 'verb' => 'POST'],
        ['name' => 'dashboard#updateNote', 'url' => '/api/notes/{id}', 'verb' => 'PUT'],
        ['name' => 'dashboard#deleteNote', 'url' => '/api/notes/{id}', 'verb' => 'DELETE'],
        ['name' => 'dashboard#listFiles', 'url' => '/api/files', 'verb' => 'GET'],
    ],
];
