<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeUser extends Mailable
{
    use Queueable, SerializesModels;

    // On passe le $user au constructeur
    // pour pouvoir l'utiliser dans le template email
    public function __construct(public User $user) {}

    // Sujet de l'email
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "🎉 Bienvenue sur BRC Market, {$this->user->first_name} !",
        );
    }

    // Quel template HTML utiliser
    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome', // → resources/views/emails/welcome.blade.php
        );
    }
}