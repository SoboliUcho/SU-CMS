<?php

namespace App\Controllers\Web;

use Core\Controller;

class IndexController extends Controller
{
    public function index()
    {
        return $this->view('index', [
            'title' => 'CMS ready'
        ]);
    }
}
