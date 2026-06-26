<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        return $this->notFoundError('No implementado');   
    }
   
    public function store(Request $request)
    {
        return $this->notFoundError('No implementado');   
    }

    public function show(string $id)
    {
        return $this->notFoundError('No implementado');   
    }
    
    public function update(Request $request, string $id)
    {
        return $this->notFoundError('No implementado');   
    }

    public function destroy(string $id)
    {
        return $this->notFoundError('No implementado');   
    }
}
