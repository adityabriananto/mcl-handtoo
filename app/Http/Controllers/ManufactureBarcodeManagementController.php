<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ManufactureBarcodeManagementController extends Controller
{
    //
    public function index(Request $request) {
        return view('mb.index');
    }

    public function create() {
        return view('mb.add');
    }

    public function store(Request $request) {

    }

    public function edit() {
        return view('mb.edit');
    }

    public function store(Request $request) {

    }

    public function destroy($id) {

    }
}
