<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;


class ApplicationController extends Controller
{
    public function submit(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string',
            'cv' => 'required|mimes:pdf,docx|max:10240' //maximum pdf size should be 10MB

        ]);

        if($validator->fails()){
            return redirect()->back()->withErrors($validator)->withInput();
        }
        $path = $request->file('cv')->store('cv-documents', 's3');
        $cvLink = Storage::disk('s3')->url($path);
        return redirect()->back()->with('success', 'Your application has been submitted successfully!');
    }
}
