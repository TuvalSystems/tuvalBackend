<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\Contracts\ProfileServiceInterface;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function  __construct(
      protected readonly ProfileServiceInterface $profile
    ) {}

    public function lista(Request $request){

    }
}
