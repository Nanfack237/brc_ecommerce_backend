<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ContactMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'phone'   => 'nullable|string|max:20',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $senderName    = $request->name;
        $senderEmail   = $request->email;
        $senderPhone   = $request->phone ?? 'Non renseigné';
        $senderSubject = $request->subject;
        $senderMessage = $request->message;

        Mail::to('businessrevcompany@gmail.com')->send(new ContactMail(
            senderName:    $senderName,
            senderEmail:   $senderEmail,
            senderPhone:   $senderPhone,
            senderSubject: $senderSubject,
            senderMessage: $senderMessage,
        ));

        return response()->json([
            'message' => 'Email envoyé avec succès.'
        ], 200);
    }
}