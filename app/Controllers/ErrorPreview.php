<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;

class ErrorPreview extends BaseController
{
    public function index()
    {
        $page = config('ErrorPages')->find($this->request->getGet('code') ?? '404');
        if ($page === null) throw PageNotFoundException::forPageNotFound();

        // A preview must never deliberately trigger an exception or change the session.
        return $this->response->setStatusCode(200)->setBody(view('errors/html/application_error', [
            'errorCode' => $page['code'],
            'isPreview' => true,
        ]));
    }
}
